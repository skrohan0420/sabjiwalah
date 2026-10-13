<?php
namespace App\Controllers\Admin;

// Preserve saved links to the former Dispatch page.
class DispatchController extends \App\Controllers\BaseController
{
    public function index()
    {
        $query=http_build_query($this->request->getGet());
        return redirect()->to(site_url('admin/live-map').($query!==''?'?'.$query:''));
    }
}
