import { ref } from 'vue';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────
export interface ProductLookupResult {
    found: boolean;
    /** Source that returned the data */
    source?: 'upcitemdb' | 'openfoodfacts' | 'pos_catalog' | 'web_search' | 'ean_search' | 'cached';
    name?: string;
    brand?: string;
    /** Single clean category string extracted from the raw API value */
    category?: string;
    description?: string;
    imageUrl?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Pull the most specific (last) segment from a raw category string.
 */
function extractCategory(raw?: string): string {
    if (!raw) return '';
    const clean = raw
        .split(/[,>]/)
        .map((s) =>
            s
                .trim()
                .replace(/^en:/i, '')
                .replace(/-/g, ' ')
                .replace(/\b\w/g, (c) => c.toUpperCase()),
        )
        .filter(Boolean);
    return clean[clean.length - 1] ?? '';
}

/** Clamp a string to a maximum length so it fits DB columns safely. */
function clamp(str: string | undefined, max = 255): string {
    if (!str) return '';
    return str.length > max ? str.slice(0, max) : str;
}

// ─────────────────────────────────────────────────────────────────────────────
// Stage 1 — UPC Item DB  (free trial: 100 req/day, no key required)
// ─────────────────────────────────────────────────────────────────────────────
async function tryUpcItemDb(barcode: string): Promise<ProductLookupResult> {
    try {
        const res = await fetch(`https://api.upcitemdb.com/prod/trial/lookup?upc=${encodeURIComponent(barcode)}`, {
            signal: AbortSignal.timeout(4000),
        });
        if (!res.ok) return { found: false };
        const data = await res.json();
        if (data.code !== 'OK' || !Array.isArray(data.items) || data.items.length === 0) {
            return { found: false };
        }
        const item = data.items[0] as Record<string, any>;
        const name = clamp(item.title as string | undefined);
        if (!name) return { found: false };
        return {
            found: true,
            source: 'upcitemdb',
            name,
            brand: clamp(item.brand as string | undefined, 100),
            category: clamp(extractCategory(item.category as string | undefined), 100),
            description: clamp(item.description as string | undefined),
            imageUrl: (item.images as string[] | undefined)?.[0],
        };
    } catch {
        return { found: false };
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Stage 2 — Open Food Facts  (unlimited, open data, great for FMCG / food)
// ─────────────────────────────────────────────────────────────────────────────
async function tryOpenFoodFacts(barcode: string): Promise<ProductLookupResult> {
    try {
        const res = await fetch(`https://world.openfoodfacts.org/api/v0/product/${encodeURIComponent(barcode)}.json`, {
            signal: AbortSignal.timeout(4000),
        });
        if (!res.ok) return { found: false };
        const data = await res.json();
        if (data.status !== 1 || !data.product) return { found: false };
        const p = data.product as Record<string, any>;
        const name = clamp(
            (p.product_name as string | undefined) || (p.product_name_en as string | undefined) || (p.generic_name as string | undefined),
        );
        if (!name) return { found: false };
        return {
            found: true,
            source: 'openfoodfacts',
            name,
            brand: clamp(p.brands as string | undefined, 100),
            category: clamp(extractCategory((p.categories as string | undefined) || (p.food_groups as string | undefined)), 100),
            description: clamp((p.generic_name as string | undefined) || (p.ingredients_text as string | undefined)),
            imageUrl: p.image_front_url as string | undefined,
        };
    } catch {
        return { found: false };
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Stage 3 — Backend Fallback (Master DB Cache + Web/Google Search)
// ─────────────────────────────────────────────────────────────────────────────
async function tryBackendLookup(barcode: string): Promise<ProductLookupResult> {
    try {
        const res = await fetch(`/products/barcode-lookup?barcode=${encodeURIComponent(barcode)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: AbortSignal.timeout(8000),
        });
        if (!res.ok) return { found: false };
        const data = await res.json();
        if (!data || !data.found || !data.name) {
            return { found: false };
        }
        return {
            found: true,
            source: data.source || 'pos_catalog',
            name: clamp(data.name),
            brand: clamp(data.brand, 100),
            category: clamp(data.category, 100),
            description: clamp(data.description),
            imageUrl: data.imageUrl,
        };
    } catch {
        return { found: false };
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Composable
// ─────────────────────────────────────────────────────────────────────────────
export function useBarcodeProductLookup() {
    const isLookingUp = ref(false);
    const lookupStatus = ref<'idle' | 'checking_public' | 'searching_extended'>('idle');

    /**
     * Look up a scanned barcode across multi-tier sources:
     * 1. Public client APIs (UPC Item DB + Open Food Facts) in parallel.
     * 2. If not found, fall back to backend multi-tier search (DB Catalog -> Server APIs -> Google/Web Search).
     */
    const lookupByBarcode = async (barcode: string): Promise<ProductLookupResult> => {
        if (!barcode || barcode.trim().length < 3) return { found: false };

        const cleanCode = barcode.trim();
        isLookingUp.value = true;
        lookupStatus.value = 'checking_public';

        try {
            // Stage 1 & 2 in parallel
            const [upc, off] = await Promise.all([tryUpcItemDb(cleanCode), tryOpenFoodFacts(cleanCode)]);

            if (upc.found) return upc;
            if (off.found) return off;

            // Stage 3: Backend Multi-Tier Search (Master DB + Server Registries + Web Search)
            lookupStatus.value = 'searching_extended';
            const backend = await tryBackendLookup(cleanCode);
            if (backend.found) return backend;

            return { found: false };
        } finally {
            isLookingUp.value = false;
            lookupStatus.value = 'idle';
        }
    };

    return { isLookingUp, lookupStatus, lookupByBarcode };
}
