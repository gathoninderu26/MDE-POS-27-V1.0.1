import { useTenant } from '@/composables/useTenant';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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

    // ── Server-authoritative expiry ──────────────────────────────────────────
    // Only trust an expiry date that the server explicitly provided.
    // localStorage is used purely for the countdown display, never to determine
    // whether the account is actually expired (stale values caused false positives).
    const serverExpiresAtMs = computed(() => {
        if (!isDemo.value) return null;

        const serverExpires =
            (page.props as any)?.demo?.expires_at || (page.props as any)?.tenant?.expires_at || (currentTenant.value as any)?.expiresAt;

        if (serverExpires) {
            const parsed = new Date(serverExpires).getTime();
            if (!isNaN(parsed)) return parsed;
        }
        return null; // No authoritative expiry from the server
    });

    // Expiry date used for the countdown display (server first, then localStorage for UX)
    const expiresAtMs = computed(() => {
        if (!isDemo.value) return 0;
        if (serverExpiresAtMs.value !== null) return serverExpiresAtMs.value;

        // localStorage fallback — only for display, never drives isExpired
        if (typeof window !== 'undefined') {
            const tenantSlug = currentTenant.value?.slug || 'default';
            const key = `mde_account_expiry_${tenantSlug}`;
            const legacyKey = `mde_demo_expiry_${tenantSlug}`;
            const stored = localStorage.getItem(key) || localStorage.getItem(legacyKey);
            if (stored) {
                const parsed = Number(stored);
                // Only use the localStorage value if it is in the future; ignore stale values
                if (!isNaN(parsed) && parsed > Date.now()) return parsed;
            }
            // Write a fresh 7-day window if nothing valid is stored
            const defaultExpiry = Date.now() + 7 * 24 * 60 * 60 * 1000;
            localStorage.setItem(key, String(defaultExpiry));
            return defaultExpiry;
        }

        return Date.now() + 7 * 24 * 60 * 60 * 1000;
    });

    const diffMs = computed(() => {
        if (!isDemo.value) return 0;
        return Math.max(0, expiresAtMs.value - now.value);
    });

    const totalSecondsLeft = computed(() => Math.floor(diffMs.value / 1000));
    const daysLeft = computed(() => Math.floor(totalSecondsLeft.value / 86400));
    const hoursLeft = computed(() => Math.floor((totalSecondsLeft.value % 86400) / 3600));
    const minutesLeft = computed(() => Math.floor((totalSecondsLeft.value % 3600) / 60));
    const secondsLeft = computed(() => totalSecondsLeft.value % 60);

    // isExpired is ONLY true when this is a demo AND the server gave us an explicit expiry in the past.
    const isExpired = computed(() => isDemo.value && serverExpiresAtMs.value !== null && serverExpiresAtMs.value <= now.value);
    const isUrgent = computed(() => isDemo.value && diffMs.value < 24 * 60 * 60 * 1000 && !isExpired.value); // Less than 24 hours

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

    const formattedExpiryDate = computed(() => {
        return new Date(expiresAtMs.value).toLocaleDateString('en-KE', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    });

    return {
        isDemo,
        isExpired,
        isUrgent,
        expiresAtMs,
        formattedExpiryDate,
        daysLeft,
        hoursLeft,
        minutesLeft,
        secondsLeft,
        countdownText,
        shortCountdown,
        detailedCountdown,
    };
}
