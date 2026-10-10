<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
class CashController extends BaseController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        return view('admin/cash',['pageTitle'=>'COD reconciliation','activeNav'=>'cash']);
    }
}
