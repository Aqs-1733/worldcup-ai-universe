<?php

use App\Core\Auth;

$user = Auth::user();
$flashMessages = flash();
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$nav = [
    '/' => '首页',
    '/worldcup' => '赛程',
    '/teams' => '球队',
    '/players' => '球员',
    '/news' => '新闻',
    '/fan-space' => '球迷空间',
    '/ai-studio' => 'AI 创作',
    '/vision' => '视觉',
];
?>
<!doctype html>
<html lang="zh-CN" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? '世界杯') . ' - ' . app_name()) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
  <div class="field-lights" aria-hidden="true"></div>
  <header class="site-header">
    <a class="brand" href="<?= e(url('/')) ?>">
      <span class="brand-mark">🏆</span>
      <span><?= e(app_name()) ?></span>
    </a>
    <nav class="top-nav" aria-label="主导航">
      <?php foreach ($nav as $path => $label): ?>
        <a class="<?= $current === $path ? 'active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($user && Auth::isAdmin((int) $user['id'])): ?>
        <a class="<?= str_starts_with($current, '/admin') ? 'active' : '' ?>" href="<?= e(url('/admin')) ?>">后台</a>
      <?php endif; ?>
    </nav>
    <div class="header-actions">
      <button class="icon-button" type="button" data-theme-toggle aria-label="切换深浅色">◐</button>
      <?php if ($user): ?>
        <span class="user-pill"><?= e($user['display_name'] ?: $user['username']) ?></span>
        <form method="post" action="<?= e(url('/logout')) ?>">
          <?= csrf_field() ?>
          <button class="ghost-button" type="submit">退出</button>
        </form>
      <?php else: ?>
        <a class="ghost-button" href="<?= e(url('/login')) ?>">登录</a>
        <a class="primary-button" href="<?= e(url('/register')) ?>">注册</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($flashMessages): ?>
    <div class="flash-stack">
      <?php foreach ($flashMessages as $kind => $message): ?>
        <div class="flash <?= e($kind) ?>"><?= e($message) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <main class="page-shell">
    <?= $content ?>
  </main>
</body>
</html>
