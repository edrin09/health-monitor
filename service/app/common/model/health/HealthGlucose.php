<?php

namespace app\common\model\health;

use app\common\model\BaseModel;

class HealthGlucose extends BaseModel
{
    protected $table = 'ed_health_glucose';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    public static $measureStatusMap = [
        0 => '空腹',
        1 => '早餐前',
        2 => '早餐后两小时',
        3 => '午餐前',
        4 => '午餐后两小时',
        5 => '晚餐前',
        6 => '晚餐后两小时',
        7 => '睡前',
        8 => '随机',
    ];
}
