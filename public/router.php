<?php

$publicDirectory = realpath(__DIR__);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($publicDirectory !== false && is_string($requestPath)) {
    $requestedFile = realpath($publicDirectory.DIRECTORY_SEPARATOR.ltrim(rawurldecode($requestPath), '/'));

    if (
        $requestedFile !== false
        && str_starts_with($requestedFile, $publicDirectory.DIRECTORY_SEPARATOR)
        && is_file($requestedFile)
        && strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION)) !== 'php'
    ) {
        return false;
    }
}

require __DIR__.'/index.php';
