<?php
// includes/header.php
$user = currentUser();
$xpInfo = $user ? xpToNextLevel($user['xp']) : null;
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop stop-color='%234f8ef7'/%3E%3Cstop offset='1' stop-color='%232dd4bf'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='64' height='64' rx='16' fill='%23070b18'/%3E%3Crect x='10' y='10' width='44' height='44' rx='12' fill='url(%23g)'/%3E%3Cpath d='M24 20h18v6H31v5h10v6H31v7h12v6H24V20z' fill='white'/%3E%3C/svg%3E">
<title><?= isset($pageTitle) ? clean($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/style.css">
</head>
<body>

<!-- SVG gradient defs for spinner ring -->
<svg class="em-svg-defs" aria-hidden="true">
  <defs>
    <linearGradient id="emGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%"   stop-color="#4f8ef7"/>
      <stop offset="50%"  stop-color="#2dd4bf"/>
      <stop offset="100%" stop-color="#a78bfa"/>
    </linearGradient>
  </defs>
</svg>

<!-- ========== FULL-PAGE SPINNER LOADER ========== -->
<div id="em-loader" aria-hidden="true">
  <div class="em-loader-box">
    <!-- Outer spinning ring -->
    <div class="em-spinner-wrap">
      <svg class="em-ring" viewBox="0 0 80 80">
        <circle class="em-ring-track" cx="40" cy="40" r="34"/>
        <circle class="em-ring-fill"  cx="40" cy="40" r="34"/>
      </svg>
      <!-- Logo center -->
      <div class="em-loader-logo">E</div>
    </div>
    <!-- Label -->
    <div class="em-loader-label" id="emLoaderLabel">Loading...</div>
    <!-- Dots row -->
    <div class="em-loader-dots">
      <span></span><span></span><span></span>
    </div>
  </div>
</div>

<!-- ========== TOP THIN PROGRESS BAR ========== -->
<div id="em-topbar"></div>

<!-- ========== TOAST CONTAINER ========== -->
<div class="em-toast" id="emToastContainer"></div>

<!-- ========== GLOBAL LOADING JS ========== -->
<script>
/* ============================================================
   EnglishMaster — Full-Page Spinner + Top Bar + Toasts
   ============================================================ */

/* ── Labels that rotate while loading ── */
const EM_LABELS = [
  'Loading...', 'Almost there...', 'Please wait...', 'Getting ready...'
];

/* ── Loader API ── */
const EMLoader = (() => {
  const el      = document.getElementById('em-loader');
  const labelEl = document.getElementById('emLoaderLabel');
  let visible   = false;
  let labelTimer = null;
  let labelIdx   = 0;

  function show(label) {
    if (!el) return;
    labelIdx = 0;
    if (labelEl) labelEl.textContent = label || EM_LABELS[0];
    el.classList.add('active');
    visible = true;
    /* cycle label text every 1.8s so it doesn't feel frozen */
    clearInterval(labelTimer);
    labelTimer = setInterval(() => {
      labelIdx = (labelIdx + 1) % EM_LABELS.length;
      if (labelEl) labelEl.textContent = EM_LABELS[labelIdx];
    }, 1800);
  }

  function hide() {
    if (!el) return;
    clearInterval(labelTimer);
    el.classList.remove('active');
    el.classList.add('hiding');
    visible = false;
    setTimeout(() => el.classList.remove('hiding'), 420);
  }

  function isVisible() { return visible; }

  return { show, hide, isVisible };
})();

/* Expose globally so AJAX pages can call it */
window.EMLoader = EMLoader;

/* ── Top thin progress bar ── */
const EmpBar = (() => {
  const bar = document.getElementById('em-topbar');
  let _w = 0, _raf = null, _done = false;
  function set(pct) {
    _w = Math.min(pct, 99);
    if (bar) { bar.style.width = _w + '%'; bar.classList.add('active'); }
  }
  function tick() {
    if (_done) return;
    _w += (_w < 20) ? 7 : (_w < 50) ? 3.5 : (_w < 80) ? 1.5 : 0.4;
    set(_w);
    _raf = requestAnimationFrame(tick);
  }
  function start() {
    _done = false; _w = 0;
    if (bar) { bar.style.transition = 'none'; bar.style.width = '0%'; bar.style.opacity = '1'; }
    requestAnimationFrame(() => {
      if (bar) bar.style.transition = 'width 0.25s ease';
      tick();
    });
  }
  function done() {
    _done = true;
    cancelAnimationFrame(_raf);
    set(100);
    setTimeout(() => { if (bar) { bar.style.opacity = '0'; bar.style.width = '0%'; } }, 300);
  }
  return { start, done, set };
})();
window.EmpBar = EmpBar;

/* ── Show loader on ALL link navigations ── */
document.addEventListener('click', function(e) {
  const a = e.target.closest('a[href]');
  if (!a) return;
  const href = a.getAttribute('href') || '';
  /* skip: hash, javascript, external, blank, download, ajax-only */
  if (!href || href.startsWith('#') || href.startsWith('javascript')
      || href.startsWith('http') || href.startsWith('mailto')
      || a.target === '_blank' || a.hasAttribute('download')
      || a.dataset.noloader) return;
  EMLoader.show();
  EmpBar.start();
});

/* ── Show loader on ALL form submissions ── */
document.addEventListener('submit', function(e) {
  const form = e.target;
  /* skip forms marked as ajax-only */
  if (form.dataset.noloader || form.dataset.ajax) return;
  EMLoader.show();
  EmpBar.start();
});

/* ── Hide loader when new page finishes loading ── */
window.addEventListener('load', () => {
  EMLoader.hide();
  EmpBar.done();
});
window.addEventListener('pageshow', () => {
  EMLoader.hide();
  EmpBar.done();
});
/* Safety net — always hide after 8s even if page hangs */
setTimeout(() => EMLoader.hide(), 8000);

/* ── Toast Notification System ── */
window.emToast = function(msg, type = 'info', duration = 2800) {
  const container = document.getElementById('emToastContainer');
  if (!container) return;
  const el = document.createElement('div');
  el.className = `em-toast-item ${type}`;
};

/* --- Toast Notification System --- */
window.emToast = function(msg, type = 'info', duration = 2800) {
  const container = document.getElementById('emToastContainer');
  if (!container) return;
  const el = document.createElement('div');
  el.className = `em-toast-item ${type}`;
  const icons = { xp: '⚡', info: 'ℹ️', warn: '⚠️', err: '❌', success: '✅' };
  el.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${msg}</span>`;
  container.appendChild(el);
  setTimeout(() => {
    el.classList.add('out');
    setTimeout(() => el.remove(), 350);
  }, duration);
};

/* --- Ripple effect on all .btn clicks --- */
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.btn');
  if (!btn) return;
  const r = document.createElement('span');
  r.className = 'ripple';
  const rect = btn.getBoundingClientRect();
  const size = Math.max(rect.width, rect.height);
  r.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px`;
  btn.appendChild(r);
  setTimeout(() => r.remove(), 600);
});

/* --- Button loading state helper (updated) --- */
window.btnLoading = function(btn, loading) {
  if (loading) {
    EMLoader.show('Loading...');
    EmpBar.start();
  } else {
    EMLoader.hide();
    EmpBar.done();
  }
};

function emIsAjaxForm(form) {
  return form?.dataset?.noloader || form?.dataset?.ajax;
}

document.addEventListener('submit', function(e) {
  const form = e.target;
  if (emIsAjaxForm(form)) return;
  EMLoader.show('Saving...');
  EmpBar.start();
}, true);

document.addEventListener('click', function(e) {
  const btn = e.target.closest('button, .btn');
  if (!btn || btn.matches('[data-noloader],[data-ajax]')) return;
  if (btn.closest('form') || btn.tagName === 'A') return;
  const important = btn.matches('[data-loader],[data-loading]') ||
    /\b(generate|check|verify|submit|save|create|update|delete|start|send|practice|answer|complete)\b/i.test(btn.textContent || '');
  if (!important) return;
  EMLoader.show('Loading...');
  EmpBar.start();
  setTimeout(() => {
    EMLoader.hide();
    EmpBar.done();
  }, 650);
});

if (window.fetch) {
  const emNativeFetch = window.fetch.bind(window);
  window.fetch = function(input, init = {}) {
    const method = (init.method || (input && input.method) || 'GET').toUpperCase();
    const shouldShow = method !== 'GET';
    if (shouldShow) {
      EMLoader.show('Saving...');
      EmpBar.start();
    }
    return emNativeFetch(input, init).finally(() => {
      if (shouldShow) {
        EMLoader.hide();
        EmpBar.done();
      }
    });
  };
}

if (window.XMLHttpRequest) {
  const emOpen = XMLHttpRequest.prototype.open;
  const emSend = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function(method) {
    this._emShowLoader = String(method || 'GET').toUpperCase() !== 'GET';
    return emOpen.apply(this, arguments);
  };
  XMLHttpRequest.prototype.send = function() {
    if (this._emShowLoader) {
      EMLoader.show('Saving...');
      EmpBar.start();
      this.addEventListener('loadend', () => {
        EMLoader.hide();
        EmpBar.done();
      }, { once: true });
    }
    return emSend.apply(this, arguments);
  };
}

/* --- Stat number count-up on page load --- */
function countUp(el, target, duration = 1200) {
  const start = parseInt((el.textContent || '0').replace(/,/g, ''), 10) || 0;
  if (start === target) {
    el.textContent = target.toLocaleString();
    return;
  }
  const direction = target >= start ? 1 : -1;
  const step = Math.max(1, Math.abs(target - start) / (duration / 16)) * direction;
  let current = start;
  const timer = setInterval(() => {
    current = direction > 0 ? Math.min(current + step, target) : Math.max(current + step, target);
    el.textContent = Math.round(current).toLocaleString();
    if ((direction > 0 && current >= target) || (direction < 0 && current <= target)) clearInterval(timer);
  }, 16);
}
window.addEventListener('load', function() {
  document.querySelectorAll('.stat-val[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count, 10);
    if (!isNaN(target)) countUp(el, target);
  });
});

/* --- Skeleton → real content swap helper --- */
window.showSkeleton = function(containerId) {
  const el = document.getElementById(containerId);
  if (el) el.classList.add('section-loading');
};
window.hideSkeleton = function(containerId) {
  const el = document.getElementById(containerId);
  if (el) el.classList.remove('section-loading');
};

/* --- Smooth SVG ring animation on load --- */
window.addEventListener('load', function() {
  document.querySelectorAll('circle[stroke-dashoffset]').forEach(circle => {
    const final = circle.getAttribute('stroke-dashoffset');
    const total = circle.getAttribute('stroke-dasharray');
    circle.setAttribute('stroke-dashoffset', total); // start full (hidden)
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        circle.style.transition = 'stroke-dashoffset 1.4s cubic-bezier(0.4,0,0.2,1)';
        circle.setAttribute('stroke-dashoffset', final);
      });
    });
  });
});
</script>

<div class="app-layout">
<!-- Sidebar overlay (mobile) -->
<div id="sidebarOverlay" onclick="closeSidebar()"></div>
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">E</div>
      <div class="logo-text">
        <span class="logo-name">EnglishMaster</span>
        <span class="logo-tag">AI Tutor</span>
      </div>
    </div>

    <?php if ($user): ?>
    <!-- User Card -->
    <div class="sidebar-user">
      <div class="user-avatar"><?= $user['avatar'] ?></div>
      <div class="user-info">
        <div class="user-name"><?= clean($user['name']) ?></div>
        <div class="user-level">Lv.<?= $user['level'] ?> · <?= levelName($user['level']) ?></div>
      </div>
      <div class="user-streak">🔥<?= $user['streak'] ?></div>
    </div>

    <!-- XP Bar -->
    <div class="xp-bar-wrap">
      <div class="xp-bar-label">
        <span><?= number_format($user['xp']) ?> XP</span>
        <span><?= $xpInfo['needed'] ?> to Lv.<?= $user['level']+1 ?></span>
      </div>
      <div class="xp-bar">
        <div class="xp-fill" style="width:<?= $xpInfo['progress'] ?>%"></div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <?php
        $practicePages = ['practice','grammar','vocabulary','challenges','quizzes'];
        $exercisePages = ['sentence_rearrangement','fill_blank','reading_comprehension','vocabulary_lesson','sentence_builder','daily_practice','scenario_practice','analytical_english'];
        $speakingPages = ['chat','speaking','interview'];
      ?>

      <!-- Main -->
      <div class="nav-group">
        <div class="nav-group-title">Main</div>
        <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
          <span class="nav-icon">🏠</span><span class="nav-label">Dashboard</span>
        </a>
        <a href="<?= APP_URL ?>/progress.php" class="nav-item <?= $currentPage==='progress'?'active':'' ?>">
          <span class="nav-icon">📈</span><span class="nav-label">My Progress</span>
        </a>
      </div>

      <!-- Practice -->
      <details class="nav-group" <?= in_array($currentPage, $practicePages, true) ? 'open' : '' ?>>
        <summary class="nav-group-title">Practice</summary>
        <a href="<?= APP_URL ?>/practice.php" class="nav-item <?= $currentPage==='practice'?'active':'' ?>">
          <span class="nav-icon">🧪</span><span class="nav-label">Practice Lab</span>
        </a>
        <a href="<?= APP_URL ?>/quizzes.php" class="nav-item <?= $currentPage==='quizzes'?'active':'' ?>">
          <span class="nav-icon">❓</span><span class="nav-label">Tense & Meaning Quizzes</span>
        </a>
        <a href="<?= APP_URL ?>/grammar.php" class="nav-item <?= $currentPage==='grammar'?'active':'' ?>">
          <span class="nav-icon">✏️</span><span class="nav-label">Grammar Check</span>
        </a>
        <a href="<?= APP_URL ?>/vocabulary.php" class="nav-item <?= $currentPage==='vocabulary'?'active':'' ?>">
          <span class="nav-icon">📚</span><span class="nav-label">Vocabulary</span>
        </a>
        <a href="<?= APP_URL ?>/challenges.php" class="nav-item <?= $currentPage==='challenges'?'active':'' ?>">
          <span class="nav-icon">🏆</span><span class="nav-label">Daily Challenges</span>
        </a>
      </details>

      <!-- AI Exercises -->
      <details class="nav-group" <?= in_array($currentPage, $exercisePages, true) ? 'open' : '' ?>>
        <summary class="nav-group-title">AI Exercises</summary>
        <a href="<?= APP_URL ?>/sentence_rearrangement.php" class="nav-item <?= $currentPage==='sentence_rearrangement'?'active':'' ?>">
          <span class="nav-icon">🔀</span><span class="nav-label">Rearrange Sentences</span>
        </a>
        <a href="<?= APP_URL ?>/fill_blank.php" class="nav-item <?= $currentPage==='fill_blank'?'active':'' ?>">
          <span class="nav-icon">🖊️</span><span class="nav-label">Fill the Blank</span>
        </a>
        <a href="<?= APP_URL ?>/reading_comprehension.php" class="nav-item <?= $currentPage==='reading_comprehension'?'active':'' ?>">
          <span class="nav-icon">📖</span><span class="nav-label">Reading Practice</span>
        </a>
        <a href="<?= APP_URL ?>/vocabulary_lesson.php" class="nav-item <?= $currentPage==='vocabulary_lesson'?'active':'' ?>">
          <span class="nav-icon">🎓</span><span class="nav-label">Vocabulary Lesson</span>
        </a>
        <a href="<?= APP_URL ?>/sentence_builder.php" class="nav-item <?= $currentPage==='sentence_builder'?'active':'' ?>">
          <span class="nav-icon">🏗️</span><span class="nav-label">5 Sentence Builder</span>
        </a>
        <a href="<?= APP_URL ?>/daily_practice.php" class="nav-item <?= $currentPage==='daily_practice'?'active':'' ?>">
          <span class="nav-icon">📅</span><span class="nav-label">Daily Practice Set</span>
        </a>
        <a href="<?= APP_URL ?>/scenario_practice.php" class="nav-item <?= $currentPage==='scenario_practice'?'active':'' ?>">
          <span class="nav-icon">🎭</span><span class="nav-label">Scenario Practice</span>
        </a>
        <a href="<?= APP_URL ?>/analytical_english.php" class="nav-item <?= $currentPage==='analytical_english'?'active':'' ?>">
          <span class="nav-icon">🔍</span><span class="nav-label">Analytical English</span>
        </a>
      </details>

      <!-- Speaking -->
      <details class="nav-group" <?= in_array($currentPage, $speakingPages, true) ? 'open' : '' ?>>
        <summary class="nav-group-title">Speaking</summary>
        <a href="<?= APP_URL ?>/chat.php" class="nav-item <?= $currentPage==='chat'?'active':'' ?>">
          <span class="nav-icon">💬</span><span class="nav-label">AI Chat</span>
        </a>
        <a href="<?= APP_URL ?>/speaking.php" class="nav-item <?= $currentPage==='speaking'?'active':'' ?>">
          <span class="nav-icon">🎤</span><span class="nav-label">Speaking Practice</span>
        </a>
        <a href="<?= APP_URL ?>/interview.php" class="nav-item <?= $currentPage==='interview'?'active':'' ?>">
          <span class="nav-icon">👔</span><span class="nav-label">Interview Prep</span>
        </a>
      </details>

      <!-- Admin (conditional) -->
      <?php if (isAdmin($user)): ?>
      <div class="nav-group">
        <div class="nav-group-title">Admin</div>
        <a href="<?= APP_URL ?>/admin.php" class="nav-item <?= $currentPage==='admin'?'active':'' ?>">
          <span class="nav-icon">⚙️</span><span class="nav-label">Admin Panel</span>
        </a>
      </div>
      <?php endif; ?>

    </nav>
    <?php endif; ?>

    <div class="sidebar-bottom">
      <?php if ($user): ?>
      <a href="<?= APP_URL ?>/logout.php" class="logout-btn">Sign Out</a>
      <?php endif; ?>
      <div class="sidebar-version">v1.0 · Powered by Claude AI</div>
    </div>
  </aside>

  <!-- Mobile top bar -->
  <div class="mobile-topbar">
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
    <span class="mobile-logo">EnglishMaster AI</span>
    <?php if ($user): ?><span class="mobile-xp">⚡ <?= $user['xp'] ?> XP</span><?php endif; ?>
  </div>

  <!-- Main Content -->
  <main class="main-content">