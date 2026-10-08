<?php
define('FCPATH', __DIR__ . '/../../public/');
function base_url(string $path = ''): string { return 'https://example.test/shop/' . $path; }
require __DIR__ . '/../../app/Helpers/deployment_helper.php';
$path = 'assets/css/page-navigation.css';
$expected = 'https://example.test/shop/' . $path . '?v=' . substr(hash_file('sha256', FCPATH . $path), 0, 12);
if (app_static_url($path . '?v=old') !== $expected || app_static_url($path) !== $expected) throw new RuntimeException('Asset version mismatch.');
if (app_static_url('assets/missing.css') !== 'https://example.test/shop/assets/missing.css') throw new RuntimeException('Missing file fallback failed.');
if (app_asset_url('https://images.example.test/photo.jpg') !== 'https://images.example.test/photo.jpg') throw new RuntimeException('External image URL changed.');
echo "Static asset version checks passed.\n";
