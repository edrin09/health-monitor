<?php
return [
    'enable' => true,
    'exception' => [
        // 是否记录异常到日志
        'enable' => true,
        // 不会记录到日志的异常类
        'dontReport' => [
            support\exception\BusinessException::class
        ]
    ],
    'dontReport' => [
        'app' => [],
        'controller' => [],
        'action' => [],
        'path' => [
            'api/payment/notify',  // 跳过微信支付回调，避免读取请求体导致 XML 解析失败
            '/api/payment/notify'
        ]
    ],
    'channel' => 'default' // 日志通道(在config/log.php里配置,默认是default)
];
