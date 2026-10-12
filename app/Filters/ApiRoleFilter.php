<?php
namespace App\Filters;
use App\Services\AuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class ApiRoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $role = $arguments[0] ?? 'customer';
        if (AuthService::forRole($role)->check()) return null;
        $status = 401;
        foreach (AuthService::ROLES as $other) if (AuthService::forRole($other)->check()) $status = 403;
        return service('response')->setStatusCode($status)->setJSON(['success' => false, 'data' => null, 'message' => 'Unauthenticated']);
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) { return null; }
}
