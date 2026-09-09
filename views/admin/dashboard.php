<section class="page-title reveal">
  <div><span class="kicker">ADMIN</span><h1>后台管理</h1></div>
</section>

<section class="module-grid reveal">
  <?php foreach ($tables as $table => $label): ?>
    <a class="module-card" href="<?= e(url('/admin/' . $table)) ?>">
      <span><?= e($label) ?></span>
      <small>编辑、发布、维护</small>
    </a>
  <?php endforeach; ?>
</section>

<section class="panel reveal">
  <div class="section-head"><div><span class="kicker">DATABASE</span><h2>数据量</h2></div></div>
  <div class="hero-stats compact-stats">
    <?php foreach ($counts as $name => $count): ?>
      <a><strong><?= e($count) ?></strong><span><?= e($name) ?></span></a>
    <?php endforeach; ?>
  </div>
</section>
