<?php

namespace app\common\service;

class DataParserService
{
    /**
     * 自动解析请求数据（支持 JSON 和 XML）
     */
    public static function parseRequest($request): array
    {
        // 1. 先通过 Content-Type 判断
        $contentType = $request->header('Content-Type', '');

        if (strpos($contentType, 'application/json') !== false) {
            return $request->post() ?: [];
        }

        if (strpos($contentType, 'xml') !== false) {
            $rawBody = $request->rawBody();
            return self::xmlToArray($rawBody);
        }

        // 2. Content-Type 不明确，通过内容判断
        $rawBody = $request->rawBody();

        if (empty($rawBody)) {
            return $request->post() ?: [];
        }

        return self::parseByContent($rawBody);
    }

    /**
     * XML 转数组
     */
    public static function xmlToArray(string $xml): array
    {
        libxml_use_internal_errors(true);
        $xmlObj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();

        if ($xmlObj === false) {
            return [];
        }

        return json_decode(json_encode($xmlObj), true) ?: [];
    }

    /**
     * 根据内容自动判断并解析
     */
    public static function parseByContent(string $content): array
    {
        $trimmed = trim($content);

        // 空内容
        if (empty($trimmed)) {
            return [];
        }

        // 判断 XML
        if (self::isXml($trimmed)) {
            return self::xmlToArray($trimmed);
        }

        // 判断 JSON
        if (self::isJson($trimmed)) {
            return json_decode($trimmed, true) ?: [];
        }

        // 尝试解析表单数据
        parse_str($trimmed, $result);
        if (!empty($result)) {
            return $result;
        }

        return [];
    }

    /**
     * 判断是否为 XML
     */
    public static function isXml(string $string): bool
    {
        $trimmed = trim($string);

        // 快速检查：以 < 开头且包含 >
        if (strpos($trimmed, '<') !== 0 || strpos($trimmed, '>') === false) {
            return false;
        }

        // 尝试解析
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($trimmed, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();

        return $xml !== false;
    }

    /**
     * 判断是否为 JSON
     */
    public static function isJson(string $string): bool
    {
        $trimmed = trim($string);

        // 快速检查：以 { 或 [ 开头
        $firstChar = $trimmed[0] ?? '';
        if ($firstChar !== '{' && $firstChar !== '[') {
            return false;
        }

        json_decode($trimmed);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 数组转 XML
     */
    public static function arrayToXml(array $data, string $root = 'xml'): string
    {
        $xml = "<{$root}>";
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $xml .= self::arrayToXml($value, $key);
            } else {
                $xml .= "<{$key}><![CDATA[{$value}]]></{$key}>";
            }
        }
        $xml .= "</{$root}>";
        return $xml;
    }

    /**
     * 返回 XML
     */
    public static function xmlResponse(string $returnCode, string $returnMsg)
    {
        $xml = "<xml>\n";
        $xml .= "<return_code><![CDATA[{$returnCode}]]></return_code>\n";
        $xml .= "<return_msg><![CDATA[{$returnMsg}]]></return_msg>\n";
        $xml .= "</xml>";

        return $xml;
    }
}