<?php

namespace app\admin\controller\health;

use app\admin\controller\comm\CommController;
use app\admin\traits\HealthCurd;

class HealthComm extends CommController
{
    use HealthCurd;

    protected $business;
    protected $business_type;
    protected $auth_header;

    protected $with_join = [];
    protected $join_type = 'INNER';

    protected $formFields = [];

    public function initialize()
    {
        parent::initialize();
        if (!isset($this->model)) {
            throw new \Exception('Model must be initialized in child class');
        }

        $this->sort = input('sort', 'desc');
        $this->business_type = input('business_type', '');
        $this->pk = $this->model->getPk();
        $this->orderby = $this->pk;
        $this->auth_header = 'health/' . $this->business;

        $data['pk'] = $this->pk;
        $data['business_type'] = $this->business_type;
        $data['auth_header'] = $this->auth_header;
        $this->assign(compact('data'));

        $auth['auth_add'] = auths($this->auth_header . '/add');
        $auth['auth_edit'] = auths($this->auth_header . '/edit');
        $auth['auth_del'] = auths($this->auth_header . '/delete');
        $auth['auth_export'] = auths($this->auth_header . '/export');
        $auth['auth_item'] = true;

        $this->assign(compact('auth'));

        $this->initFormFields();
    }

    protected function initFormFields()
    {
        if ($this->business === 'profile') {
            $this->formFields = [
                ['name' => 'customer_id', 'label' => '客户ID', 'type' => 'text', 'input_type' => 'number', 'required' => true],
                ['name' => 'height', 'label' => '身高(cm)', 'type' => 'text', 'input_type' => 'number', 'required' => false],
                ['name' => 'gender', 'label' => '性别', 'type' => 'select', 'required' => false, 'options' => [0 => '未知', 1 => '男', 2 => '女']],
                ['name' => 'birth_date', 'label' => '出生日期', 'type' => 'text', 'input_type' => 'text', 'required' => false],
            ];
        }
    }
}
