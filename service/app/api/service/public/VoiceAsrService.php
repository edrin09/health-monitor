<?php

namespace app\api\service\public;

use support\exception\BusinessException;
use support\Log;

/**
 * 语音识别服务
 * 将音频文件转写为文字（基于百度语音识别 REST API）
 *
 * 配置方式（在数据库 ed_system_config 表 baidu_asr 分组中配置）：
 *   - app_id    : 百度 AI 应用的 AppID
 *   - api_key   : API Key
 *   - secret_key: Secret Key
 *
 * 百度语音识别免费额度：5万次/天
 * 文档：https://cloud.baidu.com/doc/SPEECH/s/Fk38lxgh2
 */
class VoiceAsrService
{
    /**
     * 百度 ASR REST API 地址
     */
    private const ASR_API_URL = 'https://vop.baidu.com/server_api';
    private const TOKEN_URL = 'https://aip.baidubce.com/oauth/2.0/token';
    /**
     * 支持的音频格式映射（扩展名 -> 百度格式代码）
     * 百度 ASR 支持: pcm, wav, amr, opus, m4a, mp3, silk
     */
    private const FORMAT_MAP = [
        'pcm' => 'pcm',
        'wav' => 'wav',
        'mp3' => 'mp3',
        'm4a' => 'm4a',
        'aac'   => 'aac',
        'amr' => 'amr',
        'silk' => 'silk',
        'opus' => 'opus',
    ];
    /**
     * 百度 ASR 凭证
     */
    private string $appId;
    private string $apiKey;
    private string $secretKey;

    public function __construct()
    {
        $config = sysconfig('baidu_asr');
        $this->appId = trim($config['app_id'] ?? '');
        $this->apiKey = trim($config['api_key'] ?? '');
        $this->secretKey = trim($config['secret_key'] ?? '');

        if (empty($this->appId) || empty($this->apiKey) || empty($this->secretKey)) {
            throw new BusinessException('语音识别服务未配置，请在系统设置中配置百度 ASR 凭证');
        }
    }

    /**
     * 识别音频文件，返回文字
     *
     * @param string $audioFilePath 音频文件绝对路径
     * @param string|null $format 指定格式（如 mp3, aac），若为空则自动检测
     * @return string 识别后的文本
     * @throws BusinessException
     */
    public function recognize(string $audioFilePath, ?string $format = null): string
    {
        if (!file_exists($audioFilePath)) {
            throw new BusinessException('音频文件不存在: ' . $audioFilePath);
        }

        $audioData = file_get_contents($audioFilePath);
        if ($audioData === false || strlen($audioData) === 0) {
            throw new BusinessException('音频文件读取失败或为空');
        }

        // 1. 获取 access_token
        $accessToken = $this->getAccessToken();

// 2. 使用指定格式或检测音频格式
        if ($format) {
            $format = $this->normalizeFormat($format);
        } else {
            $format = $this->detectFormat($audioFilePath);
        }

        // 3. 调用 ASR API
        return $this->callAsrApi($audioData, $format, $accessToken);
    }

    /**
     * 标准化格式（统一大小写及映射）
     */
    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));
        return self::FORMAT_MAP[$format] ?? $format;
    }

    /**
     * 根据文件扩展名检测音频格式
     */
    private function getAccessToken(): string
    {
        $url = self::TOKEN_URL . '?grant_type=client_credentials&client_id=' . $this->apiKey . '&client_secret=' . $this->secretKey;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new BusinessException('连接百度 API 失败: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new BusinessException('百度 API 返回异常 (HTTP ' . $httpCode . ')');
        }

        $result = json_decode($response, true);
        if (empty($result['access_token'])) {
            $errorDesc = $result['error_description'] ?? '未知错误';
            throw new BusinessException('获取百度 access_token 失败: ' . $errorDesc);
        }

        return $result['access_token'];
    }

    /**
     * 根据文件扩展名检测音频格式
     */
    private function detectFormat(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return self::FORMAT_MAP[$ext] ?? 'pcm';
    }

    /**
     * 调用百度 ASR API
     * 统一使用 JSON API（base64 编码），兼容所有音频格式
     */
    private function callAsrApi(string $audioData, string $format, string $accessToken): string
    {
        $cuid = 'health-monitor-' . ($_SERVER['SERVER_NAME'] ?? 'local');

        $body = json_encode([
            'format' => $format,
            'rate' => 16000,
            'dev_pid' => 1537,
            'speech' => base64_encode($audioData),
            'len' => strlen($audioData),
            'cuid' => $cuid,
            'channel' => 1,
            'token' => $accessToken,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::ASR_API_URL,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        Log::info('百度ASR请求详情', [
            'format' => $format,
            'http_code' => $httpCode,
            'error' => $error,
            'audio_len' => strlen($audioData),
            'base64_len' => strlen(base64_encode($audioData)),
            'response_raw' => mb_substr($response, 0, 500),
        ]);

        if ($error) {
            throw new BusinessException('语音识别请求失败: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new BusinessException('语音识别服务异常 (HTTP ' . $httpCode . ')');
        }

        $result = json_decode($response, true);
        if (isset($result['err_no']) && $result['err_no'] === 0) {
            $text = $result['result'][0] ?? '';
            Log::info('百度ASR识别成功', [
                'text' => mb_substr($text, 0, 100),
                'audio_len' => strlen($audioData),
            ]);
            return trim($text);
        }

        $errMsg = $result['err_msg'] ?? '未知错误';
        $errNo = $result['err_no'] ?? -1;

        Log::error('百度ASR识别失败', [
            'err_no' => $errNo,
            'err_msg' => $errMsg,
            'audio_len' => strlen($audioData),
        ]);

        // 常见错误码友好提示
        $friendlyMessages = [
            3300 => '音频质量不佳，请重新录音',
            3301 => '音频时长过短或过长（建议 1~60 秒）',
            3302 => '语音识别服务暂不可用',
            3303 => '音频采样率不匹配',
            3304 => '音频格式不支持',
            3307 => '语音识别超时，请重试',
            3308 => '音频过长（建议 60 秒以内）',
            3309 => '音频数据损坏，请重新录制',
            3310 => '音频为空',
            3311 => '音频采样率过低',
            3312 => '音频比特率过低',
        ];

        $displayMsg = $friendlyMessages[$errNo] ?? ('语音识别失败(' . $errNo . '): ' . $errMsg);
        throw new BusinessException($displayMsg);
    }
}