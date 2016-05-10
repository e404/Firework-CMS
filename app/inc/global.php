<?php

// Set working directory to application base dir
chdir(__DIR__.'/../');

// Load App class
require_once('cls/System/App.php');

// Initialize Autoloader
App::initAutoloader();

// Set up cache
if(PHP_SAPI!=='cli') {
	Cache::setDirectory('../cache');
}

// Load config
Config::load('../config.ini');

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
