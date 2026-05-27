<?php
require_once 'config.php';

if (!empty($_SESSION['uid'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error      = '';
$activeTab  = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db     = db();
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $activeTab = 'login';
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if (!$email || !$pass) {
            $error = 'Please fill in all fields.';
        } else {
            $e = esc($email);
            $r = $db->query("SELECT * FROM users WHERE email='$e' LIMIT 1");
            $u = $r ? $r->fetch_assoc() : null;
            if ($u && password_verify($pass, $u['password'])) {
                $_SESSION['uid']  = $u['id'];
                $_SESSION['name'] = $u['name'];
                updateStreak($u['id']);
                header('Location: ' . APP_URL . '/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }

    } elseif ($action === 'register') {
        $activeTab = 'register';
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password']   ?? '';
        $level = $_POST['english_level'] ?? 'beginner';

        if (!$name || !$email || !$pass) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $e      = esc($email);
            $exists = $db->query("SELECT id FROM users WHERE email='$e' LIMIT 1");
            if ($exists && $exists->num_rows > 0) {
                $error = 'An account with that email already exists.';
            } else {
                $hashed  = password_hash($pass, PASSWORD_BCRYPT);
                $n       = esc($name);
                $h       = esc($hashed);
                $l       = esc($level);
                $avatars = ['🧑','👩','🧑‍💻','👩‍💻','🧑‍🎓','👩‍🎓','🦸','🧑‍🏫'];
                $av      = $avatars[array_rand($avatars)];
                $a       = esc($av);
                $db->query("INSERT INTO users (name,email,password,english_level,avatar,last_active) VALUES ('$n','$e','$h','$l','$a',CURDATE())");
                $newId = $db->insert_id;
                $_SESSION['uid']  = $newId;
                $_SESSION['name'] = $name;
                addXP($newId, 50, 'Welcome bonus');
                header('Location: ' . APP_URL . '/dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#070b18">
<title>EnglishMaster AI — Speak Confidently</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/style.css">
<style>
/* ── Landing page layout ── */
body { overflow-x: hidden; }

.lp-wrap {
  min-height: 100vh;
  min-height: 100dvh;
  background: var(--bg-base);
  position: relative;
  overflow: hidden;
}

/* Ambient glow */
.lp-glow {
  position: fixed; inset: 0; pointer-events: none; z-index: 0;
  background:
    radial-gradient(ellipse 70% 50% at 20% 10%, #4f8ef718 0%, transparent 65%),
    radial-gradient(ellipse 50% 40% at 85% 70%, #a78bfa12 0%, transparent 60%),
    radial-gradient(ellipse 40% 30% at 50% 50%, #2dd4bf08 0%, transparent 70%);
}

/* ── TWO-COLUMN desktop ── */
.lp-desktop {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
  max-width: 1080px;
  width: 100%;
  margin: 0 auto;
  padding: 48px 32px;
  min-height: 100vh;
  min-height: 100dvh;
  align-items: center;
  position: relative; z-index: 1;
}

/* ── Hero column ── */
.lp-hero { padding-right: 40px; }
.lp-logo {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 32px;
}
.lp-logo-icon {
  width: 44px; height: 44px;
  background: linear-gradient(135deg, var(--blue), var(--teal));
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 800; font-size: 20px; color: #fff;
  box-shadow: 0 0 24px #4f8ef750; flex-shrink: 0;
}
.lp-logo-name {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 800; font-size: 17px; color: var(--text-1);
}
.lp-h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 48px; line-height: 1.1;
  font-weight: 800; color: var(--text-1);
  margin-bottom: 16px;
}
.lp-sub {
  font-size: 16px; color: var(--text-2);
  line-height: 1.7; margin-bottom: 24px;
  max-width: 420px;
}
.lp-features {
  display: flex; flex-direction: column;
  gap: 9px; margin-bottom: 28px;
}
.lp-feature {
  display: flex; align-items: center;
  gap: 10px; font-size: 14px; color: var(--text-2);
}
.lp-feature-icon { font-size: 17px; flex-shrink: 0; }
.lp-pills {
  display: flex; gap: 8px; flex-wrap: wrap;
}
.lp-pill {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 99px;
  padding: 6px 14px;
  font-size: 12px; color: var(--text-2);
  white-space: nowrap;
}

/* ── Auth box ── */
.lp-form-col {
  display: flex;
  align-items: center;
  justify-content: center;
}
.lp-auth-box {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 36px 32px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 20px 60px #00000050;
}

/* Auth tabs */
.lp-tabs {
  display: flex;
  background: var(--bg-base);
  border-radius: 12px;
  padding: 4px;
  gap: 4px;
  margin-bottom: 28px;
}
.lp-tab {
  flex: 1; text-align: center;
  padding: 10px 8px;
  border-radius: 9px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 700; font-size: 14px;
  color: var(--text-3);
  cursor: pointer;
  border: none; background: none;
  transition: all 0.2s ease;
  min-height: 40px;
}
.lp-tab.active {
  background: var(--blue);
  color: #fff;
  box-shadow: 0 2px 12px #4f8ef750;
}

/* Error box */
.lp-error {
  background: #f8717115;
  border: 1px solid #f8717135;
  border-radius: 10px;
  padding: 11px 14px;
  font-size: 13px;
  color: var(--red);
  margin-bottom: 16px;
  display: flex; align-items: flex-start; gap: 8px;
  line-height: 1.5;
}

/* Form groups */
.lp-form { display: none; }
.lp-form.active { display: block; }

.lp-field { margin-bottom: 14px; }
.lp-label {
  display: block;
  font-size: 12px; font-weight: 700;
  color: var(--text-2);
  text-transform: uppercase; letter-spacing: 0.6px;
  margin-bottom: 7px;
}
.lp-input {
  width: 100%;
  background: var(--bg-base);
  border: 1.5px solid var(--border);
  border-radius: 10px;
  padding: 12px 14px;
  color: var(--text-1);
  font-family: 'DM Sans', sans-serif;
  font-size: 16px; /* ← prevents iOS auto-zoom */
  transition: border-color 0.2s, box-shadow 0.2s;
  -webkit-appearance: none;
  appearance: none;
  outline: none;
  box-sizing: border-box;
}
.lp-input:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px #4f8ef722;
}
.lp-input::placeholder { color: var(--text-3); }
select.lp-input {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%238b9cc8' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  padding-right: 38px;
  cursor: pointer;
}

/* Submit button */
.lp-btn {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, var(--blue), #3a7ee8);
  color: #fff;
  border: none; border-radius: 12px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 700; font-size: 16px;
  cursor: pointer;
  margin-top: 6px;
  transition: all 0.2s ease;
  min-height: 50px;
  box-shadow: 0 4px 20px #4f8ef740;
  position: relative; overflow: hidden;
}
.lp-btn:hover   { transform: translateY(-1px); box-shadow: 0 6px 28px #4f8ef760; }
.lp-btn:active  { transform: translateY(0); }
.lp-btn:disabled {
  opacity: 0.65; pointer-events: none; transform: none;
}
.lp-btn .btn-spinner {
  display: none;
  position: absolute; inset: 0;
  align-items: center; justify-content: center;
  background: inherit; border-radius: inherit;
}
.lp-btn.loading .btn-spinner { display: flex; }
.lp-btn.loading .btn-text    { opacity: 0; }

.lp-switch {
  text-align: center;
  margin-top: 16px;
  font-size: 13px; color: var(--text-3);
}
.lp-switch a { color: var(--blue); font-weight: 600; }

/* ── MOBILE layout: form on top, hero below ── */
.lp-mobile-top  { display: none; }   /* mobile-only top header */
.lp-mobile-hero { display: none; }   /* mini hero below form on mobile */

/* ==========================================
   RESPONSIVE
   ========================================== */

/* Tablet */
@media (max-width: 900px) {
  .lp-desktop { grid-template-columns: 1fr 1fr; padding: 32px 24px; gap: 0; }
  .lp-h1 { font-size: 36px; }
  .lp-hero { padding-right: 24px; }
  .lp-auth-box { padding: 28px 22px; }
}

/* Phone ≤ 768px — single column, form first */
@media (max-width: 768px) {

  .lp-wrap        { overflow-y: auto; }

  /* Hide two-column desktop layout */
  .lp-desktop     { display: none; }

  /* Show mobile layout */
  .lp-mobile-top  { display: block; }
  .lp-mobile-hero { display: block; }

  /* Mobile page wrapper */
  .lp-mobile-wrap {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-height: 100dvh;
    padding: 0;
    position: relative; z-index: 1;
  }

  /* Top sticky header bar */
  .lp-mobile-top {
    position: sticky; top: 0; z-index: 10;
    background: var(--bg-base);
    border-bottom: 1px solid var(--border);
    padding: 14px 20px;
    display: flex; align-items: center; gap: 10px;
    backdrop-filter: blur(12px);
  }
  .lp-mobile-top .lp-logo-icon { width: 34px; height: 34px; font-size: 16px; border-radius: 9px; }
  .lp-mobile-top .lp-logo-name { font-size: 15px; }

  /* Form scrollable area */
  .lp-mobile-form-area {
    flex: 1;
    padding: 24px 16px 16px;
    display: flex;
    flex-direction: column;
    align-items: stretch;
  }

  /* Auth box sits inside the form area */
  .lp-mobile-form-area .lp-auth-box {
    width: 100%;
    max-width: 100%;
    padding: 24px 18px;
    border-radius: 16px;
    box-shadow: none;
  }

  /* Hero teaser below form */
  .lp-mobile-hero {
    padding: 20px 16px 40px;
    border-top: 1px solid var(--border);
    background: var(--bg-card);
  }
  .lp-mobile-hero-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 700; font-size: 15px;
    color: var(--text-2); margin-bottom: 14px;
    text-align: center;
  }
  .lp-mobile-features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .lp-mobile-feature {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 10px;
    text-align: center;
  }
  .lp-mobile-feature .mf-icon { font-size: 22px; display: block; margin-bottom: 5px; }
  .lp-mobile-feature .mf-text { font-size: 12px; color: var(--text-2); line-height: 1.4; }

  /* Pills row  */
  .lp-mobile-pills {
    display: flex; gap: 6px; flex-wrap: wrap;
    justify-content: center; margin-top: 14px;
  }
  .lp-mobile-pills .lp-pill { font-size: 11px; padding: 5px 10px; }
}

/* Small phones ≤ 480px */
@media (max-width: 480px) {
  .lp-mobile-form-area { padding: 16px 12px 12px; }
  .lp-mobile-form-area .lp-auth-box { padding: 18px 14px; border-radius: 14px; }
  .lp-tabs { gap: 3px; padding: 3px; }
  .lp-tab  { font-size: 13px; padding: 9px 6px; min-height: 38px; }
  .lp-h1   { font-size: 22px; }
  .lp-btn  { font-size: 15px; padding: 13px; min-height: 48px; }
  .lp-field { margin-bottom: 12px; }
  .lp-mobile-features { grid-template-columns: 1fr 1fr; gap: 8px; }
}

/* Tiny ≤ 360px */
@media (max-width: 360px) {
  .lp-mobile-form-area { padding: 12px 10px; }
  .lp-mobile-form-area .lp-auth-box { padding: 16px 12px; }
  .lp-label  { font-size: 11px; margin-bottom: 5px; }
  .lp-input  { padding: 10px 12px; font-size: 15px; }
  .lp-btn    { font-size: 14px; padding: 12px; min-height: 46px; }
  .lp-tab    { font-size: 12px; }
  .lp-mobile-features { grid-template-columns: 1fr 1fr; gap: 6px; }
  .lp-mobile-feature .mf-text { font-size: 11px; }
}
</style>
</head>
<body>

<!-- SVG gradient for spinner -->
<svg style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">
  <defs>
    <linearGradient id="emGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%"   stop-color="#4f8ef7"/>
      <stop offset="50%"  stop-color="#2dd4bf"/>
      <stop offset="100%" stop-color="#a78bfa"/>
    </linearGradient>
  </defs>
</svg>

<!-- Full-page spinner loader -->
<div id="em-loader" aria-hidden="true">
  <div class="em-loader-box">
    <div class="em-spinner-wrap">
      <svg class="em-ring" viewBox="0 0 80 80">
        <circle class="em-ring-track" cx="40" cy="40" r="34"/>
        <circle class="em-ring-fill"  cx="40" cy="40" r="34"/>
      </svg>
      <div class="em-loader-logo">E</div>
    </div>
    <div class="em-loader-label" id="emLoaderLabel">Loading...</div>
    <div class="em-loader-dots"><span></span><span></span><span></span></div>
  </div>
</div>

<!-- Top bar -->
<div id="em-topbar"></div>

<script>
/* ── Loader for landing page ── */
const EMLoader = (() => {
  const el = document.getElementById('em-loader');
  const lb = document.getElementById('emLoaderLabel');
  const msgs = ['Loading...', 'Almost there...', 'Please wait...'];
  let t = null, i = 0, vis = false;
  function show(label) {
    if (!el) return;
    i = 0; if (lb) lb.textContent = label || msgs[0];
    el.classList.add('active'); vis = true;
    clearInterval(t);
    t = setInterval(() => { i=(i+1)%msgs.length; if(lb) lb.textContent=msgs[i]; }, 1800);
  }
  function hide() {
    clearInterval(t);
    if (!el) return;
    el.classList.remove('active'); el.classList.add('hiding'); vis = false;
    setTimeout(() => el.classList.remove('hiding'), 420);
  }
  return { show, hide };
})();
window.EMLoader = EMLoader;

/* Top bar */
const EmpBar = (() => {
  const bar = document.getElementById('em-topbar');
  let _w=0,_r=null,_d=false;
  function set(p){ _w=Math.min(p,99); if(bar){bar.style.width=_w+'%';bar.classList.add('active');} }
  function tick(){ if(_d)return; _w+=(_w<20)?7:(_w<50)?3:(_w<80)?1.5:0.4; set(_w); _r=requestAnimationFrame(tick); }
  function start(){ _d=false;_w=0; if(bar){bar.style.transition='none';bar.style.width='0%';bar.style.opacity='1';} requestAnimationFrame(()=>{ if(bar)bar.style.transition='width 0.25s ease'; tick(); }); }
  function done(){ _d=true;cancelAnimationFrame(_r);set(100);setTimeout(()=>{if(bar){bar.style.opacity='0';bar.style.width='0%';}},300); }
  return { start, done };
})();

/* Show on form submit */
document.addEventListener('submit', () => { EMLoader.show(); EmpBar.start(); });
/* Hide on load */
window.addEventListener('load', () => { EMLoader.hide(); EmpBar.done(); });
setTimeout(() => EMLoader.hide(), 8000);
</script>


<!-- ===================================================
     MOBILE LAYOUT  (visible ≤ 768px)
     Order: sticky header → auth form → features
=================================================== -->
<div class="lp-wrap">
<div class="lp-glow"></div>

<!-- Mobile: sticky top bar -->
<div class="lp-mobile-top">
  <div class="lp-logo-icon">E</div>
  <span class="lp-logo-name">EnglishMaster AI</span>
  <span style="margin-left:auto;font-size:11px;color:var(--text-3);font-weight:600">🆓 Free</span>
</div>

<!-- Mobile: form area (shown first on phone) -->
<div class="lp-mobile-wrap">
  <div class="lp-mobile-form-area">
    <div style="text-align:center;margin-bottom:20px;">
      <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:22px;color:var(--text-1);margin-bottom:6px">
        Speak English <span class="grad-text">Confidently</span>
      </h2>
      <p style="font-size:13px;color:var(--text-2);line-height:1.5">
        Your AI English tutor — free, instant, and always available.
      </p>
    </div>



  <!-- Mobile: feature tiles below form -->
  <div class="lp-mobile-hero">
    <div class="lp-mobile-hero-title">✨ Everything you need to learn English</div>
    <div class="lp-mobile-features">
      <div class="lp-mobile-feature">
        <span class="mf-icon">💬</span>
        <span class="mf-text">AI Chat Practice</span>
      </div>
      <div class="lp-mobile-feature">
        <span class="mf-icon">🎤</span>
        <span class="mf-text">Speaking Practice</span>
      </div>
      <div class="lp-mobile-feature">
        <span class="mf-icon">📝</span>
        <span class="mf-text">Grammar Checker</span>
      </div>
      <div class="lp-mobile-feature">
        <span class="mf-icon">📚</span>
        <span class="mf-text">Vocabulary Builder</span>
      </div>
      <div class="lp-mobile-feature">
        <span class="mf-icon">👔</span>
        <span class="mf-text">Interview Prep</span>
      </div>
      <div class="lp-mobile-feature">
        <span class="mf-icon">⚡</span>
        <span class="mf-text">Daily Challenges</span>
      </div>
    </div>
    <div class="lp-mobile-pills">
      <span class="lp-pill">🎓 Beginner to Advanced</span>
      <span class="lp-pill">🤖 Claude AI</span>
      <span class="lp-pill">🆓 Free to Use</span>
    </div>
  </div>
</div><!-- /lp-mobile-wrap -->


<!-- ===================================================
     DESKTOP LAYOUT  (visible > 768px)
     Two-column: hero | form
=================================================== -->
<div class="lp-desktop">

  <!-- Left: Hero -->
  <div class="lp-hero">
    <div class="lp-logo">
      <div class="lp-logo-icon">E</div>
      <span class="lp-logo-name">EnglishMaster AI</span>
    </div>

    <h1 class="lp-h1">
      Speak English<br>
      <span class="grad-text">Confidently</span><br>
      with AI
    </h1>

    <p class="lp-sub">
      Your personal AI English tutor. Practice conversations, fix grammar,
      build vocabulary, and prepare for interviews — all in one place.
    </p>

    <div class="lp-features">
      <div class="lp-feature"><span class="lp-feature-icon">💬</span> AI-powered real-time conversations</div>
      <div class="lp-feature"><span class="lp-feature-icon">🎤</span> Speaking practice with mic & AI scoring</div>
      <div class="lp-feature"><span class="lp-feature-icon">📝</span> Instant grammar correction & explanations</div>
      <div class="lp-feature"><span class="lp-feature-icon">📚</span> Smart vocabulary builder with daily words</div>
      <div class="lp-feature"><span class="lp-feature-icon">👔</span> Mock interview practice with scoring</div>
      <div class="lp-feature"><span class="lp-feature-icon">⚡</span> Daily challenges &amp; XP reward system</div>
    </div>

    <div class="lp-pills">
      <span class="lp-pill">🎓 Beginner to Advanced</span>
      <span class="lp-pill">🤖 Powered by Claude AI</span>
      <span class="lp-pill">🆓 Free to Use</span>
    </div>
  </div>

  <!-- Right: Form -->
  <div class="lp-form-col">
    <div class="lp-auth-box" id="desktopAuthBox">
      <?php include __DIR__ . '/includes/_auth_form.php'; ?>
    </div>
  </div>

</div><!-- /lp-desktop -->
</div><!-- /lp-wrap -->

<script>
/* Tab switching — works for both mobile and desktop boxes */
function switchTab(tab) {
  document.querySelectorAll('.lp-tab').forEach(el => {
    el.classList.toggle('active', el.dataset.tab === tab);
  });
  document.querySelectorAll('.lp-form').forEach(f => {
    f.classList.toggle('active', f.id === 'form-' + tab);
  });
}

/* Button loading state */
document.querySelectorAll('.lp-btn').forEach(btn => {
  btn.closest('form')?.addEventListener('submit', () => {
    btn.classList.add('loading');
    btn.disabled = true;
  });
});

<?php if ($activeTab === 'register'): ?>
switchTab('register');
<?php endif; ?>
</script>
</body>
</html>
