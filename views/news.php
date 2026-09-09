<section class="page-title reveal" data-news-auto-refresh>
  <div><span class="kicker">NEWS</span><h1>新闻</h1></div>
  <form method="post" action="<?= e(url('/news/refresh')) ?>">
    <?= csrf_field() ?>
    <button class="primary-button" type="submit">联网刷新</button>
  </form>
</section>

<form class="filter-panel reveal" method="get">
  <input name="q" value="<?= e($q) ?>" placeholder="搜索新闻、球队、球员或来源">
  <button class="ghost-button" type="submit">搜索</button>
</form>

<nav class="tag-filter reveal">
  <a class="<?= $activeTag === '' ? 'active' : '' ?>" href="<?= e(url('/news')) ?>">全部</a>
  <?php foreach ($tags as $tag): ?>
    <a class="<?= $activeTag === $tag['slug'] ? 'active' : '' ?>" href="<?= e(url('/news?tag=' . $tag['slug'])) ?>"><?= e($tag['name_cn']) ?></a>
  <?php endforeach; ?>
</nav>

<section class="news-list reveal">
  <?php foreach ($articles as $article): ?>
    <a class="news-card" href="<?= e(url('/news/' . $article['id'])) ?>">
      <div class="source-badge"><?= e($article['source_name']) ?></div>
      <h2><?= e($article['title_cn'] ?: '待翻译：' . $article['title_original']) ?></h2>
      <p><?= e($article['summary_cn'] ?: $article['summary_original'] ?: '来源未提供摘要，点开查看原文链接。') ?></p>
      <div class="meta-row">
        <span><?= e($article['tag_names'] ?: '未分类') ?></span>
        <span>可信度 <?= e($article['credibility_score']) ?>%</span>
        <span><?= e(date_label($article['published_at'] ?: $article['fetched_at'])) ?></span>
      </div>
    </a>
  <?php endforeach; ?>
  <?php if (!$articles): ?><div class="data-empty wide">还没有新闻。点击“联网刷新”从 RSS 来源抓取。</div><?php endif; ?>
</section>
