<section class="empty-state">
  <div class="kicker">SETUP</div>
  <h1>运行环境未就绪</h1>
  <p><?= e($error->getMessage()) ?></p>
  <div class="panel" style="text-align:left; margin-top:1rem;">
    <h2>现在要做的事</h2>
    <p>浏览器出现连接被拒绝时，表示 PHP 服务没启动；如果看到本页，表示 PHP 已启动，但 MySQL 或配置还没准备好。</p>
    <pre><code>cd D:\worldcup-ai-universe
notepad .env
php scripts/install.php
php -S 127.0.0.1:8080 -t public public/index.php</code></pre>
    <p>如果你用 XAMPP 的 PHP，可把命令里的 <code>php</code> 换成 <code>D:\XAMPP\php\php.exe</code>。</p>
  </div>
</section>
