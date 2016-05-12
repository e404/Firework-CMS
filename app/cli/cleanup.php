#!/usr/bin/php5
<?php

// Ensure CLI access only
if(PHP_SAPI!=='cli') die('Error: Only CLI access allowed.');

// Load environment
chdir(__DIR__);
require_once('../inc/global.php');

// Call App cleanup
App::cleanup();
