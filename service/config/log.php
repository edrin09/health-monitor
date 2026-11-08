<?php

return [
    'default' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/app.log',
                    30,
                    Monolog\Logger::DEBUG,
                    true,
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [
                        "[%datetime%] %channel%.%level_name%: %message% %context%\n",
                        'Y-m-d H:i:s',
                        true,
                    ],
                ],
            ]
        ],
    ],

    // ⭐ API 专用通道（含 SQL）
    'api' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/api.log',
                    30,
                    Monolog\Logger::DEBUG, // ⚠️ 必须 DEBUG
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [
                        "%datetime% %message% %context%\n",
                        'Y-m-d H:i:s',
                        true,
                    ],
                ],
            ]
        ],
    ],

    'frontend' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/frontend.log',
                    30,
                    Monolog\Logger::DEBUG, // ⚠️ 必须 DEBUG
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [
                        "%datetime% %message% %context%\n",
                        'Y-m-d H:i:s',
                        true,
                    ],
                ],
            ]
        ],
    ],

    // ⭐ 付款 专用通道
    'pay' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/pay.log',
                    30,
                    Monolog\Logger::DEBUG, // ⚠️ 必须 DEBUG
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [
                        "%datetime% %message% %context%\n",
                        'Y-m-d H:i:s',
                        true,
                    ],
                ],
            ]
        ],
    ],
];