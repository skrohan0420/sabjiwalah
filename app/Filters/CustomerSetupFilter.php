<?php

namespace App\Filters;

use App\Services\CustomerSetup;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CustomerSetupFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session('is_logged_in') || CustomerSetup::complete([
            'role' => session('user_role'), 'name' => session('user_name'),
        ])) {
            return null;
        }

        $path = trim($request->getUri()->getPath(), '/');
        $basePath = trim((string) parse_url(base_url(), PHP_URL_PATH), '/');
        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath) + 1);
        }
        $path = preg_replace('#^index\.php/?#', '', $path);
        // These are the only screens needed to finish setup or end the session.
        if (in_array($path, ['account', 'logout'], true) || str_starts_with($path, 'assets/')) {
            return null;
        }
        if (str_starts_with($path, 'api/')) {
            if (! str_starts_with($path, 'api/v1/checkout/')) {
                return null;
            }

            return service('response')->setStatusCode(409)->setJSON([
                'success' => false, 'data' => null,
                'message' => 'Complete your name and delivery location before checkout.',
            ]);
        }

        return redirect()->to(site_url('account'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
