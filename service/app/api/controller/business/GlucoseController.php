<?php

namespace app\api\controller\business;

use app\api\service\business\GlucoseService;

class GlucoseController extends ApiMallComm
{
    protected $noNeedLogin = ['statusTags'];
    protected $noCheckCustomer = ['statusTags'];

    public function initialize()
    {
        parent::initialize();
    }

    public function list()
    {
        $service = new GlucoseService($this->param, $this->user_info);
        return $this->render_success($service->getList());
    }

    public function add()
    {
        $rule['value'] = 'require|float';
        $this->validate($this->param, $rule);

        $service = new GlucoseService($this->param, $this->user_info);
        return $this->render_success($service->add());
    }

    public function delete()
    {
        $rule['id'] = 'require|number';
        $this->validate($this->param, $rule);

        $service = new GlucoseService($this->param, $this->user_info);
        return $this->render_success($service->delete());
    }

    public function statusTags()
    {
        return $this->render_success(\app\common\model\health\HealthGlucose::$measureStatusMap);
    }
}
