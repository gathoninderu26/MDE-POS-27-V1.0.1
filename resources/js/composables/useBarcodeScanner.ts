import { onMounted, onUnmounted, ref } from 'vue';

export interface BarcodeScannerOptions {
    onScan: (barcode: string) => void;
    minChars?: number;
    maxInterval?: number; // Max milliseconds between keystrokes for hardware scanner (default 45ms)
    soundEnabled?: boolean;
    ignoreSelector?: string; // CSS selector for elements where scanner interception should be ignored
}

export function useBarcodeScanner(options: BarcodeScannerOptions) {
    const minChars = options.minChars ?? 3;
    const maxInterval = options.maxInterval ?? 45;
    const soundEnabled = ref(options.soundEnabled ?? true);
    
    const isScanning = ref(false);
    const lastScannedBarcode = ref<string>('');
    const scanCount = ref(0);
    const scannerConnected = ref(true); // Indicator that HID listening is live

    let buffer = '';
    let lastKeyTime = 0;
    let isFastTypingStream = false;

    // Web Audio API Beep Synthesizer (Instant, offline, zero asset dependencies)
    const playScanBeep = (type: 'success' | 'error' = 'success') => {
        if (!soundEnabled.value) return;
        try {
            const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
            if (!AudioContextClass) return;

            const audioCtx = new AudioContextClass();
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();

            if (type === 'success') {
                // Crisp, high-pitch POS confirmation chirp (1800Hz -> 0 in 110ms)
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1800, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.18, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.11);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.11);
            } else {
                // Low double alert tone for not-found / error
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(320, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.22);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.22);
            }
        } catch {
            // Audio context silently handled if browser restricts until interaction
        }
    };

    const triggerHaptic = () => {
        if (typeof navigator !== 'undefined' && navigator.vibrate) {
            try {
                navigator.vibrate([60]);
            } catch {}
        }
    };

    const handleKeyDown = (event: KeyboardEvent) => {
        const target = event.target as HTMLElement | null;

        // Check if user is typing in a modal text area or non-search input where they want literal typing
        if (target) {
            const isTextarea = target.tagName === 'TEXTAREA';
            const isSelect = target.tagName === 'SELECT';
            const isModalInput = target.closest('[role="dialog"]') && !target.classList.contains('allow-scanner');

            // If inside a modal form field, don't hijack unless it's explicitly allowed
            if (isTextarea || isSelect || isModalInput) {
                return;
            }
        }

        const now = performance.now();
        const interval = now - lastKeyTime;
        lastKeyTime = now;

        // Scanners transmit keys with very short delays (< 45ms per character)
        if (interval > maxInterval && buffer.length > 0) {
            // Delay was too long for a single continuous barcode scan; reset buffer
            buffer = '';
            isFastTypingStream = false;
        }

        // Detect termination characters (Enter or Tab)
        if (event.key === 'Enter' || event.key === 'Tab') {
            if (buffer.length >= minChars) {
                event.preventDefault();
                event.stopPropagation();

                const scannedCode = buffer.trim();
                buffer = '';
                isFastTypingStream = false;

                if (scannedCode) {
                    lastScannedBarcode.value = scannedCode;
                    scanCount.value++;
                    isScanning.value = true;
                    playScanBeep('success');
                    triggerHaptic();

                    options.onScan(scannedCode);

                    setTimeout(() => {
                        isScanning.value = false;
                    }, 300);
                }
                return;
            }
            // Reset buffer on Enter if too short
            buffer = '';
            isFastTypingStream = false;
            return;
        }

        // Only buffer printable single characters
        if (event.key.length === 1 && !event.ctrlKey && !event.altKey && !event.metaKey) {
            if (buffer.length === 0) {
                buffer = event.key;
            } else {
                if (interval <= maxInterval) {
                    isFastTypingStream = true;
                }
                buffer += event.key;
            }
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeyDown, true);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeyDown, true);
    });

    return {
        scannerConnected,
        isScanning,
        lastScannedBarcode,
        scanCount,
        soundEnabled,
        playScanBeep,
        triggerHaptic,
    };
}
