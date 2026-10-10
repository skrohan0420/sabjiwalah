<?php
namespace App\Services;
use App\Models\PromotionModel;
class PromotionService
{
    public function home(): array
    {
        $now = date('Y-m-d H:i:s');
        $rows = (new PromotionModel())->where('is_active', 1)->where('position', 'home')
            ->groupStart()->where('starts_at', null)->orWhere('starts_at <=', $now)->groupEnd()
            ->groupStart()->where('ends_at', null)->orWhere('ends_at >', $now)->groupEnd()
            ->orderBy('id', 'DESC')->findAll(8);
        $locations = new CampaignLocationService(); $items = [];
        foreach ($rows as $row) {
            // Legacy rows receive the same URL validation as newly created promotions.
            if (($row['link'] && !$locations->safe($row['link'])) || ($row['image'] && !$locations->safe($row['image']))) continue;
            $items[] = ['uid' => $row['uid'], 'title' => $row['title'], 'image' => $row['image'], 'link' => $row['link'] ?: '/products'];
        }
        return $items;
    }
}
