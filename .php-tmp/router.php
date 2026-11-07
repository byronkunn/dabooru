<?php
// Router for `php -S` pointed at public/. Serves real files; everything else goes to Laravel.
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . '/../public' . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // let the built-in server serve the asset
}
require __DIR__ . '/../public/index.php';
