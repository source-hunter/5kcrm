CREATE TABLE IF NOT EXISTS `5kcrm_login_history` (
	  `login_id` int(11) NOT NULL AUTO_INCREMENT,
	  `user_id` int(11) NOT NULL COMMENT '用户id',
	  `login_time` int(11) NOT NULL COMMENT '登录时间',
	  `login_ip` varchar(50) NOT NULL COMMENT '登录ip',
	  `login_status` char(1) NOT NULL COMMENT '登录 1成功   2 失败',
	  PRIMARY KEY (`login_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='用户登录历史表' AUTO_INCREMENT=12 ;

TRUNCATE 5kcrm_praise
ALTER TABLE `5kcrm_praise` ADD PRIMARY KEY (`praise_id`);
ALTER TABLE `5kcrm_praise` CHANGE `praise_id` `praise_id` INT(10) NOT NULL AUTO_INCREMENT;

ALTER TABLE `5kcrm_contacts` CHANGE `qq` `qq_no` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'qq';