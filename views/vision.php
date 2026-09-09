<section class="page-title reveal">
  <div><span class="kicker">VISION</span><h1>视觉分析</h1></div>
</section>

<section class="dashboard-grid">
  <form class="panel reveal" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="section-head"><div><span class="kicker">UPLOAD</span><h2>上传图片</h2></div></div>
    <input type="file" name="image" accept="image/*" required>
    <button class="primary-button full" type="submit">分析</button>
  </form>
  <div class="panel reveal">
    <div class="section-head"><div><span class="kicker">RESULT</span><h2>分析结果</h2></div></div>
    <?php if (!empty($error)): ?>
      <div class="alert-box"><?= e($error) ?></div>
    <?php elseif (!empty($analysis)): ?>
      <p><?= e($analysis) ?></p>
      <?php if (!empty($uploadedPath)): ?><img class="generated-image" src="<?= e($uploadedPath) ?>" alt="上传图片"><?php endif; ?>
    <?php else: ?>
      <div class="data-empty">上传世界杯相关图片后显示记录。</div>
    <?php endif; ?>
  </div>
</section>
