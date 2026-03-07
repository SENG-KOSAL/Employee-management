<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant domain enforcement
    |--------------------------------------------------------------------------
    |
    | When true, non-super-admin users must sign in from a host that maps to
    | an active company slug. Set to false for platforms like Railway/ngrok
    | where tenant subdomains are not available yet.
    |
    */
    'strict_tenant_domain' => env('TENANCY_STRICT_TENANT_DOMAIN', true),

    /*
    |--------------------------------------------------------------------------
    | Super admin host inference
    |--------------------------------------------------------------------------
    |
    | When false (recommended), super-admin users will not automatically get
    | tenant context from the current host. They must explicitly provide
    | X-Active-Company / active_company_id to enter tenant context.
    |
    */
    'allow_super_admin_host_inference' => env('TENANCY_ALLOW_SUPER_ADMIN_HOST_INFERENCE', false),

    /*
    |--------------------------------------------------------------------------
    | Temporary super-admin testing override
    |--------------------------------------------------------------------------
    |
    | Optional: allow forcing "platform-only" (no active company) context
    | using query parameter on external URLs, e.g. ?sa=1.
    |
    */
    'allow_super_admin_query_override' => env('TENANCY_ALLOW_SUPER_ADMIN_QUERY_OVERRIDE', false),
    'super_admin_query_key' => env('TENANCY_SUPER_ADMIN_QUERY_KEY', 'sa'),
];
