<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session('is_logged_in')) {
            $path = '/' . ltrim($request->getUri()->getPath(), '/');
            $query = $request->getUri()->getQuery();
            $redirect = $query === '' ? $path : $path . '?' . $query;

            return redirect()
                ->to('/login?redirect=' . rawurlencode($redirect))
                ->with('error', 'Please log in to continue.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
