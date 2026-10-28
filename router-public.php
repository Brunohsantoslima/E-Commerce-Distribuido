<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? str_replace('\\', '/', rawurldecode($requestPath)) : '/';

if (preg_match('~(?:^|/)(?:cache|queue)(?:/|$)~i', $requestPath)
    || preg_match('~(?:^|/)sync\.php$~i', $requestPath)) {
    http_response_code(404);
    exit;
}

return false;