<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
class SettingsController extends BaseController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        return view('admin/settings',['pageTitle'=>'Settings','activeNav'=>'settings']);
    }
}
