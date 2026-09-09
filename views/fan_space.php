<section class="page-title reveal">
  <div><span class="kicker">FAN SPACE</span><h1>球迷空间</h1></div>
</section>

<section class="setup-panel reveal">
  <form method="post">
    <?= csrf_field() ?>
    <div class="section-head"><div><span class="kicker">PREFERENCES</span><h2>我的偏好</h2></div></div>
    <?php $selectedTeams = $selected['teams'] ?? []; $selectedPlayers = $selected['players'] ?? []; $blockedTeams = $selected['blocked_teams'] ?? []; $prefs = $selected['prefs'] ?? []; ?>
    <h3>支持球队</h3>
    <div class="select-grid">
      <?php foreach ($teams as $team): ?>
        <label><input type="checkbox" name="favorite_teams[]" value="<?= e($team['id']) ?>" <?= in_array((int) $team['id'], $selectedTeams, true) ? 'checked' : '' ?>><span><?= team_name_html($team['flag_url'] ?? null, $team['flag_emoji'] ?? null, $team['name_cn'], $team['name_original']) ?></span></label>
      <?php endforeach; ?>
    </div>
    <h3>支持球员</h3>
    <input class="wide-input" data-filter-input data-filter-target="#fanspace-players" placeholder="搜索球员">
    <div class="select-grid tall" id="fanspace-players">
      <?php foreach ($players as $player): ?>
        <label data-filter-text="<?= e(display_name($player['name_cn'], $player['name_original']) . ' ' . ($player['team_name_cn'] ?? '') . ' ' . ($player['team_name_original'] ?? '')) ?>">
          <input type="checkbox" name="favorite_players[]" value="<?= e($player['id']) ?>" <?= in_array((int) $player['id'], $selectedPlayers, true) ? 'checked' : '' ?>>
          <span><?= flag_html($player['flag_url'] ?? null, $player['flag_emoji'] ?? null, $player['team_name_cn'] ?? '') ?><?= e(display_name($player['name_cn'], $player['name_original'])) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <h3>屏蔽球队消息</h3>
    <div class="select-grid">
      <?php foreach ($teams as $team): ?>
        <label><input type="checkbox" name="blocked_teams[]" value="<?= e($team['id']) ?>" <?= in_array((int) $team['id'], $blockedTeams, true) ? 'checked' : '' ?>><span><?= team_name_html($team['flag_url'] ?? null, $team['flag_emoji'] ?? null, $team['name_cn'], $team['name_original']) ?></span></label>
      <?php endforeach; ?>
    </div>
    <h3>推送内容</h3>
    <div class="select-grid compact-select">
      <?php foreach (['match' => '比赛内容', 'tactics' => '战术分析', 'team' => '球队动态', 'entertainment' => '娱乐内容', 'fanmade' => '二创内容'] as $key => $label): ?>
        <?php $field = 'push_' . ($key === 'match' ? 'match' : $key); ?>
        <label><input type="checkbox" name="push[<?= e($key) ?>]" <?= (int) ($prefs[$field] ?? 1) ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
      <?php endforeach; ?>
    </div>
    <button class="primary-button" type="submit">保存</button>
  </form>
</section>

<section class="panel reveal">
  <div class="section-head"><div><span class="kicker">COMMENTS</span><h2>留言</h2></div></div>
  <form class="comment-form" method="post">
    <?= csrf_field() ?>
    <textarea name="comment_body" required placeholder="写一条留言"></textarea>
    <button class="primary-button" type="submit">发布</button>
  </form>
  <?php foreach ($comments as $comment): ?>
    <article class="comment"><b><?= e($comment['display_name'] ?: $comment['username']) ?></b><p><?= e($comment['body']) ?></p><small><?= e(date_label($comment['created_at'])) ?></small></article>
  <?php endforeach; ?>
</section>
