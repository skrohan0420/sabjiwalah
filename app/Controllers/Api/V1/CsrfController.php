<?php

namespace App\Controllers\Api\V1;

use Config\Security;

class CsrfController extends BaseApiController
{
    public function show()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $security = config(Security::class);

        return $this->success([
            'token_name'  => csrf_token(),
            'token_value' => csrf_hash(),
            'header_name' => $security->headerName,
        ], 'Send this token with state-changing AJAX requests.');
    }
}
