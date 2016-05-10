<?php

header('HTTP/1.1 404 Not Found');
echo '<html><head><style>body{background:#555;color:#fff;font-family:sans-serif;text-align:center;margin:3em;}</style></head><body>';
echo '<h1>Not Found</h1><p>The requested URI '.htmlspecialchars($_SERVER['REQUEST_URI']).' was not found on this server.</p>';
echo '</body></html>';
