<?php

// Router for PHP's built-in server (local demo only): serve real files from
// public/ directly, send everything else through Laravel.
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if ($path !== '/' && ! str_contains($path, '..') && is_file(__DIR__.'/../public'.$path)) {
    return false;
}
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';
require __DIR__.'/../public/index.php';
