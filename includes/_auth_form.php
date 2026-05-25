<?php
/* _auth_form.php — shared auth form used by both mobile and desktop boxes in index.php */
$err       = isset($error) ? $error : '';
$activeTab = isset($activeTab) ? $activeTab : 'login';
?>

<!-- Tabs -->
<div class="lp-tabs" role="tablist">
  <button class="lp-tab <?= $activeTab==='login'?'active':'' ?>"
          data-tab="login" onclick="switchTab('login')" type="button" role="tab">
    Sign In
  </button>
  <button class="lp-tab <?= $activeTab==='register'?'active':'' ?>"
          data-tab="register" onclick="switchTab('register')" type="button" role="tab">
    Create Account
  </button>
</div>

<!-- Error message -->
<?php if ($err): ?>
<div class="lp-error">
  <span>⚠️</span><span><?= htmlspecialchars($err, ENT_QUOTES) ?></span>
</div>
<?php endif; ?>

<!-- ── LOGIN ── -->
<form class="lp-form <?= $activeTab==='login'?'active':'' ?>" id="form-login" method="POST" novalidate>
  <input type="hidden" name="action" value="login">

  <div class="lp-field">
    <label class="lp-label" for="login_email">Email Address</label>
    <input class="lp-input" type="email" id="login_email" name="email"
           placeholder="you@email.com" autocomplete="email"
           inputmode="email" required>
  </div>

  <div class="lp-field">
    <label class="lp-label" for="login_pass">Password</label>
    <input class="lp-input" type="password" id="login_pass" name="password"
           placeholder="Your password" autocomplete="current-password" required>
  </div>

  <button type="submit" class="lp-btn">
    <span class="btn-text">Sign In →</span>
    <span class="btn-spinner">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
      </svg>
    </span>
  </button>

  <p class="lp-switch">
    Don't have an account?
    <a href="#" onclick="switchTab('register');return false">Create one free</a>
  </p>
</form>

<!-- ── REGISTER ── -->
<form class="lp-form <?= $activeTab==='register'?'active':'' ?>" id="form-register" method="POST" novalidate>
  <input type="hidden" name="action" value="register">

  <div class="lp-field">
    <label class="lp-label" for="reg_name">Your Name</label>
    <input class="lp-input" type="text" id="reg_name" name="name"
           placeholder="e.g. Maria Santos"
           autocomplete="name" autocapitalize="words" required>
  </div>

  <div class="lp-field">
    <label class="lp-label" for="reg_email">Email Address</label>
    <input class="lp-input" type="email" id="reg_email" name="email"
           placeholder="you@email.com"
           autocomplete="email" inputmode="email" required>
  </div>

  <div class="lp-field">
    <label class="lp-label" for="reg_pass">Password</label>
    <input class="lp-input" type="password" id="reg_pass" name="password"
           placeholder="Min. 6 characters"
           autocomplete="new-password" required
           minlength="6">
  </div>

  <div class="lp-field">
    <label class="lp-label" for="reg_level">Your English Level</label>
    <select class="lp-input" id="reg_level" name="english_level">
      <option value="beginner">🌱 Beginner — I'm just starting</option>
      <option value="intermediate">📖 Intermediate — I know basics</option>
      <option value="advanced">🚀 Advanced — I want to be fluent</option>
    </select>
  </div>

  <button type="submit" class="lp-btn">
    <span class="btn-text">Start Learning Free →</span>
    <span class="btn-spinner">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
      </svg>
    </span>
  </button>

  <p class="lp-switch" style="font-size:12px;">
    Already have an account?
    <a href="#" onclick="switchTab('login');return false">Sign in</a>
  </p>
</form>
