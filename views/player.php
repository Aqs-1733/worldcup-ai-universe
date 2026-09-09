<section class="detail-hero reveal">
  <div class="flag-hero"><?= e($player['flag_emoji'] ?: '★') ?></div>
  <div>
    <span class="kicker"><?= e($player['team_code'] ?: 'PLAYER') ?></span>
    <h1><?= e(display_name($player['name_cn'], $player['name_original'])) ?></h1>
    <p><?= e(($player['team_name_cn'] ?: $player['team_name_original'] ?: '球队待同步') . ' · ' . ($player['position'] ?: '位置待同步') . ' · ' . ($player['club'] ?: '俱乐部待同步')) ?></p>
    <div class="tag-row">
      <span>号码 <?= e($player['shirt_number'] ?: '-') ?></span>
      <span>出场 <?= e($player['caps'] ?? '-') ?></span>
      <span>进球 <?= e($player['goals'] ?? '-') ?></span>
      <span>知名度 <?= e($player['popularity_score']) ?></span>
    </div>
  </div>
</section>

<section class="panel reveal">
  <div class="section-head"><div><span class="kicker">SOURCE</span><h2>数据来源</h2></div></div>
  <?php if ($player['source_url']): ?>
    <a class="source-link" href="<?= e($player['source_url']) ?>" target="_blank" rel="noreferrer"><?= e($player['source_url']) ?></a>
  <?php else: ?>
    <div class="data-empty">来源待同步。</div>
  <?php endif; ?>
</section>
