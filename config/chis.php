<?php

return [

    /*
    | The single shared bearer token Telemed authenticates with. When empty,
    | every authenticated endpoint fails closed with a 500 rather than letting
    | requests through unauthenticated.
    */
    'api_token' => env('CHIS_API_TOKEN'),

    'rate_limit_per_minute' => (int) env('CHIS_RATE_LIMIT_PER_MINUTE', 60),

    /*
    | The read-only CHIS viewer and the patient-record form at /viewer, for
    | presenting the integration. Off unless enabled. They show medical data,
    | so outside APP_ENV=local they also require CHIS_VIEWER_PASSWORD (HTTP
    | Basic auth); without one they stay hidden (404).
    */
    'viewer_enabled' => (bool) env('CHIS_VIEWER_ENABLED', false),

    'viewer_password' => env('CHIS_VIEWER_PASSWORD'),

];
