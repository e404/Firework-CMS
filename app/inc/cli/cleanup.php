#!/usr/bin/php5
<?php

if(PHP_SAPI!=='cli') die('Error: Only CLI access allowed.');

chdir(realpath(__DIR__.'/../../'));

// Load App class
require_once('cls/System/App.php');

// Initialize Autoloader
App::initAutoloader();

// Load config
Config::load('config.ini');

// Establish DB connection
$db = MysqlDb::getInstance();
$db->connect(Config::get('mysql','host',true),Config::get('mysql','user',true),Config::get('mysql','pass',true),Config::get('mysql','db',true));
$db->query('SET NAMES UTF8');

App::cleanup();
