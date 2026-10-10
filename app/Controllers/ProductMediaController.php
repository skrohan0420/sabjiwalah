<?php

namespace App\Controllers;

class ProductMediaController extends BaseController
{
    public function show(string $name)
    {
        if (! preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D', $name, $match)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $path = WRITEPATH . 'uploads/products/' . $name;
        if (! is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $type = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$match[1]];
        return $this->response->setHeader('X-Content-Type-Options', 'nosniff')
            ->removeHeader('Cache-Control')->setHeader('Cache-Control', 'public, max-age=86400')->setContentType($type)
            ->setBody(file_get_contents($path));
    }
}
