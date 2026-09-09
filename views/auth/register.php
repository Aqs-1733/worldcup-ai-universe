<section class="auth-shell reveal">
  <form class="auth-card" method="post">
    <?= csrf_field() ?>
    <span class="kicker">REGISTER</span>
    <h1>注册账号</h1>
    <label>用户名<input name="username" required pattern="[A-Za-z0-9_]{3,40}" autocomplete="username"></label>
    <label>邮箱<input name="email" type="email" required autocomplete="email"></label>
    <label>昵称<input name="display_name" autocomplete="nickname"></label>
    <label>密码<input name="password" type="password" required minlength="6" autocomplete="new-password"></label>
    <button class="primary-button full" type="submit">注册并进入问卷</button>
    <p>已有账号？<a href="<?= e(url('/login')) ?>">直接登录</a></p>
  </form>
</section>
