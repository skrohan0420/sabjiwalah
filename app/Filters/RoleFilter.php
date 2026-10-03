<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
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

        $allowedRoles = $arguments ?? [];

        if ($allowedRoles !== [] && ! in_array(session('user_role'), $allowedRoles, true)) {
            return redirect()->to('/')->with('error', 'You do not have access to that area.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
