#!/usr/bin/php5
<?php

// Ensure CLI access only
if(PHP_SAPI!=='cli') die('Error: Only CLI access allowed.');

// Load environment
chdir(__DIR__);
require_once('../global.php');

// Call App cron (triggers 'cron' hooks)
App::cron(isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : null);
