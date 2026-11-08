<?php

namespace app\api\controller\business;

use app\api\service\business\WaistService;

class WaistController extends ApiMallComm
{
    protected $noNeedLogin = [];
    protected $noCheckCustomer = [];

    public function initialize()
    {
        parent::initialize();
    }

    public function list()
    {
        $service = new WaistService($this->param, $this->user_info);
        return $this->render_success($service->getList());
    }

    public function add()
    {
        $rule['value'] = 'require|float';
        $this->validate($this->param, $rule);

        $service = new WaistService($this->param, $this->user_info);
        return $this->render_success($service->add());
    }

    public function delete()
    {
        $rule['id'] = 'require|number';
        $this->validate($this->param, $rule);

        $service = new WaistService($this->param, $this->user_info);
        return $this->render_success($service->delete());
    }
}
