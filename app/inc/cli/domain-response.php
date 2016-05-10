#!/usr/bin/php5
<?php

chdir(__DIR__);
require('../global.php');

$mail = '';
while(!feof(STDIN)) $mail.= fgets(STDIN);

if(!Install::processDomainResponse($mail)) {
	$logfile = 'inc/cli/log/domainresponse-'.date('Y-m-d-H:i:s').'-'.substr(md5($mail),0,6);
	Error::warning('Domain response handling failed. See log file: '.$logfile);
	file_put_contents($logfile, $mail);
}
