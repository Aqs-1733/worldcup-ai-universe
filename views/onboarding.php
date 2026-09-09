<section class="setup-panel reveal">
  <form method="post">
    <?= csrf_field() ?>
    <div class="section-head">
      <div><span class="kicker">FIRST SETUP</span><h1>选择世界杯偏好</h1></div>
      <button class="ghost-button" type="submit" name="skip" value="1">跳过</button>
    </div>

    <h2>支持球队</h2>
    <div class="select-grid">
      <?php foreach ($teams as $team): ?>
        <label><input type="checkbox" name="favorite_teams[]" value="<?= e($team['id']) ?>"><span><?= team_name_html($team['flag_url'] ?? null, $team['flag_emoji'] ?? null, $team['name_cn'], $team['name_original']) ?></span></label>
      <?php endforeach; ?>
    </div>

    <h2>支持球员</h2>
    <input class="wide-input" data-filter-input data-filter-target="#onboarding-players" placeholder="搜索球员">
    <div class="select-grid tall" id="onboarding-players">
      <?php foreach ($players as $player): ?>
        <label data-filter-text="<?= e(display_name($player['name_cn'], $player['name_original']) . ' ' . ($player['team_name_cn'] ?? '') . ' ' . ($player['team_name_original'] ?? '')) ?>">
          <input type="checkbox" name="favorite_players[]" value="<?= e($player['id']) ?>">
          <span><?= flag_html($player['flag_url'] ?? null, $player['flag_emoji'] ?? null, $player['team_name_cn'] ?? '') ?><?= e(display_name($player['name_cn'], $player['name_original'])) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <h2>屏蔽球队消息</h2>
    <div class="select-grid">
      <?php foreach ($teams as $team): ?>
        <label><input type="checkbox" name="blocked_teams[]" value="<?= e($team['id']) ?>"><span><?= team_name_html($team['flag_url'] ?? null, $team['flag_emoji'] ?? null, $team['name_cn'], $team['name_original']) ?></span></label>
      <?php endforeach; ?>
    </div>

    <h2>推送内容</h2>
    <div class="select-grid compact-select">
      <?php foreach (['match' => '比赛内容', 'tactics' => '战术分析', 'team' => '球队动态', 'entertainment' => '娱乐内容', 'fanmade' => '二创内容'] as $key => $label): ?>
        <label><input type="checkbox" name="push[<?= e($key) ?>]" checked><span><?= e($label) ?></span></label>
      <?php endforeach; ?>
    </div>
    <button class="primary-button full" type="submit">保存偏好</button>
  </form>
</section>
