<?php

namespace app\admin\controller\system;

use app\admin\controller\comm\CommController;
use app\admin\services\annotation\ControllerAnnotation;
use app\admin\services\annotation\NodeAnnotation;
use app\common\model\system\SystemConfig;
use support\Cache;
use support\Request;
use support\Response;

#[ControllerAnnotation(title: '协议配置')]
class ConfigController extends CommController
{
    public function initialize()
    {
        parent::initialize();
    }

    #[NodeAnnotation(title: '协议配置', auth: true)]
    public function save(Request $request): Response
    {
        if ($request->isAjax()) {

            $post = $request->post();

            $userAgreement = $post['user_agreement'] ?? '';
            $privacyPolicy = $post['privacy_policy'] ?? '';

            $model = new SystemConfig();

            $row = $model->where(['group' => 'site', 'name' => 'user_agreement'])->find();
            if ($row) {
                $row->value = $userAgreement;
                $row->save();
            } else {
                $model->create(['group' => 'site', 'name' => 'user_agreement', 'value' => $userAgreement]);
            }

            $row2 = $model->where(['group' => 'site', 'name' => 'privacy_policy'])->find();
            if ($row2) {
                $row2->value = $privacyPolicy;
                $row2->save();
            } else {
                $model->create(['group' => 'site', 'name' => 'privacy_policy', 'value' => $privacyPolicy]);
            }

            Cache::delete('sysconfig_site_user_agreement');
            Cache::delete('sysconfig_site_privacy_policy');

            return $this->success('保存成功');
        }

        return $this->fetch('admin/system/config/save');

    }
}