<article class="article-detail reveal">
  <a class="mini-link" href="<?= e(url('/news')) ?>">返回新闻</a>
  <div class="source-badge"><?= e($article['source_name']) ?></div>
  <h1><?= e($article['title_cn'] ?: '待翻译：' . $article['title_original']) ?></h1>
  <div class="meta-row">
    <span><?= e($article['tag_names'] ?: '未分类') ?></span>
    <span>可信度 <?= e($article['credibility_score']) ?>%</span>
    <span><?= e(date_label($article['published_at'] ?: $article['fetched_at'])) ?></span>
  </div>
  <section>
    <h2>中文归纳</h2>
    <p><?= nl2br(e($article['content_cn'] ?: $article['summary_cn'] ?: '该来源没有提供可翻译正文，或尚未配置 ARK 翻译模型。')) ?></p>
  </section>
  <section>
    <h2>原文版本</h2>
    <p><?= nl2br(e($article['content_original'] ?: $article['summary_original'] ?: '来源未在 RSS 中提供正文。')) ?></p>
  </section>
  <a class="primary-button" href="<?= e($article['source_url']) ?>" target="_blank" rel="noreferrer">打开来源</a>
</article>
