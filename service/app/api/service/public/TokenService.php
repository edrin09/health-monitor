<?php

namespace app\api\service\public;

use support\Cache;

class TokenService
{
    private static $expiry_time = 86400;

    /**
     * 生成token
     * @param array $userInfo 用户信息
     * @return string token
     */
    public static function generateToken($userInfo)
    {
        $token = md5(uniqid() . time() . rand(1000, 9999));
        Cache::set($token, $userInfo, self::$expiry_time);
        return $token;
    }

    /**
     * 验证token
     * @param string $token token
     * @return mixed 用户信息或false
     */
    public static function verifyToken($token)
    {
        if (empty($token)) {
            return false;
        }

        $userInfo = Cache::get($token);
        if (empty($userInfo)) {
            return false;
        }

        // 自动续期
        Cache::set($token, $userInfo, self::$expiry_time);
        return $userInfo;
    }

    /**
     * 清除token
     * @param string $token token
     * @return bool
     */
    public static function clearToken($token)
    {
        if (empty($token)) {
            return false;
        }

        return Cache::delete($token);
    }
}