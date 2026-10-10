<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Server-rendered pages and APIs contain session-specific state; do not share/cache them. */
class PrivateResponseFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null) { return null; }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Only generated product media has an explicitly public cache policy.
        if (preg_match('#/media/products/[a-f0-9]{48}\.(?:jpg|png|webp)$#D', $request->getUri()->getPath())
            && $response->getStatusCode() === 200
            && str_starts_with($response->getHeaderLine('Content-Type'), 'image/')) return null;

        $response->removeHeader('Cache-Control')->setHeader('Cache-Control', 'private, no-store, max-age=0');
        $response->removeHeader('Pragma')->setHeader('Pragma', 'no-cache');
        return null;
    }
}
