<section class="page-title reveal">
  <div><span class="kicker">TEAMS</span><h1>球队</h1></div>
  <form class="search-box" method="get">
    <input name="q" value="<?= e($q) ?>" placeholder="搜索球队、国家代码">
    <button class="primary-button" type="submit">搜索</button>
  </form>
</section>

<section class="card-grid reveal">
  <?php foreach ($teams as $team): ?>
    <a class="team-card" href="<?= e(url('/teams/' . $team['slug'])) ?>">
      <div class="flag-large"><?= flag_html($team['flag_url'] ?? null, $team['flag_emoji'] ?? null, $team['name_cn'] ?: $team['name_original']) ?></div>
      <h2><?= e(display_name($team['name_cn'], $team['name_original'])) ?></h2>
      <p><?= e($team['confederation'] ?: '赛区待同步') ?> · <?= e($team['group_name'] ?: '分组待同步') ?></p>
      <div class="meta-row">
        <span><?= e($team['code'] ?: '--') ?></span>
        <span><?= e((string) ($team['player_count'] ?? 0)) ?> 名球员</span>
      </div>
    </a>
  <?php endforeach; ?>
  <?php if (!$teams): ?><div class="data-empty wide">没有匹配球队。请先运行赛事/球员同步。</div><?php endif; ?>
</section>
