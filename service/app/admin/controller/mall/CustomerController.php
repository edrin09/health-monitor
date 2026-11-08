<?php

namespace app\admin\controller\mall;

use app\admin\controller\comm\MallComm;
use app\admin\services\annotation\ControllerAnnotation;
use app\admin\services\annotation\NodeAnnotation;
use app\common\model\business\MallCustomer;
use app\common\model\business\MallCustomerExpansion;
use app\common\model\health\CustomerProfile;
use Exception;
use support\Request;
use support\Response;

#[ControllerAnnotation(title: '客户管理')]
class CustomerController extends MallComm
{
    public function initialize()
    {
        $this->model = new MallCustomer();
        $this->business = 'customer';
        parent::initialize();

        $this->with_join = ['expansion' => [
            'login_num',
            'recommend_id',
            'status',
            'is_freeze'
        ]];
    }

    #[NodeAnnotation(title: '列表', auth: true)]
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
        return $this->fetch('admin/mall/customer/index');
    }

    #[NodeAnnotation(title: '客户详情', auth: true)]
    public function info(Request $request): Response
    {
        $customer_id = $request->get('customer_id', 0);
        if (!$customer_id) {
            return $this->error('请提供客户ID');
        }

        $customer = $this->model
            ->withJoin($this->with_join, $this->join_type)
            ->where('mall_customer.customer_id', $customer_id)
            ->find();

        if (!$customer) {
            return $this->error('客户不存在');
        }

        $profile = CustomerProfile::where('customer_id', $customer_id)->find();

        $this->assign('customer', $customer);
        $this->assign('profile', $profile);
        $this->assign('pk', 'customer_id');
        $this->assign('cid', 'customer_id');
        $this->assign('auth_header', '/mall/customer');

        return $this->fetch('admin/mall/customer/info');
    }

    #[NodeAnnotation(title: '冻结账户', auth: true)]
    public function freezeAccount(Request $request): Response
    {
        $customer_id = $request->post('customer_id', 0);
        $freeze_status = $request->post('freeze_status', 0);
        if (!$customer_id) {
            return $this->error('请提供客户ID');
        }

        $expansion = MallCustomerExpansion::where('customer_id', $customer_id)->find();
        if (!$expansion) {
            return $this->error('客户扩展信息不存在');
        }

        try {
            $expansion->is_freeze = $freeze_status;
            $expansion->update_time = time();
            $expansion->save();

            $statusText = $freeze_status == 1 ? '冻结' : '正常';
            return $this->success("账户已{$statusText}");
        } catch (Exception $e) {
            return $this->error('操作失败：' . $e->getMessage());
        }
    }
}
