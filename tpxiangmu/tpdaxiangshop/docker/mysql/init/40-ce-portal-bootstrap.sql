-- community-ce: digitalavatar portal local bootstrap
SET NAMES utf8mb4;

-- site must not be expired (ApiBase checks admin.endtime)
UPDATE `ddwx_admin`
SET `status`=1, `endtime`=UNIX_TIMESTAMP()+86400*3650
WHERE `id`=1;

-- bind local preview host so SPA can omit ?aid= when opened at 127.0.0.1
UPDATE `ddwx_digitalavatar_setting`
SET `portal_domain`='127.0.0.1'
WHERE `aid`=1;

INSERT INTO `ddwx_digitalavatar_setting` (`aid`,`product_title`,`enabled`,`portal_domain`,`createtime`,`updatetime`)
SELECT 1,'AI数字人工作台',1,'127.0.0.1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ddwx_digitalavatar_setting` WHERE `aid`=1);

UPDATE `ddwx_digitalavatar_setting` SET `portal_domain`='127.0.0.1' WHERE `aid`=1;

-- demo member: 13800138000 / 123456 (md5)
INSERT INTO `ddwx_member` (
  `aid`,`tel`,`pwd`,`nickname`,`createtime`,`last_visittime`,`checkst`,
  `money`,`total_fenhong`,`total_fenhong_team`,`total_fenhong_level_team`,`total_fenhong_partner`,`total_fenhong_area`,
  `scorehuang`,`scoreA`,`scoreB`,`duihuanfen`,`cyz`,`heiscore`,`contribution_num`,`suo1jiang`,`suo2jiang`,`jiangcount`,`zijifh`,`fxfhc`,`syc`,`score3`,`tuozhanfei`,`temp_frozen_money`
)
SELECT 1,'13800138000','e10adc3949ba59abbe56e057f20f883e','CE演示用户',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),1,
  0,0,0,0,0,0, 0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ddwx_member` WHERE `aid`=1 AND `tel`='13800138000');
