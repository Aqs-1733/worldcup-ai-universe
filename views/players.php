<section class="page-title reveal">
  <div><span class="kicker">PLAYERS</span><h1>球员</h1></div>
  <form class="search-box" method="get">
    <input name="q" value="<?= e($q) ?>" placeholder="搜索球员中文名、原名、俱乐部">
    <button class="primary-button" type="submit">搜索</button>
  </form>
</section>

<section class="card-grid player-cards reveal">
  <?php foreach ($players as $player): ?>
    <a class="player-card" href="<?= e(url('/players/' . $player['slug'])) ?>">
      <span class="shirt"><?= e($player['shirt_number'] ?: '★') ?></span>
      <h2><?= e(display_name($player['name_cn'], $player['name_original'])) ?></h2>
      <p><?= e(($player['flag_emoji'] ?: '') . ' ' . ($player['team_name_cn'] ?: $player['team_name_original'] ?: '球队待同步')) ?></p>
      <div class="meta-row">
        <span><?= e($player['position'] ?: '位置待同步') ?></span>
        <span><?= e($player['club'] ?: '俱乐部待同步') ?></span>
      </div>
    </a>
  <?php endforeach; ?>
  <?php if (!$players): ?><div class="data-empty wide">没有匹配球员。请先运行球员同步。</div><?php endif; ?>
</section>
