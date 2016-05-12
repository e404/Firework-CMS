SET NAMES utf8mb4;

CREATE TABLE `affiliateclicks` (
  `affid` char(6) NOT NULL DEFAULT '',
  `t` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip` varchar(64) DEFAULT NULL,
  KEY `affid` (`affid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `affiliates` (
  `affid` char(6) NOT NULL DEFAULT '',
  `secret` char(32) NOT NULL DEFAULT '',
  `company` varchar(255) DEFAULT NULL,
  `commission` decimal(5,2) NOT NULL,
  `gender` char(1) NOT NULL DEFAULT '',
  `firstname` varchar(255) NOT NULL DEFAULT '',
  `lastname` varchar(255) NOT NULL DEFAULT '',
  `country` char(2) CHARACTER SET ascii NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  `postalcode` varchar(32) NOT NULL DEFAULT '',
  `street` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `t` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paypal` varchar(255) NOT NULL DEFAULT '',
  `active` tinyint(1) unsigned DEFAULT NULL,
  `sum_month` decimal(7,2) DEFAULT NULL,
  `sum_total` decimal(10,2) DEFAULT NULL,
  `count_month` int(11) unsigned DEFAULT NULL,
  `count_total` int(11) unsigned DEFAULT NULL,
  `clicks_total` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`affid`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `commissions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `affid` varchar(20) NOT NULL DEFAULT '',
  `uid` bigint(14) unsigned zerofill NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `amount` decimal(5,2) NOT NULL,
  `cleared` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `affid` (`affid`),
  KEY `cleared` (`cleared`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `emails` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `t` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `recipient` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `install` (
  `uid` bigint(14) unsigned zerofill NOT NULL,
  `user` varchar(32) NOT NULL DEFAULT '',
  `domain` varchar(255) NOT NULL DEFAULT '',
  `sandbox` tinyint(1) unsigned DEFAULT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `invoices` (
  `number` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint(14) unsigned zerofill NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `total` decimal(5,2) NOT NULL,
  PRIMARY KEY (`number`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `jobs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `job` enum('erase','install','extend') CHARACTER SET ascii NOT NULL,
  `params` text,
  `sandboxed` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `job` (`job`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `links` (
  `id` char(8) NOT NULL DEFAULT '',
  `url` varchar(255) NOT NULL DEFAULT '',
  `desc` varchar(255) NOT NULL DEFAULT '',
  `context` varchar(255) DEFAULT NULL,
  `value` text,
  `expires` timestamp NULL DEFAULT NULL,
  `t_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `t_action` timestamp NULL DEFAULT NULL,
  `actions_count` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii;

CREATE TABLE `paymenttickets` (
  `tid` char(6) CHARACTER SET ascii NOT NULL DEFAULT '',
  `uid` bigint(14) unsigned zerofill NOT NULL,
  `amount` decimal(5,2) NOT NULL,
  `ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `job` enum('install','extend') CHARACTER SET ascii NOT NULL,
  `affid` char(6) DEFAULT NULL,
  `params` text,
  `payed` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `payed_ts` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tid`),
  UNIQUE KEY `uid` (`uid`),
  KEY `payed` (`payed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sessions` (
  `sid` char(40) NOT NULL DEFAULT '',
  `ip` varchar(64) DEFAULT NULL,
  `t` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii;

CREATE TABLE `sessionstore` (
  `sid` char(40) CHARACTER SET ascii NOT NULL DEFAULT '',
  `key` varchar(255) CHARACTER SET ascii NOT NULL DEFAULT '',
  `value` text NOT NULL,
  PRIMARY KEY (`sid`,`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `uid` bigint(14) unsigned zerofill NOT NULL,
  `lang` char(2) NOT NULL DEFAULT '',
  `gender` char(1) NOT NULL DEFAULT '',
  `firstname` varchar(255) NOT NULL DEFAULT '',
  `lastname` varchar(255) NOT NULL DEFAULT '',
  `company` varchar(255) DEFAULT NULL,
  `country` char(2) CHARACTER SET ascii NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  `postalcode` varchar(32) NOT NULL DEFAULT '',
  `street` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `businessemail` varchar(255) NOT NULL DEFAULT '',
  `passhash` char(64) NOT NULL DEFAULT '',
  `t` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sitename` varchar(255) NOT NULL DEFAULT '',
  `sld` varchar(200) NOT NULL DEFAULT '',
  `tld` varchar(16) NOT NULL DEFAULT '',
  `domain` varchar(255) NOT NULL DEFAULT '',
  `industry` varchar(16) DEFAULT NULL,
  `mailing` tinyint(1) unsigned DEFAULT NULL,
  `privatekey` char(64) CHARACTER SET ascii NOT NULL DEFAULT '',
  `active` tinyint(1) unsigned DEFAULT NULL,
  `expires` date DEFAULT NULL,
  `price` decimal(5,2) NOT NULL DEFAULT '100.00',
  `brought_customer` tinyint(1) unsigned DEFAULT NULL,
  `domain_mode` enum('transfer','none') DEFAULT NULL,
  `domain_authcode` varchar(255) DEFAULT NULL,
  `status_install` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `status_domain` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `reminder` char(2) CHARACTER SET ascii DEFAULT NULL,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `domain` (`domain`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
