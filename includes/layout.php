<?php
/** قطعات لایه‌بندی مشترک (head / toast / scripts) */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';

function page_head(string $title, string $desc = '', array $extraCss = []): void
{
    $full = $title ? ($title . ' · ' . APP_NAME) : (APP_NAME . ' · ' . APP_TAGLINE);
    $desc = $desc ?: APP_TAGLINE . ' — ' . APP_OWNER;
    ?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($full) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <meta name="theme-color" content="#0c1512">
  <meta property="og:title" content="<?= e($full) ?>">
  <meta property="og:description" content="<?= e($desc) ?>">
  <meta property="og:type" content="website">
  <link rel="icon" href="<?= url('favicon.ico') ?>" sizes="any">
  <link rel="icon" href="<?= asset('icons/favicon-64.png') ?>" type="image/png" sizes="64x64">
  <link rel="apple-touch-icon" href="<?= asset('icons/icon-180.png') ?>">
  <link rel="manifest" href="<?= url('manifest.php') ?>">
  <meta name="sw-url" content="<?= url('sw.js') ?>">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/persian_datepicker.css') ?>">
  <?php foreach ($extraCss as $c): ?>
  <link rel="stylesheet" href="<?= asset('css/' . $c) ?>">
  <?php endforeach; ?>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
  <meta name="csrf-token" content="<?= function_exists('csrf_token') ? e(csrf_token()) : '' ?>">
  <script>window.MADAR_ICON='<?= asset('icons/icon-192.png') ?>';window.MADAR_BADGE='<?= asset('icons/favicon-64.png') ?>';</script>
</head>
<body><?php
}

function logo_svg(int $size = 38): string
{
    return '<img class="brand-logo-img" src="' . asset('img/logo.png') . '" alt="' . e(APP_NAME) . '" width="' . $size . '" height="' . $size . '">';
}

function brand_block(): string
{
    return '<a href="' . url('') . '" class="brand">' . logo_svg(40)
        . '<span><span class="b-name gradient-text">' . e(APP_NAME) . '</span>'
        . '<span class="b-sub">STUDY OS</span></span></a>';
}

function page_foot(array $extraJs = []): void
{
    ?>
  <div id="toast-wrap"></div>
  <script src="<?= asset('js/app.js') ?>"></script>
  <script src="<?= asset('js/persian_datepicker.js') ?>"></script>
  <?php foreach ($extraJs as $j): ?>
  <script src="<?= asset('js/' . $j) ?>"></script>
  <?php endforeach; ?>
</body>
</html><?php
}
