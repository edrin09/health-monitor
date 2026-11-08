<?php

namespace app\common\model\business;

use app\common\model\BaseModel;

/**
 * 用户扩展信息模型
 * 表名: mall_customer_expansion
 * 主键: customer_id(与mall_customer一对一)
 * 字段: customer_id, token, openid, unionid, recommend_id(推荐人), login_time, category
 */
class MallCustomerExpansion extends BaseModel
{
    public $pk = 'customer_id';
    public $name = 'mall_customer_expansion';

    protected $defaultValues = [
    ];
}
