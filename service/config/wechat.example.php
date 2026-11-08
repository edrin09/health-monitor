<?php

// 微信配置模板：复制为 wechat.php 后填入自己的值（wechat.php 不入库）
// cp service/config/wechat.example.php service/config/wechat.php

return [
    // 小程序配置
    'app_id' => 'wx0000000000000000',            // 小程序 AppID
    'app_secret' => 'YOUR_APP_SECRET',           // 小程序 AppSecret

    // 微信支付配置
    'mch_id' => 'YOUR_MCH_ID',                   // 微信支付商户号

    // V2 API 配置
    'pay_key' => 'YOUR_V2_PAY_KEY',              // V2 微信支付 API 密钥
    'cert_path' => __DIR__ . '/cert/wechatpay/v2/apiclient_cert.pem',
    'key_path'  => __DIR__ . '/cert/wechatpay/v2/apiclient_key.pem',

    // V3 API 配置
    'v3_pay_key' => 'YOUR_V3_PAY_KEY',
    'serial_no'  => 'YOUR_CERT_SERIAL_NO',        // 商户 API 证书序列号
    'v3_cert_path' => __DIR__ . '/cert/wechatpay/v3/apiclient_cert.pem',
    'v3_key_path'  => __DIR__ . '/cert/wechatpay/v3/apiclient_key.pem',

    // 转账回调
    'transfer_notify_url' => env('API_URL') . '/api/payment/wechat/receiveTransferNotify',
];
