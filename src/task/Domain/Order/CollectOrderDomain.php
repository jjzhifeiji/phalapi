<?php

namespace Task\Domain\Order;

use Task\Common\BaseDomain;
use Task\Common\ComRedis;
use PhalApi\Tool;
use function PhalApi\DI;

class CollectOrderDomain extends BaseDomain
{


    public function createOrder($pay_type, $amount, $platform, $business_no, $callback_url)
    {

        $data = array(
            'order_no' => 'i' . date('YmdHis') . rand(1000, 9999),
            'type' => 1,
            'pay_type' => $pay_type,
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'business_id' => $platform['id'],
            'business_name' => $platform['name'],
            'free' => $platform['collect_free'],
            'order_amount' => $amount,
            'cost_free' => $amount * $platform['collect_free'] / 10000,
            'entry_amount' => $amount * (10000 - $platform['collect_free']) / 10000,
            'business_no' => $business_no,
            'callback_url' => $callback_url
        );

        $res = $this->_getCollectOrderModel()->createOrder($data);

        DI()->logger->info($platform['name'] . "createOrder:" . $res);

        $this->autoAccept($res);

        return $data['order_no'];
    }

    //分配
    private function autoAccept($res)
    {
    }

    public function getOrder($orderNo)
    {
        //order_no,status,pay_type,user_id,order_sum,code_id
        $res = $this->_getCollectOrderModel()->getOrder($orderNo);

        $res['end_time'] = date('Y/m/d H:i:s', strtotime($res['create_time']) + 60 * 5);

        $res['pay_no'] = '';
        $res['pay_name'] = '';
        $res['pay_organ'] = '';
        $res['pay_local'] = '';

        if ($res['status'] == 2) {
            $code = $this->_getUserCollectInfoModel()->getCode($res['user_id'], $res['code_id']);
            if (empty($code)) {
                return null;
            }

            //{"pay_bank": "666666", "pay_name": "测试", "pay_account": "66666666666666666666", "pay_bank_local": "6666666"}
            $pi = json_decode($code['pay_info'], true);

            DI()->logger->info("pay_info:" . $code['pay_info']);
            DI()->logger->info("pay_info:" . $pi);

            $res['pay_no'] = $pi['pay_account'];
            $res['pay_name'] = $pi['pay_name'];
            $res['pay_organ'] = $pi['pay_bank'];
            $res['pay_local'] = $pi['pay_bank_local'];
        }

        return $res;
    }

    public function checkOrder()
    {
        $res = $this->_getCollectOrderModel()->getCheckOrder();
        foreach ($res as $order) {
            $ptime = strtotime($order['create_time']);
            $etime = time() - $ptime;
            //订单五分钟超时
            if ($etime < 60 * 5) {
                return $this->backOrder($order);
            }
        }
    }

    private function backOrder($order)
    {

        $orderLock = 'collect' . $order['id'];
        $isLock = ComRedis::lock($orderLock);
        if (!$isLock) {
            return "too hot";
        }

        $res = $this->_getCollectOrderModel()->timeOutOrder($order);
        ComRedis::unlock($orderLock);

        if (!empty($res)) {
            return "退款失败";
        }

        //扣款
        $res = $this->_getUserModel()->changeUserAmount($order['user_id'], $order['order_amount'], true);

        if (empty($res)) {
            return "扣款失败";
        }

        //用户金额log
        $logData = array(
            'user_id' => $order['user_id'],
            'create_time' => date('Y-m-d H:i:s'),
            'before_amount' => $res['beforeAmount'],
            'change_amount' => $res['changAmount'],
            'result_amount' => $res['afterAmount'],
            'type' => 7,
            'business_id' => $order['business_id'],
            'order_id' => $order['id'],
            'order_no' => $order['order_no'],
            'remark' => '超时退款',
        );
        $this->_getUserAmountRecordModel()->addUserLog($logData);


        return $res;
    }


}