<?php

return [
    /*
    | Optional shared secret for the management API (create/list links, analytics).
    | Leave empty for an open local demo. When set, requests must send
    | "Authorization: Bearer <token>". Redirects (/{slug}) and QR images stay public.
    */
    'api_token' => env('API_TOKEN'),
];
