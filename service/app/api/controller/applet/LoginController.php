<?php

namespace app\api\controller\applet;

use app\api\service\public\AppletService;
use app\common\controller\ApiController;

/**
 * 微信小程序登录控制器
 * 路由前缀: /applet/login/
 * 无需登录: doLogin, checkRegisterStatus
 */
class LoginController extends ApiController
{
    protected $noNeedLogin = ['doLogin','checkRegisterStatus'];

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * 微信小程序登录
     * 路由: POST /applet/login/doLogin
     * 入参: login_code(string 必填 微信code), avatar(可选), nickname(可选)
     * 出参: user_info { customer_id, nickname, avatar, phone_number, expansion.token }
     */
    public function doLogin()
    {
        $rule['login_code'] = 'require';
        $this->validate($this->param, $rule);

        $AppletService = new AppletService($this->param);
        $res = $AppletService->doLogin($this->param['login_code']);

        return $this->render_success($res);
    }

    /**
     * 检查用户是否已注册
     * 路由: POST /applet/login/checkRegisterStatus
     * 入参: login_code(string 必填)
     * 出参: is_registered(bool), user_info(已注册时返回)
     */
    public function checkRegisterStatus()
    {
        $rule['login_code'] = 'require';
        $this->validate($this->param, $rule);

        $AppletService = new AppletService($this->param);
        $res = $AppletService->checkRegisterStatus($this->param['login_code']);

        return $this->render_success($res);
    }
}
