<?php

/** Resolve stored asset paths while preserving external image URLs. */
function app_asset_url(?string $path): string
{
    $path = (string) $path;
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $path)) {
        return $path;
    }

    $basePath = parse_url(base_url(), PHP_URL_PATH) ?: '/';
    if ($basePath !== '/' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }

    return base_url(ltrim($path, '/'));
}
