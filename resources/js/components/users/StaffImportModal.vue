<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/composables/useToast';
import { router } from '@inertiajs/vue3';
import JSZip from 'jszip';
import {
    AlertCircle,
    ArrowRight,
    Check,
    CheckCircle2,
    Crown,
    Download,
    FileSpreadsheet,
    FileText,
    FileType2,
    Filter,
    HelpCircle,
    Layers,
    Loader2,
    Plus,
    RefreshCw,
    Shield,
    ShieldCheck,
    Sparkles,
    Trash2,
    Upload,
    UserCheck,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import * as XLSX from 'xlsx';

const props = defineProps<{
    open: boolean;
    branches?: any[];
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

// System standard staff schema
interface StaffField {
    key: string;
    label: string;
    required: boolean;
    synonyms: string[];
}

const SYSTEM_FIELDS: StaffField[] = [
    {
        key: 'name',
        label: 'Staff Full Name',
        required: true,
        synonyms: [
            'name',
            'staff',
            'staff_name',
            'fullname',
            'full_name',
            'employee',
            'employee_name',
            'personnel',
            'worker',
            'member',
            'user',
            'username',
            'first_name',
            'staffname',
            'employeename',
        ],
    },
    {
        key: 'email',
        label: 'Email Address',
        required: true,
        synonyms: [
            'email',
            'mail',
            'email_address',
            'e-mail',
            'contact_email',
            'work_email',
            'emailaddress',
            'user_email',
            'login_email',
        ],
    },
    {
        key: 'role',
        label: 'System Role',
        required: false,
        synonyms: [
            'role',
            'position',
            'designation',
            'job_title',
            'job',
            'title',
            'rank',
            'department',
            'access',
            'permission',
            'access_tier',
            'jobtitle',
            'user_role',
        ],
    },
    {
        key: 'phone',
        label: 'Phone / Mobile',
        required: false,
        synonyms: [
            'phone',
            'mobile',
            'telephone',
            'tel',
            'phone_number',
            'contact',
            'cell',
            'cellphone',
            'msisdn',
            'mobile_no',
            'phonenumber',
            'mobile_number',
        ],
    },
    {
        key: 'branch',
        label: 'Assigned Branch',
        required: false,
        synonyms: [
            'branch',
            'store',
            'location',
            'outlet',
            'shop',
            'station',
            'branch_name',
            'store_location',
            'site',
            'facility',
            'branchname',
        ],
    },
    {
        key: 'status',
        label: 'Account Status',
        required: false,
        synonyms: [
            'status',
            'state',
            'active',
            'employment_status',
            'account_status',
            'condition',
            'status_name',
        ],
    },
];

// Column mapping state: map system field key -> document raw header index (or -1 for none)
const columnMapping = ref<Record<string, number>>({});

// Selected rows for import
const selectedRowIndexes = ref<number[]>([]);

// Sanitization helpers
const sanitizeString = (val: any, fallback = ''): string => {
    if (val === null || val === undefined) return fallback;
    return String(val).trim();
};

const isValidEmail = (email: string): boolean => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
};

const normalizeRole = (rawRole: string): string => {
    const r = rawRole.toLowerCase();
    if (r.includes('owner') || r.includes('admin') || r.includes('proprietor') || r.includes('director')) return 'Owner';
    if (r.includes('inv') || r.includes('stock') || r.includes('warehouse')) return 'Inventory Manager';
    if (r.includes('account') || r.includes('finance') || r.includes('bookkeep')) return 'Accountant';
    if (r.includes('manag') || r.includes('supervis') || r.includes('lead') || r.includes('overseer')) return 'Manager';
    return 'Cashier';
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
    // Look for email in columns by checking @ character
    if (mapping['email'] === -1) {
        for (let i = 0; i < headers.length; i++) {
            if (usedHeaderIndexes.has(i)) continue;
            const hasEmails = sampleRows.some((row) => String(row[i] || '').includes('@'));
            if (hasEmails) {
                mapping['email'] = i;
                usedHeaderIndexes.add(i);
                break;
            }
        }
    }

    // Look for name in columns with strings of 2+ words or alpha characters
    if (mapping['name'] === -1) {
        for (let i = 0; i < headers.length; i++) {
            if (usedHeaderIndexes.has(i)) continue;
            const isText = sampleRows.every((row) => {
                const val = String(row[i] || '').trim();
                return val.length > 0 && isNaN(Number(val)) && !val.includes('@');
            });
            if (isText) {
                mapping['name'] = i;
                usedHeaderIndexes.add(i);
                break;
            }
        }
    }

    columnMapping.value = mapping;
};

// Arranged staff table preview
export interface ArrangedStaffPreview {
    index: number;
    name: string;
    email: string;
    role: string;
    phone: string;
    branch: string;
    status: string;
    isValid: boolean;
    validationMessage?: string;
}

const arrangedStaff = computed<ArrangedStaffPreview[]>(() => {
    if (rawRows.value.length === 0) return [];

    const nameIdx = columnMapping.value['name'] ?? -1;
    const emailIdx = columnMapping.value['email'] ?? -1;
    const roleIdx = columnMapping.value['role'] ?? -1;
    const phoneIdx = columnMapping.value['phone'] ?? -1;
    const branchIdx = columnMapping.value['branch'] ?? -1;
    const statusIdx = columnMapping.value['status'] ?? -1;

    return rawRows.value.map((row, idx) => {
        const rawName = nameIdx >= 0 ? sanitizeString(row[nameIdx]) : '';
        const rawEmail = emailIdx >= 0 ? sanitizeString(row[emailIdx]) : '';
        const rawRole = roleIdx >= 0 ? sanitizeString(row[roleIdx]) : '';
        const rawPhone = phoneIdx >= 0 ? sanitizeString(row[phoneIdx]) : '';
        const rawBranch = branchIdx >= 0 ? sanitizeString(row[branchIdx]) : '';
        const rawStatus = statusIdx >= 0 ? sanitizeString(row[statusIdx]) : '';

        // Auto-generate placeholder email if missing
        let email = rawEmail;
        if (!email && rawName) {
            const cleanSlug = rawName.toLowerCase().replace(/[^a-z0-9]/g, '.');
            email = `${cleanSlug}@metropos.local`;
        }

        const role = normalizeRole(rawRole);
        const branch = rawBranch || (props.branches && props.branches[0]?.name) || 'Main Branch';
        const status = rawStatus.toLowerCase().includes('inact') || rawStatus.toLowerCase().includes('susp')
            ? 'Suspended'
            : 'Active';

        const hasValidEmail = isValidEmail(email);
        const hasValidName = rawName.length > 0;
        const isValid = hasValidName && hasValidEmail;

        let validationMessage = '';
        if (!hasValidName) validationMessage = 'Missing staff name';
        else if (!hasValidEmail) validationMessage = 'Invalid email address format';

        return {
            index: idx,
            name: rawName || `Staff Member #${idx + 1}`,
            email,
            role,
            phone: rawPhone || '—',
            branch,
            status,
            isValid,
            validationMessage,
        };
    });
});

// Summary Metrics
const validStaffCount = computed(() => arrangedStaff.value.filter((s) => s.isValid).length);
const selectedValidCount = computed(() =>
    arrangedStaff.value.filter((s) => s.isValid && selectedRowIndexes.value.includes(s.index)).length,
);
const uniqueRolesCount = computed(() => {
    const set = new Set(arrangedStaff.value.map((s) => s.role).filter(Boolean));
    return set.size;
});
const uniqueBranchesCount = computed(() => {
    const set = new Set(arrangedStaff.value.map((s) => s.branch).filter(Boolean));
    return set.size;
});

// PDF stream text extractor
const extractPdfText = async (file: File): Promise<string[]> => {
    const buffer = await file.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    const textDecoder = new TextDecoder('utf-8');
    const rawPdf = textDecoder.decode(bytes);

    // Extract text blocks inside streams or parenthesis
    const lines: string[] = [];

    // Search for stream blocks
    const streamRegex = /stream[\r\n]+([\s\S]*?)[\r\n]+endstream/g;
    let match;
    let combinedText = '';

    while ((match = streamRegex.exec(rawPdf)) !== null) {
        const streamData = match[1];
        // Look for literal text in parentheses (text) Tj or TJ
        const textMatches = streamData.match(/\((.*?)\)\s*(?:Tj|TJ|'|")/g);
        if (textMatches) {
            const chunk = textMatches
                .map((m) => m.replace(/^\(|\)\s*(?:Tj|TJ|'|")$/g, ''))
                .join(' ');
            combinedText += chunk + '\n';
        }
    }

    // Fallback: search for plain parenthesis text anywhere in document
    if (!combinedText.trim()) {
        const allParenthesis = rawPdf.match(/\(([A-Za-z0-9@._\s\-+,:]{3,})\)/g);
        if (allParenthesis && allParenthesis.length > 5) {
            combinedText = allParenthesis.map((m) => m.slice(1, -1)).join(' ');
        }
    }

    if (combinedText.trim()) {
        return combinedText.split(/\r?\n/).filter((l) => l.trim().length > 0);
    }

    throw new Error('Could not extract text lines from PDF. If the PDF contains scanned images, please copy-paste the text table using the Paste Table Text tab.');
};

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

            success('Spreadsheet Parsed', `Extracted ${rows.length} rows from ${file.name}. Automatically arranged into staff columns.`);
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
        } else if (ext === 'pdf') {
            const lines = await extractPdfText(file);
            parseDelimitedTextLines(lines);
        } else if (ext === 'json') {
            const text = await file.text();
            const parsed = JSON.parse(text);
            const array = Array.isArray(parsed) ? parsed : parsed.staff || parsed.users || parsed.employees || [parsed];
            if (Array.isArray(array) && array.length > 0) {
                const headers = Object.keys(array[0]);
                const rows = array.map((item) => headers.map((h) => item[h]));
                rawHeaders.value = headers;
                rawRows.value = rows;
                autoDetectAndArrangeColumns(headers, rows.slice(0, 10));
                selectedRowIndexes.value = rows.map((_, i) => i);
                success('JSON Parsed', `Loaded ${rows.length} staff members from JSON document.`);
            } else {
                error('Invalid JSON', 'JSON file must contain an array of staff objects.');
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
        error('Insufficient Data', 'Document needs at least a header line and one staff row.');
        return;
    }

    const firstLine = cleanLines[0];
    let delimiter = ',';
    if (firstLine.includes('\t')) delimiter = '\t';
    else if (firstLine.includes('|')) delimiter = '|';
    else if (firstLine.includes(';')) delimiter = ';';
    else if (firstLine.includes('   ')) delimiter = '   ';

    const parsed2D = cleanLines.map((line) =>
        line
            .split(delimiter === '   ' ? /\s{2,}/ : delimiter)
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
    if (e.dataTransfer?.files && e.dataTransfer.files[0]) {
        processFile(e.dataTransfer.files[0]);
    }
};

const handleParsePasted = () => {
    if (!pastedText.value.trim()) {
        warning('Empty Text', 'Please paste table text or comma/tab separated lines first.');
        return;
    }
    uploadedFileName.value = 'Clipboard Text Buffer';
    uploadedFileSize.value = `${(pastedText.value.length / 1024).toFixed(1)} KB`;
    uploadedFileType.value = 'PASTE';
    parseDelimitedTextLines(pastedText.value.split(/\r?\n/));
};

const toggleSelectAll = () => {
    if (selectedRowIndexes.value.length === arrangedStaff.value.length) {
        selectedRowIndexes.value = [];
    } else {
        selectedRowIndexes.value = arrangedStaff.value.map((s) => s.index);
    }
};

const toggleSelectRow = (index: number) => {
    const idx = selectedRowIndexes.value.indexOf(index);
    if (idx >= 0) {
        selectedRowIndexes.value.splice(idx, 1);
    } else {
        selectedRowIndexes.value.push(index);
    }
};

const removeRow = (index: number) => {
    rawRows.value.splice(index, 1);
    selectedRowIndexes.value = selectedRowIndexes.value
        .filter((i) => i !== index)
        .map((i) => (i > index ? i - 1 : i));
};

const resetImportState = () => {
    rawHeaders.value = [];
    rawRows.value = [];
    columnMapping.value = {};
    selectedRowIndexes.value = [];
    uploadedFileName.value = '';
    uploadedFileSize.value = '';
    uploadedFileType.value = '';
    pastedText.value = '';
};

const downloadStaffTemplate = () => {
    const sampleHeaders = ['Full Name', 'Email Address', 'Role', 'Phone Number', 'Branch Location', 'Status'];
    const sampleData = [
        ['Grace Wanjiku', 'grace.wanjiku@metropos.local', 'Cashier', '+254 712 345 678', 'Westlands Mega Store', 'Active'],
        ['David Mwangi', 'david.mwangi@metropos.local', 'Manager', '+254 722 987 654', 'Westlands Mega Store', 'Active'],
        ['Kevin Otieno', 'kevin.otieno@metropos.local', 'Inventory Manager', '+254 733 112 233', 'Westlands Mega Store', 'Active'],
        ['Hassan Ali', 'hassan.ali@metropos.local', 'Accountant', '+254 701 556 778', 'HQ Central', 'Active'],
        ['Sarah Muthoni', 'sarah.muthoni@metropos.local', 'Cashier', '+254 798 443 221', 'CBD City Square Branch', 'Active'],
    ];

    const ws = XLSX.utils.aoa_to_sheet([sampleHeaders, ...sampleData]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Staff Directory');
    XLSX.writeFile(wb, 'staff_directory_import_template.xlsx');
    success('Template Downloaded', 'Staff import template downloaded (Excel format).');
};

const executeImport = () => {
    const selected = arrangedStaff.value.filter((s) => s.isValid && selectedRowIndexes.value.includes(s.index));
    if (selected.length === 0) {
        warning('No Valid Staff Selected', 'Please select at least one valid staff member to import.');
        return;
    }

    isSubmitting.value = true;
    router.post(
        '/users/import',
        {
            staff: selected.map((s) => ({
                name: s.name,
                email: s.email,
                role: s.role,
                phone: s.phone === '—' ? null : s.phone,
                branch: s.branch,
                status: s.status,
            })),
            update_existing: updateExisting.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                success('Staff Import Successful', `Imported ${selected.length} staff members into your organization directory.`);
                emit('imported');
                emit('update:open', false);
                resetImportState();
            },
            onError: (errs) => {
                error('Import Failed', Object.values(errs)[0] as string || 'An error occurred during import.');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

// Reset when opened
watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            resetImportState();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="w-[94vw] max-w-3xl max-h-[85vh] flex flex-col p-0 overflow-hidden border-border/90 shadow-2xl">
            <!-- Modal Header -->
            <DialogHeader class="p-3.5 sm:p-4 border-b border-border/80 bg-muted/20 shrink-0">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 border border-primary/20 shadow-xs">
                            <Users class="size-4.5" />
                        </div>
                        <div>
                            <DialogTitle class="text-sm sm:text-base font-bold text-foreground flex items-center gap-2">
                                <span>Import Staff Directory</span>
                                <Badge variant="outline" class="text-[10px] font-mono border-primary/30 text-primary">
                                    Excel · Word · PDF · CSV
                                </Badge>
                            </DialogTitle>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Upload a spreadsheet or document, or paste table text. The system auto-arranges staff into roles & credentials.
                            </p>
                        </div>
                    </div>

                    <!-- Template download button -->
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        class="h-7 text-xs gap-1.5 border-border shadow-xs self-start sm:self-auto"
                        @click="downloadStaffTemplate"
                    >
                        <Download class="size-3 text-muted-foreground" />
                        Download Template
                    </Button>
                </div>

                <!-- Input Method Switcher Tabs -->
                <div class="flex items-center gap-2 mt-2.5 pt-2 border-t border-border/60">
                    <button
                        type="button"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all',
                            importMethod === 'file'
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'bg-muted/50 text-muted-foreground hover:text-foreground',
                        ]"
                        @click="importMethod = 'file'"
                    >
                        <Upload class="size-3" />
                        <span>File Upload (Word / PDF / Excel)</span>
                    </button>

                    <button
                        type="button"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all',
                            importMethod === 'paste'
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'bg-muted/50 text-muted-foreground hover:text-foreground',
                        ]"
                        @click="importMethod = 'paste'"
                    >
                        <FileText class="size-3" />
                        <span>Paste Table Text</span>
                    </button>
                </div>
            </DialogHeader>

            <!-- Modal Body (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-3.5 sm:p-4 space-y-4">
                <!-- 1. Upload or Paste Area (Visible when no rows parsed yet) -->
                <div v-if="rawRows.length === 0" class="space-y-4">
                    <!-- File Drag & Drop -->
                    <div v-if="importMethod === 'file'">
                        <div
                            :class="[
                                'border-2 border-dashed rounded-2xl p-8 text-center transition-all cursor-pointer relative bg-card',
                                isDragging
                                    ? 'border-primary bg-primary/5 scale-[0.99]'
                                    : 'border-border/90 hover:border-primary/50 hover:bg-muted/20',
                            ]"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop"
                            @click="($refs.fileInput as HTMLInputElement)?.click()"
                        >
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".xlsx,.xls,.csv,.tsv,.txt,.docx,.pdf,.json"
                                class="hidden"
                                @change="handleFileSelect"
                            />

                            <div class="size-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mx-auto mb-3 border border-primary/20 shadow-xs">
                                <Upload class="size-6" />
                            </div>

                            <p class="text-sm font-bold text-foreground">
                                Drag & drop your staff roster file here, or <span class="text-primary underline">browse</span>
                            </p>
                            <p class="text-xs text-muted-foreground mt-1">
                                Supported formats: <strong>Excel (.xlsx, .xls)</strong>, <strong>Word (.docx tables)</strong>, <strong>PDF (.pdf)</strong>, <strong>CSV</strong>, <strong>TXT</strong>
                            </p>

                            <div class="flex items-center justify-center gap-2 mt-4 flex-wrap">
                                <Badge variant="outline" class="text-[10px] gap-1 bg-background">
                                    <FileSpreadsheet class="size-3 text-emerald-500" /> Excel Spreadsheet
                                </Badge>
                                <Badge variant="outline" class="text-[10px] gap-1 bg-background">
                                    <FileText class="size-3 text-blue-500" /> Word Document (.docx)
                                </Badge>
                                <Badge variant="outline" class="text-[10px] gap-1 bg-background">
                                    <FileType2 class="size-3 text-rose-500" /> PDF Document (.pdf)
                                </Badge>
                                <Badge variant="outline" class="text-[10px] gap-1 bg-background">
                                    <FileText class="size-3 text-amber-500" /> CSV / TSV Text
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <!-- Paste Text Area -->
                    <div v-else class="space-y-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold text-foreground">
                                Paste Table Data from Clipboard (Excel, Word, PDF or Google Sheets)
                            </Label>
                            <textarea
                                v-model="pastedText"
                                rows="6"
                                placeholder="Full Name	Email Address	Role	Phone Number	Branch
Grace Wanjiku	grace@metropos.local	Cashier	+254712345678	Westlands Mega Store
David Mwangi	david@metropos.local	Manager	+254722987654	Westlands Mega Store
Kevin Otieno	kevin@metropos.local	Inventory Manager	+254733112233	Westlands Mega Store"
                                class="w-full font-mono text-xs p-3 rounded-xl border border-input bg-background resize-y focus:ring-1 focus:ring-primary focus:outline-none"
                            ></textarea>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            class="text-xs gap-1.5 bg-primary"
                            :disabled="!pastedText.trim() || isProcessing"
                            @click="handleParsePasted"
                        >
                            <Sparkles class="size-3.5" />
                            Parse & Auto-Arrange Staff Table
                        </Button>
                    </div>

                    <!-- Format Guidelines Box -->
                    <div class="p-3.5 rounded-xl border border-border/80 bg-muted/20 text-xs space-y-1.5 text-muted-foreground">
                        <div class="font-bold text-foreground flex items-center gap-1.5">
                            <Sparkles class="size-3.5 text-primary" />
                            How the Auto-Arranger Engine Works
                        </div>
                        <p class="leading-relaxed">
                            You don't need to rename your columns! The system automatically scans header synonyms (e.g. <em>Staff, Employee, Full Name</em> &rarr; <strong>Name</strong>; <em>Mail, E-mail</em> &rarr; <strong>Email</strong>; <em>Position, Title, Designation</em> &rarr; <strong>Role</strong>).
                        </p>
                    </div>
                </div>

                <!-- 2. Parsed & Arranged Preview Section (Visible once document is parsed) -->
                <div v-else class="space-y-4">
                    <!-- File Metadata Bar -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3 bg-muted/30 rounded-xl border border-border/80 text-xs">
                        <div class="flex items-center gap-2">
                            <Badge variant="outline" class="font-mono text-[10px] bg-background">
                                {{ uploadedFileType }}
                            </Badge>
                            <span class="font-bold text-foreground truncate max-w-xs">{{ uploadedFileName }}</span>
                            <span class="text-muted-foreground">({{ uploadedFileSize }})</span>
                            <span class="text-muted-foreground">•</span>
                            <span class="font-semibold text-foreground">{{ rawRows.length }} rows extracted</span>
                        </div>

                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            class="h-7 text-xs gap-1 text-muted-foreground hover:text-foreground self-start sm:self-auto"
                            @click="resetImportState"
                        >
                            <RefreshCw class="size-3" />
                            Change Document
                        </Button>
                    </div>

                    <!-- Summary Telemetry Cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                        <div class="p-3 bg-card rounded-xl border border-border/80">
                            <span class="text-muted-foreground text-[11px]">Total Staff Extracted</span>
                            <p class="text-base font-bold font-mono text-foreground mt-0.5">
                                {{ arrangedStaff.length }} Members
                            </p>
                        </div>
                        <div class="p-3 bg-card rounded-xl border border-border/80">
                            <span class="text-muted-foreground text-[11px]">Valid & Ready</span>
                            <p class="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                                {{ validStaffCount }} Ready
                            </p>
                        </div>
                        <div class="p-3 bg-card rounded-xl border border-border/80">
                            <span class="text-muted-foreground text-[11px]">Roles Detected</span>
                            <p class="text-base font-bold font-mono text-primary mt-0.5">
                                {{ uniqueRolesCount }} Roles
                            </p>
                        </div>
                        <div class="p-3 bg-card rounded-xl border border-border/80">
                            <span class="text-muted-foreground text-[11px]">Branches Assigned</span>
                            <p class="text-base font-bold font-mono text-foreground mt-0.5">
                                {{ uniqueBranchesCount }} Stores
                            </p>
                        </div>
                    </div>

                    <!-- Column Mapping Adjuster Accordion -->
                    <details class="group border border-border/80 rounded-xl bg-card overflow-hidden">
                        <summary class="p-3 text-xs font-bold text-foreground cursor-pointer flex items-center justify-between hover:bg-muted/20 transition-colors list-none">
                            <span class="flex items-center gap-1.5">
                                <Layers class="size-3.5 text-primary" />
                                Inspect / Adjust Auto-Detected Column Mappings
                            </span>
                            <span class="text-[11px] text-muted-foreground font-normal group-open:rotate-180 transition-transform">
                                ▼
                            </span>
                        </summary>
                        <div class="p-3 pt-1 border-t border-border/70 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2 text-xs">
                            <div v-for="field in SYSTEM_FIELDS" :key="field.key" class="space-y-1">
                                <label class="text-[10px] font-bold text-muted-foreground flex items-center gap-0.5">
                                    <span>{{ field.label }}</span>
                                    <span v-if="field.required" class="text-rose-500">*</span>
                                </label>
                                <select
                                    v-model="columnMapping[field.key]"
                                    class="w-full text-xs p-1.5 rounded-lg border border-input bg-background font-mono"
                                >
                                    <option :value="-1">— Skip / Default —</option>
                                    <option
                                        v-for="(h, idx) in rawHeaders"
                                        :key="idx"
                                        :value="idx"
                                    >
                                        {{ h || `Column ${idx + 1}` }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </details>

                    <!-- Auto-Arranged Staff Preview Table -->
                    <div class="border border-border/80 rounded-xl overflow-hidden bg-card">
                        <div class="p-2.5 bg-muted/30 border-b border-border/80 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="text-xs font-bold text-primary hover:underline"
                                    @click="toggleSelectAll"
                                >
                                    {{ selectedRowIndexes.length === arrangedStaff.length ? 'Deselect All' : 'Select All' }}
                                </button>
                                <span class="text-muted-foreground">·</span>
                                <span class="text-muted-foreground">
                                    <strong>{{ selectedValidCount }}</strong> of {{ validStaffCount }} selected for import
                                </span>
                            </div>

                            <span class="text-[11px] text-muted-foreground">
                                Default temporary password: <code class="bg-muted px-1.5 py-0.5 rounded font-mono text-foreground font-bold">Password@2026!</code>
                            </span>
                        </div>

                        <div class="overflow-x-auto max-h-[380px]">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-muted/40 text-muted-foreground border-b border-border/80 sticky top-0 z-10 backdrop-blur-xs">
                                    <tr>
                                        <th class="px-3 py-2 w-8 text-center">
                                            <input
                                                type="checkbox"
                                                :checked="selectedRowIndexes.length > 0 && selectedRowIndexes.length === arrangedStaff.length"
                                                class="rounded border-input text-primary focus:ring-primary size-3.5"
                                                @change="toggleSelectAll"
                                            />
                                        </th>
                                        <th class="px-3 py-2 font-semibold uppercase">Staff Name</th>
                                        <th class="px-3 py-2 font-semibold uppercase">Email Address</th>
                                        <th class="px-3 py-2 font-semibold uppercase">Role</th>
                                        <th class="px-3 py-2 font-semibold uppercase">Phone Number</th>
                                        <th class="px-3 py-2 font-semibold uppercase">Branch</th>
                                        <th class="px-3 py-2 font-semibold uppercase text-center">Status</th>
                                        <th class="px-3 py-2 font-semibold uppercase text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60 font-sans">
                                    <tr
                                        v-for="staff in arrangedStaff"
                                        :key="staff.index"
                                        :class="[
                                            'hover:bg-muted/20 transition-colors',
                                            !staff.isValid ? 'bg-rose-500/5' : '',
                                            selectedRowIndexes.includes(staff.index) ? 'bg-primary/5' : '',
                                        ]"
                                    >
                                        <td class="px-3 py-2 text-center">
                                            <input
                                                type="checkbox"
                                                :checked="selectedRowIndexes.includes(staff.index)"
                                                :disabled="!staff.isValid"
                                                class="rounded border-input text-primary focus:ring-primary size-3.5"
                                                @change="toggleSelectRow(staff.index)"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-foreground">{{ staff.name }}</span>
                                                <Badge
                                                    v-if="!staff.isValid"
                                                    variant="destructive"
                                                    class="text-[9px] px-1 py-0"
                                                >
                                                    {{ staff.validationMessage }}
                                                </Badge>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 font-mono text-[11px] text-muted-foreground">
                                            {{ staff.email }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <Badge
                                                :variant="staff.role === 'Owner' ? 'default' : staff.role === 'Manager' ? 'info' : 'outline'"
                                                class="font-bold text-[10px] font-mono"
                                            >
                                                {{ staff.role }}
                                            </Badge>
                                        </td>
                                        <td class="px-3 py-2 font-mono text-muted-foreground text-[11px]">
                                            {{ staff.phone }}
                                        </td>
                                        <td class="px-3 py-2 text-muted-foreground text-[11px]">
                                            {{ staff.branch }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <Badge :variant="staff.status === 'Active' ? 'success' : 'destructive'" class="text-[10px]">
                                                {{ staff.status }}
                                            </Badge>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="h-6 w-6 p-0 text-muted-foreground hover:text-destructive"
                                                title="Remove Row"
                                                @click="removeRow(staff.index)"
                                            >
                                                <Trash2 class="size-3" />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <DialogFooter class="p-3 border-t border-border/80 bg-muted/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 shrink-0">
                <div class="flex items-center gap-2 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            v-model="updateExisting"
                            type="checkbox"
                            class="rounded border-input text-primary focus:ring-primary size-3.5"
                        />
                        <span class="text-muted-foreground text-[11px]">Update existing staff if email already exists</span>
                    </label>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        class="h-8 text-xs gap-1.5 bg-primary px-3.5 shadow-sm"
                        :disabled="isSubmitting || selectedValidCount === 0"
                        @click="executeImport"
                    >
                        <Loader2 v-if="isSubmitting" class="size-3 animate-spin" />
                        <CheckCircle2 v-else class="size-3" />
                        <span>Import {{ selectedValidCount }} Staff Members</span>
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
