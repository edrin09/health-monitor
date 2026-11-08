<?php

namespace app\api\controller\business;

use app\api\service\business\CustomerService;

/**
 * 用户控制器
 * 路由前缀: /business/customer/
 * 需登录: 是
 */
class CustomerController extends ApiMallComm
{
    protected $noNeedLogin = ['stats', 'dashboard'];
    protected $noCheckCustomer = ['stats', 'dashboard'];

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * 获取用户信息
     * 路由: GET /business/customer/getInfo
     * 入参: 无
     * 出参: customer_id, openid, nickname, avatar, phone_number, expansion(推荐人信息), has_superior, superior_id
     */
    public function getInfo()
    {
        $service = new CustomerService($this->param, $this->user_info);
        return $this->render_success($service->getInfo());
    }

    /**
     * 更新用户信息
     * 路由: POST /business/customer/updateInfo
     * 入参: nickname(可选), avatar(可选), phone_number(可选), address(可选)
     * 出参: 无
     */
    public function updateInfo()
    {
        $service = new CustomerService($this->param, $this->user_info);
        return $this->render_success($service->updateInfo());
    }

    /**
     * 获取首页仪表盘数据
     * 路由: GET /business/customer/dashboard
     * 入参: 无
     * 出参:
     *   bmr: 基础代谢率
     *   latest_glucose: 最新血糖记录
     *   latest_blood_pressure: 最新血压记录
     *   latest_weight: 最新体重记录
     *   latest_waist: 最新腰围记录
     *   records: 最近10条混合记录列表
     */
    public function dashboard()
    {
        $service = new CustomerService($this->param, $this->user_info);

        $res = $service->getDashboard();
        return $this->render_success($res);
    }

    /**
     * 获取个人档案
     * 路由: GET /business/customer/profile
     * 入参: 无
     * 出参: profile { customer_id, nickname, height, gender, birth_date }
     */
    public function profile()
    {
        $service = new CustomerService($this->param, $this->user_info);
        return $this->render_success($service->getProfile());
    }

    /**
     * 更新个人档案
     * 路由: POST /business/customer/updateProfile
     * 入参: nickname(可选), gender(可选 0女/1男), birth_date(可选 YYYY-MM-DD), height(可选 cm)
     * 出参: profile { 更新后的档案数据 }
     */
    public function updateProfile()
    {
        $service = new CustomerService($this->param, $this->user_info);
        return $this->render_success($service->updateProfile());
    }

    /**
     * 获取统计图表数据
     * 路由: GET /business/customer/stats
     * 入参: type(glucose|blood_pressure|weight|waist), period(天数 默认7)
     * 出参:
     *   stats: { avg, max, min, count }
     *   list: 详细记录列表
     *   chart: 趋势图数据
     */
    public function stats()
    {
        $service = new CustomerService($this->param, $this->user_info);
        return $this->render_success($service->getStats());
    }
}
