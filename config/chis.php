<?php

return [

    /*
    | The single shared bearer token Telemed authenticates with. When empty,
    | every authenticated endpoint fails closed with a 500 rather than letting
    | requests through unauthenticated.
    */
    'api_token' => env('CHIS_API_TOKEN'),

    'rate_limit_per_minute' => (int) env('CHIS_RATE_LIMIT_PER_MINUTE', 60),

];
