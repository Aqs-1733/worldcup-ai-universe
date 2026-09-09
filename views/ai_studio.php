<section class="page-title reveal">
  <div><span class="kicker">AI STUDIO</span><h1>AI 创作</h1></div>
  <div class="tag-row"><span>文本：<?= $textReady ? '已配置' : '未配置' ?></span><span>生图：<?= $imageReady ? '已配置' : '未配置' ?></span></div>
</section>

<section class="dashboard-grid">
  <form class="panel reveal" method="post">
    <?= csrf_field() ?>
    <div class="section-head"><div><span class="kicker">PROMPT</span><h2>创作配置</h2></div></div>
    <label>支持球队或自定义球队<input name="target_team" value="<?= e($targetTeam ?? '') ?>" placeholder="例如：阿根廷、Japan、自己输入也可以"></label>
    <label>语气<input name="tone" value="<?= e($tone ?? '中立、清楚、有球迷感') ?>" placeholder="例如：热血、克制、幽默、官方"></label>
    <label>内容<textarea name="prompt" rows="8" required placeholder="输入战术分析、球迷文案、新闻归纳或创作需求"><?= e($question ?? '') ?></textarea></label>
    <label class="check-line"><input type="checkbox" name="generate_image" value="1"> 同时生成图片</label>
    <label>图片提示词<textarea name="image_prompt" rows="4" placeholder="勾选生图时可单独写画面要求；留空则使用上面的内容"></textarea></label>
    <button class="primary-button full" type="submit">开始生成</button>
  </form>

  <div class="panel reveal">
    <div class="section-head"><div><span class="kicker">OUTPUT</span><h2>结果</h2></div></div>
    <?php if (!empty($error)): ?>
      <div class="alert-box"><?= e($error) ?></div>
    <?php elseif (!empty($result) || !empty($image)): ?>
      <?php if (!empty($result)): ?><div class="ai-output"><?= nl2br(e($result)) ?></div><?php endif; ?>
      <?php $img = $image['url'] ?? $image['b64'] ?? null; ?>
      <?php if ($img): ?><img class="generated-image" src="<?= e($img) ?>" alt="AI 生成图片"><?php endif; ?>
    <?php else: ?>
      <div class="data-empty">输入内容后生成。图片生成会消耗额度，只有勾选时才调用。</div>
    <?php endif; ?>
  </div>
</section>
