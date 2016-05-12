#!/usr/bin/php5
<?php

define('LANG_TO', 'en');

##########################################

chdir(realpath(__DIR__).'/../../');

// Load App class
require_once('cls/System/App.php');

// Initialize Autoloader
App::initAutoloader();

// Load config
Config::load('config.ini');

// Initialize translation system
$lang = new Language();
$lang->setBase(Config::get('lang', 'base'));
$lang->setLanguage(LANG_TO);
$lang->setAutoappend(true);

foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.')) as $file) {
	$path = preg_replace('@^\./@','',$file->getPathname());
	if(!preg_match('@\.php$@',$path) || preg_match('@^(lib|cls)/@',$path) || realpath($path)===__FILE__) continue;
	$tnt = file_get_contents($path);
	if(preg_match_all('@\{\{([^\}\t\r\n]+)\}\}@', $tnt, $matches)) {
		echo "$path\n";
		for($i=0; $i<count($matches[0]); $i++) {
			$string = str_replace(array("\\'",'\"'),array("'",'"'),$matches[1][$i]);
			$tnt = str_replace($matches[0][$i], '{{'.$lang->translateString($string).'}}', $tnt);
		}
		$newpath = '../new/'.$path;
		shell_exec('mkdir -p '.dirname($newpath).' 2>/dev/null');
		file_put_contents($newpath, $tnt);
	}
}
