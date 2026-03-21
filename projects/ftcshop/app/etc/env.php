<?php
return [
    'cache_types' => [
        'compiled_config' => 1,
        'config' => 1,
        'layout' => 1,
        'block_html' => 1,
        'collections' => 1,
        'reflection' => 1,
        'db_ddl' => 1,
        'eav' => 1,
        'customer_notification' => 1,
        'config_integration' => 1,
        'config_integration_api' => 1,
        'google_product' => 1,
        'full_page' => 1,
        'config_webservice' => 1,
        'translate' => 1,
        'amasty_shopby' => 1,
        'vertex' => 1,
        'wp_ga4_categories' => 1
    ],
    'directories' => [
        'document_root_is_pub' => true
    ],
    'backend' => [
        'frontName' => 'admin_1vyp59'
    ],
    'crypt' => [
        'key' => 'fa1cc423a92df87c00189898f2f9517f'
    ],
    'db' => [
        'table_prefix' => '',
        'connection' => [
            'default' => [
                'host' => 'localhost',
                'dbname' => 'ftcshop',
                'username' => 'ftcshop',
                'password' => '?IK$Asohbizfa897',
                'model' => 'mysql4',
                'engine' => 'innodb',
                'initStatements' => 'SET NAMES utf8;',
                'active' => '1',
                'driver_options' => [
                    1014 => false
                ]
            ]
        ]
    ],
    'resource' => [
        'default_setup' => [
            'connection' => 'default'
        ]
    ],
    'x-frame-options' => 'SAMEORIGIN',
    'MAGE_MODE' => 'developer',
    'session' => [
        'save' => 'files'
    ],
    'cache' => [
        'frontend' => [
            'default' => [
                'id_prefix' => '586_'
            ],
            'page_cache' => [
                'id_prefix' => '586_'
            ]
        ],
        'graphql' => [
            'id_salt' => 'yiEEwOjiPPofkyzKzWI9U1fYHOZp9YRT'
        ],
        'allow_parallel_generation' => false
    ],
    'lock' => [
        'provider' => 'db'
    ],
    'queue' => [
        'consumers_wait_for_messages' => 0
    ],
    'install' => [
        'date' => 'Wed, 22 May 2024 14:31:45 +0000'
    ],
    'remote_storage' => [
        'driver' => 'file'
    ],
    'system' => [
        'default' => [
            'web' => [
                'unsecure' => [
                    'base_url' => 'https://ftcshop.staging.devqon.ch/',
                    'base_link_url' => '{{unsecure_base_url}}'
                ],
                'secure' => [
                    'base_url' => 'https://ftcshop.staging.devqon.ch/',
                    'base_link_url' => '{{secure_base_url}}'
                ]
            ],
            'admin' => [
                'url' => [
                    'use_custom' => 0,
                    'custom' => ''
                ]
            ]
        ]
    ]
];
