<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import { router } from '@inertiajs/vue3';
import JSZip from 'jszip';
import { Check, Database, Download, FileSpreadsheet, FileText, Filter, Loader2, Sparkles, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import * as XLSX from 'xlsx';

const props = defineProps<{
    open: boolean;
    existingCategories?: string[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'imported'): void;
}>();

const { success, warning, error, info } = useToast();

// Tabs: File Upload vs Paste Table Text
const importMethod = ref<'file' | 'paste'>('file');
const pastedText = ref('');
const isDragging = ref(false);
const isProcessing = ref(false);
const isSubmitting = ref(false);
const updateExisting = ref(true);

const uploadedFileName = ref('');
const uploadedFileSize = ref('');
const uploadedFileType = ref('');

// Raw parsed data
const rawHeaders = ref<string[]>([]);
const rawRows = ref<any[][]>([]);

// System standard schema
interface SystemField {
    key: string;
    label: string;
    required: boolean;
    synonyms: string[];
}

const SYSTEM_FIELDS: SystemField[] = [
    {
        key: 'name',
        label: 'Product Name',
        required: true,
        synonyms: [
            'name',
            'product',
            'item',
            'title',
            'description',
            'product_name',
            'item_name',
            'item_title',
            'product_title',
            'product_description',
            'goods',
            'article',
            'productname',
            'itemname',
        ],
    },
    {
        key: 'sku',
        label: 'SKU / Item Code',
        required: false,
        synonyms: ['sku', 'code', 'item_code', 'product_code', 'item_no', 'ref', 'reference', 'model', 'id', 'product_id', 'itemcode', 'productcode'],
    },
    {
        key: 'barcode',
        label: 'Barcode / EAN / UPC',
        required: false,
        synonyms: ['barcode', 'bar_code', 'ean', 'upc', 'qr', 'code128', 'serial', 'isbn', 'barcode_number', 'barcodeno'],
    },
    {
        key: 'category',
        label: 'Category',
        required: false,
        synonyms: ['category', 'dept', 'department', 'group', 'type', 'genre', 'classification', 'section', 'family', 'category_name'],
    },
    {
        key: 'cost_price',
        label: 'Cost Price (KES)',
        required: false,
        synonyms: [
            'cost',
            'cost_price',
            'buying_price',
            'buy_price',
            'purchase_price',
            'wholesale_price',
            'unit_cost',
            'costprice',
            'buyprice',
            'purchaseprice',
            'buying',
        ],
    },
    {
        key: 'selling_price',
        label: 'Selling Price (KES)',
        required: true,
        synonyms: [
            'price',
            'selling_price',
            'retail_price',
            'unit_price',
            'sale_price',
            'amount',
            'mrp',
            'rate',
            'retail',
            'sellingprice',
            'price_kes',
            'selling',
        ],
    },
    {
        key: 'stock_quantity',
        label: 'Stock Quantity',
        required: false,
        synonyms: [
            'stock',
            'qty',
            'quantity',
            'available',
            'units',
            'on_hand',
            'inventory',
            'count',
            'stock_quantity',
            'balance',
            'opening_stock',
            'stockqty',
            'onhand',
        ],
    },
    {
        key: 'reorder_level',
        label: 'Reorder Level',
        required: false,
        synonyms: [
            'reorder',
            'min',
            'min_stock',
            'reorder_level',
            'minimum',
            'minimum_stock',
            'alert_level',
            'low_stock_level',
            'threshold',
            'minlevel',
        ],
    },
    {
        key: 'unit',
        label: 'Unit of Measure',
        required: false,
        synonyms: ['unit', 'uom', 'packaging', 'measure', 'package_unit', 'unit_of_measure', 'measurement', 'unit_name'],
    },
];

// Column mapping state: map system field key -> document raw header index (or -1 for none)
const columnMapping = ref<Record<string, number>>({});

// Selected rows for import
const selectedRowIndexes = ref<number[]>([]);

// Sanitization helpers
const sanitizeNumber = (val: any, fallback = 0): number => {
    if (val === null || val === undefined || val === '') return fallback;
    if (typeof val === 'number') return isNaN(val) ? fallback : val;
    const cleaned = String(val).replace(/[^0-9.-]/g, '');
    const num = parseFloat(cleaned);
    return isNaN(num) ? fallback : Math.max(0, num);
};

const sanitizeString = (val: any, fallback = ''): string => {
    if (val === null || val === undefined) return fallback;
    return String(val).trim();
};

// Automatic Column Arranger Engine
const autoDetectAndArrangeColumns = (headers: string[], sampleRows: any[][]) => {
    const mapping: Record<string, number> = {};
    const usedHeaderIndexes = new Set<number>();

    const normalizedHeaders = headers.map((h) =>
        String(h || '')
            .toLowerCase()
            .replace(/[^a-z0-9]/g, ''),
    );

    // 1. Synonym-based matching
    for (const field of SYSTEM_FIELDS) {
        let bestIndex = -1;

        for (let i = 0; i < normalizedHeaders.length; i++) {
            if (usedHeaderIndexes.has(i)) continue;
            const norm = normalizedHeaders[i];

            if (field.synonyms.includes(norm)) {
                bestIndex = i;
                break;
            }

            if (field.synonyms.some((s) => norm.includes(s) || s.includes(norm))) {
                bestIndex = i;
                break;
            }
        }

        if (bestIndex !== -1) {
            mapping[field.key] = bestIndex;
            usedHeaderIndexes.add(bestIndex);
        } else {
            mapping[field.key] = -1;
        }
    }

    // 2. Value-heuristic fallback if required fields are unmapped
    if (mapping['name'] === -1) {
        let maxLen = 0;
        let chosenIdx = -1;
        for (let i = 0; i < headers.length; i++) {
            if (usedHeaderIndexes.has(i)) continue;
            const avgLen = sampleRows.reduce((acc, row) => acc + String(row[i] || '').length, 0) / (sampleRows.length || 1);
            if (avgLen > maxLen) {
                maxLen = avgLen;
                chosenIdx = i;
            }
        }
        if (chosenIdx !== -1) {
            mapping['name'] = chosenIdx;
            usedHeaderIndexes.add(chosenIdx);
        }
    }

    if (mapping['selling_price'] === -1) {
        for (let i = 0; i < headers.length; i++) {
            if (usedHeaderIndexes.has(i)) continue;
            const isNumeric = sampleRows.every((row) => !isNaN(sanitizeNumber(row[i], NaN)));
            if (isNumeric) {
                mapping['selling_price'] = i;
                usedHeaderIndexes.add(i);
                break;
            }
        }
    }

    columnMapping.value = mapping;
};

// Arranged products table preview
export interface ArrangedProductPreview {
    index: number;
    name: string;
    sku: string;
    barcode: string;
    category: string;
    cost_price: number;
    selling_price: number;
    stock_quantity: number;
    reorder_level: number;
    unit: string;
    isValid: boolean;
    validationMessage?: string;
}

const arrangedProducts = computed<ArrangedProductPreview[]>(() => {
    if (rawRows.value.length === 0) return [];

    const nameIdx = columnMapping.value['name'] ?? -1;
    const skuIdx = columnMapping.value['sku'] ?? -1;
    const barcodeIdx = columnMapping.value['barcode'] ?? -1;
    const categoryIdx = columnMapping.value['category'] ?? -1;
    const costIdx = columnMapping.value['cost_price'] ?? -1;
    const priceIdx = columnMapping.value['selling_price'] ?? -1;
    const stockIdx = columnMapping.value['stock_quantity'] ?? -1;
    const reorderIdx = columnMapping.value['reorder_level'] ?? -1;
    const unitIdx = columnMapping.value['unit'] ?? -1;

    return rawRows.value.map((row, idx) => {
        const rawName = nameIdx >= 0 ? sanitizeString(row[nameIdx]) : '';
        const rawSku = skuIdx >= 0 ? sanitizeString(row[skuIdx]) : '';
        const rawBarcode = barcodeIdx >= 0 ? sanitizeString(row[barcodeIdx]) : '';
        const rawCategory = categoryIdx >= 0 ? sanitizeString(row[categoryIdx]) : '';
        const costPrice = costIdx >= 0 ? sanitizeNumber(row[costIdx], 0) : 0;
        const sellingPrice = priceIdx >= 0 ? sanitizeNumber(row[priceIdx], 0) : 0;
        const stockQty = stockIdx >= 0 ? Math.round(sanitizeNumber(row[stockIdx], 0)) : 0;
        const reorderLevel = reorderIdx >= 0 ? Math.round(sanitizeNumber(row[reorderIdx], 5)) : 5;
        const unit = unitIdx >= 0 ? sanitizeString(row[unitIdx], 'Piece') : 'Piece';

        let sku = rawSku;
        if (!sku && rawName) {
            const cleanCat =
                (rawCategory || 'PRD')
                    .replace(/[^A-Za-z]/g, '')
                    .slice(0, 3)
                    .toUpperCase() || 'PRD';
            sku = `${cleanCat}-${String(idx + 1).padStart(4, '0')}`;
        }

        const isValid = rawName.length > 0 && sellingPrice > 0;
        let validationMessage = '';
        if (!rawName) validationMessage = 'Missing product name';
        else if (sellingPrice <= 0) validationMessage = 'Selling price must be greater than 0';

        return {
            index: idx,
            name: rawName || `Unnamed Item #${idx + 1}`,
            sku,
            barcode: rawBarcode,
            category: rawCategory || 'General',
            cost_price: costPrice,
            selling_price: sellingPrice,
            stock_quantity: stockQty,
            reorder_level: reorderLevel,
            unit: unit || 'Piece',
            isValid,
            validationMessage,
        };
    });
});

// Summary Metrics
const validProductsCount = computed(() => arrangedProducts.value.filter((p) => p.isValid).length);
const totalImportCostValuation = computed(() => arrangedProducts.value.reduce((acc, p) => acc + p.cost_price * p.stock_quantity, 0));
const totalImportRetailValuation = computed(() => arrangedProducts.value.reduce((acc, p) => acc + p.selling_price * p.stock_quantity, 0));
const uniqueCategoriesCount = computed(() => {
    const set = new Set(arrangedProducts.value.map((p) => p.category).filter(Boolean));
    return set.size;
});

// File processing handlers
const processFile = async (file: File) => {
    isProcessing.value = true;
    uploadedFileName.value = file.name;
    uploadedFileSize.value = `${(file.size / 1024).toFixed(1)} KB`;
    uploadedFileType.value = file.name.split('.').pop()?.toUpperCase() || 'FILE';

    try {
        const ext = file.name.split('.').pop()?.toLowerCase() || '';

        if (['xlsx', 'xls', 'csv', 'tsv', 'txt'].includes(ext)) {
            const buffer = await file.arrayBuffer();
            const workbook = XLSX.read(buffer, { type: 'array' });
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const data: any[][] = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });

            if (!data || data.length === 0) {
                error('Empty File', 'The selected file contains no rows.');
                isProcessing.value = false;
                return;
            }

            let headerRowIndex = 0;
            while (headerRowIndex < data.length && data[headerRowIndex].every((c) => !c || String(c).trim() === '')) {
                headerRowIndex++;
            }

            const headers = (data[headerRowIndex] || []).map((h, i) => String(h || `Column ${i + 1}`).trim());
            const rows = data.slice(headerRowIndex + 1).filter((r) => r.some((c) => c !== '' && c !== null && c !== undefined));

            rawHeaders.value = headers;
            rawRows.value = rows;
            autoDetectAndArrangeColumns(headers, rows.slice(0, 10));
            selectedRowIndexes.value = rows.map((_, i) => i);

            success('Document Parsed', `Extracted ${rows.length} rows from ${file.name}. Automatically arranged into system columns.`);
        } else if (ext === 'docx') {
            const zip = new JSZip();
            const loadedZip = await zip.loadAsync(file);
            const docXml = await loadedZip.file('word/document.xml')?.async('text');

            if (!docXml) {
                error('Unsupported DOCX', 'Could not locate document content in the Word file.');
                isProcessing.value = false;
                return;
            }

            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(docXml, 'application/xml');
            const tableRows = xmlDoc.getElementsByTagName('w:tr');

            if (tableRows.length > 0) {
                const parsed2D: string[][] = [];
                for (let i = 0; i < tableRows.length; i++) {
                    const row = tableRows[i];
                    const cells = row.getElementsByTagName('w:tc');
                    const rowData: string[] = [];
                    for (let j = 0; j < cells.length; j++) {
                        rowData.push(cells[j].textContent?.trim() || '');
                    }
                    if (rowData.some((c) => c.length > 0)) {
                        parsed2D.push(rowData);
                    }
                }

                if (parsed2D.length >= 2) {
                    const headers = parsed2D[0];
                    const rows = parsed2D.slice(1);
                    rawHeaders.value = headers;
                    rawRows.value = rows;
                    autoDetectAndArrangeColumns(headers, rows.slice(0, 10));
                    selectedRowIndexes.value = rows.map((_, i) => i);
                    success('Word Table Parsed', `Extracted ${rows.length} table rows from Word document.`);
                } else {
                    error('No Table Found', 'Word document did not contain a readable table with at least 2 rows.');
                }
            } else {
                const paragraphs = xmlDoc.getElementsByTagName('w:p');
                const textLines: string[] = [];
                for (let i = 0; i < paragraphs.length; i++) {
                    const t = paragraphs[i].textContent?.trim();
                    if (t) textLines.push(t);
                }
                parseDelimitedTextLines(textLines);
            }
        } else if (ext === 'json') {
            const text = await file.text();
            const parsed = JSON.parse(text);
            const array = Array.isArray(parsed) ? parsed : parsed.products || parsed.items || [parsed];
            if (Array.isArray(array) && array.length > 0) {
                const headers = Object.keys(array[0]);
                const rows = array.map((item) => headers.map((h) => item[h]));
                rawHeaders.value = headers;
                rawRows.value = rows;
                autoDetectAndArrangeColumns(headers, rows.slice(0, 10));
                selectedRowIndexes.value = rows.map((_, i) => i);
                success('JSON Parsed', `Loaded ${rows.length} items from JSON document.`);
            } else {
                error('Invalid JSON', 'JSON file must contain an array of product objects.');
            }
        } else {
            const text = await file.text();
            parseDelimitedTextLines(text.split(/\r?\n/));
        }
    } catch (err: any) {
        error('Parse Error', err?.message || 'Unable to parse document format.');
    } finally {
        isProcessing.value = false;
    }
};

const parseDelimitedTextLines = (lines: string[]) => {
    const cleanLines = lines.map((l) => l.trim()).filter((l) => l.length > 0);
    if (cleanLines.length < 2) {
        error('Insufficient Data', 'Document needs at least a header line and one item line.');
        return;
    }

    const firstLine = cleanLines[0];
    let delimiter = ',';
    if (firstLine.includes('\t')) delimiter = '\t';
    else if (firstLine.includes('|')) delimiter = '|';
    else if (firstLine.includes(';')) delimiter = ';';

    const parsed2D = cleanLines.map((line) =>
        line
            .split(delimiter)
            .map((c) => c.trim().replace(/^["']|["']$/g, ''))
            .filter((_, idx, arr) => delimiter !== '|' || (idx > 0 && idx < arr.length - 1)),
    );

    const headers = parsed2D[0];
    const rows = parsed2D.slice(1).filter((r) => r.some((c) => c.length > 0));

    rawHeaders.value = headers;
    rawRows.value = rows;
    autoDetectAndArrangeColumns(headers, rows.slice(0, 10));
    selectedRowIndexes.value = rows.map((_, i) => i);
    success('Text Parsed', `Extracted ${rows.length} records using delimiter "${delimiter === '\t' ? 'TAB' : delimiter}".`);
};

const handleFileSelect = (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        processFile(input.files[0]);
    }
};

const handleDrop = (e: DragEvent) => {
    isDragging.value = false;
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
        processFile(e.dataTransfer.files[0]);
    }
};

const handleProcessPastedText = () => {
    if (!pastedText.value.trim()) {
        warning('Empty Content', 'Please paste table text, CSV, or formatted document lines.');
        return;
    }
    uploadedFileName.value = 'Pasted Document Content';
    uploadedFileSize.value = `${(pastedText.value.length / 1024).toFixed(1)} KB`;
    uploadedFileType.value = 'PASTED TEXT';
    parseDelimitedTextLines(pastedText.value.split(/\r?\n/));
};

const toggleSelectAllRows = () => {
    if (selectedRowIndexes.value.length === arrangedProducts.value.length) {
        selectedRowIndexes.value = [];
    } else {
        selectedRowIndexes.value = arrangedProducts.value.map((_, i) => i);
    }
};

const toggleSelectRow = (idx: number) => {
    const i = selectedRowIndexes.value.indexOf(idx);
    if (i >= 0) selectedRowIndexes.value.splice(i, 1);
    else selectedRowIndexes.value.push(idx);
};

// Download Sample Template for User Reference
const downloadSampleTemplate = (format: 'csv' | 'xlsx') => {
    const sampleData = [
        ['Product Name', 'SKU', 'Barcode', 'Category', 'Cost Price', 'Selling Price', 'Stock Qty', 'Reorder Level', 'Unit'],
        ['Pembe Maize Flour 2kg', 'GRO-001', '616110000101', 'Groceries', 140, 180, 50, 10, 'Packet'],
        ['Mumias Sugar 1kg', 'GRO-002', '616110000102', 'Groceries', 160, 195, 80, 15, 'Packet'],
        ['Brookside Fresh Milk 500ml', 'DAI-001', '616110000201', 'Dairy', 55, 70, 45, 12, 'Pouch'],
        ['Ketepa Pride Tea Leaves 500g', 'BEV-001', '616110000301', 'Beverages', 220, 280, 30, 8, 'Box'],
        ['Geisha Soap Rose 225g', 'HPC-001', '616110000401', 'Toiletries', 90, 120, 60, 10, 'Piece'],
    ];

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(sampleData);
    XLSX.utils.book_append_sheet(wb, ws, 'Products Template');

    if (format === 'xlsx') {
        XLSX.writeFile(wb, 'product_catalog_import_template.xlsx');
    } else {
        XLSX.writeFile(wb, 'product_catalog_import_template.csv');
    }
    success('Template Downloaded', `Downloaded sample product import template in .${format}`);
};

// Submit arranged products to backend
const handleConfirmImport = () => {
    const selected = arrangedProducts.value.filter((p) => selectedRowIndexes.value.includes(p.index));

    if (selected.length === 0) {
        warning('No Items Selected', 'Please check at least one product to import.');
        return;
    }

    const validItems = selected.filter((p) => p.isValid);
    if (validItems.length === 0) {
        error('Invalid Products', 'None of the selected products have a valid Name and Selling Price.');
        return;
    }

    isSubmitting.value = true;

    router.post(
        '/products/import',
        {
            products: validItems.map((p) => ({
                name: p.name,
                sku: p.sku || null,
                barcode: p.barcode || null,
                category: p.category || 'General',
                cost_price: p.cost_price,
                selling_price: p.selling_price,
                stock_quantity: p.stock_quantity,
                reorder_level: p.reorder_level,
                unit: p.unit || 'Piece',
            })),
            update_existing: updateExisting.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting.value = false;
                emit('update:open', false);
                emit('imported');
                rawHeaders.value = [];
                rawRows.value = [];
                pastedText.value = '';
                uploadedFileName.value = '';
            },
            onError: (errs) => {
                isSubmitting.value = false;
                error('Import Failed', (Object.values(errs)[0] as string) || 'An error occurred during product import.');
            },
        },
    );
};

const handleReset = () => {
    rawHeaders.value = [];
    rawRows.value = [];
    pastedText.value = '';
    uploadedFileName.value = '';
    columnMapping.value = {};
    selectedRowIndexes.value = [];
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="flex max-h-[94vh] w-[98vw] max-w-5xl flex-col overflow-hidden p-0 xl:max-w-6xl">
            <!-- Modal Header -->
            <DialogHeader class="shrink-0 border-b border-border bg-card p-4 sm:p-6">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <DialogTitle class="flex items-center gap-2.5 text-lg font-bold">
                        <div class="flex size-9 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary">
                            <Download class="size-5" />
                        </div>
                        <div>
                            <span>Import Catalog Documents</span>
                            <p class="mt-0.5 text-xs font-normal text-muted-foreground">
                                Automatically arrange and map documents in <strong>Excel, Word, CSV, JSON, or Text</strong> into the system catalog.
                            </p>
                        </div>
                    </DialogTitle>

                    <!-- Template Download Helpers -->
                    <div class="flex shrink-0 items-center gap-1.5">
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 gap-1 border-border text-xs"
                            @click="downloadSampleTemplate('csv')"
                            title="Download CSV reference template"
                        >
                            <FileSpreadsheet class="size-3.5 text-muted-foreground" />
                            Sample CSV
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 gap-1 border-border text-xs"
                            @click="downloadSampleTemplate('xlsx')"
                            title="Download Excel reference template"
                        >
                            <FileText class="size-3.5 text-emerald-600" />
                            Sample Excel
                        </Button>
                    </div>
                </div>
            </DialogHeader>

            <!-- Modal Body (Scrollable) -->
            <div class="flex-1 space-y-5 overflow-y-auto p-4 sm:p-6">
                <!-- STEP 1: If no document loaded yet, show Upload / Paste options -->
                <div v-if="rawRows.length === 0" class="space-y-4">
                    <!-- Switch Tabs: File Upload vs Paste Text -->
                    <div class="flex items-center justify-center">
                        <div class="inline-flex gap-1 rounded-xl border border-border bg-muted/40 p-1 text-xs font-semibold">
                            <button
                                type="button"
                                :class="[
                                    'flex cursor-pointer items-center gap-2 rounded-lg px-4 py-2 transition-all',
                                    importMethod === 'file'
                                        ? 'shadow-xs bg-background font-bold text-primary'
                                        : 'text-muted-foreground hover:text-foreground',
                                ]"
                                @click="importMethod = 'file'"
                            >
                                <Upload class="size-3.5" />
                                <span>Upload Document File</span>
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'flex cursor-pointer items-center gap-2 rounded-lg px-4 py-2 transition-all',
                                    importMethod === 'paste'
                                        ? 'shadow-xs bg-background font-bold text-primary'
                                        : 'text-muted-foreground hover:text-foreground',
                                ]"
                                @click="importMethod = 'paste'"
                            >
                                <FileText class="size-3.5" />
                                <span>Paste Document Content / Table</span>
                            </button>
                        </div>
                    </div>

                    <!-- Mode A: File Upload Drag & Drop Zone -->
                    <div
                        v-if="importMethod === 'file'"
                        :class="[
                            'relative cursor-pointer rounded-2xl border-2 border-dashed p-8 text-center transition-all sm:p-12',
                            isDragging ? 'scale-[0.99] border-primary bg-primary/5' : 'border-border/80 hover:border-primary/60 hover:bg-muted/30',
                        ]"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop"
                    >
                        <input
                            type="file"
                            accept=".csv, .xlsx, .xls, .docx, .doc, .json, .txt, .tsv, .pdf"
                            class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                            @change="handleFileSelect"
                        />

                        <div class="flex flex-col items-center justify-center gap-3">
                            <div
                                class="shadow-xs flex size-16 items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 text-primary"
                            >
                                <Upload class="size-8" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-foreground sm:text-base">
                                    Drop your document here, or <span class="text-primary underline">browse to select</span>
                                </h3>
                                <p class="mx-auto mt-1 max-w-md text-xs text-muted-foreground">
                                    Supports <strong>Excel (.xlsx, .xls)</strong>, <strong>CSV / TSV</strong>, <strong>Word (.docx)</strong>,
                                    <strong>JSON</strong>, or <strong>Plain Text</strong>.
                                </p>
                            </div>

                            <!-- Formats Badges -->
                            <div class="flex flex-wrap items-center justify-center gap-1.5 pt-2">
                                <Badge variant="outline" class="border-emerald-500/20 bg-emerald-500/10 font-mono text-[10px] text-emerald-600"
                                    >.xlsx / .xls</Badge
                                >
                                <Badge variant="outline" class="border-sky-500/20 bg-sky-500/10 font-mono text-[10px] text-sky-600"
                                    >.csv / .tsv</Badge
                                >
                                <Badge variant="outline" class="border-blue-500/20 bg-blue-500/10 font-mono text-[10px] text-blue-600"
                                    >.docx (Word Tables)</Badge
                                >
                                <Badge variant="outline" class="border-amber-500/20 bg-amber-500/10 font-mono text-[10px] text-amber-600"
                                    >.json</Badge
                                >
                                <Badge variant="outline" class="border-neutral-500/20 bg-neutral-500/10 font-mono text-[10px] text-neutral-600"
                                    >.txt</Badge
                                >
                            </div>
                        </div>

                        <div
                            v-if="isProcessing"
                            class="backdrop-blur-xs absolute inset-0 z-20 flex items-center justify-center gap-2 bg-background/80"
                        >
                            <Loader2 class="size-6 animate-spin text-primary" />
                            <span class="text-xs font-semibold text-foreground">Reading and auto-arranging document rows...</span>
                        </div>
                    </div>

                    <!-- Mode B: Paste Raw Table Content -->
                    <div v-else class="space-y-3">
                        <Label class="text-xs font-semibold">Paste raw table rows, copied cells, or delimited text:</Label>
                        <textarea
                            v-model="pastedText"
                            rows="9"
                            placeholder="Example table format:&#10;Product Name	SKU	Category	Cost Price	Selling Price	Stock Qty&#10;Pembe Flour 2kg	GRO-001	Groceries	140	180	50&#10;Mumias Sugar 1kg	GRO-002	Groceries	160	195	80&#10;Brookside Milk 500ml	DAI-001	Dairy	55	70	45"
                            class="focus:outline-hidden w-full rounded-xl border border-border bg-background p-3 font-mono text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary"
                        ></textarea>
                        <div class="flex justify-end">
                            <Button size="sm" class="gap-1.5 bg-primary" @click="handleProcessPastedText">
                                <Sparkles class="size-3.5" />
                                Parse & Auto-Arrange Table
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Document Loaded - Auto-Arranged Table Preview & Column Mapping -->
                <div v-else class="space-y-4">
                    <!-- File Metadata & Reset Bar -->
                    <div class="flex flex-col justify-between gap-3 rounded-xl border border-border/80 bg-muted/20 p-3 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-8 items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-xs font-bold text-emerald-600"
                            >
                                <Check class="size-4" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2 text-xs font-bold text-foreground">
                                    <span>{{ uploadedFileName }}</span>
                                    <Badge variant="outline" class="px-1 font-mono text-[9px]">{{ uploadedFileType }}</Badge>
                                    <span class="text-[10px] text-muted-foreground">({{ uploadedFileSize }})</span>
                                </div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <Sparkles class="size-3 text-amber-500" />
                                    <span
                                        >Auto-arranged <strong>{{ arrangedProducts.length }}</strong> products into the system catalog format</span
                                    >
                                </div>
                            </div>
                        </div>

                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-8 shrink-0 gap-1 text-xs text-muted-foreground hover:text-destructive"
                            @click="handleReset"
                        >
                            <Trash2 class="size-3.5" />
                            Choose Different File
                        </Button>
                    </div>

                    <!-- KPI Preview Metric Cards -->
                    <div class="grid grid-cols-2 gap-2.5 text-xs sm:grid-cols-4">
                        <div class="rounded-xl border border-border bg-card p-2.5">
                            <span class="block font-mono text-[10px] uppercase text-muted-foreground">Ready for Import</span>
                            <span class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400"
                                >{{ validProductsCount }} / {{ arrangedProducts.length }}</span
                            >
                        </div>
                        <div class="rounded-xl border border-border bg-card p-2.5">
                            <span class="block font-mono text-[10px] uppercase text-muted-foreground">Est. Cost Valuation</span>
                            <span class="font-mono text-sm font-bold text-foreground">{{ formatCurrency(totalImportCostValuation) }}</span>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-2.5">
                            <span class="block font-mono text-[10px] uppercase text-muted-foreground">Est. Retail Valuation</span>
                            <span class="font-mono text-sm font-bold text-primary">{{ formatCurrency(totalImportRetailValuation) }}</span>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-2.5">
                            <span class="block font-mono text-[10px] uppercase text-muted-foreground">Categories Detected</span>
                            <span class="font-mono text-sm font-bold text-foreground">{{ uniqueCategoriesCount }} Categories</span>
                        </div>
                    </div>

                    <!-- Column Mapping Adjuster Collapsible / Control -->
                    <div class="space-y-2 rounded-xl border border-border bg-card p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                <Filter class="size-3.5 text-primary" />
                                <span>Column Auto-Arrangement Mappings</span>
                            </div>
                            <span class="text-[11px] text-muted-foreground">Adjust if any header requires custom matching</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-1 sm:grid-cols-3 lg:grid-cols-5">
                            <div v-for="field in SYSTEM_FIELDS" :key="field.key" class="space-y-1">
                                <Label class="flex items-center gap-1 font-mono text-[10px] text-muted-foreground">
                                    <span>{{ field.label }}</span>
                                    <span v-if="field.required" class="text-destructive">*</span>
                                </Label>
                                <select
                                    v-model="columnMapping[field.key]"
                                    class="focus:outline-hidden h-8 w-full cursor-pointer rounded-md border border-border bg-background px-2 text-xs text-foreground focus:ring-1 focus:ring-primary"
                                >
                                    <option :value="-1">-- Unmapped / Default --</option>
                                    <option v-for="(h, idx) in rawHeaders" :key="idx" :value="idx">
                                        {{ h }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Live Arranged System Table -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-foreground">System Catalog Table Preview:</span>
                                <span class="text-[11px] text-muted-foreground"
                                    >({{ selectedRowIndexes.length }} of {{ arrangedProducts.length }} items selected)</span
                                >
                            </div>
                            <button
                                type="button"
                                class="cursor-pointer text-[11px] font-semibold text-primary hover:underline"
                                @click="toggleSelectAllRows"
                            >
                                {{ selectedRowIndexes.length === arrangedProducts.length ? 'Deselect All' : 'Select All' }}
                            </button>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-border bg-card">
                            <div class="scrollbar-thin max-h-[380px] overflow-x-auto overflow-y-auto">
                                <table class="w-full whitespace-nowrap text-left text-xs">
                                    <thead
                                        class="backdrop-blur-xs sticky top-0 z-10 border-b border-border/80 bg-muted/95 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                                    >
                                        <tr>
                                            <th class="w-10 p-2.5 text-center">
                                                <input
                                                    type="checkbox"
                                                    :checked="selectedRowIndexes.length === arrangedProducts.length && arrangedProducts.length > 0"
                                                    class="size-3.5 cursor-pointer rounded border-input text-primary focus:ring-primary"
                                                    @change="toggleSelectAllRows"
                                                />
                                            </th>
                                            <th class="p-2.5">Product Name</th>
                                            <th class="p-2.5">SKU Code</th>
                                            <th class="p-2.5">Barcode</th>
                                            <th class="p-2.5">Category</th>
                                            <th class="p-2.5 text-right">Cost (KES)</th>
                                            <th class="p-2.5 text-right">Selling Price (KES)</th>
                                            <th class="p-2.5 text-center">Opening Stock</th>
                                            <th class="p-2.5">Unit</th>
                                            <th class="p-2.5 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/60">
                                        <tr
                                            v-for="item in arrangedProducts"
                                            :key="item.index"
                                            :class="[
                                                'transition-colors',
                                                selectedRowIndexes.includes(item.index)
                                                    ? 'bg-background hover:bg-muted/30'
                                                    : 'bg-muted/10 opacity-60',
                                            ]"
                                        >
                                            <td class="p-2.5 text-center">
                                                <input
                                                    type="checkbox"
                                                    :checked="selectedRowIndexes.includes(item.index)"
                                                    class="size-3.5 cursor-pointer rounded border-input text-primary focus:ring-primary"
                                                    @change="toggleSelectRow(item.index)"
                                                />
                                            </td>
                                            <td class="p-2.5 font-bold text-foreground">
                                                <span>{{ item.name }}</span>
                                            </td>
                                            <td class="p-2.5 font-mono text-[11px] text-muted-foreground">
                                                {{ item.sku || 'Auto-generated' }}
                                            </td>
                                            <td class="p-2.5 font-mono text-[11px] text-muted-foreground">
                                                {{ item.barcode || 'N/A' }}
                                            </td>
                                            <td class="p-2.5">
                                                <Badge variant="secondary" class="px-1.5 py-0 text-[10px] font-medium">
                                                    {{ item.category }}
                                                </Badge>
                                            </td>
                                            <td class="p-2.5 text-right font-mono text-muted-foreground">
                                                {{ formatCurrency(item.cost_price) }}
                                            </td>
                                            <td class="p-2.5 text-right font-mono font-bold text-foreground">
                                                {{ formatCurrency(item.selling_price) }}
                                            </td>
                                            <td class="p-2.5 text-center font-mono font-bold">
                                                <Badge
                                                    :variant="item.stock_quantity > 0 ? 'outline' : 'secondary'"
                                                    class="px-1.5 py-0 font-mono text-[10px]"
                                                >
                                                    {{ item.stock_quantity }} {{ item.unit }}
                                                </Badge>
                                            </td>
                                            <td class="p-2.5 text-[11px] text-muted-foreground">
                                                {{ item.unit }}
                                            </td>
                                            <td class="p-2.5 text-center">
                                                <Badge
                                                    v-if="item.isValid"
                                                    variant="outline"
                                                    class="border-emerald-500/20 bg-emerald-500/10 py-0 text-[9px] text-emerald-600"
                                                >
                                                    Ready ✓
                                                </Badge>
                                                <Badge v-else variant="destructive" class="py-0 text-[9px]" :title="item.validationMessage">
                                                    Fix Price / Name
                                                </Badge>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Sync options -->
                    <div class="flex items-center gap-2 pt-1 text-xs">
                        <input
                            id="update-existing"
                            v-model="updateExisting"
                            type="checkbox"
                            class="size-4 cursor-pointer rounded border-input text-primary focus:ring-primary"
                        />
                        <label for="update-existing" class="cursor-pointer select-none text-muted-foreground">
                            <strong>Update existing products</strong> if matching SKU, Barcode, or Product Name is found in database
                        </label>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <DialogFooter class="shrink-0 gap-2 border-t border-border bg-card p-4 sm:justify-between sm:p-6">
                <Button variant="ghost" size="sm" @click="emit('update:open', false)"> Cancel </Button>

                <div class="flex items-center gap-2">
                    <Button
                        v-if="rawRows.length > 0"
                        size="sm"
                        class="shadow-xs gap-2 bg-primary font-bold hover:bg-primary/90"
                        :disabled="isSubmitting || validProductsCount === 0"
                        @click="handleConfirmImport"
                    >
                        <Loader2 v-if="isSubmitting" class="size-4 animate-spin" />
                        <Database v-else class="size-4" />
                        <span>Confirm & Import {{ validProductsCount }} Products</span>
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
