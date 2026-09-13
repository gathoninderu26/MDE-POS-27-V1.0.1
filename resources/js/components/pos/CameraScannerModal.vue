<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import {
    AlertCircle,
    Camera,
    Check,
    Flashlight,
    FlipHorizontal,
    Maximize2,
    RefreshCw,
    ScanBarcode,
    Sparkles,
    Volume2,
    VolumeX,
    X,
    Zap,
} from 'lucide-vue-next';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'scan', barcode: string): void;
}>();

const { error, success, info } = useToast();

const videoRef = ref<HTMLVideoElement | null>(null);
const stream = ref<MediaStream | null>(null);
const cameraActive = ref(false);
const cameraError = ref<string | null>(null);
const facingMode = ref<'environment' | 'user'>('environment');
const torchOn = ref(false);
const torchSupported = ref(false);
const soundEnabled = ref(true);
const continuousMode = ref(true);
const manualBarcode = ref('');
const lastScanned = ref<string | null>(null);
const scanCount = ref(0);
const isScanning = ref(false);

let scanInterval: any = null;
let barcodeDetector: any = null;

// Synthesize pleasant POS scanner beep using Web Audio API
const playBeep = () => {
    if (!soundEnabled.value) return;
    try {
        const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1760, audioCtx.currentTime); // High pitch (A6)
        gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.12);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.12);
    } catch {
        // Audio API may be restricted until user gesture
    }
};

const triggerHaptic = () => {
    if (typeof navigator !== 'undefined' && navigator.vibrate) {
        try {
            navigator.vibrate([70]);
        } catch {}
    }
};

const handleBarcodeDetected = (code: string) => {
    if (!code || isScanning.value) return;
    
    // Debounce duplicate reads within 1.2 seconds for continuous scanning
    if (lastScanned.value === code && isScanning.value) return;

    isScanning.value = true;
    lastScanned.value = code;
    scanCount.value++;

    playBeep();
    triggerHaptic();

    emit('scan', code);

    if (!continuousMode.value) {
        emit('update:open', false);
    } else {
        setTimeout(() => {
            isScanning.value = false;
        }, 1200);
    }
};

const startCamera = async () => {
    cameraError.value = null;
    cameraActive.value = false;

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        cameraError.value = 'Camera API is not supported on this browser/device.';
        return;
    }

    try {
        stopCamera();

        const constraints: MediaStreamConstraints = {
            video: {
                facingMode: facingMode.value,
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
            audio: false,
        };

        const mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
        stream.value = mediaStream;

        await nextTick();
        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            await videoRef.value.play();
            cameraActive.value = true;

            // Check torch support
            const track = mediaStream.getVideoTracks()[0];
            const capabilities: any = track?.getCapabilities ? track.getCapabilities() : {};
            torchSupported.value = !!capabilities?.torch;

            startBarcodeLoop();
        }
    } catch (err: any) {
        console.warn('Camera access error:', err);
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            cameraError.value = 'Camera permission denied. Please allow camera access in browser settings.';
        } else if (err.name === 'NotFoundError') {
            cameraError.value = 'No rear camera found on this device.';
        } else {
            cameraError.value = 'Unable to start camera stream. Use manual barcode input below.';
        }
    }
};

const stopCamera = () => {
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }
    if (stream.value) {
        stream.value.getTracks().forEach((track) => track.stop());
        stream.value = null;
    }
    cameraActive.value = false;
    torchOn.value = false;
};

const toggleTorch = async () => {
    if (!stream.value || !torchSupported.value) return;
    const track = stream.value.getVideoTracks()[0];
    if (track) {
        try {
            torchOn.value = !torchOn.value;
            await (track as any).applyConstraints({
                advanced: [{ torch: torchOn.value }],
            });
        } catch (e) {
            torchOn.value = false;
        }
    }
};

const toggleCamera = () => {
    facingMode.value = facingMode.value === 'environment' ? 'user' : 'environment';
    startCamera();
};

const startBarcodeLoop = () => {
    if (scanInterval) clearInterval(scanInterval);

    // Check if BarcodeDetector is available natively
    if (typeof (window as any).BarcodeDetector !== 'undefined') {
        try {
            barcodeDetector = new (window as any).BarcodeDetector({
                formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'qr_code', 'upc_a', 'upc_e'],
            });
        } catch {
            barcodeDetector = null;
        }
    }

    scanInterval = setInterval(async () => {
        if (!videoRef.value || !cameraActive.value || isScanning.value) return;

        if (barcodeDetector && videoRef.value.readyState === videoRef.value.HAVE_ENOUGH_DATA) {
            try {
                const barcodes = await barcodeDetector.detect(videoRef.value);
                if (barcodes && barcodes.length > 0) {
                    const detected = barcodes[0].rawValue;
                    if (detected) {
                        handleBarcodeDetected(detected);
                    }
                }
            } catch {}
        }
    }, 250);
};

const handleManualSubmit = () => {
    if (!manualBarcode.value.trim()) return;
    handleBarcodeDetected(manualBarcode.value.trim());
    manualBarcode.value = '';
};

// Demo Barcodes Quick Selector for testing on desktop / emulators without a physical camera barcode
const demoBarcodes = [
    { name: 'Fresh Milk', code: '6161101234567' },
    { name: 'Paracetamol', code: '6164009876543' },
    { name: 'Maize Flour', code: '6161109988776' },
    { name: 'Sugar 1kg', code: '6161104455667' },
    { name: 'Cooking Oil', code: '6161107788990' },
];

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            lastScanned.value = null;
            scanCount.value = 0;
            isScanning.value = false;
            startCamera();
        } else {
            stopCamera();
        }
    },
);

onUnmounted(() => {
    stopCamera();
});
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="w-[95vw] sm:max-w-md p-0 overflow-hidden bg-zinc-950 text-zinc-100 border-zinc-800 shadow-2xl flex flex-col max-h-[95vh]">
            <!-- Header -->
            <DialogHeader class="p-3.5 border-b border-zinc-800/80 flex flex-row items-center justify-between bg-zinc-900/90 shrink-0">
                <div class="flex items-center gap-2">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-emerald-600 font-bold text-white shadow-sm">
                        <ScanBarcode class="size-4 animate-pulse" />
                    </div>
                    <div>
                        <DialogTitle class="text-xs sm:text-sm font-bold text-white leading-tight flex items-center gap-1.5">
                            Camera Barcode Scanner
                            <Badge v-if="scanCount > 0" variant="outline" class="text-[9px] px-1 py-0 font-mono bg-emerald-500/10 text-emerald-400 border-emerald-500/30">
                                {{ scanCount }} scanned
                            </Badge>
                        </DialogTitle>
                        <p class="text-[10px] text-zinc-400">Point phone camera at item barcode</p>
                    </div>
                </div>

                <!-- Controls: Audio, Continuous, Close -->
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        :class="['p-1.5 rounded-lg border transition-colors', soundEnabled ? 'bg-zinc-800 text-emerald-400 border-zinc-700' : 'bg-zinc-900 text-zinc-500 border-zinc-800']"
                        :title="soundEnabled ? 'Mute Beep' : 'Enable Beep'"
                        @click="soundEnabled = !soundEnabled"
                    >
                        <Volume2 v-if="soundEnabled" class="size-3.5" />
                        <VolumeX v-else class="size-3.5" />
                    </button>

                    <button
                        v-if="torchSupported"
                        type="button"
                        :class="['p-1.5 rounded-lg border transition-colors', torchOn ? 'bg-amber-500 text-zinc-950 border-amber-400' : 'bg-zinc-800 text-zinc-400 border-zinc-700']"
                        title="Toggle Flashlight"
                        @click="toggleTorch"
                    >
                        <Zap class="size-3.5" />
                    </button>

                    <button
                        type="button"
                        class="p-1.5 rounded-lg bg-zinc-800 text-zinc-400 hover:text-white border border-zinc-700"
                        title="Switch Camera"
                        @click="toggleCamera"
                    >
                        <FlipHorizontal class="size-3.5" />
                    </button>

                    <button
                        type="button"
                        class="p-1.5 rounded-lg bg-zinc-800 text-zinc-400 hover:text-rose-400 border border-zinc-700 ml-1"
                        @click="emit('update:open', false)"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </DialogHeader>

            <!-- Camera Viewfinder Viewport -->
            <div class="relative bg-black aspect-[4/3] sm:aspect-[16/10] w-full flex items-center justify-center overflow-hidden shrink-0">
                <!-- Video Element -->
                <video
                    ref="videoRef"
                    autoplay
                    playsinline
                    muted
                    class="h-full w-full object-cover"
                ></video>

                <!-- Scanning HUD Overlay -->
                <div v-if="cameraActive" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                    <!-- Darkened edges mask -->
                    <div class="absolute inset-0 bg-black/40"></div>

                    <!-- Clear Scan Box -->
                    <div class="relative z-10 w-64 h-36 sm:w-72 sm:h-40 rounded-xl border-2 border-emerald-500/80 bg-transparent shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]">
                        <!-- Corner Crosshairs -->
                        <div class="absolute -top-1 -left-1 size-4 border-t-2 border-l-2 border-emerald-400"></div>
                        <div class="absolute -top-1 -right-1 size-4 border-t-2 border-r-2 border-emerald-400"></div>
                        <div class="absolute -bottom-1 -left-1 size-4 border-b-2 border-l-2 border-emerald-400"></div>
                        <div class="absolute -bottom-1 -right-1 size-4 border-b-2 border-r-2 border-emerald-400"></div>

                        <!-- Animated Laser Scanner Line -->
                        <div class="absolute left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-emerald-400 to-transparent shadow-[0_0_12px_#10b981] animate-laser-scan"></div>
                    </div>

                    <!-- Scanner Status / Flash feedback -->
                    <div
                        v-if="isScanning"
                        class="absolute z-20 inset-0 bg-emerald-500/30 backdrop-blur-[1px] flex items-center justify-center transition-all"
                    >
                        <div class="bg-emerald-600 text-white px-3 py-1.5 rounded-full font-bold text-xs flex items-center gap-1.5 shadow-xl animate-bounce">
                            <Check class="size-4" />
                            <span>Barcode Added: {{ lastScanned }}</span>
                        </div>
                    </div>
                </div>

                <!-- Camera Error / Fallback Banner -->
                <div v-if="cameraError" class="absolute inset-0 z-20 bg-zinc-950/90 p-4 flex flex-col items-center justify-center text-center space-y-2">
                    <AlertCircle class="size-8 text-amber-400" />
                    <p class="text-xs font-bold text-zinc-200">{{ cameraError }}</p>
                    <Button size="sm" variant="outline" class="h-8 text-xs gap-1.5 mt-2 bg-zinc-900 border-zinc-700" @click="startCamera">
                        <RefreshCw class="size-3.5" />
                        Retry Camera
                    </Button>
                </div>
            </div>

            <!-- Bottom Controller & Manual Fallback Input -->
            <div class="p-3 space-y-2.5 bg-zinc-900 border-t border-zinc-800 text-xs overflow-y-auto flex-1">
                <!-- Mode & Status Selector -->
                <div class="flex items-center justify-between bg-zinc-950 p-2 rounded-lg border border-zinc-800">
                    <div class="flex items-center gap-1.5 text-[11px] text-zinc-300">
                        <span :class="['size-2 rounded-full', cameraActive ? 'bg-emerald-500 animate-ping' : 'bg-amber-500']"></span>
                        <span>{{ cameraActive ? 'Ready to Scan' : 'Camera Standby' }}</span>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer text-[11px] text-zinc-300">
                        <input
                            v-model="continuousMode"
                            type="checkbox"
                            class="size-3.5 rounded border-zinc-700 bg-zinc-800 text-emerald-500 focus:ring-0"
                        />
                        <span>Continuous Mode</span>
                    </label>
                </div>

                <!-- Manual Barcode Type-in -->
                <div class="flex items-center gap-2">
                    <Input
                        v-model="manualBarcode"
                        placeholder="Or enter barcode manually..."
                        class="h-8 text-xs bg-zinc-950 border-zinc-700 text-zinc-100 font-mono"
                        @keydown.enter.prevent="handleManualSubmit"
                    />
                    <Button size="sm" class="h-8 px-3 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-bold" @click="handleManualSubmit">
                        Add
                    </Button>
                </div>

                <!-- Quick Test Barcodes (Handy for devices without test products) -->
                <div>
                    <div class="text-[10px] text-zinc-500 font-semibold uppercase tracking-wider mb-1 flex items-center gap-1">
                        <Sparkles class="size-3 text-emerald-500" />
                        Quick Test Barcodes
                    </div>
                    <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-none pb-1">
                        <button
                            v-for="b in demoBarcodes"
                            :key="b.code"
                            type="button"
                            class="px-2 py-1 bg-zinc-950 hover:bg-zinc-800 border border-zinc-800 rounded-md text-[10px] font-mono text-zinc-300 whitespace-nowrap active:scale-95 transition-all"
                            @click="handleBarcodeDetected(b.code)"
                        >
                            {{ b.name }}
                        </button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@keyframes laserScan {
    0% {
        top: 8%;
    }
    50% {
        top: 90%;
    }
    100% {
        top: 8%;
    }
}

.animate-laser-scan {
    animation: laserScan 1.8s ease-in-out infinite;
}
</style>
