<?php

namespace app\api\service\business;

use app\api\service\CommService;
use app\common\model\business\MallCustomer;
use app\common\model\health\HealthGlucose;
use app\common\model\health\HealthBloodPressure;
use app\common\model\health\HealthWeight;
use app\common\model\health\HealthWaist;
use app\common\model\health\CustomerProfile;
use support\exception\BusinessException;

/**
 * 用户业务服务
 * 处理用户信息、首页仪表盘、个人档案、统计数据
 */
class CustomerService extends CommService
{
    public function __construct($param, $user_info)
    {
        parent::_initialize($param, $user_info);
    }

    /**
     * 获取当前用户信息（含推荐关系）
     * 出参: customer_id, openid, nickname, avatar, phone_number, has_superior, superior_id
     */
    public function getInfo()
    {
        $this->data['current_time'] = get_datas();
        $customerInfo = MallCustomer::where('mall_customer.customer_id', $this->user_info->customer_id)
            ->field('mall_customer.customer_id,openid,nickname,avatar,phone_number')
            ->withJoin(
                [
                    'expansion' => ['recommend_id']
                ]
            )
            ->find();

        $hasSuperior = false;
        $superiorId = 0;
        if ($customerInfo && !empty($customerInfo['expansion'])) {
            $expansion = $customerInfo['expansion'];
            if (!empty($expansion['recommend_id']) && $expansion['recommend_id'] > 0) {
                $hasSuperior = true;
                $superiorId = (int)$expansion['recommend_id'];
            }
        }

        $this->data['customer_expansion'] = $customerInfo;
        $this->data['has_superior'] = $hasSuperior;
        $this->data['superior_id'] = $superiorId;

        return $this->data;
    }

    /**
     * 更新用户基本信息（昵称、头像、手机号、地址）
     * 入参(从$this->param读取): nickname, avatar, phone_number, address
     */
    public function updateInfo()
    {
        $user = MallCustomer::where('customer_id', $this->user_info->customer_id)->find();
        if (!$user) {
            throw new BusinessException('用户不存在', 401);
        }

        if (!empty($this->param['nickname'])) {
            $nickname = trim($this->param['nickname']);
            if (mb_strlen($nickname) > 50) {
                throw new BusinessException('昵称不能超过50个字符', 401);
            }
            $user->nickname = $nickname;
        }
        if (!empty($this->param['avatar'])) {
            $user->avatar = $this->param['avatar'];
        }

        if (!empty($this->param['phone_number'])) {
            $phone = trim($this->param['phone_number']);
            if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                throw new BusinessException('手机号格式不正确', 401);
            }

            $existsPhone = MallCustomer::where('phone_number', $phone)
                ->where('customer_id', '<>', $this->user_info->customer_id)
                ->find();
            if ($existsPhone) {
                throw new BusinessException('该手机号已被其他用户使用', 401);
            }

            $user->phone_number = $phone;
        }

        if (!empty($this->param['address'])) {
            $user->address = $this->param['address'];
        }

        $user->save();

        return $this->data;
    }

    /**
     * 组装首页仪表盘数据
     * 返回: bmr + 四项最新记录 + 最近10条混合时间线
     */
    public function getDashboard()
    {
        if (empty($this->user_info)) {
            return [
                'bmr' => 0,
                'latest_glucose' => null,
                'latest_blood_pressure' => null,
                'latest_weight' => null,
                'latest_waist' => null,
                'records' => [],
            ];
        }

        $customer_id = $this->user_info->customer_id;

        // 计算基础代谢率(BMR)
        $profile = CustomerProfile::where('customer_id', $customer_id)->find();
        $bmr = 0;
        if ($profile && $profile->height > 0) {
            $latestWeight = HealthWeight::where('customer_id', $customer_id)
                ->order('measure_time', 'desc')
                ->find();
            if ($latestWeight) {
                $bmr = $this->calculateBMR($profile, $latestWeight->weight);
            }
        }
        $this->data['bmr'] = $bmr;

        // 最新血糖
        $latestGlucose = HealthGlucose::where('customer_id', $customer_id)
            ->order('measure_time', 'desc')
            ->find();
        $this->data['latest_glucose'] = $latestGlucose ? [
            'id' => $latestGlucose->id,
            'value' => $latestGlucose->value,
            'measure_status' => $latestGlucose->measure_status,
            'measure_status_text' => HealthGlucose::$measureStatusMap[$latestGlucose->measure_status] ?? '',
            'measure_time' => $latestGlucose->measure_time,
        ] : null;

        // 最新血压
        $latestBloodPressure = HealthBloodPressure::where('customer_id', $customer_id)
            ->order('measure_time', 'desc')
            ->find();
        $this->data['latest_blood_pressure'] = $latestBloodPressure ? [
            'id' => $latestBloodPressure->id,
            'systolic' => $latestBloodPressure->systolic,
            'diastolic' => $latestBloodPressure->diastolic,
            'pulse' => $latestBloodPressure->pulse,
            'measure_time' => $latestBloodPressure->measure_time,
        ] : null;

        // 最新体重
        $latestWeight = HealthWeight::where('customer_id', $customer_id)
            ->order('measure_time', 'desc')
            ->find();
        $this->data['latest_weight'] = $latestWeight ? [
            'id' => $latestWeight->id,
            'weight' => $latestWeight->weight,
            'bmi' => $latestWeight->bmi,
            'measure_time' => $latestWeight->measure_time,
        ] : null;

        // 最新腰围
        $latestWaist = HealthWaist::where('customer_id', $customer_id)
            ->order('measure_time', 'desc')
            ->find();
        $this->data['latest_waist'] = $latestWaist ? [
            'id' => $latestWaist->id,
            'value' => $latestWaist->value,
            'measure_time' => $latestWaist->measure_time,
        ] : null;

        // 最近10条混合记录（按时间倒序）
        $allRecords = [];

        $glucoseList = HealthGlucose::where('customer_id', $customer_id)
            ->order('measure_time', 'desc')
            ->limit(5)
            ->select();
        foreach ($glucoseList as $item) {
            $allRecords[] = [
                'type' => 'glucose',
                'id' => $item->id,
                'value' => $item->value,
                'unit' => 'mmol/L',
                'measure_status_text' => HealthGlucose::$measureStatusMap[$item->measure_status] ?? '',
                'measure_time' => $item->measure_time,
                'color' => 'red',
            ];
        }

        $bpList = HealthBloodPressure::where('customer_id', $customer_id)
            ->order('measure_time', 'desc')
            ->limit(5)
            ->select();
        foreach ($bpList as $item) {
            $allRecords[] = [
                'type' => 'blood_pressure',
                'id' => $item->id,
                'value' => $item->systolic . '/' . $item->diastolic,
                'unit' => 'mmHg',
                'measure_status_text' => '',
                'measure_time' => $item->measure_time,
                'color' => 'blue',
            ];
        }

        $weightList = HealthWeight::where('customer_id', $customer_id)
            ->order('measure_time', 'desc')
            ->limit(5)
            ->select();
        foreach ($weightList as $item) {
            $allRecords[] = [
                'type' => 'weight',
                'id' => $item->id,
                'value' => $item->weight,
                'unit' => 'kg',
                'measure_status_text' => '',
                'measure_time' => $item->measure_time,
                'color' => 'purple',
            ];
        }

        $waistList = HealthWaist::where('customer_id', $customer_id)
            ->order('measure_time', 'desc')
            ->limit(5)
            ->select();
        foreach ($waistList as $item) {
            $allRecords[] = [
                'type' => 'waist',
                'id' => $item->id,
                'value' => $item->value,
                'unit' => 'cm',
                'measure_status_text' => '',
                'measure_time' => $item->measure_time,
                'color' => 'orange',
            ];
        }

        usort($allRecords, function ($a, $b) {
            return $b['measure_time'] - $a['measure_time'];
        });

        $this->data['records'] = array_slice($allRecords, 0, 10);

        return $this->data;
    }

    /**
     * 计算基础代谢率 BMR
     * 使用 Mifflin-St Jeor 公式
     * 男性: 88.362 + (13.397 × 体重kg) + (4.799 × 身高cm) - (5.677 × 年龄)
     * 女性: 447.593 + (9.247 × 体重kg) + (3.098 × 身高cm) - (4.330 × 年龄)
     */
    private function calculateBMR($profile, $weight)
    {
        $height = $profile->height;
        $age = 30;

        if (!empty($profile->birth_date)) {
            $birthDate = new \DateTime($profile->birth_date);
            $now = new \DateTime();
            $age = $birthDate->diff($now)->y;
        }

        if ($profile->gender == 1) {
            $bmr = 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        } else {
            $bmr = 447.593 + (9.247 * $weight) + (3.098 * $height) - (4.330 * $age);
        }

        return round($bmr);
    }

    /**
     * 获取或创建用户个人档案
     * 出参: profile { customer_id, nickname, height, gender, birth_date }
     */
    public function getProfile()
    {
        $customer_id = $this->user_info->customer_id;

        $profile = CustomerProfile::where('customer_id', $customer_id)->find();
        if (!$profile) {
            $profile = CustomerProfile::create([
                'customer_id' => $customer_id,
                'height' => 0,
                'gender' => 0,
                'birth_date' => '',
            ]);
        }

        $this->data['profile'] = $profile->toArray();
        return $this->data;
    }

    /**
     * 更新个人档案（身高/性别/出生日期）
     * 入参: height(float cm), gender(int 0女/1男), birth_date(string YYYY-MM-DD)
     * 出参: profile { 更新后的档案 }
     */
    public function updateProfile()
    {
        $customer_id = $this->user_info->customer_id;

        $profile = CustomerProfile::where('customer_id', $customer_id)->find();
        if (!$profile) {
            $profile = new CustomerProfile();
            $profile->customer_id = $customer_id;
        }

        if (isset($this->param['height'])) {
            $profile->height = $this->param['height'];
        }
        if (isset($this->param['gender'])) {
            $profile->gender = $this->param['gender'];
        }
        if (isset($this->param['birth_date'])) {
            $profile->birth_date = $this->param['birth_date'];
        }
        $profile->save();

        $this->data['profile'] = $profile->toArray();
        return $this->data;
    }

    /**
     * 获取统计数据（按类型和时间范围）
     * 入参: type(glucose|blood_pressure|weight|waist), period(天数 默认7)
     * 出参:
     *   stats: { avg, max, min, count }
     *   list: 详细记录(最多30条)
     *   chart: 趋势图数据(按时间升序)
     */
    public function getStats()
    {
        if (empty($this->user_info)) {
            return ['stats' => ['avg' => '--', 'max' => '--', 'min' => '--', 'count' => 0], 'list' => [], 'chart' => []];
        }

        $customer_id = $this->user_info->customer_id;
        $type = $this->param['type'] ?? 'glucose';
        $period = $this->param['period'] ?? 7;
        $startTime = time() - $period * 86400;

        $stats = ['avg' => '--', 'max' => '--', 'min' => '--', 'count' => 0];
        $list = [];
        $chart = [];

        switch ($type) {
            case 'glucose':
                $query = HealthGlucose::where('customer_id', $customer_id)
                    ->where('measure_time', '>=', $startTime);
                $count = $query->count();
                $stats['count'] = $count;

                if ($count > 0) {
                    $stats['avg'] = round($query->avg('value'), 1);
                    $stats['max'] = round($query->max('value'), 1);
                    $stats['min'] = round($query->min('value'), 1);
                }

                $list = $query->order('measure_time', 'desc')
                    ->limit(30)
                    ->select()
                    ->toArray();
                foreach ($list as &$item) {
                    $item['measure_status_text'] = HealthGlucose::$measureStatusMap[$item['measure_status']] ?? '';
                }
                unset($item);

                $chartData = HealthGlucose::where('customer_id', $customer_id)
                    ->where('measure_time', '>=', $startTime)
                    ->order('measure_time', 'asc')
                    ->field('value,measure_time')
                    ->select()
                    ->toArray();
                $chart = $chartData;
                break;

            case 'blood_pressure':
                $query = HealthBloodPressure::where('customer_id', $customer_id)
                    ->where('measure_time', '>=', $startTime);
                $count = $query->count();
                $stats['count'] = $count;

                if ($count > 0) {
                    $stats['avg'] = round($query->avg('systolic'), 0) . '/' . round($query->avg('diastolic'), 0);
                    $stats['max'] = round($query->max('systolic'), 0);
                    $stats['min'] = round($query->min('diastolic'), 0);
                }

                $list = $query->order('measure_time', 'desc')
                    ->limit(30)
                    ->select()
                    ->toArray();

                $chartData = HealthBloodPressure::where('customer_id', $customer_id)
                    ->where('measure_time', '>=', $startTime)
                    ->order('measure_time', 'asc')
                    ->field('systolic,diastolic,measure_time')
                    ->select()
                    ->toArray();
                $chart = $chartData;
                break;

            case 'weight':
                $query = HealthWeight::where('customer_id', $customer_id)
                    ->where('measure_time', '>=', $startTime);
                $count = $query->count();
                $stats['count'] = $count;

                if ($count > 0) {
                    $stats['avg'] = round($query->avg('weight'), 1);
                    $stats['max'] = round($query->max('weight'), 1);
                    $stats['min'] = round($query->min('weight'), 1);
                }

                $list = $query->order('measure_time', 'desc')
                    ->limit(30)
                    ->select()
                    ->toArray();

                $chartData = HealthWeight::where('customer_id', $customer_id)
                    ->where('measure_time', '>=', $startTime)
                    ->order('measure_time', 'asc')
                    ->field('weight,measure_time')
                    ->select()
                    ->toArray();
                $chart = $chartData;
                break;

            case 'waist':
                $query = HealthWaist::where('customer_id', $customer_id)
                    ->where('measure_time', '>=', $startTime);
                $count = $query->count();
                $stats['count'] = $count;

                if ($count > 0) {
                    $stats['avg'] = round($query->avg('value'), 1);
                    $stats['max'] = round($query->max('value'), 1);
                    $stats['min'] = round($query->min('value'), 1);
                }

                $list = $query->order('measure_time', 'desc')
                    ->limit(30)
                    ->select()
                    ->toArray();

                $chartData = HealthWaist::where('customer_id', $customer_id)
                    ->where('measure_time', '>=', $startTime)
                    ->order('measure_time', 'asc')
                    ->field('value,measure_time')
                    ->select()
                    ->toArray();
                $chart = $chartData;
                break;
        }

        $this->data['stats'] = $stats;
        $this->data['list'] = $list;
        $this->data['chart'] = $chart;

        return $this->data;
    }
}
