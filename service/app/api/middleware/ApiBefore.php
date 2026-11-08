<?php

namespace app\api\middleware;

use app\common\service\SaveLogsService;
use support\Response;
use Webman\Http\Request;

class ApiBefore
{
    public function process(Request $request, callable $next)
    {
        // 处理 OPTIONS 预检请求
        if ($request->method() === 'OPTIONS') {
            $response = new Response(200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Max-Age' => '86400'
            ]);
            return $response;
        }

        // 生成 trace_id
        $traceId = bin2hex(random_bytes(5));
        $request->trace_id = $traceId;

        // 微信支付回调路由不记录详细响应日志
        $requestRoute = $request->path();
        if (strpos($requestRoute, 'payment/notify') === false) {
            $SaveLogsService = new SaveLogsService();
            // 设置日志表名
            $SaveLogsService->setLogTableName('ed_api_logs_');
            // 2. 获取请求内容（自动支持 JSON 和 XML）
            $SaveLogsService->saveRequestLog($traceId);
        }

        return $next($request);
    }
}
