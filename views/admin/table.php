<section class="page-title reveal">
  <div><span class="kicker">ADMIN</span><h1><?= e($label) ?>管理</h1></div>
  <a class="ghost-button" href="<?= e(url('/admin')) ?>">返回后台</a>
</section>

<section class="panel reveal">
  <div class="section-head"><div><span class="kicker">EDIT</span><h2>新增 / 修改</h2></div></div>
  <form class="admin-form" method="post" action="<?= e(url('/admin/' . $table . '/save')) ?>">
    <?= csrf_field() ?>
    <label>ID<input name="id" placeholder="留空为新增"></label>
    <?php
      $fields = [
        'team_members' => ['name' => '姓名', 'student_no' => '学号', 'role_name' => '角色', 'bio' => '简介', 'photo_url' => '照片URL', 'sort_order' => '排序', 'is_visible' => '显示(1/0)'],
        'teams' => ['code' => '代码', 'name_cn' => '中文名', 'name_original' => '原名', 'country_code' => '国家代码', 'flag_emoji' => '国旗', 'confederation' => '赛区', 'group_name' => '分组', 'coach_name' => '教练', 'world_ranking' => '排名', 'profile' => '简介', 'source_url' => '来源'],
        'players' => ['team_id' => '球队ID', 'name_cn' => '中文名', 'name_original' => '原名', 'position' => '位置', 'shirt_number' => '号码', 'birth_date' => '生日', 'age' => '年龄', 'club' => '俱乐部', 'caps' => '出场', 'goals' => '进球', 'height_cm' => '身高', 'photo_url' => '照片URL', 'popularity_score' => '知名度', 'source_url' => '来源'],
        'matches' => ['stage' => '阶段', 'group_name' => '分组', 'home_team_id' => '主队ID', 'away_team_id' => '客队ID', 'home_team_name' => '主队名', 'away_team_name' => '客队名', 'home_score' => '主队比分', 'away_score' => '客队比分', 'status' => '状态', 'starts_at' => '时间', 'source_url' => '来源'],
        'news_articles' => ['source_name' => '来源名', 'source_url' => '来源URL', 'title_cn' => '中文标题', 'title_original' => '原文标题', 'summary_cn' => '中文摘要', 'summary_original' => '原文摘要', 'content_cn' => '中文正文', 'content_original' => '原文正文', 'language_code' => '语言', 'published_at' => '发布时间', 'credibility_score' => '可信度', 'translation_status' => '翻译状态'],
        'admin_posts' => ['title' => '标题', 'body' => '正文', 'post_type' => '类型', 'status' => '状态', 'published_at' => '发布时间'],
        'comments' => ['status' => '状态'],
      ][$table] ?? [];
    ?>
    <?php foreach ($fields as $field => $labelText): ?>
      <label><?= e($labelText) ?>
        <?php if (in_array($field, ['bio', 'profile', 'summary_cn', 'summary_original', 'content_cn', 'content_original', 'body'], true)): ?>
          <textarea name="<?= e($field) ?>" rows="4"></textarea>
        <?php else: ?>
          <input name="<?= e($field) ?>">
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
    <button class="primary-button full" type="submit">保存</button>
  </form>
</section>

<section class="panel reveal">
  <div class="section-head"><div><span class="kicker">ROWS</span><h2>记录</h2></div></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <?php foreach (array_keys($rows[0] ?? ['id' => '']) as $col): ?><th><?= e($col) ?></th><?php endforeach; ?>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <?php foreach ($row as $value): ?><td><?= e(mb_strimwidth((string) $value, 0, 80, '...')) ?></td><?php endforeach; ?>
          <td>
            <form method="post" action="<?= e(url('/admin/' . $table . '/delete')) ?>" onsubmit="return confirm('确认删除？')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= e($row['id'] ?? 0) ?>">
              <button class="danger-button" type="submit">删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$rows): ?><div class="data-empty">暂无记录。</div><?php endif; ?>
</section>
