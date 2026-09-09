<section class="hero-grid reveal">
  <div class="hero-panel">
    <div class="kicker">WORLD CUP 2026</div>
    <h1><span>世界杯</span>赛事中控台</h1>
    <div class="hero-stats">
      <a href="<?= e(url('/teams')) ?>"><strong><?= e($counts['teams'] ?? 0) ?></strong><span>球队</span></a>
      <a href="<?= e(url('/players')) ?>"><strong><?= e($counts['players'] ?? 0) ?></strong><span>球员</span></a>
      <a href="<?= e(url('/worldcup')) ?>"><strong><?= e($counts['matches'] ?? 0) ?></strong><span>赛程</span></a>
      <a href="<?= e(url('/news')) ?>"><strong><?= e($counts['news_articles'] ?? 0) ?></strong><span>新闻</span></a>
    </div>
    <div class="hero-actions">
      <a class="primary-button" href="<?= e(url('/worldcup')) ?>">看赛程</a>
      <a class="ghost-button" href="<?= e(url('/ai-studio')) ?>">AI 创作</a>
    </div>
  </div>

  <div class="pitch-card">
    <div class="section-head compact">
      <div>
        <span class="kicker">KNOCKOUT</span>
        <h2>晋级关系</h2>
      </div>
      <span class="source-chip">按比赛库更新</span>
    </div>
    <div class="bracket-mini">
      <?php
        $knockouts = array_values(array_filter($matches, static fn ($m) => !in_array($m['stage'], ['Group', 'group', '小组赛'], true)));
        $preview = array_slice($knockouts ?: $matches, 0, 8);
      ?>
      <?php if (!$preview): ?>
        <div class="data-empty">同步赛事后显示真实对战关系</div>
      <?php else: ?>
        <?php foreach ($preview as $match): ?>
          <a class="bracket-node <?= e($match['status']) ?>" href="<?= e(url('/worldcup')) ?>">
            <span><?= team_name_html($match['home_flag_url'] ?? null, $match['home_flag'] ?? null, $match['home_name_cn'] ?? null, $match['home_team_name'] ?: ($match['home_name_original'] ?? null)) ?></span>
            <b><?= $match['home_score'] === null ? '-' : e($match['home_score']) ?></b>
            <span><?= team_name_html($match['away_flag_url'] ?? null, $match['away_flag'] ?? null, $match['away_name_cn'] ?? null, $match['away_team_name'] ?: ($match['away_name_original'] ?? null)) ?></span>
            <b><?= $match['away_score'] === null ? '-' : e($match['away_score']) ?></b>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="ticker reveal" aria-label="滚动新闻">
  <span>LIVE</span>
  <div>
    <?php foreach (array_slice($news, 0, 6) as $article): ?>
      <a href="<?= e(url('/news/' . $article['id'])) ?>"><?= e($article['title_cn'] ?: $article['title_original']) ?></a>
    <?php endforeach; ?>
    <?php if (!$news): ?><a href="<?= e(url('/news')) ?>">同步新闻后显示实时中文摘要</a><?php endif; ?>
  </div>
</section>

<?php if (!empty($qualityChecks)): ?>
<section class="panel reveal">
  <div class="section-head">
    <div><span class="kicker">DATA CHECK</span><h2>真实数据校验</h2></div>
    <span class="source-chip"><?= e(($counts['source_files'] ?? 0) . ' 个来源文件') ?></span>
  </div>
  <div class="quality-grid">
    <?php foreach ($qualityChecks as $check): ?>
      <div class="quality-chip <?= e($check['status']) ?>">
        <strong><?= e($check['label']) ?></strong>
        <span><?= e($check['actual_value']) ?> / <?= e($check['expected_value']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="dashboard-grid">
  <div class="panel reveal">
    <div class="section-head">
      <div><span class="kicker">SCHEDULE</span><h2>近期赛程</h2></div>
      <a class="mini-link" href="<?= e(url('/worldcup')) ?>">全部</a>
    </div>
    <div class="match-list">
      <?php foreach (array_slice($matches, 0, 6) as $match): ?>
        <article class="match-row">
          <time><?= e(date_label($match['starts_at'])) ?></time>
          <div class="versus">
            <span><?= team_name_html($match['home_flag_url'] ?? null, $match['home_flag'] ?? null, $match['home_name_cn'] ?? null, $match['home_team_name'] ?: ($match['home_name_original'] ?? null)) ?></span>
            <b><?= $match['home_score'] === null ? 'vs' : e($match['home_score'] . ':' . $match['away_score']) ?></b>
            <span><?= team_name_html($match['away_flag_url'] ?? null, $match['away_flag'] ?? null, $match['away_name_cn'] ?? null, $match['away_team_name'] ?: ($match['away_name_original'] ?? null)) ?></span>
          </div>
          <small><?= e($match['stage']) ?> · <?= e($match['status']) ?></small>
        </article>
      <?php endforeach; ?>
      <?php if (!$matches): ?><div class="data-empty">还没有赛事数据，运行同步脚本后显示。</div><?php endif; ?>
    </div>
  </div>

  <div class="panel reveal">
    <div class="section-head">
      <div><span class="kicker">CHART</span><h2>数据图形</h2></div>
      <span class="source-chip">MySQL</span>
    </div>
    <div class="bar-chart" data-bars>
      <?php foreach (array_slice($standings ? array_merge(...array_values($standings)) : [], 0, 8) as $row): ?>
        <?php $width = min(100, max(8, (int) $row['points'] * 8)); ?>
        <div class="bar-line">
          <span><?= team_name_html($row['flag_url'] ?? null, $row['flag_emoji'] ?? null, $row['name_cn'] ?? null, $row['name_original'] ?? null) ?></span>
          <i style="--w: <?= e((string) $width) ?>%"></i>
          <b><?= e($row['points']) ?></b>
        </div>
      <?php endforeach; ?>
      <?php if (!$standings): ?><div class="data-empty">同步积分榜后生成动态图表。</div><?php endif; ?>
    </div>
  </div>
</section>

<section class="module-grid reveal">
  <?php
    $modules = [
      ['/teams', '球队档案', '国旗、教练、分组、球员名单'],
      ['/players', '球员库', '中文名 / 原名，按知名度排序'],
      ['/news', '新闻中心', '多来源抓取、中文归纳、来源标注'],
      ['/fan-space', '球迷空间', '支持球队、支持球员、屏蔽球队'],
      ['/ai-studio', 'AI 创作', '语气自定义，勾选生成图片'],
      ['/admin', '后台管理', '编辑资料、发布内容、维护数据'],
    ];
  ?>
  <?php foreach ($modules as [$link, $name, $desc]): ?>
    <a class="module-card" href="<?= e(url($link)) ?>">
      <span><?= e($name) ?></span>
      <small><?= e($desc) ?></small>
    </a>
  <?php endforeach; ?>
</section>

<section class="dashboard-grid">
  <div class="panel reveal">
    <div class="section-head">
      <div><span class="kicker">TEAMS</span><h2>球队</h2></div>
      <a class="mini-link" href="<?= e(url('/teams')) ?>">全部</a>
    </div>
    <div class="chip-grid">
      <?php foreach ($teams as $team): ?>
        <a class="team-chip" href="<?= e(url('/teams/' . $team['slug'])) ?>">
          <?= team_name_html($team['flag_url'] ?? null, $team['flag_emoji'] ?? null, $team['name_cn'], $team['name_original']) ?>
        </a>
      <?php endforeach; ?>
      <?php if (!$teams): ?><div class="data-empty">同步球队后显示。</div><?php endif; ?>
    </div>
  </div>

  <div class="panel reveal">
    <div class="section-head">
      <div><span class="kicker">PLAYERS</span><h2>热门球员</h2></div>
      <a class="mini-link" href="<?= e(url('/players')) ?>">全部</a>
    </div>
    <div class="player-stack">
      <?php foreach ($players as $player): ?>
        <a class="player-row" href="<?= e(url('/players/' . $player['slug'])) ?>">
          <?= flag_html($player['flag_url'] ?? null, $player['flag_emoji'] ?? null, $player['team_name_cn'] ?? '') ?>
          <b><?= e(display_name($player['name_cn'], $player['name_original'])) ?></b>
          <small><?= e($player['position'] ?: '位置待同步') ?> · <?= e($player['club'] ?: '俱乐部待同步') ?></small>
        </a>
      <?php endforeach; ?>
      <?php if (!$players): ?><div class="data-empty">同步球员后显示。</div><?php endif; ?>
    </div>
  </div>
</section>

<section class="panel reveal">
  <div class="section-head">
    <div><span class="kicker">TEAM</span><h2>项目团队</h2></div>
    <?php if (\App\Core\Auth::isAdmin()): ?><a class="mini-link" href="<?= e(url('/admin/team_members')) ?>">编辑</a><?php endif; ?>
  </div>
  <div class="member-grid">
    <?php foreach ($members as $member): ?>
      <article class="member-card">
        <strong><?= e($member['name']) ?></strong>
        <span><?= e($member['role_name']) ?></span>
        <p><?= e($member['bio'] ?: '后台可补充个人展示。') ?></p>
      </article>
    <?php endforeach; ?>
    <?php if (!$members): ?><div class="data-empty">后台录入团队成员后显示。</div><?php endif; ?>
  </div>
</section>

<section class="panel reveal">
  <div class="section-head">
    <div><span class="kicker">POSTS</span><h2>发布内容</h2></div>
    <?php if (\App\Core\Auth::isAdmin()): ?><a class="mini-link" href="<?= e(url('/admin/admin_posts')) ?>">发布</a><?php endif; ?>
  </div>
  <div class="news-list">
    <?php foreach ($posts as $post): ?>
      <article class="news-card">
        <div class="source-badge"><?= e($post['post_type']) ?></div>
        <h2><?= e($post['title']) ?></h2>
        <p><?= nl2br(e(mb_strimwidth((string) $post['body'], 0, 180, '...'))) ?></p>
        <div class="meta-row"><span><?= e($post['display_name'] ?: $post['username'] ?: '管理员') ?></span><span><?= e(date_label($post['published_at'] ?: $post['created_at'])) ?></span></div>
      </article>
    <?php endforeach; ?>
    <?php if (!$posts): ?><div class="data-empty">后台发布内容后显示。</div><?php endif; ?>
  </div>
</section>
