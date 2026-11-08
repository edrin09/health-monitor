<?php

namespace app\common\service;

use think\Container;

/**
 * 自定义服务基类
 * Class Service
 * @package app\common\service
 */
abstract class AbstractService
{
    /**
     * Service constructor.
     */
    public function __construct()
    {
        $this->initialize();
    }

    /**
     * 初始化服务
     * @return $this
     */
    protected function initialize()
    {
        return $this;
    }

    /**
     * 静态实例对象
     * @param array $args
     * @return static
     */
    public static function instance(...$args)
    {
        return Container::getInstance()->make(static::class, $args);
    }
}