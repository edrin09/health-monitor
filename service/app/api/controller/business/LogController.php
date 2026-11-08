<?php

namespace app\api\controller\business;

use support\Log;

/**
 * 日志上报接口
 * 接收客户端批量上报的前端日志
 */
class LogController extends ApiMallComm
{
    protected $noNeedLogin = ['report'];
    protected $noCheckCustomer = ['report'];

    public function initialize()
    {
        parent::initialize();
        // 不调用父类 initialize，跳过用户认证
    }

    /**
     * 接收客户端批量上报的日志
     * POST /business/log/report
     */
    public function report()
    {
        $logs = $this->param['logs'] ?? [];

        if (empty($logs) || !is_array($logs)) {
            return $this->render_success(['received' => 0]);
        }

        $received = 0;
        foreach ($logs as $log) {
            $level = $log['level'] ?? 'log';
            $message = $log['message'] ?? '';
            $data = $log['data'] ?? '';
            $timestamp = $log['timestamp'] ?? time();

            $logLine = sprintf(
                '[%s] [%s] %s %s',
                date('Y-m-d H:i:s', (int)($timestamp / 1000)),
                strtoupper($level),
                $message,
                $data ? '| ' . $data : ''
            );
            Log::channel('frontend')->info($logLine);

            $received++;
        }

        return $this->render_success(['received' => $received]);
    }
}