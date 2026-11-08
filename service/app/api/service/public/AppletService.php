<?php

/**
 * 微信小程序服务
 */

namespace app\api\service\public;

use app\api\service\CommService;
use app\common\model\business\MallCustomer;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Http\StreamResponse;
use Exception;
use support\exception\BusinessException;
use support\think\Db;

class AppletService extends CommService
{
    protected $app = [];
    protected $app_id = '';
    protected $is_debug = false;
    protected $category = 'applet';
    protected $access_path = '/storage/qrcode/applet';
    protected $storage_path = '';

    public function __construct($param)
    {
        parent::_initialize($param);
        $this->storage_path = public_path() . $this->access_path;
        if (!file_exists($this->storage_path)) {
            mkdir($this->storage_path, 0755, true);
        }

        $wx_config = config('wechat');
        $this->app_id = $wx_config['app_id'];

        if (!$this->is_debug) {
            $config = [
                'app_id' => $wx_config['app_id'],
                'secret' => $wx_config['app_secret'],
                'response_type' => 'array',
                'log' => [
                    'level' => 'debug',
                    'file' => __DIR__ . '/wechat.log',
                ],
            ];
            $this->app = Factory::miniProgram($config);
        }
    }

    public function checkRegisterStatus($login_code)
    {
        $info = $this->getAppletInfo($login_code);
        $MallCustomer = new MallCustomer();
        $user_info = $MallCustomer->findUser(['unionid' => $info['user']['unionid']]);

        $this->data['user_info'] = $MallCustomer->doLogin($user_info, $info['user']);

        return $this->data;
    }

    public function getAppletInfo($login_code)
    {
        if ($this->is_debug) {
            $openid = '1234568790';
            $unionid = '1234568790';
        } else {
            $wechat_user_info = $this->app->auth->session($login_code);

            //            Log::info('wechat_user_info', $wechat_user_info);
            if (isset($wechat_user_info['errcode'])) {
                return throwError($wechat_user_info['errmsg'], $wechat_user_info['errcode']);
            }

            $openid = $wechat_user_info['openid'];
            $unionid = $wechat_user_info['unionid'] ?? $wechat_user_info['openid'];
        }

        $user['openid'] = $openid;
        $user['unionid'] = $unionid;
        $user['app_id'] = $this->app_id;
        $user['avatar'] = $this->param['avatar'] ?? '';
        $user['nickname'] = $this->param['nickname'] ?? '';

        //      $user['channel'] = $this->param['channel'] ?? '';

        $recommend_id = 0;

        // 处理 scene 参数，提取推荐人 ID
        if (!empty($this->param['scene'])) {
            $scene = $this->param['scene'];
            // 先进行 URL 解码
            $scene = urldecode($scene);

            // 解析 scene 参数，格式为 "customer_id=123"
            parse_str($scene, $scene_params);
            if (isset($scene_params['customer_id'])) {
                $recommend_id = (int)$scene_params['customer_id'];
            }
        }
        return ['user' => $user, 'recommend_id' => $recommend_id];
    }

    public function doLogin($login_code)
    {
        Db::startTrans();
        try {

            $info = $this->getAppletInfo($login_code);
            $MallCustomer = new MallCustomer();
            $user_info = $MallCustomer->findUser(['unionid' => $info['user']['unionid']]);
            $this->data['user_info'] = $MallCustomer->doLogin($user_info, $info['user'], true, $info['user']['app_id'], 0, $this->category);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw new BusinessException($e->getMessage(), 401);
        }

        return $this->data;
    }

    public function getUserPhone($phone_code, $customer_id)
    {
        try {
            $phone = $this->getAppletPhone($phone_code);

            $user_info = MallCustomer::where('customer_id', $customer_id)->find();
            $user_info->phone_number = $phone['phone_number'];
            $user_info->country_code = $phone['country_code'];
            $user_info->save();
        } catch (Exception $e) {
            throw new BusinessException($e->getMessage(), 401);
        }

        return $this->data;
    }

    public function getAppletPhone($phone_code)
    {
        if ($this->is_debug) {
            $phone_number = '15319751011';
            $country_code = '86';
        } else {
            $info = $this->app->phone_number->getUserPhoneNumber($phone_code);
            if ($info['errcode']) {
                return throwError($info['errmsg'], $info['errcode']);
            }

            $phone_number = $info['phone_info']['phoneNumber'];
            $country_code = $info['phone_info']['countryCode'];
        }

        return ['phone_number' => $phone_number, 'country_code' => $country_code];
    }


    // 该接口用于获取小程序码，适用于需要的码数量较少的业务场景。通过该接口生成的小程序码，永久有效，有数量限制
    public function createLessAppCode()
    {
        $qr_filename = md5($this->param['path']) . '.png';

        if (file_exists($this->storage_path . '/' . $qr_filename)) {
            $this->data['url'] = $this->access_path . '/' . $qr_filename;
            return $this->data;
        }

        $response = $this->app->app_code->get($this->param['path']);
        $this->data['url'] = $this->saveQrFile($response, $qr_filename);

        // 如果生成失败，记录错误
        if (empty($this->data['url'])) {
            $this->data['error'] = '二维码生成失败';
        }

        return $this->data;
    }

    protected function saveQrFile($response, $qr_filename)
    {
        // 记录响应类型，用于调试
        if (!($response instanceof StreamResponse)) {
            // 如果不是图片流，可能是错误信息
            if (is_array($response) && isset($response['errcode'])) {
                $this->data['wechat_error'] = $response;
                return '';
            }
            $this->data['response_type'] = gettype($response);
            return '';
        }

        $filename = $response->saveAs($this->storage_path, $qr_filename);
        return $this->access_path . '/' . $filename;
    }

    // 该接口用于获取小程序码，适用于需要的码数量极多的业务场景。通过该接口生成的小程序码，永久有效，数量暂无限制
    public function createUnlimitAppCode()
    {
        $qr_filename = md5($this->param['scene']) . '.png';
        if (file_exists($this->storage_path . '/' . $qr_filename)) {
            $this->data['url'] = $this->access_path . '/' . $qr_filename;
            return $this->data;
        }
        $optional['check_path'] = false;
        $optional['env_version'] = $this->param['env_version'];
        if (!empty($this->param['page'])) {
            $optional['page'] = $this->param['page'];
        }

        //        d($optional);
        $response = $this->app->app_code->getUnlimit($this->param['scene'], $optional);
        $this->data['url'] = $this->saveQrFile($response, $qr_filename);

        // 如果生成失败，记录错误
        if (empty($this->data['url'])) {
            $this->data['error'] = '二维码生成失败';
        }

        return $this->data;
    }

    public function sendMessage($touser, $template_id, $page, $form_id, $data)
    {
        $send_body = $this->getMessageTemplate($touser, $template_id, $page, $form_id, $data);
        $send_res = $this->app->template_message->send($send_body);
        if ($send_res['errcode'] != 200) {
            throw new BusinessException($send_res['errmsg'], $send_res['errcode']);
        }

        return $this->data;
    }

    public function getMessageTemplate($touser, $template_id, $page, $form_id, $data)
    {
        $patterns = array(
            '/{touser}/',
            '/{page}/',
            '/{form_id}/',
        );
        $replacements = array(
            $touser,
            $page,
            $form_id,
        );
        $template = config('applet_template.' . $template_id);

        //方法说明：preg_replace($pattern, $replacement, $subject, $limit, $count)  搜索subject中匹配pattern的部分， 以replacement进行替换。
        $send_body = preg_replace($patterns, $replacements, $template);
        $send_body = array_merge($send_body, ['data' => $data]);

        return $send_body;
    }
}