<section class="detail-hero reveal">
  <div class="flag-hero"><?= flag_html($team['flag_url'] ?? null, $team['flag_emoji'] ?? null, $team['name_cn'] ?: $team['name_original']) ?></div>
  <div>
    <span class="kicker"><?= e($team['code'] ?: 'TEAM') ?></span>
    <h1><?= e(display_name($team['name_cn'], $team['name_original'])) ?></h1>
    <p><?= e($team['profile'] ?: '球队简介尚未同步，后台可人工补充，来源字段会保留。') ?></p>
    <div class="tag-row">
      <span><?= e($team['confederation'] ?: '赛区待同步') ?></span>
      <span><?= e($team['group_name'] ?: '分组待同步') ?></span>
      <span>主教练：<?= e($team['coach_name'] ?: '待同步') ?></span>
    </div>
  </div>
</section>

<section class="panel reveal">
  <div class="section-head"><div><span class="kicker">SQUAD</span><h2>球员名单</h2></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>号码</th><th>球员</th><th>位置</th><th>俱乐部</th><th>出场</th><th>进球</th></tr></thead>
      <tbody>
      <?php foreach ($players as $player): ?>
        <tr>
          <td><?= e($player['shirt_number'] ?: '-') ?></td>
          <td><a href="<?= e(url('/players/' . $player['slug'])) ?>"><?= e(display_name($player['name_cn'], $player['name_original'])) ?></a></td>
          <td><?= e($player['position'] ?: '-') ?></td>
          <td><?= e($player['club'] ?: '-') ?></td>
          <td><?= e($player['caps'] ?? '-') ?></td>
          <td><?= e($player['goals'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$players): ?><div class="data-empty">该队球员尚未同步。</div><?php endif; ?>
</section>

<section class="panel reveal">
  <div class="section-head"><div><span class="kicker">COMMENTS</span><h2>球队留言</h2></div></div>
  <?php foreach ($comments as $comment): ?>
    <article class="comment"><b><?= e($comment['display_name'] ?: $comment['username']) ?></b><p><?= e($comment['body']) ?></p></article>
  <?php endforeach; ?>
  <?php if (!$comments): ?><div class="data-empty">还没有留言。</div><?php endif; ?>
</section>
