<?php

namespace app\common\model\health;

use app\common\model\BaseModel;

class HealthBloodPressure extends BaseModel
{
    protected $table = 'ed_health_blood_pressure';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
}
