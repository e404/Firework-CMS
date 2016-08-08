<?php

require_once(__DIR__.'/../global.php');

header('HTTP/1.1 403 Forbidden');

$skinfile = Config::get('dirs', 'pages').'/+403.php';
if(file_exists($skinfile)) {
	include($skinfile);
	App::halt();
}

echo '<html><head><style>body{background:#555;color:#fff;font-family:sans-serif;text-align:center;margin:3em;}</style></head><body>';
echo '<h1>Access Denied</h1><p>You don\'t have permission to access '.htmlspecialchars($_SERVER['REQUEST_URI']).' on this server.</p>';
echo '</body></html>';
