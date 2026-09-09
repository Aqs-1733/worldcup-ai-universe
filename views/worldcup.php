<section class="page-title reveal">
  <div><span class="kicker">WORLD CUP</span><h1>赛程与积分</h1></div>
  <a class="ghost-button" href="<?= e(url('/news')) ?>">查看新闻</a>
</section>

<section class="panel reveal">
  <div class="section-head"><div><span class="kicker">BRACKET</span><h2>对战图</h2></div><span class="source-chip">不预测未赛比赛</span></div>
  <?php
    $stageOrder = ['32强', '16强', '四分之一决赛', '半决赛', '季军赛', '决赛'];
    $knockoutGroups = array_fill_keys($stageOrder, []);
    foreach ($matches as $match) {
        if (isset($knockoutGroups[$match['stage']])) {
            $knockoutGroups[$match['stage']][] = $match;
        }
    }
  ?>
  <div class="world-bracket bracket-rounds">
    <?php foreach ($knockoutGroups as $stage => $stageMatches): ?>
      <div class="bracket-round">
        <h3><?= e($stage) ?></h3>
        <?php foreach ($stageMatches as $match): ?>
          <article class="bracket-match <?= e($match['status']) ?>">
            <small><?= e(date_label($match['starts_at'])) ?></small>
            <div><span><?= team_name_html($match['home_flag_url'] ?? null, $match['home_flag'] ?? null, $match['home_name_cn'] ?? null, $match['home_team_name'] ?: ($match['home_name_original'] ?? null)) ?></span><b><?= $match['home_score'] === null ? '-' : e($match['home_score']) ?></b></div>
            <div><span><?= team_name_html($match['away_flag_url'] ?? null, $match['away_flag'] ?? null, $match['away_name_cn'] ?? null, $match['away_team_name'] ?: ($match['away_name_original'] ?? null)) ?></span><b><?= $match['away_score'] === null ? '-' : e($match['away_score']) ?></b></div>
          </article>
        <?php endforeach; ?>
        <?php if (!$stageMatches): ?><div class="bracket-slot is-empty"></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$matches): ?><div class="data-empty wide">同步赛事后显示真实赛程和结果。</div><?php endif; ?>
  </div>
</section>

<section class="panel reveal">
  <div class="section-head"><div><span class="kicker">MATCHES</span><h2>比赛列表</h2></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>时间</th><th>阶段</th><th>主队</th><th>比分</th><th>客队</th><th>状态</th><th>来源</th></tr></thead>
      <tbody>
      <?php foreach ($matches as $match): ?>
        <tr>
          <td><?= e(date_label($match['starts_at'])) ?></td>
          <td><?= e($match['stage']) ?></td>
          <td><?= team_name_html($match['home_flag_url'] ?? null, $match['home_flag'] ?? null, $match['home_name_cn'] ?? null, $match['home_team_name'] ?: ($match['home_name_original'] ?? null)) ?></td>
          <td><?= $match['home_score'] === null ? '未赛' : e($match['home_score'] . ' : ' . $match['away_score']) ?></td>
          <td><?= team_name_html($match['away_flag_url'] ?? null, $match['away_flag'] ?? null, $match['away_name_cn'] ?? null, $match['away_team_name'] ?: ($match['away_name_original'] ?? null)) ?></td>
          <td><?= e($match['status']) ?></td>
          <td><?php if ($match['source_url']): ?><a href="<?= e($match['source_url']) ?>" target="_blank" rel="noreferrer">来源</a><?php else: ?>-<?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="panel reveal">
  <div class="section-head"><div><span class="kicker">STANDINGS</span><h2>积分榜</h2></div></div>
  <?php foreach ($standings as $group => $rows): ?>
    <h3 class="group-title"><?= e($group) ?> 组</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>球队</th><th>赛</th><th>胜</th><th>平</th><th>负</th><th>进</th><th>失</th><th>净</th><th>分</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= team_name_html($row['flag_url'] ?? null, $row['flag_emoji'] ?? null, $row['name_cn'] ?? null, $row['name_original'] ?? null) ?></td>
            <td><?= e($row['played']) ?></td><td><?= e($row['won']) ?></td><td><?= e($row['drawn']) ?></td><td><?= e($row['lost']) ?></td>
            <td><?= e($row['goals_for']) ?></td><td><?= e($row['goals_against']) ?></td><td><?= e($row['goal_difference']) ?></td><td><b><?= e($row['points']) ?></b></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
  <?php if (!$standings): ?><div class="data-empty">同步积分榜后显示。</div><?php endif; ?>
</section>
