<?php
/**
 * 修改时间
 * 2023/03/03 18:10:14
 *
 * 微乐源码QQ 718147916
 **/
return [
	'model' => 'email',
	'stmp' => 'smtp.exmail.qq.com',
	'user' => '',
	'pass' => '',
	'smgtpl' => '<table width="500" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 50px auto;border-radius: 3px; overflow: hidden; font-size: 14px; color:#333; background-color: #ffffff; box-shadow: 0 0 5px rgba(0,0,0,.2);">
   	<thead>
   		<tr>
   			<td style="height: 60px;border-radius: 3px 3px 0 0;overflow: hidden;text-align: center;vertical-align: middle;font-size: 16px;color: #2a96d8;border-bottom: 1px solid #2a96d8;">
				<table width="500" cellspacing="0" cellpadding="0" border="0">
                    <tbody>
                        <tr>
                            <td width="105" height="60" align="center" style="padding-left: 15px;" >
								<a href="http://test.365shouka.com/" target="_blank" rel="noopener">
									<img src="http://test.365shouka.com/uploads/20201211/753f8a71228316de88056183559686a0.png" width="105" height="30" border="0" style="display: block; margin: auto;">
								</a>
							</td>
                            <td width="380" align="right" style="padding-right: 20px; font-size: 15px;">
                               	<a style=" text-decoration: none; font-size:15px; margin-right: 20px;" href="http://test.365shouka.com/" target="_blank" rel="noopener">首页</a>
                                <a style="text-decoration: none; font-size:15px;" href="href="http://test.365shouka.com/home/card.html" target="_blank" rel="noopener">卡券寄售</a>
							</td>
                        </tr>
                    </tbody>
                </table>  			
   			</td>
   		</tr>
   	</thead>
    <tbody>
       	<tr>
            <td style="padding: 30px 40px; width: 420px;">
				<table width="420" cellspacing="0" cellpadding="0" border="0" align="center">
					<tbody>
						<tr>
							<td style="background-color:#fff;text-align:left;padding-bottom: 25px;">
								<p style="font-size: 15px; margin: 10px 0; color:#333;font-weight:bold; ">亲爱的的收卡用户：</p>
								<p style="font-size: 14px; margin: 10px 0; color: #666;">您正在进行邮箱操作，您的验证码为：</p>
								<p style="font-size: 24px; margin: 0; color: #000; text-align: center; ">{code}</p>
								<p style="font-size: 14px; margin: 10px 0; color: #666;">此邮件内容5分钟内有效，请及时输入。</p>
							</td>
						</tr>
						<tr>
							<td style="border-top: 1px solid #eee; padding-top: 25px;">
								<p style="font-size:12px;color:#666; line-height: 1.5;">
									您之所以收到这封邮件，是因为您已经是收卡的用户；<br>
									我们不会以系统维护，订单异常等为由，要求您点击链接或其他方式进行退款操作或提供个人信息；<br>
									在回收中遇到任何问题，请点击 <a href="http://test.365shouka.com/home/help_index.html" target="_blank" rel="noopener"> 联系我们</a> 联系客服咨询；<br>
									更多请查看<a target="_blank" style="font-size:12px;color:#06c;" href=http://www.365shouka.com/home/help_index.html" rel="noopener">天天收卡帮助中心</a>，我们给您提供了多种渠道，方便您解决问题。联系电话：0571-87801558
								</p>
							</td>
						</tr>
					</tbody>
				</table>
            </td>
        </tr>
        <tr>
            <td style="background-color: #f9f9f9; text-align: center; font-size: 12px; padding: 15px 0; overflow: hidden; border-radius: 0 0 3px 3px;">
				<a style="color: #999; font-size:12px; margin-right: 15px; text-decoration: none;" href="http://www.365shouka.com/home/card.html?cid=33" target="_blank" rel="noopener">电商卡回收</a>
				<a style="color: #999; font-size:12px; margin-right: 15px; text-decoration: none;" href="http://www.365shouka.com/home/card.html?cid=34" target="_blank" rel="noopener">游戏卡回收</a>
				<a style="color: #999; font-size:12px; margin-right: 15px; text-decoration: none;" href="http://www.365shouka.com/home/card.html?cid=32" target="_blank" rel="noopener">话费卡回收</a>
				<a style="color: #999; font-size:12px; margin-right: 15px; text-decoration: none;" href="http://www.365shouka.com/home/card.html?cid=35" target="_blank" rel="noopener">美食出行</a>
				<a style="color: #999; font-size:12px; text-decoration: none;" href="http://www.365shouka.com/home/card.html?cid=36" target="_blank" rel="noopener">加油卡回收</a>
            </td>
        </tr>
    </tbody>
</table>
',
	'type' => '0',
	'duankou' => '465',
	'login' => '<div style="width:600px;margin: 0 auto;"><table width="600" border="0" align="center" cellpadding="0" cellspacing="0" bgcolor="#fefcfa" style="border-radius:5px; overflow:hidden; border-top:4px solid #00c3b6; border-right:1px solid #dbd1ce; border-bottom:1px solid #dbd1ce; border-left:1px solid #dbd1ce;font-family:微软雅黑;"><tbody><tr><td><table width="600" border="0" align="center" cellpadding="0" cellspacing="0" height="48"><tbody><tr><td width="74" height="35" border="0" align="center" valign="middle" style="padding-left:20px;"><a href="http://{url}" target="_blank" style="text-decoration: none;" rel="noopener"><span style="vertical-align:middle;font-size:20px;line-height:32px;white-space:nowrap;">{title}</span></a></td><td width="580" height="48" colspan="2" align="right" valign="middle" style="color:#333; padding-right:20px;font-size:14px;font-family:微软雅黑"><a style="padding:0 10px;text-decoration:none;" target="_blank" href="https://{url}" rel="noopener">首页</a></td></tr></tbody></table></td></tr><tr><td><div style="padding:10px 20px;font-size:14px;color:#333333;border-top:1px solid #dbd1ce;font-family:微软雅黑"><p>你好！你的后台登陆验证码！</p><p>请确定是您自己的登录，以防别人攻击！登录信息如下：</p><p>验证码：{code}</p><p></p><p>登录时间：<span style="border-bottom:1px dashed #ccc;" t="5" times=" 22:38">{dt}</span> {dh}</p><p></p><p>登录IP：{ip}</p><p></p><p style="padding:10px 0;margin-top:30px;margin-bottom:0;color:#a8979a;font-size:12px;border-top:1px dashed #dbd1ce;">此为系统邮件请勿回复<span style="float:right">{title}</span></p></div></td></tr></tbody></table></div>',
];