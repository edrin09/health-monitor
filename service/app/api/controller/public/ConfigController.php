<?php

namespace app\api\controller\public;

use app\common\controller\ApiController;

class ConfigController extends ApiController
{
    protected $noNeedLogin = ['getAgreement'];

    public function initialize()
    {
        parent::initialize();
    }

    public function getAgreement()
    {
        $data = [
            'user_agreement' => sysconfig('site', 'user_agreement') ?: '',
            'privacy_policy' => sysconfig('site', 'privacy_policy') ?: '',
        ];
        return $this->render_success($data);
    }
}