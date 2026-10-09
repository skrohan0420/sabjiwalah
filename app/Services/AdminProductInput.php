<?php

namespace App\Services;

class AdminProductInput
{
    public static function normalize(array $input, ?array $existing = null): array
    {
        $fields = ['name', 'slug', 'description', 'price', 'sale_price', 'unit', 'stock_quantity', 'is_active'];
        $data = array_intersect_key($input, array_flip($fields));
        if ($existing) $data = array_replace(array_intersect_key($existing, array_flip($fields)), $data);
        foreach (['name', 'slug', 'unit', 'description'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) $data[$field] = trim($data[$field]);
        }
        $data['sale_price'] = ($data['sale_price'] ?? '') === '' ? null : $data['sale_price'];
        if (! array_key_exists('is_active', $data)) $data['is_active'] = 1;
        return $data;
    }

    public static function rules(?int $id = null): array
    {
        return [
            'name' => 'required|max_length[150]',
            'slug' => 'required|max_length[190]|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]|is_unique[products.slug,id,' . ($id ?? 0) . ']',
            'description' => 'permit_empty|max_length[10000]',
            'price' => 'required|regex_match[/^\d{1,8}(?:\.\d{1,2})?$/]|greater_than_equal_to[0]|less_than_equal_to[99999999.99]',
            'sale_price' => 'permit_empty|regex_match[/^\d{1,8}(?:\.\d{1,2})?$/]|greater_than_equal_to[0]|less_than_equal_to[99999999.99]',
            'unit' => 'required|max_length[40]',
            'stock_quantity' => 'required|is_natural|less_than_equal_to[4294967295]',
            'is_active' => 'required|in_list[0,1]',
        ];
    }
}
