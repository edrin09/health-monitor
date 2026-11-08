<?php

namespace app\api\service\business;

use app\api\service\CommService;
use app\common\model\health\HealthGlucose;
use support\exception\BusinessException;

class GlucoseService extends CommService
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

        $query = HealthGlucose::where('customer_id', $customer_id);
        $total = $query->count();
        $list = $query->order('measure_time', 'desc')
            ->page($page, $limit)
            ->select();

        $this->data['list'] = $list ? $list->toArray() : [];
        foreach ($this->data['list'] as &$item) {
            $item['measure_status_text'] = HealthGlucose::$measureStatusMap[$item['measure_status']] ?? '未知';
        }
        unset($item);
        $this->data['total'] = $total;
        $this->data['has_more'] = ($page * $limit) < $total;
        return $this->data;
    }

    public function add()
    {
        $customer_id = $this->user_info->customer_id;
        $model = new HealthGlucose();
        $model->customer_id = $customer_id;
        $model->value = $this->param['value'] ?? 0;
        $model->measure_status = $this->param['measure_status'] ?? 0;
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
        $record = HealthGlucose::where('id', $id)->where('customer_id', $customer_id)->find();
        if (!$record) {
            throw new BusinessException('记录不存在', 401);
        }
        $record->delete();
        return $this->data;
    }
}
