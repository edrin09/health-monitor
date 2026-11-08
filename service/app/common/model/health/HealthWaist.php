<?php

namespace app\common\model\health;

use app\common\model\BaseModel;

class HealthWaist extends BaseModel
{
    protected $table = 'ed_health_waist';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
}
