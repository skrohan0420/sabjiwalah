<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
class CampaignController extends BaseController
{
    public function offers() { return $this->page('offers', 'Offers'); }
    public function promotions() { return $this->page('promotions', 'Promotions'); }
    private function page(string $kind, string $title)
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        return view('admin/campaigns', ['kind' => $kind, 'pageTitle' => $title, 'activeNav' => $kind]);
    }
}
