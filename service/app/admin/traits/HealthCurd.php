<?php

namespace app\admin\traits;

use support\Request;
use support\Response;

trait HealthCurd
{
    public function index(Request $request): Response
    {
        if ($request->isAjax()) {
            if ($request->input('selectFields')) {
                return $this->selectList();
            }
            list($page, $limit, $where) = $this->buildTableParams();

            $count = $this->model
                ->withJoin($this->with_join, $this->join_type)
                ->where($where)
                ->count();

            $list = $this->model
                ->withJoin($this->with_join, $this->join_type)
                ->where($where)
                ->page($page, $limit)
                ->order($this->orderby, $this->sort)
                ->select()
                ->toArray();

            return json($this->formatSuccessRrturn($count, $list));
        }
        return $this->fetch('admin/health/comm/index');
    }

    public function add(Request $request): Response
    {
        if ($request->isAjax()) {
            $post = $request->post();
            $rules = [];
            $this->validate($post, $rules);
            try {
                $save = $this->model->save($post);
            } catch (\Exception $e) {
                return $this->error('保存失败:' . $e->getMessage());
            }
            if ($save) {
                return $this->success('保存成功');
            } else {
                return $this->error('保存失败');
            }
        }
        $this->assign('formFields', $this->formFields);
        return $this->fetch('admin/health/comm/save');
    }

    public function edit(Request $request): Response
    {
        $pk = $this->model->getPk();
        $id = $request->get($pk, 0);
        $row = $this->model->find($id);
        if (empty($row)) return $this->error('数据不存在');
        if ($request->isAjax()) {
            $post = $request->post();
            $rules = [];
            $this->validate($post, $rules);
            try {
                $save = $row->save($post);
            } catch (\Exception $e) {
                return $this->error('保存失败:' . $e->getMessage());
            }
            if ($save) {
                return $this->success('保存成功');
            } else {
                return $this->error('保存失败');
            }
        }
        $this->assign('formFields', $this->formFields);
        $this->assign('row', $row);
        return $this->fetch('admin/health/comm/save');
    }
}
