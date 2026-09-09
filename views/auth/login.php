<section class="auth-shell reveal">
  <form class="auth-card" method="post">
    <?= csrf_field() ?>
    <span class="kicker">LOGIN</span>
    <h1>账号登录</h1>
    <label>用户名或邮箱<input name="username" required autocomplete="username"></label>
    <label>密码<input name="password" type="password" required autocomplete="current-password"></label>
    <button class="primary-button full" type="submit">登录</button>
    <p>还没有账号？<a href="<?= e(url('/register')) ?>">去注册</a></p>
  </form>
</section>
