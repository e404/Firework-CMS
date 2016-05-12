<?php

// Dir handling
chdir(__DIR__.'/../');
$app_dir = getcwd().'/';

// Load App class
require_once('./cls/System/App.php');

// Change working directory to one level above app
chdir(__DIR__.'/../../');
$base_dir = getcwd().'/';

// Set app directory
App::setAppDir(substr($app_dir,strlen($base_dir)));

// Initialize Autoloader
App::initAutoloader();

// Set up cache
if(PHP_SAPI!=='cli') {
	Cache::setDirectory('cache');
}

// Check if app has already been set up
if(!file_exists('config.ini') || !is_dir('pages')) {
	require(App::getAppDir().'inc/install/install.php');
	die();
}

// Load config
Config::load('config.ini');

// Check app installation status
if(!Config::get('env', 'host')) {
	require(App::getAppDir().'inc/install/install.php');
	die();
}

// Debugging
if(Config::get('debug')) {
	Error::setMode('debug');
}else{
	Error::setMode('production');
}

// Establish DB connection
$db = MysqlDb::getInstance();
$db->connect(Config::get('mysql','host',true),Config::get('mysql','user',true),Config::get('mysql','pass',true),Config::get('mysql','db',true));

// Initialize Application
App::init();
