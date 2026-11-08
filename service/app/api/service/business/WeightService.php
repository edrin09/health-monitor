<?php

namespace app\api\service\business;

use app\api\service\CommService;
use app\common\model\health\CustomerProfile;
use app\common\model\health\HealthWeight;
use support\exception\BusinessException;

class WeightService extends CommService
{
    public function __construct($param, $user_info)
    {
        parent::_initialize($param, $user_info);
    }

    public function getList()
    {
        $customer_id = $this->user_info->customer_id;
        $page = max(1, (int)($this->param['page'] ?? 1));
        $limit = max(1, min(50, (int)($this->param['limit'] ?? 15)));

        $query = HealthWeight::where('customer_id', $customer_id);
        $total = $query->count();
        $list = $query->order('measure_time', 'desc')
            ->page($page, $limit)
            ->select();

        $this->data['list'] = $list ? $list->toArray() : [];
        $this->data['total'] = $total;
        $this->data['has_more'] = ($page * $limit) < $total;
        return $this->data;
    }

    public function add()
    {
        $customer_id = $this->user_info->customer_id;
        $weight = $this->param['weight'] ?? 0;

        $model = new HealthWeight();
        $model->customer_id = $customer_id;
        $model->weight = $weight;

        $profile = CustomerProfile::where('customer_id', $customer_id)->find();
        if ($profile && $profile->height > 0) {
            $heightM = $profile->height / 100;
            $model->bmi = round($weight / ($heightM * $heightM), 1);
        }

        $model->measure_time = $this->param['measure_time'] ?? time();
        $model->remark = $this->param['remark'] ?? '';
        $model->save();
        $this->data['id'] = $model->id;
        return $this->data;
    }

    public function delete()
    {
        $customer_id = $this->user_info->customer_id;
        $id = $this->param['id'] ?? 0;
        $record = HealthWeight::where('id', $id)->where('customer_id', $customer_id)->find();
        if (!$record) {
            throw new BusinessException('记录不存在', 401);
        }
        $record->delete();
        return $this->data;
    }
}
