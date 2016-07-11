<?php

require_once(__DIR__.'/../global.php');

header('HTTP/1.1 404 Not Found');

$skinfile = App::getSkinPath().'+404.php';
if(file_exists($skinfile)) {
	include($skinfile);
	App::halt();
}

echo '<html><head><style>body{background:#555;color:#fff;font-family:sans-serif;text-align:center;margin:3em;}</style></head><body>';
echo '<h1>Not Found</h1><p>The requested URI '.htmlspecialchars($_SERVER['REQUEST_URI']).' was not found on this server.</p>';
echo '</body></html>';
