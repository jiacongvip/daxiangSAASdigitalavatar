<?php
return [
    // 公钥文件
    'publicKeyPath'  =>  '../cert/68888TS121248.cer',
    // 私钥文件
    'privateKeyPath' =>  '../cert/68888TS121248.pfx',
    // 私钥证书密码
    'privateKeyPwd'  =>  '123456',
        // 接口地址
//    'apiUrl'         =>  'https://cashier.sandpay.com.cn',
    'apiUrl'         =>  'https://smp-uat01.sand.com.cn',

    'variable' =>[
        // 商户号
        'mid'      =>  '68888TS121248',
        // 产品id https://open.sandpay.com.cn/product/detail/43305/43667/
        'productId'      =>  '00000016',
        // 接入类型  1-普通商户接入 2-平台商户接入 3-核心企业商户接入
        'accessType'     =>  '1',
        // 渠道类型  07-互联网  08-移动端
        'channelType'     =>  '07',
        // 平台ID accessType为2时必填，在担保支付模式下填写核心商户号
        'plMid'          =>  '',
    ],
]; 

