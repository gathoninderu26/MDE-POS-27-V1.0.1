<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BarcodeLookupService
{
    /**
     * Cache duration for positive lookups (90 days).
     */
    protected const CACHE_TTL_DAYS = 90;

    /**
     * Look up product details by barcode across multi-tier sources.
     *
     * @param string $barcode
     * @return array{found: bool, name?: string, brand?: string, category?: string, description?: string, imageUrl?: string, source?: string}
     */
    public function lookup(string $barcode): array
    {
        $barcode = trim($barcode);
        if (strlen($barcode) < 4) {
            return ['found' => false];
        }

        // Tier 1: Check Cache / Master Record
        $cacheKey = "barcode:{$barcode}";
        $cached = Cache::get($cacheKey);
        if ($cached && !empty($cached['name'])) {
            return array_merge($cached, ['found' => true, 'source' => $cached['source'] ?? 'cached']);
        }

        // Tier 2: Check Local & Cross-Tenant Database
        $dbMatch = $this->searchDatabase($barcode);
        if ($dbMatch['found']) {
            $this->remember($barcode, $dbMatch);
            return $dbMatch;
        }

        // Tier 3: Server-side Open Food Facts
        $offMatch = $this->searchOpenFoodFacts($barcode);
        if ($offMatch['found']) {
            $this->remember($barcode, $offMatch);
            return $offMatch;
        }

        // Tier 4: Server-side UPC Item DB
        $upcMatch = $this->searchUpcItemDb($barcode);
        if ($upcMatch['found']) {
            $this->remember($barcode, $upcMatch);
            return $upcMatch;
        }

        // Tier 5: Web Search / E-commerce Fallback (Google / Serper / Search APIs)
        $webMatch = $this->searchWebFallback($barcode);
        if ($webMatch['found']) {
            $this->remember($barcode, $webMatch);
            return $webMatch;
        }

        return ['found' => false];
    }

    /**
     * Store a verified product lookup in the master cache.
     */
    public function remember(string $barcode, array $data): void
    {
        $barcode = trim($barcode);
        if (empty($barcode) || empty($data['name'])) {
            return;
        }

        $payload = [
            'found' => true,
            'name' => mb_substr(trim($data['name']), 0, 255),
            'brand' => isset($data['brand']) ? mb_substr(trim($data['brand']), 0, 100) : null,
            'category' => isset($data['category']) ? mb_substr(trim($data['category']), 0, 100) : 'General',
            'description' => isset($data['description']) ? mb_substr(trim($data['description']), 0, 500) : null,
            'imageUrl' => $data['imageUrl'] ?? null,
            'source' => $data['source'] ?? 'pos_catalog',
        ];

        Cache::put("barcode:{$barcode}", $payload, now()->addDays(self::CACHE_TTL_DAYS));
    }

    /**
     * Search existing products in the database.
     */
    protected function searchDatabase(string $barcode): array
    {
        try {
            $existing = Product::where('barcode', $barcode)
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->latest()
                ->first();

            if ($existing) {
                return [
                    'found' => true,
                    'source' => 'pos_catalog',
                    'name' => $existing->name,
                    'category' => $existing->category ?? 'General',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("Barcode DB search error: {$e->getMessage()}");
        }

        return ['found' => false];
    }

    /**
     * Query Open Food Facts with proper User-Agent.
     */
    protected function searchOpenFoodFacts(string $barcode): array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'MDE-POS/1.0 (inventory-catalog-lookup)',
                ])
                ->get("https://world.openfoodfacts.org/api/v0/product/{$barcode}.json");

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? 0) === 1 && !empty($data['product'])) {
                    $p = $data['product'];
                    $name = $p['product_name'] ?? $p['product_name_en'] ?? $p['generic_name'] ?? null;
                    if ($name) {
                        return [
                            'found' => true,
                            'source' => 'openfoodfacts',
                            'name' => trim($name),
                            'brand' => isset($p['brands']) ? trim($p['brands']) : null,
                            'category' => $this->cleanCategory($p['categories'] ?? null),
                            'description' => $p['generic_name'] ?? null,
                            'imageUrl' => $p['image_front_url'] ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info("OpenFoodFacts search exception for {$barcode}: {$e->getMessage()}");
        }

        return ['found' => false];
    }

    /**
     * Query UPC Item DB.
     */
    protected function searchUpcItemDb(string $barcode): array
    {
        try {
            $response = Http::timeout(5)->get("https://api.upcitemdb.com/prod/trial/lookup", [
                'upc' => $barcode,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['code'] ?? '') === 'OK' && !empty($data['items'][0])) {
                    $item = $data['items'][0];
                    if (!empty($item['title'])) {
                        return [
                            'found' => true,
                            'source' => 'upcitemdb',
                            'name' => trim($item['title']),
                            'brand' => isset($item['brand']) ? trim($item['brand']) : null,
                            'category' => $this->cleanCategory($item['category'] ?? null),
                            'description' => $item['description'] ?? null,
                            'imageUrl' => $item['images'][0] ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info("UPC Item DB search exception for {$barcode}: {$e->getMessage()}");
        }

        return ['found' => false];
    }

    /**
     * Web search fallback (Google Custom Search, Serper, or e-commerce title search).
     */
    protected function searchWebFallback(string $barcode): array
    {
        // 1. Google Custom Search API (if configured in .env)
        $googleKey = config('services.google.search_api_key', env('GOOGLE_SEARCH_API_KEY'));
        $googleCx = config('services.google.search_engine_id', env('GOOGLE_SEARCH_ENGINE_ID'));

        if ($googleKey && $googleCx) {
            $googleResult = $this->queryGoogleSearch($barcode, $googleKey, $googleCx);
            if ($googleResult['found']) {
                return $googleResult;
            }
        }

        // 2. Serper API (if configured in .env)
        $serperKey = config('services.serper.api_key', env('SERPER_API_KEY'));
        if ($serperKey) {
            $serperResult = $this->querySerper($barcode, $serperKey);
            if ($serperResult['found']) {
                return $serperResult;
            }
        }

        // 3. EAN-Search.org API (if configured in .env)
        $eanKey = config('services.ean_search.api_key', env('EAN_SEARCH_API_KEY'));
        if ($eanKey) {
            $eanResult = $this->queryEanSearch($barcode, $eanKey);
            if ($eanResult['found']) {
                return $eanResult;
            }
        }

        return ['found' => false];
    }

    /**
     * Query Google Custom Search JSON API.
     */
    protected function queryGoogleSearch(string $barcode, string $key, string $cx): array
    {
        try {
            $res = Http::timeout(6)->get('https://www.googleapis.com/customsearch/v1', [
                'key' => $key,
                'cx' => $cx,
                'q' => "\"{$barcode}\"",
                'num' => 3,
            ]);

            if ($res->successful()) {
                $items = $res->json('items');
                if (!empty($items) && is_array($items)) {
                    foreach ($items as $item) {
                        $rawTitle = $item['title'] ?? '';
                        $cleanTitle = $this->cleanProductTitle($rawTitle);
                        if ($cleanTitle) {
                            return [
                                'found' => true,
                                'source' => 'web_search',
                                'name' => $cleanTitle,
                                'description' => $item['snippet'] ?? null,
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Google Search error for {$barcode}: {$e->getMessage()}");
        }

        return ['found' => false];
    }

    /**
     * Query Serper API.
     */
    protected function querySerper(string $barcode, string $key): array
    {
        try {
            $res = Http::timeout(6)
                ->withHeaders([
                    'X-API-KEY' => $key,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://google.serper.dev/search', [
                    'q' => "\"{$barcode}\"",
                    'num' => 3,
                ]);

            if ($res->successful()) {
                $organic = $res->json('organic');
                if (!empty($organic) && is_array($organic)) {
                    foreach ($organic as $item) {
                        $rawTitle = $item['title'] ?? '';
                        $cleanTitle = $this->cleanProductTitle($rawTitle);
                        if ($cleanTitle) {
                            return [
                                'found' => true,
                                'source' => 'web_search',
                                'name' => $cleanTitle,
                                'description' => $item['snippet'] ?? null,
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Serper search error for {$barcode}: {$e->getMessage()}");
        }

        return ['found' => false];
    }

    /**
     * Query official EAN-Search.org API.
     */
    protected function queryEanSearch(string $barcode, string $token): array
    {
        try {
            $res = Http::timeout(5)->get('https://api.ean-search.org/api', [
                'token' => $token,
                'op' => 'barcode-lookup',
                'ean' => $barcode,
                'format' => 'json',
            ]);

            if ($res->successful()) {
                $data = $res->json();
                if (!empty($data[0]['name'])) {
                    return [
                        'found' => true,
                        'source' => 'ean_search',
                        'name' => trim($data[0]['name']),
                        'category' => isset($data[0]['categoryName']) ? trim($data[0]['categoryName']) : 'General',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("EAN-Search error for {$barcode}: {$e->getMessage()}");
        }

        return ['found' => false];
    }

    /**
     * Clean messy page titles from web search into crisp product titles.
     * E.g., "Buy Brookside Whole Milk 500ml Online in Kenya | Jumia" -> "Brookside Whole Milk 500ml"
     */
    protected function cleanProductTitle(string $rawTitle): ?string
    {
        $title = trim($rawTitle);

        // Remove store suffixes and common separators (| Amazon, - Jumia, etc.)
        $title = preg_replace('/(\s*[-|–:]\s*(Jumia|Amazon|Carrefour|Walmart|eBay|Target|Naivas|Quickmart|KiliMall|Alibaba|AliExpress).*)$/i', '', $title);
        $title = preg_replace('/(\s*[-|–]\s*Buy Online.*)$/i', '', $title);
        $title = preg_replace('/(\s*[-|–]\s*Best Price.*)$/i', '', $title);

        // Remove leading promo text (e.g., "Buy ...", "Order ...")
        $title = preg_replace('/^(Buy|Order|Shop)\s+/i', '', $title);

        $title = trim($title);

        // If title is reasonably concise (under 120 chars and over 3 chars)
        if (mb_strlen($title) >= 3) {
            return mb_substr($title, 0, 150);
        }

        return null;
    }

    /**
     * Clean category string into a human readable segment.
     */
    protected function cleanCategory(?string $raw): string
    {
        if (empty($raw)) {
            return 'General';
        }

        $parts = preg_split('/[,>]/', $raw);
        $last = trim(end($parts));
        $last = preg_replace('/^en:/i', '', $last);
        $last = str_replace('-', ' ', $last);

        return ucwords(mb_substr($last, 0, 50)) ?: 'General';
    }
}
