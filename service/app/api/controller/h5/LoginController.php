<?php

namespace app\api\controller\h5;

use app\api\service\public\TokenService;
use app\common\controller\ApiController;
use app\common\model\business\MallCustomer;
use app\common\model\business\MallCustomerExpansion;
use support\exception\BusinessException;

/**
 * H5登录控制器
 * 路由前缀: /h5/login/
 * 无需登录: doLogin
 */
class LoginController extends ApiController
{
    protected $noNeedLogin = ['doLogin'];

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * H5手机号+密码登录
     * 路由: POST /h5/login/doLogin
     * 入参: phone_number(string 必填), password(string 必填)
     * 出参:
     *   customer_id, phone_number, nickname, avatar, openid, unionid
     *   token: 登录凭证(需在后续请求中放入authenticate.token)
     */
    public function doLogin()
    {
        $rule['phone_number'] = 'require';
        $rule['password'] = 'require';
        $this->validate($this->param, $rule);

        $phone = $this->param['phone_number'];
        $password = $this->param['password'];

        $MallCustomer = new MallCustomer();
        $user_info = $MallCustomer->findUserByPhone($phone);

        if (empty($user_info)) {
            throw new BusinessException('用户不存在', 401);
        }

        if ($user_info->password != password($password)) {
            throw new BusinessException('密码错误', 401);
        }

        $expansion = MallCustomerExpansion::find($user_info->customer_id);
        if (empty($expansion)) {
            throw new BusinessException('用户数据异常', 601);
        }

        $user_token = TokenService::verifyToken($expansion->token);
        if (empty($user_token)) {
            $expansion->openid = $user_info->openid;
            $expansion->unionid = $user_info->unionid;
            $token = TokenService::generateToken($expansion);
            $expansion->token = $token;
        }

        $expansion->login_time = time();
        $expansion->save();

        $result = $user_info->toArray();
        unset($result['password']);
        $result['token'] = $expansion->token;

        return $this->render_success($result);
    }
}
