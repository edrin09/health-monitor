<?php


namespace app\api\controller\business;

use app\api\traits\ApiCurd;
use app\common\controller\ApiController;
use app\common\model\business\MallCustomer;
use support\exception\BusinessException;

/**
 * Class ApiMallComm
 */
class ApiMallComm extends ApiController
{
    use ApiCurd;

    protected $business = '';
    protected $noCheckCustomer = [];

    public function initialize()
    {
        parent::initialize();
        if (!in_array($this->request->action, $this->noCheckCustomer)) {
            $MallCustomer = new MallCustomer();
            $this->user_info = $MallCustomer->findUser(['mall_customer.customer_id' => $this->user_info['customer_id']]);
            if (empty($this->user_info)) {
//                throwError('获取用户信息失败', 601);

                throw new BusinessException('获取用户信息失败', 601);
            }
        }
    }
}