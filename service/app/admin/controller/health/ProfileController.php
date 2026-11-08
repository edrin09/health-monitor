<?php

namespace app\admin\controller\health;

use app\common\model\health\CustomerProfile;

class ProfileController extends HealthComm
{
    public function initialize()
    {
        $this->model = new CustomerProfile();
        $this->business = 'profile';
        parent::initialize();
    }
}
