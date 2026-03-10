<?php

declare(strict_types=1);

$allowed = [
    'js'  => ['pb-main.js', 'pb-main.min.js'],
    'css' => ['pb-main.css', 'pb-main.min.css'],
];

$type = $_GET['type'] ?? null;
$file = $_GET['file'] ?? null;
$pkg = $_GET['pkg'] ?? null;

if (!isset($allowed[$type]) || !in_array($file, $allowed[$type], true)) {
    http_response_code(404);
    exit;
}

$mime = $type === 'js' ? 'application/javascript' : 'text/css';

$filePath = __DIR__ . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $file;

if (!is_readable($filePath)) {
    http_response_code(404);
    exit('Not found');
}

$maxAge = 604800;
$lastModified = filemtime($filePath);
$etag = md5_file($filePath);

header("Content-Type: $mime; charset=UTF-8");
header("Cache-Control: public, max-age=$maxAge");
header("ETag: $etag");
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
$ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

if ($ifNoneMatch === $etag || strtotime($ifModifiedSince) >= $lastModified) {
    http_response_code(304);
    exit;
}

readfile($filePath);
