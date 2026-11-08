<?php

namespace app\api\controller\applet;

use app\api\service\public\AppletService;
use app\common\controller\ApiController;

class AppletController extends ApiController
{
    protected $noNeedLogin = ['createLessAppCode', 'createUnlimitAppCode'];

    public function initialize()
    {
        parent::initialize();
    }

    public function getUserPhone()
    {
        $rule['app_id'] = 'require';
        $rule['phone_code'] = 'require';
        $this->validate($this->param, $rule);

        $AppletService = new AppletService($this->param);
        $res = $AppletService->getUserPhone($this->param['phone_code'], $this->user_info['customer_id']);

       return $this->render_success($res);
    }

    //该接口用于获取小程序码，适用于需要的码数量较少的业务场景。通过该接口生成的小程序码，永久有效，有数量限制
    public function createLessAppCode()
    {
        $rule['path'] = 'require';
        $this->validate($this->param, $rule);

        $AppletService = new AppletService($this->param);
        $res = $AppletService->createLessAppCode();

        return $this->render_success($res);
    }


    // 该接口用于获取小程序码，适用于需要的码数量极多的业务场景。通过该接口生成的小程序码，永久有效，数量暂无限制
    public function createUnlimitAppCode()
    {
//      scene=最大32个可见字符，只支持数字，大小写英文以及部分特殊字符：!#$&'()*+,/:;=?@-._~，其它字符请自行编码为合法字符（因不支持%，中文无法使用 urlencode 处理，请使用其他编码方式）
//      page= 页面 page，例如 pages/index/index，根路径前不要填加 /，不能携带参数（参数请放在scene字段里），如果不填写这个字段，默认跳主页面。scancode_time为系统保留参数，不允许配置
//      env_version= 要打开的小程序版本。正式版为 "release"，体验版为 "trial"，开发版为 "develop"。默认是正式版。

        $rule['scene'] = 'require';
//        $rule['page'] = 'require';
        $rule['env_version'] = 'require|in:release,trial,develop';
        $this->validate($this->param, $rule);

        $AppletService = new AppletService($this->param);
        $res = $AppletService->createUnlimitAppCode();

        return $this->render_success($res);
    }
}