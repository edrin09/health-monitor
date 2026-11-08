<?php

namespace app\common\model\business;

use app\api\service\public\TokenService;
use app\common\model\BaseModel;
use support\exception\BusinessException;

/**
 * 商城用户模型
 * 表名: mall_customer
 * 字段: customer_id(主键), phone_number, password, openid, unionid, nickname, avatar, status, address, create_time, update_time
 * 关系: 一对一 MallCustomerExpansion (customer_id)
 */
class MallCustomer extends BaseModel
{
    public $pk = 'customer_id';

    public $name = 'mall_customer';
    public $noExportFields = ['password', 'tel', 'create_time', 'update_time'];

    protected $defaultValues = [
        'status' => 1,
    ];

    /**
     * 小程序登录 - 首次自动创建用户
     * 入参: user_info(已存在用户), param(登录参数), auto_login, app_id, recommend_id, category
     */
    public function doLogin($user_info, $param = [], $auto_login = false, $app_id = '', $recommend_id = 0, $category = 'h5')
    {
        if ($user_info) {
            $user_info = $this->getUserToken($user_info);
            return $user_info;
        }
        if ($auto_login == false) {
            return [];
        }

        $this->createUser($param, $app_id, $recommend_id, $category);

        $user_info = $this->findUser(['openid' => $param['openid']]);
        $user_info = $this->getUserToken($user_info);

        return $user_info;
    }

    /**
     * 生成/刷新用户token
     */
    private function getUserToken($user_info)
    {
        $expansion = MallCustomerExpansion::field('customer_id,token,login_time')->find($user_info->customer_id);

        if (empty($expansion)) {
            throw new BusinessException('用户不存在1', 601);
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

        $user_info->expansion = $expansion;
        return $user_info;
    }

    /**
     * 创建新用户
     */
    private function createUser($user, $app_id = '', $recommend_id = 0, $category = 'h5')
    {
        $this->app_id = $app_id;
        $this->openid = $user['openid'];
        $this->unionid = $user['unionid'] ?? $user['openid'];
        $this->avatar = $user['avatar'] ?? '';
        $this->nickname = $user['nickname'] ?? '';
        $this->phone_number = isset($user['phone_number']) ? trim($user['phone_number']) : 0;
        $this->sex = $user['sex'] ?? 0;
        $this->recommend_id = $recommend_id;
        $this->category = $category;
        $this->login_time = time();
        $this->token = get_guid_v4();

        $this->together(
            ['expansion' =>
                [
                    'token',
                    'recommend_id',
                    'category',
                    'login_time',
                ]
            ]
        )->save();
        return $this;
    }

    /**
     * 根据openid查找用户
     */
    public function findUser($where)
    {
        $user_info = $this
            ->field('customer_id,phone_number,openid,unionid,nickname,avatar')
            ->withJoin(
                [
                    'expansion' => [
                        'customer_id',
                        'token',
                        'login_time',
                    ]
                ]
            )
            ->where($where)
            ->find();

        return $user_info;
    }

    /**
     * 根据手机号查找用户(H5登录用)
     */
    public function findUserByPhone($phone)
    {
        return $this
            ->field('customer_id,phone_number,password,openid,unionid,nickname,avatar')
            ->where('phone_number', $phone)
            ->find();
    }

    /**
     * 关联用户扩展表
     */
    public function expansion()
    {
        return $this->hasOne(MallCustomerExpansion::class, 'customer_id', 'customer_id');
    }
}
