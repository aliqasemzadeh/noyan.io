<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Whitelisted Artisan commands
    |--------------------------------------------------------------------------
    |
    | Only these exact command names can be executed from the system
    | functions panel. Arguments beyond the command name are not accepted.
    |
    */

    'commands' => [
        'cache:clear' => [
            'label' => 'general.cmd_cache_clear',
            'icon' => 'eraser',
            'color' => 'amber',
        ],
        'route:clear' => [
            'label' => 'general.cmd_route_clear',
            'icon' => 'route',
            'color' => 'sky',
        ],
        'view:clear' => [
            'label' => 'general.cmd_view_clear',
            'icon' => 'eye-off',
            'color' => 'violet',
        ],
        'config:clear' => [
            'label' => 'general.cmd_config_clear',
            'icon' => 'settings',
            'color' => 'zinc',
        ],
        'event:clear' => [
            'label' => 'general.cmd_event_clear',
            'icon' => 'bell-off',
            'color' => 'rose',
        ],
        'optimize:clear' => [
            'label' => 'general.cmd_optimize_clear',
            'icon' => 'circle-x',
            'color' => 'red',
        ],
        'optimize' => [
            'label' => 'general.cmd_optimize',
            'icon' => 'zap',
            'color' => 'lime',
        ],
        'config:cache' => [
            'label' => 'general.cmd_config_cache',
            'icon' => 'database',
            'color' => 'cyan',
        ],
        'route:cache' => [
            'label' => 'general.cmd_route_cache',
            'icon' => 'git-branch',
            'color' => 'blue',
        ],
        'view:cache' => [
            'label' => 'general.cmd_view_cache',
            'icon' => 'layers',
            'color' => 'indigo',
        ],
        'queue:restart' => [
            'label' => 'general.cmd_queue_restart',
            'icon' => 'refresh-cw',
            'color' => 'orange',
        ],
        'storage:link' => [
            'label' => 'general.cmd_storage_link',
            'icon' => 'link',
            'color' => 'teal',
        ],
    ],

];
