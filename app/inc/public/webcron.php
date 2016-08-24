<?php

// Run this script even if the browser closes the connection
ignore_user_abort(true);

require_once('../global.php');
ob_end_clean();

// With zero content length the browser stops listening for further data
header('Content-Encoding: none');
header('Content-Length: 0');
header('Access-Control-Allow-Origin: '.Config::get('env', 'protocol').App::getHost());
header('Access-Control-Allow-Methods: GET, OPTIONS');

// Flush the output to send the headers
ob_end_flush(); // This is required for flush() to work due to a strange bug in some versions of PHP
flush();

if($_SERVER['REQUEST_METHOD']==='OPTIONS') {
	App::halt();
}

// ----
// Everything below this line gets executed without the browser waiting for any output.
// This script could take forever and leaves the user agent in a perfect state.

// Now run the webcron script
App::webcron();