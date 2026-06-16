<?php
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$base = BASE_URL;
echo json_encode([
  'name' => APP_NAME . ' · ' . APP_TAGLINE,
  'short_name' => APP_NAME,
  'description' => APP_TAGLINE,
  'lang' => 'fa',
  'dir' => 'rtl',
  'start_url' => $base . '/',
  'scope' => $base . '/',
  'display' => 'standalone',
  'orientation' => 'portrait',
  'background_color' => '#0c1512',
  'theme_color' => '#0c1512',
  'icons' => [
    ['src'=>$base.'/assets/icons/icon-192.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable'],
    ['src'=>$base.'/assets/icons/icon-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'any maskable'],
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
