<?php

namespace app\api\controller\business;

use app\api\service\business\VoiceParserService;
use app\api\service\public\VoiceAsrService;
use support\exception\BusinessException;
use support\Log;

/**
 * 语音录入控制器
 * 路由前缀: /business/voice/
 * 需登录: 是
 */
class VoiceController extends ApiMallComm
{
    protected $noNeedLogin = [];
    protected $noCheckCustomer = [];

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * 提交语音解析后的记录到数据库
     * 路由: POST /business/voice/submit
     * 入参: records(array 必填 解析后的记录数组)
     * 出参: saved: [{ type, id, value }] 已保存的记录列表
     */
    public function submit()
    {
        $rule['records'] = 'require|array';
        $this->validate($this->param, $rule);

        $service = new VoiceParserService($this->param, $this->user_info);
        return $this->render_success($service->submit($this->param['records']));
    }

    /**
     * 语音识别：接收音频文件 → ASR 转文字 → NLP 解析为健康记录
     * 路由: POST /business/voice/recognize
     * 请求: multipart/form-data
     *   - audio: 音频文件 (支持 mp3/wav/pcm/aac/m4a/amr/silk)
     *   - token: 认证令牌（扁平字段，兼容 multipart 上传）
     * 出参:
     *   records: [{ type, type_cn, value, measure_time, measure_status, time_text }]
     *   original: ASR 识别的原始文本
     */
    public function recognize()
    {
        // 获取上传的音频文件
        $file = $this->request->file('audio');
        if (!$file || !$file->isValid()) {
            throw new BusinessException('请上传有效的音频文件');
        }

        // 检查文件大小（限制 10MB）
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new BusinessException('音频文件过大，请录制 60 秒以内的语音');
        }

        // 保存到临时目录
        $tmpDir = runtime_path() . '/voice_tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $ext = strtolower($file->getUploadExtension() ?: 'mp3');
        $tmpFile = $tmpDir . '/' . uniqid('asr_', true) . '.' . $ext;
        $file->move($tmpFile);

        // 从前端传来的 formData 获取格式
        $format = strtolower($this->request->post('format', 'mp3'));
        $allowedFormats = ['mp3', 'aac', 'm4a', 'wav', 'amr', 'pcm', 'silk'];
        if (!in_array($format, $allowedFormats)) {
            $format = 'mp3';
        }

        Log::info('语音上传文件', [
            'upload_name' => $file->getUploadName(),
            'ext' => $ext,
            'format' => $format,
            'tmpFile' => $tmpFile
        ]);

        // 微信录音是 silk 格式，百度 ASR 不支持，需要转换为 pcm
        $asrFile = $tmpFile;
        if ($ext === 'silk') {
            $pcmFile = $tmpDir . '/' . uniqid('asr_pcm_', true) . '.pcm';
            if ($this->convertSilkToPcm($tmpFile, $pcmFile)) {
                $asrFile = $pcmFile;
                Log::info('silk 转 pcm 成功', ['file' => $pcmFile]);
            } else {
                Log::warning('silk 转 pcm 失败，尝试直接使用 silk', ['file' => $tmpFile]);
            }
        }

        try {
            // 1. ASR 语音转文字
            $asrService = new VoiceAsrService();
            $text = $asrService->recognize($asrFile, $format);

            if (empty(trim($text))) {
                throw new BusinessException('未能识别出有效语音，请重试');
            }

            if (empty(trim($text))) {
                throw new BusinessException('未能识别出有效语音，请重试');
            }

            // 2. NLP 解析文本为健康记录
            $parser = new VoiceParserService($this->param, $this->user_info);
            $result = $parser->parse($text);

            return $this->render_success($result);
        } finally {
            // 清理临时文件
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            if ($asrFile !== $tmpFile && file_exists($asrFile)) {
                @unlink($asrFile);
            }
        }
    }

    /**
     * 将 silk 格式音频转换为 pcm（用于百度 ASR）
     * 使用 ffmpeg 或手动解码
     */
    private function convertSilkToPcm(string $silkFile, string $pcmFile): bool
    {
        // 方法1: 尝试使用 ffmpeg
        $ffmpegPath = env('FFMPEG_PATH', 'ffmpeg');
        $cmd = escapeshellcmd($ffmpegPath) . ' -i ' . escapeshellarg($silkFile) . ' -f s16le -ar 16000 -ac 1 ' . escapeshellarg($pcmFile) . ' -y 2>&1';
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($pcmFile) && filesize($pcmFile) > 0) {
            return true;
        }

        // 方法2: 微信 silk 格式实际上是 Opus 编码，尝试直接发送
        // 百度 ASR 新版支持 opus 格式
        Log::info('ffmpeg 转换失败，尝试使用 silk 原始格式');
        return false;
    }

    /**
     * 解析语音文本为健康记录
     * 路由: POST /business/voice/parse
     * 入参: text(string 必填 如"血糖5.6空腹 血压12080")
     * 出参:
     *   records: [{ type, type_cn, value, measure_time, measure_status, time_text }]
     *   original: 原始文本
     */
    public function parse()
    {
        $rule['text'] = 'require';
        $this->validate($this->param, $rule);

        $service = new VoiceParserService($this->param, $this->user_info);
        return $this->render_success($service->parse($this->param['text']));
    }
}
