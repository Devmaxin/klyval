<form class="auth-card" method="post" action="/admin/login" novalidate>
  <?php View::partial('logo') ?>
  <h1 class="h3">Sign in</h1>
  <?php if (!empty($error)): ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif ?>
  <?= csrf_field() ?>
  <label class="field"><span>Email</span><input type="email" name="email" autocomplete="username" required autofocus></label>
  <label class="field"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
  <button class="btn btn--primary" type="submit"><span class="btn__label">Sign in</span><span class="btn__arrow" aria-hidden="true">→</span></button>
</form>
