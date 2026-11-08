<?php

namespace app\api\service\business;

use app\api\service\CommService;
use app\common\model\health\HealthWaist;
use support\exception\BusinessException;

class WaistService extends CommService
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

        $query = HealthWaist::where('customer_id', $customer_id);
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
        $model = new HealthWaist();
        $model->customer_id = $customer_id;
        $model->value = $this->param['value'] ?? 0;
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
        $record = HealthWaist::where('id', $id)->where('customer_id', $customer_id)->find();
        if (!$record) {
            throw new BusinessException('记录不存在', 401);
        }
        $record->delete();
        return $this->data;
    }
}
