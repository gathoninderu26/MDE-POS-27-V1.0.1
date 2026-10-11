<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import { router } from '@inertiajs/vue3';
import { Camera, CheckCircle2, FileText, Loader2, RefreshCw, Scan, ScanLine, Upload, VideoOff } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    purchase?: any | null;
    purchases?: any[];
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'scanned', result: any): void;
    (e: 'saved', result: any): void;
}>();

const { error, success, info } = useToast();

const internalSelectedPurchaseId = ref<number | null>(null);
const manualInvoiceNumber = ref('');
const manualInvoiceDate = ref(new Date().toISOString().slice(0, 10));
const manualTotalAmount = ref<number | null>(null);

watch(
    () => [props.open, props.purchase],
    () => {
        if (props.purchase) {
            internalSelectedPurchaseId.value = props.purchase.id;
        } else if (props.purchases && props.purchases.length > 0) {
            const withoutInvoice = props.purchases.find((p) => !p.invoice);
            internalSelectedPurchaseId.value = withoutInvoice ? withoutInvoice.id : props.purchases[0].id;
        }
    },
    { immediate: true },
);

const activePurchase = computed(() => {
    if (props.purchase) return props.purchase;
    if (internalSelectedPurchaseId.value && props.purchases) {
        return props.purchases.find((p) => p.id === internalSelectedPurchaseId.value) || null;
    }
    return null;
});

watch(
    () => activePurchase.value,
    (po) => {
        if (po) {
            const ref = po.documentType === 'LPO' ? po.lpoNumber || po.poNumber : po.poNumber;
            manualInvoiceNumber.value = po.invoice?.invoiceNumber || `INV-${ref}`;
            manualInvoiceDate.value = po.invoice?.invoiceDate || new Date().toISOString().slice(0, 10);
            manualTotalAmount.value = po.invoice?.totalAmount || Number(po.amount) || 0;
        }
    },
    { immediate: true },
);

const scanMode = ref<'camera' | 'file'>('camera');
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const isCameraActive = ref(false);
const cameraError = ref<string | null>(null);
const isProcessing = ref(false);
const isSavingSoftcopy = ref(false);
const capturedImageBase64 = ref<string | null>(null);
const selectedFile = ref<File | null>(null);
const filePreviewUrl = ref<string | null>(null);

// Scanned QR Details
const detectedQrPayload = ref<string>('');
const isQrDetected = ref(false);
let mediaStream: MediaStream | null = null;
let animationFrameId: number | null = null;
let barcodeDetector: any = null;

// Helper to extract clean invoice number from QR
const parseQrData = (payload: string) => {
    if (!payload) return;
    const invMatch = payload.match(/(?:invoiceNo|inv|invoice_number|num)=([^&,\s]+)/i);
    if (invMatch && invMatch[1]) {
        manualInvoiceNumber.value = decodeURIComponent(invMatch[1]);
    }
    const totMatch = payload.match(/(?:tot|total|amount)=([0-9.]+)/i);
    if (totMatch && totMatch[1]) {
        manualTotalAmount.value = parseFloat(totMatch[1]);
    }
};

// Initialize BarcodeDetector API if supported in browser
if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
    try {
        barcodeDetector = new (window as any).BarcodeDetector({
            formats: ['qr_code', 'code_128', 'ean_13', 'data_matrix'],
        });
    } catch {
        barcodeDetector = null;
    }
}

// POS Confirmation Beep Audio
const playBeep = () => {
    try {
        const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1900, ctx.currentTime);
        gain.gain.setValueAtTime(0.18, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.12);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.12);
    } catch {
        // audio context optional
    }
};

const startCamera = async () => {
    cameraError.value = null;
    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value = 'Camera access is not supported by your browser or connection is not secure (HTTPS).';
        scanMode.value = 'file';
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1920 },
                height: { ideal: 1080 },
            },
            audio: false,
        });

        mediaStream = stream;
        await nextTick();
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            videoRef.value.setAttribute('playsinline', 'true');
            await videoRef.value.play();
            isCameraActive.value = true;
            startScanningFrames();
        }
    } catch (err: any) {
        console.error('Camera access error:', err);
        cameraError.value = 'Unable to access camera. Please allow camera permissions or upload an invoice file.';
        scanMode.value = 'file';
    }
};

const stopCamera = () => {
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
    isCameraActive.value = false;
};

// Continuous frame scanning for QR codes
const startScanningFrames = () => {
    if (!barcodeDetector || !videoRef.value) return;

    const scanFrame = async () => {
        if (!isCameraActive.value || !videoRef.value || videoRef.value.readyState < 2) {
            animationFrameId = requestAnimationFrame(scanFrame);
            return;
        }

        try {
            const barcodes = await barcodeDetector.detect(videoRef.value);
            if (barcodes && barcodes.length > 0) {
                const qr = barcodes[0].rawValue;
                if (qr && qr !== detectedQrPayload.value) {
                    detectedQrPayload.value = qr;
                    isQrDetected.value = true;
                    parseQrData(qr);
                    playBeep();
                    // Automatically take snapshot on QR detection if not already captured
                    if (!capturedImageBase64.value) {
                        captureFrame();
                    }
                }
            }
        } catch {
            // Ignore detection frame exceptions
        }

        if (isCameraActive.value) {
            animationFrameId = requestAnimationFrame(scanFrame);
        }
    };

    animationFrameId = requestAnimationFrame(scanFrame);
};

const captureFrame = () => {
    if (!videoRef.value) return;
    const video = videoRef.value;
    const canvas = canvasRef.value || document.createElement('canvas');
    canvas.width = video.videoWidth || 1280;
    canvas.height = video.videoHeight || 720;
    const ctx = canvas.getContext('2d');
    if (ctx) {
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        capturedImageBase64.value = canvas.toDataURL('image/jpeg', 0.92);
        playBeep();
        stopCamera();
    }
};

const retakePhoto = () => {
    capturedImageBase64.value = null;
    detectedQrPayload.value = '';
    isQrDetected.value = false;
    startCamera();
};

const handleFileSelect = async (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const file = target.files[0];
    selectedFile.value = file;
    filePreviewUrl.value = URL.createObjectURL(file);
    capturedImageBase64.value = null;

    // Attempt to detect QR code from uploaded image
    if (barcodeDetector && file.type.startsWith('image/')) {
        try {
            const img = new Image();
            img.src = filePreviewUrl.value;
            await img.decode();
            const barcodes = await barcodeDetector.detect(img);
            if (barcodes && barcodes.length > 0) {
                detectedQrPayload.value = barcodes[0].rawValue;
                isQrDetected.value = true;
                parseQrData(barcodes[0].rawValue);
                playBeep();
            }
        } catch {
            // Ignore scan failure on image
        }
    }
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
        const file = e.dataTransfer.files[0];
        selectedFile.value = file;
        filePreviewUrl.value = URL.createObjectURL(file);
        capturedImageBase64.value = null;
    }
};

const handleStoreSoftcopyOnly = async () => {
    if (!activePurchase.value) {
        error('Order Required', 'Please select a purchase order for this invoice.');
        return;
    }
    if (!capturedImageBase64.value && !selectedFile.value) {
        error('Scan Required', 'Please capture or upload a physical invoice document.');
        return;
    }

    isSavingSoftcopy.value = true;
    const formData = new FormData();

    if (selectedFile.value) {
        formData.append('invoice_file', selectedFile.value);
    } else if (capturedImageBase64.value) {
        formData.append('image_base64', capturedImageBase64.value);
    }

    if (detectedQrPayload.value) {
        formData.append('qr_payload', detectedQrPayload.value);
    }

    if (manualInvoiceNumber.value) {
        formData.append('invoice_number', manualInvoiceNumber.value.trim());
    }

    if (manualInvoiceDate.value) {
        formData.append('invoice_date', manualInvoiceDate.value);
    }

    if (manualTotalAmount.value !== null && manualTotalAmount.value > 0) {
        formData.append('total_amount', manualTotalAmount.value.toString());
    }

    formData.append('attach_only', '1');

    try {
        const metaToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
        const xsrfCookieMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const xsrfCookie = xsrfCookieMatch ? decodeURIComponent(xsrfCookieMatch[1]) : null;

        const headers: Record<string, string> = {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        };

        if (metaToken) {
            headers['X-CSRF-TOKEN'] = metaToken;
            formData.append('_token', metaToken);
        } else if (xsrfCookie) {
            headers['X-XSRF-TOKEN'] = xsrfCookie;
        }

        const response = await fetch(`/purchases/${activePurchase.value.id}/invoice/upload`, {
            method: 'POST',
            body: formData,
            headers,
            credentials: 'same-origin',
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Failed to attach invoice softcopy.');
        }

        success('Invoice Stored', data.message || 'Invoice document attached and archived successfully.');
        emit('saved', { purchaseId: activePurchase.value.id });
        router.reload({ preserveScroll: true });
        closeModal();
    } catch (err: any) {
        error('Save Failed', err.message || 'Unable to store invoice softcopy.');
    } finally {
        isSavingSoftcopy.value = false;
    }
};

const handleProcessAndCompare = async () => {
    if (!activePurchase.value) {
        error('Order Required', 'Please select a purchase order for this invoice.');
        return;
    }
    if (!capturedImageBase64.value && !selectedFile.value) {
        error('Scan Required', 'Please capture or upload a physical invoice document.');
        return;
    }

    isProcessing.value = true;
    const formData = new FormData();

    if (selectedFile.value) {
        formData.append('invoice_file', selectedFile.value);
    } else if (capturedImageBase64.value) {
        formData.append('image_base64', capturedImageBase64.value);
    }

    if (detectedQrPayload.value) {
        formData.append('qr_payload', detectedQrPayload.value);
    }

    try {
        const metaToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
        const xsrfCookieMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const xsrfCookie = xsrfCookieMatch ? decodeURIComponent(xsrfCookieMatch[1]) : null;

        const headers: Record<string, string> = {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        };

        if (metaToken) {
            headers['X-CSRF-TOKEN'] = metaToken;
            formData.append('_token', metaToken);
        } else if (xsrfCookie) {
            headers['X-XSRF-TOKEN'] = xsrfCookie;
        }

        const response = await fetch(`/purchases/${activePurchase.value.id}/invoice/upload`, {
            method: 'POST',
            body: formData,
            headers,
            credentials: 'same-origin',
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Failed to upload and analyze invoice.');
        }

        success('Invoice Analyzed', 'Document ingested and matched against purchase order items.');
        emit('scanned', {
            ...data,
            localPreview: capturedImageBase64.value || filePreviewUrl.value,
            purchase: activePurchase.value,
        });
        emit('update:open', false);
    } catch (err: any) {
        error('Processing Failed', err.message || 'Unable to analyze invoice scan.');
    } finally {
        isProcessing.value = false;
    }
};

const closeModal = () => {
    stopCamera();
    capturedImageBase64.value = null;
    selectedFile.value = null;
    if (filePreviewUrl.value) {
        URL.revokeObjectURL(filePreviewUrl.value);
        filePreviewUrl.value = null;
    }
    detectedQrPayload.value = '';
    isQrDetected.value = false;
    emit('update:open', false);
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (scanMode.value === 'camera') {
                startCamera();
            }
        } else {
            stopCamera();
        }
    },
);

watch(scanMode, (newMode) => {
    if (props.open) {
        if (newMode === 'camera') {
            startCamera();
        } else {
            stopCamera();
        }
    }
});

onBeforeUnmount(() => {
    stopCamera();
    if (filePreviewUrl.value) {
        URL.revokeObjectURL(filePreviewUrl.value);
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="closeModal">
        <DialogContent class="overflow-hidden border-border bg-card p-0 shadow-2xl sm:max-w-2xl">
            <!-- Header with Gradient Accent -->
            <div class="border-b border-border bg-muted/20 px-6 pb-4 pt-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="shadow-xs flex size-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary"
                        >
                            <Scan class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                                <span>Scan & Capture Physical Invoice</span>
                                <span
                                    v-if="activePurchase"
                                    class="rounded-full bg-sky-500/10 px-2 py-0.5 font-mono text-xs text-sky-600 dark:text-sky-400"
                                >
                                    {{ activePurchase?.refNumber || activePurchase?.poNumber }}
                                </span>
                            </DialogTitle>
                            <p v-if="props.purchase" class="mt-0.5 text-xs text-muted-foreground">
                                Vendor: <strong class="text-foreground">{{ activePurchase?.supplier }}</strong> • PO Value:
                                <strong class="font-mono text-foreground">KES {{ Number(activePurchase?.amount || 0).toLocaleString() }}</strong>
                            </p>
                            <p v-else class="mt-0.5 text-xs text-muted-foreground">
                                Upload a document or capture with camera, and link to a supplier purchase order.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Purchase Order Selector (Shown if opened from Tab section without preselected order) -->
                <div v-if="!props.purchase" class="mt-3.5 rounded-lg border border-border bg-muted/40 p-2.5">
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-[11px] font-semibold text-foreground">Select Purchase Order to Link Invoice:</label>
                        <span v-if="activePurchase" class="font-mono text-[10px] text-muted-foreground">
                            Amount: KES {{ Number(activePurchase.amount || 0).toLocaleString() }}
                        </span>
                    </div>
                    <select
                        v-model="internalSelectedPurchaseId"
                        class="h-8 w-full cursor-pointer rounded-md border border-border bg-background px-2.5 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary"
                    >
                        <option :value="null" disabled>Select an Order...</option>
                        <option v-for="po in purchases || []" :key="po.id" :value="po.id">
                            {{ po.documentType === 'LPO' ? po.lpoNumber || po.poNumber : po.poNumber }} — {{ po.supplier }} (KES
                            {{ Number(po.amount).toLocaleString() }}) {{ po.invoice ? '• [Attached: ' + po.invoice.invoiceNumber + ']' : '' }}
                        </option>
                    </select>
                </div>

                <!-- Segmented Tabs: Live Camera vs File Upload -->
                <div class="mt-4 flex items-center justify-between">
                    <div class="inline-flex items-center gap-1 rounded-lg border border-border bg-muted/40 p-1 text-xs font-semibold">
                        <button
                            type="button"
                            :class="[
                                'flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 transition-all',
                                scanMode === 'camera'
                                    ? 'shadow-xs bg-background font-bold text-primary'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="scanMode = 'camera'"
                        >
                            <Camera class="size-3.5" />
                            <span>Camera Scanner</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 transition-all',
                                scanMode === 'file'
                                    ? 'shadow-xs bg-background font-bold text-primary'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="scanMode = 'file'"
                        >
                            <Upload class="size-3.5" />
                            <span>Upload Scan / PDF</span>
                        </button>
                    </div>

                    <!-- QR Detection Status Badge -->
                    <div
                        v-if="isQrDetected"
                        class="inline-flex animate-pulse items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        <CheckCircle2 class="size-3.5" />
                        <span>QR Code Detected</span>
                    </div>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="space-y-4 p-6">
                <!-- CAMERA SCANNER VIEW -->
                <div
                    v-if="scanMode === 'camera'"
                    class="relative flex aspect-video items-center justify-center overflow-hidden rounded-2xl border border-border bg-slate-950 shadow-inner"
                >
                    <!-- Live Stream -->
                    <video
                        v-show="isCameraActive && !capturedImageBase64"
                        ref="videoRef"
                        class="h-full w-full object-cover"
                        autoplay
                        playsinline
                        muted
                    ></video>

                    <!-- Captured Photo Preview -->
                    <img
                        v-if="capturedImageBase64"
                        :src="capturedImageBase64"
                        alt="Captured Invoice"
                        class="h-full w-full bg-black/90 object-contain"
                    />

                    <!-- Camera Reticle Overlay (When active and not captured) -->
                    <div
                        v-if="isCameraActive && !capturedImageBase64"
                        class="pointer-events-none absolute inset-0 flex items-center justify-center p-8"
                    >
                        <div
                            class="aspect-4/3 relative w-full max-w-sm rounded-xl border-2 border-dashed border-emerald-400/70 shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]"
                        >
                            <!-- Reticle Corners -->
                            <div class="border-t-3 border-l-3 absolute -left-1 -top-1 size-5 border-emerald-400"></div>
                            <div class="border-t-3 border-r-3 absolute -right-1 -top-1 size-5 border-emerald-400"></div>
                            <div class="border-b-3 border-l-3 absolute -bottom-1 -left-1 size-5 border-emerald-400"></div>
                            <div class="border-b-3 border-r-3 absolute -bottom-1 -right-1 size-5 border-emerald-400"></div>

                            <!-- Animated Laser Scan Line -->
                            <div
                                class="h-0.5 w-full animate-pulse bg-gradient-to-r from-transparent via-emerald-400 to-transparent shadow-[0_0_12px_#34d399]"
                            ></div>

                            <div class="absolute inset-x-0 bottom-2 text-center">
                                <span class="backdrop-blur-xs rounded-full bg-black/70 px-2 py-0.5 font-mono text-[10px] text-white">
                                    Align invoice document or QR code
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Camera Error Notice -->
                    <div v-if="cameraError" class="max-w-sm space-y-3 p-6 text-center">
                        <VideoOff class="mx-auto size-10 text-amber-500" />
                        <p class="text-xs text-slate-300">{{ cameraError }}</p>
                        <Button variant="outline" size="sm" class="text-xs" @click="scanMode = 'file'"> Switch to File Upload </Button>
                    </div>

                    <!-- Controls floating over camera -->
                    <div class="absolute inset-x-0 bottom-4 flex items-center justify-center gap-3">
                        <Button
                            v-if="isCameraActive && !capturedImageBase64"
                            type="button"
                            size="sm"
                            class="h-9 gap-1.5 rounded-full bg-emerald-600 px-4 font-semibold text-white shadow-lg hover:bg-emerald-700"
                            @click="captureFrame"
                        >
                            <Camera class="size-4" />
                            <span>Capture Photo</span>
                        </Button>
                        <Button
                            v-if="capturedImageBase64"
                            type="button"
                            variant="secondary"
                            size="sm"
                            class="h-8 gap-1 rounded-full text-xs font-semibold"
                            @click="retakePhoto"
                        >
                            <RefreshCw class="size-3" />
                            <span>Retake Photo</span>
                        </Button>
                    </div>
                </div>

                <!-- FILE UPLOAD VIEW -->
                <div v-else class="space-y-3">
                    <div
                        class="cursor-pointer rounded-2xl border-2 border-dashed border-border bg-muted/10 p-8 text-center transition-colors hover:border-primary/50 hover:bg-muted/20"
                        @click="fileInputRef?.click()"
                        @dragover.prevent
                        @drop="handleDrop"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            class="hidden"
                            @change="handleFileSelect"
                        />

                        <div v-if="!filePreviewUrl" class="space-y-3">
                            <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <Upload class="size-6" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-foreground">Click to upload or drag & drop invoice scan</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">Supports JPG, PNG, WebP, or PDF up to 10MB</p>
                            </div>
                        </div>

                        <!-- Preview if Selected -->
                        <div v-else class="space-y-3">
                            <div class="shadow-xs relative mx-auto max-h-48 max-w-sm overflow-hidden rounded-xl border border-border">
                                <img
                                    v-if="selectedFile?.type.startsWith('image/')"
                                    :src="filePreviewUrl"
                                    alt="Invoice Preview"
                                    class="max-h-48 w-full bg-muted/40 object-contain"
                                />
                                <div v-else class="flex items-center justify-center gap-2 p-8 text-xs font-semibold text-foreground">
                                    <FileText class="size-8 text-rose-500" />
                                    <span>{{ selectedFile?.name }}</span>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" class="h-7 gap-1 text-xs" @click.stop="fileInputRef?.click()">
                                <RefreshCw class="size-3" />
                                <span>Choose Different File</span>
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- INVOICE METADATA INPUTS (Clean & Professional) -->
                <div v-if="capturedImageBase64 || selectedFile" class="space-y-3 rounded-xl border border-border bg-muted/30 p-3.5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-foreground">Invoice / Receipt No:</label>
                            <Input v-model="manualInvoiceNumber" type="text" placeholder="e.g. INV-2026-001" class="h-8 font-mono text-xs" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-foreground">Invoice Date:</label>
                            <Input v-model="manualInvoiceDate" type="date" class="h-8 font-mono text-xs" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-foreground">Billed Total (KES):</label>
                            <Input
                                v-model.number="manualTotalAmount"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="h-8 font-mono text-xs font-bold"
                            />
                        </div>
                    </div>

                    <!-- Clean QR Verification Confirmation (Without raw URL clutter) -->
                    <div
                        v-if="detectedQrPayload"
                        class="flex items-center justify-between gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 py-1.5 text-xs"
                    >
                        <div class="flex items-center gap-1.5 font-semibold text-emerald-600 dark:text-emerald-400">
                            <CheckCircle2 class="size-3.5 shrink-0" />
                            <span>Fiscal / QR Code Decoded</span>
                        </div>
                        <span class="font-mono text-[10px] text-muted-foreground">Ready to archive</span>
                    </div>
                </div>
            </div>

            <!-- Dialog Footer -->
            <DialogFooter class="flex flex-col items-center justify-between gap-2.5 border-t border-border bg-muted/20 px-6 py-4 sm:flex-row">
                <Button variant="ghost" size="sm" class="order-last text-xs sm:order-first" @click="closeModal"> Cancel </Button>

                <div class="flex w-full items-center justify-end gap-2 sm:w-auto">
                    <!-- Optional: Item-by-Item PO Comparison -->
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="shadow-xs h-9 cursor-pointer gap-1.5 border-border px-3 text-xs font-medium text-muted-foreground hover:text-foreground"
                        :disabled="isProcessing || isSavingSoftcopy || (!capturedImageBase64 && !selectedFile)"
                        @click="handleProcessAndCompare"
                        title="Compare line items against Purchase Order"
                    >
                        <Loader2 v-if="isProcessing" class="size-3.5 animate-spin" />
                        <ScanLine v-else class="size-3.5" />
                        <span>Compare PO Items</span>
                    </Button>

                    <!-- Primary Action: Save & Store Invoice Directly -->
                    <Button
                        type="button"
                        size="sm"
                        class="shadow-xs h-9 cursor-pointer gap-1.5 bg-indigo-600 px-4 text-xs font-bold text-white hover:bg-indigo-700"
                        :disabled="isProcessing || isSavingSoftcopy || (!capturedImageBase64 && !selectedFile)"
                        @click="handleStoreSoftcopyOnly"
                    >
                        <Loader2 v-if="isSavingSoftcopy" class="size-3.5 animate-spin" />
                        <FileText v-else class="size-3.5" />
                        <span>{{ isSavingSoftcopy ? 'Saving Softcopy...' : 'Save & Store Invoice' }}</span>
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
