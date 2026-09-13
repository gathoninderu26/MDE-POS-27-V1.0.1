import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useTenant } from '@/composables/useTenant';

export function useDemoCountdown() {
    const page = usePage();
    const { isDemo, currentTenant } = useTenant();

    const now = ref(Date.now());
    let intervalId: any = null;

    onMounted(() => {
        intervalId = setInterval(() => {
            now.value = Date.now();
        }, 1000);
    });

    onUnmounted(() => {
        if (intervalId) clearInterval(intervalId);
    });

    // Expiry date from Inertia session props or localStorage fallback
    const expiresAtMs = computed(() => {
        const serverExpires = (page.props as any)?.demo?.expires_at;
        if (serverExpires) {
            const parsed = new Date(serverExpires).getTime();
            if (!isNaN(parsed)) return parsed;
        }

        // Fallback to localStorage for this specific demo tenant
        if (typeof window !== 'undefined') {
            const key = `mde_demo_expiry_${currentTenant.value.slug}`;
            const stored = localStorage.getItem(key);
            if (stored) {
                const parsed = Number(stored);
                if (!isNaN(parsed) && parsed > 0) return parsed;
            }
            // default 7 days from now
            const defaultExpiry = Date.now() + 7 * 24 * 60 * 60 * 1000;
            localStorage.setItem(key, String(defaultExpiry));
            return defaultExpiry;
        }

        return Date.now() + 7 * 24 * 60 * 60 * 1000;
    });

    const diffMs = computed(() => Math.max(0, expiresAtMs.value - now.value));

    const totalSecondsLeft = computed(() => Math.floor(diffMs.value / 1000));
    const daysLeft = computed(() => Math.floor(totalSecondsLeft.value / 86400));
    const hoursLeft = computed(() => Math.floor((totalSecondsLeft.value % 86400) / 3600));
    const minutesLeft = computed(() => Math.floor((totalSecondsLeft.value % 3600) / 60));
    const secondsLeft = computed(() => totalSecondsLeft.value % 60);

    const isExpired = computed(() => diffMs.value <= 0);
    const isUrgent = computed(() => diffMs.value < 24 * 60 * 60 * 1000 && !isExpired.value); // Less than 24 hours

    const countdownText = computed(() => {
        if (isExpired.value) return 'Demo Expired';
        if (daysLeft.value > 0) {
            return `${daysLeft.value}d ${hoursLeft.value}h left`;
        }
        if (hoursLeft.value > 0) {
            return `${hoursLeft.value}h ${minutesLeft.value}m left`;
        }
        return `${minutesLeft.value}m ${secondsLeft.value}s left`;
    });

    // Compact version for tight spaces like POS header (e.g. "2d 4h" / "3h 22m" / "12m 5s")
    const shortCountdown = computed(() => {
        if (isExpired.value) return 'Expired';
        if (daysLeft.value > 0) return `${daysLeft.value}d ${hoursLeft.value}h`;
        if (hoursLeft.value > 0) return `${hoursLeft.value}h ${minutesLeft.value}m`;
        return `${minutesLeft.value}m ${secondsLeft.value}s`;
    });

    const detailedCountdown = computed(() => {
        if (isExpired.value) return 'Demo Access Expired';
        if (daysLeft.value > 0) {
            return `${daysLeft.value} Day${daysLeft.value > 1 ? 's' : ''} ${hoursLeft.value} Hour${hoursLeft.value !== 1 ? 's' : ''} Left`;
        }
        if (hoursLeft.value > 0) {
            return `${hoursLeft.value} Hour${hoursLeft.value > 1 ? 's' : ''} ${minutesLeft.value} Mins Left`;
        }
        return `${minutesLeft.value} Mins ${secondsLeft.value} Secs Left`;
    });

    return {
        isDemo,
        isExpired,
        isUrgent,
        daysLeft,
        hoursLeft,
        minutesLeft,
        secondsLeft,
        countdownText,
        shortCountdown,
        detailedCountdown,
    };
}
