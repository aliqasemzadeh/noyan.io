<?php

return [
    'short_url' => env('SHORT_LINK_BASE_URL', env('APP_URL')),
    'code_length' => (int) env('SHORT_LINK_CODE_LENGTH', 5),
];
