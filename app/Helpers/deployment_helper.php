<?php

/** Content-versioned local assets can be cached without hiding later edits. */
function app_static_url(string $path): string
{
    static $versions = [];
    $path = ltrim(explode('?', $path, 2)[0], '/');
    if (! preg_match('#^(?:assets/[\w./-]+|favicon\.ico)$#', $path) || str_contains($path, '..')) {
        return base_url($path);
    }
    if (! array_key_exists($path, $versions)) {
        $file = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $versions[$path] = is_file($file) ? substr(hash_file('sha256', $file), 0, 12) : null;
    }

    return base_url($path) . ($versions[$path] ? '?v=' . $versions[$path] : '');
}

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
