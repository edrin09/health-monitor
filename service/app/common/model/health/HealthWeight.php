<?php

namespace app\common\model\health;

use app\common\model\BaseModel;

class HealthWeight extends BaseModel
{
    protected $table = 'ed_health_weight';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
}
