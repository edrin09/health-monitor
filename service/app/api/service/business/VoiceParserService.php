<?php

namespace app\api\service\business;

use app\api\service\CommService;

/**
 * 语音解析服务
 * 将自然语言文本解析为结构化健康记录，并提交入库
 *
 * 支持的指令示例:
 *   "血糖5.6空腹" -> glucose: 5.6, measure_status=0
 *   "血压12080" -> blood_pressure: 120/80
 *   "早上体重65公斤" -> weight: 65
 *   "腰围80厘米" -> waist: 80
 *   "早餐后血糖7.2" -> glucose: 7.2, measure_status=1
 */
class VoiceParserService extends CommService
{
    public function __construct($param, $user_info)
    {
        parent::_initialize($param, $user_info);
    }

    /**
     * 解析文本为健康记录数组
     * 入参: text(string 原始中文文本)
     * 出参: records(array 解析后的记录), original(string 原始文本)
     */
    public function parse($text)
    {
        // 解析前归一化：中文数字转阿拉伯、全角标点转半角
        $text = $this->normalizeText($text);

        $records = [];
        $sentences = $this->splitSentences($text);

        foreach ($sentences as $sentence) {
            $record = $this->parseSentence(trim($sentence));
            if ($record) {
                $records[] = $record;
            }
        }

        $this->data['records'] = $records;
        $this->data['original'] = $text;
        return $this->data;
    }

    /**
     * 文本归一化（解析前预处理）
     * 1. 中文数字转阿拉伯数字（八点→8点, 二十→20, 十一→11）
     * 2. 全角小数点转半角（5．2 → 5.2）
     */
    private function normalizeText($text)
    {
        // 中文数字转阿拉伯
        $text = preg_replace_callback('/(零|一|二|两|三|四|五|六|七|八|九|十)+/u', function ($m) {
            return $this->cnToNum($m[0]);
        }, $text);

        // 全角小数点 → 半角（语音识别常返回 5．2）
        $text = str_replace('．', '.', $text);
        // 全角逗号 → 半角
        $text = str_replace('，', ',', $text);

        return $text;
    }

    /**
     * 中文数字转阿拉伯（支持 0~99）
     * 例: 八→8, 十→10, 十一→11, 二十→20, 二十一→21
     */
    private function cnToNum($str)
    {
        $map = ['零' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9];
        $total = 0;
        $section = 0;

        $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $c) {
            if ($c === '十') {
                if ($section === 0) {
                    $section = 1;
                }
                $total += $section * 10;
                $section = 0;
            } elseif (isset($map[$c])) {
                $section = $map[$c];
            }
        }
        $total += $section;

        return (string) $total;
    }


    /**
     * 提交解析后的记录到对应数据表
     * 入参: records(array 解析记录数组)
     * 出参: saved(array 已保存的记录)
     */
    public function submit($records)
    {
        $customer_id = $this->user_info->customer_id;
        $saved = [];

        foreach ($records as $record) {
            $type = $record['type'] ?? '';
            $value = $record['value'] ?? '';
            $measure_time = $record['measure_time'] ?? time();

            switch ($type) {
                case 'glucose':
                    $model = new \app\common\model\health\HealthGlucose();
                    $model->customer_id = $customer_id;
                    $model->value = $value;
                    $model->measure_status = $record['measure_status'] ?? 0;
                    $model->measure_time = $measure_time;
                    $model->save();
                    $saved[] = ['type' => 'glucose', 'id' => $model->id, 'value' => $value];
                    break;

                case 'blood_pressure':
                    $parts = explode('/', $value);
                    $model = new \app\common\model\health\HealthBloodPressure();
                    $model->customer_id = $customer_id;
                    $model->systolic = (int)$parts[0];
                    $model->diastolic = isset($parts[1]) ? (int)$parts[1] : 0;
                    $model->measure_time = $measure_time;
                    $model->save();
                    $saved[] = ['type' => 'blood_pressure', 'id' => $model->id, 'value' => $value];
                    break;

                case 'weight':
                    $model = new \app\common\model\health\HealthWeight();
                    $model->customer_id = $customer_id;
                    $model->weight = $value;
                    $model->measure_time = $measure_time;
                    $model->save();
                    $saved[] = ['type' => 'weight', 'id' => $model->id, 'value' => $value];
                    break;

                case 'waist':
                    $model = new \app\common\model\health\HealthWaist();
                    $model->customer_id = $customer_id;
                    $model->value = $value;
                    $model->measure_time = $measure_time;
                    $model->save();
                    $saved[] = ['type' => 'waist', 'id' => $model->id, 'value' => $value];
                    break;
            }
        }

        $this->data['saved'] = $saved;
        return $this->data;
    }

    /**
     * 按标点符号切分句子
     */
    private function splitSentences($text)
    {
        $delimiters = ['，', '。', '；', ',', '.', ';', '！', '!', '？', '?', "\n"];
        $pattern = '/[' . preg_quote(implode('', $delimiters), '/') . ']/u';
        $parts = preg_split($pattern, $text);
        return array_filter($parts, function ($v) {
            return trim($v) !== '';
        });
    }

    /**
     * 解析单句，先提取时间再提取测量值
     */
    private function parseSentence($text)
    {
        $measure_time = $this->extractTime($text);
        return $this->extractMeasurement($text, $measure_time);
    }

    /**
     * 从文本中提取时间信息
     * 支持: "早上X点Y分" "下午X点" "晚上X点" "X点Y分"
     * 返回: Unix时间戳（基于当天日期+提取的时间）
     */
    private function extractTime($text)
    {
        $now = strtotime(date('Y-m-d'));
        $hour = null;
        $minute = 0;

        $patterns = [
            '/凌晨\s*(\d{1,2})\s*点\s*(\d{1,2})?\s*分?/u',
            '/早上\s*(\d{1,2})\s*点\s*(\d{1,2})?\s*分?/u',
            '/早晨\s*(\d{1,2})\s*点\s*(\d{1,2})?\s*分?/u',
            '/上午\s*(\d{1,2})\s*点\s*(\d{1,2})?\s*分?/u',
            '/中午\s*(\d{1,2})\s*点\s*(\d{1,2})?\s*分?/u',
            '/下午\s*(\d{1,2})\s*点\s*(\d{1,2})?\s*分?/u',
            '/晚上\s*(\d{1,2})\s*点\s*(\d{1,2})?\s*分?/u',
            '/夜里\s*(\d{1,2})\s*点\s*(\d{1,2})?\s*分?/u',
            '/半夜\s*(\d{1,2})\s*点\s*(\d{1,2})?\s*分?/u',
            '/(\d{1,2})\s*点\s*(\d{1,2})?\s*分?/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $hour = (int)$m[1];
                $minute = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 0;

                // 下午/晚上/夜里/半夜 => 加12小时
                if (preg_match('/^(下午|晚上|夜里|半夜)/u', $m[0])) {
                    if ($hour < 12) $hour += 12;
                } elseif (preg_match('/^(凌晨)/u', $m[0])) {
                    if ($hour >= 12) $hour -= 12;
                }
                break;
            }
        }

        if ($hour !== null) {
            return $now + $hour * 3600 + $minute * 60;
        }

        return time();
    }

    /**
     * 提取测量类型和数值
     * 支持类型: 血糖(含状态), 血压(高压/低压), 体重(含单位), 腰围(含单位)
     */
    private function extractMeasurement($text, $defaultTime)
    {
        $typeMap = [
            '血糖' => 'glucose',
            '血压' => 'blood_pressure',
            '体重' => 'weight',
            '腰围' => 'waist',
        ];

        $statusMap = [
            '空腹' => 0,
            '早餐后' => 1,
            '午餐后' => 2,
            '晚餐后' => 3,
            '睡前' => 4,
        ];

        foreach ($typeMap as $typeCn => $type) {
            if (mb_strpos($text, $typeCn) === false) {
                continue;
            }

            $measure_status = 0;
            if ($type === 'glucose') {
                foreach ($statusMap as $statusCn => $status) {
                    if (mb_strpos($text, $statusCn) !== false) {
                        $measure_status = $status;
                        break;
                    }
                }
            }

            // 只在类型关键词之后的文本中提取数值，避免误抓时间里的数字（如"8点"的8）
            $pos = mb_strpos($text, $typeCn);
            $valueText = mb_substr($text, $pos + mb_strlen($typeCn));
            $value = $this->extractValue($valueText, $type);

            if ($value !== null) {
                return [
                    'type' => $type,
                    'type_cn' => $typeCn,
                    'value' => $value,
                    'measure_time' => $defaultTime,
                    'measure_status' => $measure_status,
                    'time_text' => date('H:i', $defaultTime),
                ];
            }
        }

        return null;
    }

    /**
     * 根据类型提取数值，支持不同的匹配模式
     */
    private function extractValue($text, $type)
    {
        switch ($type) {
            case 'blood_pressure':
                if (preg_match('/(\d{2,3})\s*[\/\/\s]\s*(\d{2,3})/u', $text, $m)) {
                    return $m[1] . '/' . $m[2];
                }
                if (preg_match('/高压\s*(\d{2,3})[\s,，]*低压\s*(\d{2,3})/u', $text, $m)) {
                    return $m[1] . '/' . $m[2];
                }
                if (preg_match('/收缩压\s*(\d{2,3})[\s,，]*舒张压\s*(\d{2,3})/u', $text, $m)) {
                    return $m[1] . '/' . $m[2];
                }
                // 兜底：无分隔符的连续数字（如 "12080" -> "120/80"）
                if (preg_match('/(\d{2,3})(\d{2,3})/u', $text, $m)) {
                    return $m[1] . '/' . $m[2];
                }
                return null;

            case 'weight':
                if (preg_match('/(\d+\.?\d*)\s*(公斤|千克|kg|KG)/u', $text, $m)) {
                    return $m[1];
                }
                if (preg_match('/体重\s*[是为:：]?\s*(\d+\.?\d*)/u', $text, $m)) {
                    return $m[1];
                }
                if (preg_match('/(\d+\.?\d*)\s*$/u', $text, $m)) {
                    return $m[1];
                }
                return null;

            case 'glucose':
                if (preg_match('/(\d+\.?\d*)\s*(mmol|mmol\/L)?/u', $text, $m)) {
                    return $m[1];
                }
                if (preg_match('/血糖\s*[是为:：]?\s*(\d+\.?\d*)/u', $text, $m)) {
                    return $m[1];
                }
                return null;

            case 'waist':
                if (preg_match('/(\d+\.?\d*)\s*(厘米|cm|公分)/u', $text, $m)) {
                    return $m[1];
                }
                if (preg_match('/腰围\s*[是为:：]?\s*(\d+\.?\d*)/u', $text, $m)) {
                    return $m[1];
                }
                return null;
        }

        return null;
    }
}
