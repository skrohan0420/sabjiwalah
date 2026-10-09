<?php

namespace App\Filters;

use App\Services\ActiveSessionService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ActiveSessionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            (new ActiveSessionService())->validate();
        } catch (\Throwable $exception) {
            log_message('error', 'Session validation failed: {type}', ['type' => get_class($exception)]);
            $response = service('response')->setStatusCode(503)->setHeader('Cache-Control', 'no-store');
            if (str_contains($request->getUri()->getPath(), '/api/')) {
                return $response->setJSON(['success' => false, 'data' => null, 'message' => 'Unable to verify your session. Please try again.']);
            }
            return $response->setContentType('text/plain')->setBody('Unable to verify your session. Please try again.');
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) { return null; }
}
