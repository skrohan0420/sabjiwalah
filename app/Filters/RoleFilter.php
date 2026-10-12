<?php
namespace App\Filters;
use App\Services\AuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $role = $arguments[0] ?? 'customer';
        if (AuthService::forRole($role)->check()) return null;
        $login = $role === 'customer' ? 'login' : $role . '/login';
        $path = $request->getUri()->getPath();
        $base = (string) parse_url(base_url(), PHP_URL_PATH);
        if ($base !== '/' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
        $path = '/' . ltrim(preg_replace('#^/?index\.php/#', '', $path), '/');
        return redirect()->to(site_url($login) . '?redirect=' . rawurlencode($path))->with('error', 'Please log in to continue.');
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) { return null; }
}
