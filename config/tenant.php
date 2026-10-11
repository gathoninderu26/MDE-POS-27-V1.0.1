<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Operation Mode
    |--------------------------------------------------------------------------
    | 'multi'  : Multi-tenant cloud/subdomain mode (e.g. nairobi.pos.com).
    | 'single' : Dedicated single-tenant PC mode (locked to ONE merchant on XAMPP/localhost).
    */
    'mode' => env('TENANT_MODE', 'multi'),

    /*
    |--------------------------------------------------------------------------
    | Locked Tenant Identifier (Active when TENANT_MODE is 'single')
    |--------------------------------------------------------------------------
    | When locked, the local instance ignores subdomains and strictly binds
    | to this merchant account across all local routes, POS terminals, and queries.
    */
    'locked_id' => env('LOCKED_TENANT_ID', null),
    'locked_slug' => env('LOCKED_TENANT_SLUG', null),
    'locked_branch_id' => env('LOCKED_BRANCH_ID', null),
    'license_key' => env('TENANT_LICENSE_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | Cloud Sync Configuration (Supabase)
    |--------------------------------------------------------------------------
    | Pushes local transactions to Supabase Cloud and pulls catalog updates.
    */
    'sync' => [
        'enabled' => env('CLOUD_SYNC_ENABLED', false),
        'supabase_url' => env('SUPABASE_URL', null),
        'supabase_key' => env('SUPABASE_KEY', null),
        'batch_size' => (int) env('CLOUD_SYNC_BATCH_SIZE', 50),
        'retry_limit' => (int) env('CLOUD_SYNC_RETRY_LIMIT', 5),
    ],
];
