<?php

namespace app\common\model\health;

use app\common\model\BaseModel;

class CustomerProfile extends BaseModel
{
    protected $table = 'ed_customer_profile';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
}
