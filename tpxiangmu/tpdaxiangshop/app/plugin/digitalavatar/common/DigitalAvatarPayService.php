<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;
use think\facade\Log;

class DigitalAvatarPayService
{
    public static function getPayOptions(int $aid, int $memberId): array
    {
        $appinfo = \app\admin\common\System::appinfo($aid, 'h5') ?: [];
        $set = Db::name('admin_set')->where('aid', $aid)->find() ?: [];
        $member = [];
        if ($memberId > 0) {
            $member = Db::name('member')->where('aid', $aid)->where('id', $memberId)->find() ?: [];
        }
        $wxEnabled = intval($appinfo['wxpay'] ?? 0) === 1 && intval($set['wxpay_enabled'] ?? 1) === 1;
        $aliEnabled = intval($appinfo['alipay'] ?? 0) === 1;
        $moneyEnabled = intval($set['moneypay'] ?? 0) === 1;
        $memberMoney = round(floatval($member['money'] ?? 0), 2);
        $methods = [];
        if ($wxEnabled) {
            $methods[] = [
                'type' => 'wxpay',
                'name' => '微信支付',
                'hint' => '微信扫码支付',
            ];
        }
        if ($aliEnabled) {
            $methods[] = [
                'type' => 'alipay',
                'name' => '支付宝支付',
                'hint' => '跳转到支付宝完成付款',
            ];
        }
        if ($moneyEnabled) {
            $methods[] = [
                'type' => 'money',
                'name' => (function_exists('t') ? t('余额') : '余额') . '支付',
                'hint' => '可用余额 ¥' . number_format($memberMoney, 2, '.', ''),
            ];
        }
        return [
            'wxpay' => $wxEnabled ? 1 : 0,
            'alipay' => $aliEnabled ? 1 : 0,
            'moneypay' => $moneyEnabled ? 1 : 0,
            'member_money' => number_format($memberMoney, 2, '.', ''),
            'methods' => $methods,
        ];
    }

    public static function pay(int $aid, int $bid, int $memberId, int $orderId, string $payType, string $returnUrl = ''): array
    {
        $payType = strtolower(trim($payType));
        if (!in_array($payType, ['wxpay', 'alipay', 'money'], true)) {
            throw new \RuntimeException('请选择支付方式');
        }
        $order = self::loadMemberOrder($aid, $bid, $memberId, $orderId);
        $status = self::ensureSettledIfPaid($order);
        if (intval($status['paid'] ?? 0) === 1) {
            return $status;
        }

        $options = self::getPayOptions($aid, $memberId);
        if ($payType === 'wxpay' && intval($options['wxpay'] ?? 0) !== 1) {
            throw new \RuntimeException('当前未开启微信支付');
        }
        if ($payType === 'alipay' && intval($options['alipay'] ?? 0) !== 1) {
            throw new \RuntimeException('当前未开启支付宝支付');
        }
        if ($payType === 'money' && intval($options['moneypay'] ?? 0) !== 1) {
            throw new \RuntimeException('当前未开启余额支付');
        }

        $payorderId = intval($order['payorder_id'] ?? 0);
        $payorder = Db::name('payorder')->where('id', $payorderId)->where('aid', $aid)->find();
        if (!$payorder) {
            throw new \RuntimeException('支付单不存在，请重新下单');
        }
        if (intval($payorder['mid'] ?? 0) !== $memberId) {
            throw new \RuntimeException('支付单与当前会员不匹配');
        }
        if (intval($payorder['status'] ?? 0) === 1) {
            return self::ensureSettledIfPaid($order);
        }

        if ($payType === 'money') {
            return self::payByBalance($aid, $memberId, $order, $payorder);
        }
        if ($payType === 'wxpay') {
            return self::payByWechatNative($aid, $bid, $memberId, $order, $payorder);
        }
        return self::payByAlipay($aid, $bid, $memberId, $order, $payorder, $returnUrl);
    }

    public static function getOrderStatus(int $aid, int $bid, int $memberId, int $orderId): array
    {
        $order = self::loadMemberOrder($aid, $bid, $memberId, $orderId);
        return self::ensureSettledIfPaid($order);
    }

    private static function loadMemberOrder(int $aid, int $bid, int $memberId, int $orderId): array
    {
        if ($orderId <= 0) {
            throw new \RuntimeException('请传入套餐订单');
        }
        $query = Db::name('digitalavatar_package_order')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $orderId);
        if ($bid > 0) {
            $query->where('bid', $bid);
        }
        $order = $query->find();
        if (!$order) {
            throw new \RuntimeException('套餐订单不存在');
        }
        return $order;
    }

    private static function ensureSettledIfPaid(array $order): array
    {
        $orderId = intval($order['id'] ?? 0);
        $payorderId = intval($order['payorder_id'] ?? 0);
        $payorder = $payorderId > 0
            ? (Db::name('payorder')->where('id', $payorderId)->find() ?: [])
            : [];
        $packagePaid = intval($order['status'] ?? 0) === 1 || intval($order['grant_status'] ?? 0) === 1;
        $payorderPaid = intval($payorder['status'] ?? 0) === 1;
        if ($payorderPaid && !$packagePaid) {
            $payType = trim(strval($payorder['paytype'] ?? '')) ?: '在线支付';
            DigitalAvatarService::onPackageOrderPaid($orderId, $payType);
            $order = Db::name('digitalavatar_package_order')->where('id', $orderId)->find() ?: $order;
            $packagePaid = true;
        }
        $package = Db::name('digitalavatar_package')->where('id', intval($order['package_id'] ?? 0))->find() ?: [];
        return [
            'paid' => $packagePaid ? 1 : 0,
            'need_pay' => (!$packagePaid && $payorderId > 0) ? 1 : 0,
            'order_id' => $orderId,
            'ordernum' => strval($order['ordernum'] ?? ''),
            'package_id' => intval($order['package_id'] ?? 0),
            'package_title' => strval($package['title'] ?? $order['package_title'] ?? ''),
            'price' => number_format(floatval($order['price'] ?? 0), 2, '.', ''),
            'credit_amount' => DigitalAvatarCreditService::creditAmount($order['credit_amount'] ?? 0),
            'status' => intval($order['status'] ?? 0),
            'payorderid' => $payorderId,
            'pay_type' => strval($order['paytype'] ?? $payorder['paytype'] ?? ''),
        ];
    }

    private static function payByBalance(int $aid, int $memberId, array $order, array $payorder): array
    {
        $payorderId = intval($payorder['id'] ?? 0);
        $money = round(floatval($payorder['money'] ?? $order['price'] ?? 0), 2);
        $payName = (function_exists('t') ? t('余额') : '余额') . '支付';
        Db::transaction(function () use ($aid, $memberId, $order, $payorder, $payorderId, $money, $payName) {
            $locked = Db::name('digitalavatar_package_order')->where('id', intval($order['id']))->lock(true)->find();
            if (!$locked) {
                throw new \RuntimeException('套餐订单不存在');
            }
            if (intval($locked['status'] ?? 0) === 1 || intval($locked['grant_status'] ?? 0) === 1) {
                return;
            }
            $freshPayorder = Db::name('payorder')->where('id', $payorderId)->lock(true)->find();
            if (!$freshPayorder) {
                throw new \RuntimeException('支付单不存在');
            }
            if (intval($freshPayorder['status'] ?? 0) === 1) {
                DigitalAvatarService::onPackageOrderPaid(intval($order['id']), $payName);
                return;
            }
            $member = Db::name('member')->where('aid', $aid)->where('id', $memberId)->lock(true)->find();
            if (!$member) {
                throw new \RuntimeException('会员不存在');
            }
            if (round(floatval($member['money'] ?? 0), 2) < $money) {
                throw new \RuntimeException((function_exists('t') ? t('余额') : '余额') . '不足,请充值');
            }
            if ($money > 0) {
                $remark = '购买AI数字人套餐 ' . strval($order['ordernum'] ?? '');
                \app\admin\common\Member::addmoney($aid, $memberId, -$money, $remark, intval($order['id']));
            }
            $rs = \app\admin\model\Payorder::payorder($payorderId, $payName, 1, '');
            if (is_array($rs) && isset($rs['status']) && intval($rs['status']) === 0) {
                throw new \RuntimeException(strval($rs['msg'] ?? '余额支付失败'));
            }
        });
        $fresh = Db::name('digitalavatar_package_order')->where('id', intval($order['id']))->find() ?: $order;
        $result = self::ensureSettledIfPaid($fresh);
        $result['pay_mode'] = 'money';
        $result['paid'] = 1;
        $result['msg'] = '支付成功';
        return $result;
    }

    private static function payByWechatNative(int $aid, int $bid, int $memberId, array $order, array $payorder): array
    {
        $title = 'AI数字人套餐-' . trim(strval($order['package_title'] ?? ''));
        if ($title === 'AI数字人套餐-') {
            $packageTitle = strval(Db::name('digitalavatar_package')->where('id', intval($order['package_id'] ?? 0))->value('title'));
            $title = 'AI数字人套餐-' . $packageTitle;
        }
        $price = round(floatval($payorder['money'] ?? $order['price'] ?? 0), 2);
        $native = self::buildWechatNative($aid, $bid, $memberId, $title, strval($payorder['ordernum'] ?? $order['ordernum'] ?? ''), $price);
        if (intval($native['status'] ?? 0) !== 1) {
            throw new \RuntimeException(strval($native['msg'] ?? '微信支付下单失败'));
        }
        $codeUrl = trim(strval($native['code_url'] ?? ''));
        if ($codeUrl === '') {
            throw new \RuntimeException('未获取到微信支付二维码');
        }
        Db::name('payorder')->where('id', intval($payorder['id']))->update([
            'paytype' => '微信支付',
            'paytypeid' => 2,
        ]);
        $qrImage = '';
        if (function_exists('createqrcode')) {
            try {
                $qrImage = strval(createqrcode($codeUrl));
            } catch (\Throwable $e) {
                $qrImage = '';
            }
        }
        if ($qrImage === '') {
            $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($codeUrl);
        }
        $result = self::ensureSettledIfPaid($order);
        $result['pay_mode'] = 'wxpay';
        $result['code_url'] = $codeUrl;
        $result['qr_image'] = $qrImage;
        $result['msg'] = '请使用微信扫码支付';
        return $result;
    }

    private static function payByAlipay(int $aid, int $bid, int $memberId, array $order, array $payorder, string $returnUrl): array
    {
        $title = 'AI数字人套餐-' . trim(strval($order['package_title'] ?? ''));
        if ($title === 'AI数字人套餐-') {
            $packageTitle = strval(Db::name('digitalavatar_package')->where('id', intval($order['package_id'] ?? 0))->value('title'));
            $title = 'AI数字人套餐-' . $packageTitle;
        }
        $price = round(floatval($payorder['money'] ?? $order['price'] ?? 0), 2);
        $returnUrl = trim($returnUrl);
        if ($returnUrl === '') {
            $settings = [];
            try {
                $settings = DigitalAvatarCreditService::ensureSettings($aid);
            } catch (\Throwable $e) {
            }
            $returnUrl = DigitalAvatarPortalDomain::entryUrl($settings, $bid, [
                'paydone' => 1,
                'order_id' => intval($order['id']),
            ]);
            if ($returnUrl === '') {
                $returnUrl = rtrim(strval(request()->domain()), '/') . '/portal/digitalavatar/?aid=' . $aid
                    . ($bid > 0 ? '&bid=' . $bid : '')
                    . '&paydone=1&order_id=' . intval($order['id']);
            }
        }
        $rs = \app\admin\common\Alipay::build_h5(
            $aid,
            $bid,
            $memberId,
            $title,
            strval($payorder['ordernum'] ?? $order['ordernum'] ?? ''),
            $price,
            'digitalavatar_package',
            '',
            $returnUrl
        );
        if (intval($rs['status'] ?? 0) !== 1) {
            throw new \RuntimeException(strval($rs['msg'] ?? '支付宝下单失败'));
        }
        Db::name('payorder')->where('id', intval($payorder['id']))->update([
            'paytype' => '支付宝支付',
            'paytypeid' => 3,
        ]);
        $html = '';
        $payUrl = '';
        $data = $rs['data'] ?? '';
        if (is_array($data)) {
            $payUrl = trim(strval($data['url'] ?? $data['pay_url'] ?? $data['qr_code'] ?? ''));
            $html = strval($data['html'] ?? $data['form'] ?? '');
        } else {
            $html = strval($data);
        }
        if ($html !== '' && preg_match('#https?://[^\s"\'<>]+#', $html, $match) && stripos($html, '<form') === false) {
            $payUrl = $payUrl !== '' ? $payUrl : trim($match[0]);
        }
        $result = self::ensureSettledIfPaid($order);
        $result['pay_mode'] = 'alipay';
        $result['pay_html'] = $html;
        $result['pay_url'] = $payUrl;
        $result['msg'] = '正在跳转支付宝';
        return $result;
    }

    private static function buildWechatNative(int $aid, int $bid, int $mid, string $title, string $ordernum, float $price): array
    {
        $notifyUrl = rtrim(strval(defined('PRE_URL') ? PRE_URL : request()->domain()), '/') . '/notify.php';
        $appinfo = \app\admin\common\System::appinfo($aid, 'h5') ?: [];
        $appid = strval($appinfo['appid'] ?? '');
        $package = [];
        $mchkey = '';
        $isbusinesspay = false;

        if ($bid > 0) {
            $bset = Db::name('business_sysset')->where('aid', $aid)->find();
            if ($bset && intval($bset['wxfw_status'] ?? 0) === 1) {
                $business = Db::name('business')->where('id', $bid)->find();
                if ($business && intval($business['wxpayst'] ?? 0) === 1 && trim(strval($business['wxpay_submchid'] ?? '')) !== '') {
                    $isbusinesspay = true;
                    $package['appid'] = strval($bset['wxfw_appid'] ?? '');
                    $package['mch_id'] = strval($bset['wxfw_mchid'] ?? '');
                    if ($appid !== '') {
                        $package['sub_appid'] = $appid;
                    }
                    $package['sub_mch_id'] = strval($business['wxpay_submchid']);
                    $mchkey = strval($bset['wxfw_mchkey'] ?? '');
                    $package['attach'] = $aid . ':digitalavatar_package:h5:' . $bid;
                }
            }
        }

        if (!$isbusinesspay) {
            if (intval($appinfo['wxpay_type'] ?? 0) === 0) {
                $package['appid'] = $appid;
                $package['mch_id'] = strval($appinfo['wxpay_mchid'] ?? '');
                $mchkey = strval($appinfo['wxpay_mchkey'] ?? '');
            } else {
                $dbwxpayset = Db::name('sysset')->where('name', 'wxpayset')->value('value');
                $dbwxpayset = is_string($dbwxpayset) ? json_decode($dbwxpayset, true) : [];
                if (!is_array($dbwxpayset) || $dbwxpayset === []) {
                    return ['status' => 0, 'msg' => '未配置服务商微信支付信息'];
                }
                $package['appid'] = strval($dbwxpayset['appid'] ?? '');
                if ($appid !== '') {
                    $package['sub_appid'] = $appid;
                }
                $package['mch_id'] = strval($dbwxpayset['mchid'] ?? '');
                $package['sub_mch_id'] = strval($appinfo['wxpay_sub_mchid'] ?? '');
                $mchkey = strval($dbwxpayset['mchkey'] ?? '');
            }
            $package['attach'] = $aid . ':digitalavatar_package:h5';
        }

        if ($mchkey === '' || trim(strval($package['mch_id'] ?? '')) === '' || trim(strval($package['appid'] ?? '')) === '') {
            return ['status' => 0, 'msg' => '未配置微信支付信息'];
        }

        $package['nonce_str'] = function_exists('random') ? random(8) : substr(md5(uniqid('', true)), 0, 8);
        $package['body'] = mb_substr($title, 0, 40);
        $package['out_trade_no'] = $ordernum;
        $package['total_fee'] = intval(round($price * 100));
        $package['spbill_create_ip'] = strval(request()->ip());
        $package['notify_url'] = $notifyUrl;
        $package['trade_type'] = 'NATIVE';
        $package['product_id'] = $ordernum;
        ksort($package, SORT_STRING);
        $string1 = '';
        foreach ($package as $key => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $string1 .= "{$key}={$v}&";
        }
        $string1 .= 'key=' . $mchkey;
        $package['sign'] = strtoupper(md5($string1));
        $dat = function_exists('array2xml') ? array2xml($package) : '';
        if ($dat === '') {
            return ['status' => 0, 'msg' => '微信支付参数组装失败'];
        }
        $response = function_exists('request_post')
            ? request_post('https://api.mch.weixin.qq.com/pay/unifiedorder', $dat)
            : '';
        $xml = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            Log::error('digitalavatar wx native empty: ' . substr(strval($response), 0, 500));
            return ['status' => 0, 'msg' => '微信支付接口无响应'];
        }
        if (strval($xml->return_code) === 'FAIL') {
            return ['status' => 0, 'msg' => strval($xml->return_msg)];
        }
        if (strval($xml->result_code) === 'FAIL') {
            return ['status' => 0, 'msg' => strval($xml->err_code_des)];
        }
        $codeUrl = strval($xml->code_url);
        if ($codeUrl === '') {
            return ['status' => 0, 'msg' => '未返回微信支付二维码'];
        }
        return ['status' => 1, 'code_url' => $codeUrl];
    }
}
