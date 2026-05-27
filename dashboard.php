<?php
require_once 'config.php';
auth();
$db = db();
$uid = (int)$_SESSION['uid'];
updateStreak($uid);
$user = currentUser();
$xpInfo = xpToNextLevel($user['xp']);
$pageTitle = 'Dashboard';

// Stats
$chatCount = $db->query("SELECT COUNT(DISTINCT session_id) as c FROM chat_history WHERE user_id=$uid AND role='user'")->fetch_assoc()['c'] ?? 0;
$grammarCount = $db->query("SELECT COUNT(*) as c FROM grammar_sessions WHERE user_id=$uid")->fetch_assoc()['c'] ?? 0;
$wordsLearned = $db->query("SELECT COUNT(*) as c FROM user_vocabulary WHERE user_id=$uid AND status='mastered'")->fetch_assoc()['c'] ?? 0;
$challengesDone = $db->query("SELECT COUNT(*) as c FROM user_challenges WHERE user_id=$uid")->fetch_assoc()['c'] ?? 0;

// Today's challenge
$todayChallenge = $db->query("SELECT * FROM challenges WHERE challenge_date=CURDATE() ORDER BY id LIMIT 1")->fetch_assoc();

// Recent activity (xp log)
$recentActivity = $db->query("SELECT * FROM xp_log WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5");

// Recent grammar sessions
$recentGrammar = $db->query("SELECT * FROM grammar_sessions WHERE user_id=$uid ORDER BY created_at DESC LIMIT 3");

include 'includes/header.php';
?>

<div class="page-header">
  <h1>Welcome back, <span class="grad-text"><?= clean($user['name']) ?></span> 👋</h1>
  <p>You're on a <strong style="color:var(--yellow)"><?= $user['streak'] ?>-day streak</strong>! Keep it up — consistency is the key to fluency.</p>
</div>

<!-- Stats Row -->
<div class="grid-4 mb-24">
  <div class="stat-card">
    <div class="stat-icon">⚡</div>
    <div>
      <div class="stat-val grad-text" data-count="<?= (int)$user['xp'] ?>"><?= number_format((int)$user['xp']) ?></div>
      <div class="stat-label">Total XP Earned</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🔥</div>
    <div>
      <div class="stat-val" style="color:var(--yellow)" data-count="<?= (int)$user['streak'] ?>"><?= number_format((int)$user['streak']) ?></div>
      <div class="stat-label">Day Streak</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📚</div>
    <div>
      <div class="stat-val" style="color:var(--green)" data-count="<?= (int)$wordsLearned ?>"><?= number_format((int)$wordsLearned) ?></div>
      <div class="stat-label">Words Mastered</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⭐</div>
    <div>
      <div class="stat-val" style="color:var(--purple)" data-count="<?= (int)$challengesDone ?>"><?= number_format((int)$challengesDone) ?></div>
      <div class="stat-label">Challenges Done</div>
    </div>
  </div>
</div>

<!-- Level Progress -->
<div class="card mb-24">
  <div class="flex-between mb-8 level-row-mobile" style="flex-wrap:wrap;gap:8px;">
    <div>
      <h2 style="font-size:18px;margin-bottom:3px">Level <?= $user['level'] ?> — <span class="grad-text-purple"><?= levelName($user['level']) ?></span></h2>
      <p class="text-sm text-muted"><?= $xpInfo['needed'] ?> XP needed to reach Level <?= $user['level']+1 ?></p>
    </div>
    <div style="text-align:right">
      <div style="font-size:22px;font-weight:800;color:var(--text-1)"><?= $xpInfo['progress'] ?>%</div>
      <div class="text-xs text-faint">Progress</div>
    </div>
  </div>
  <div style="height:10px;background:var(--bg-hover);border-radius:99px;overflow:hidden;">
    <div style="height:100%;width:<?= $xpInfo['progress'] ?>%;background:linear-gradient(90deg,var(--blue),var(--teal));border-radius:99px;transition:width 1.5s ease;"></div>
  </div>
</div>

<!-- Today's Challenge Banner -->
<?php if ($todayChallenge): ?>
<div style="background:linear-gradient(135deg,#4f8ef715,#a78bfa10);border:1px solid var(--border-2);border-radius:var(--radius-lg);padding:24px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
  <div>
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--blue);margin-bottom:6px">⚡ Today's Challenge</div>
    <h3 style="font-size:18px;color:var(--text-1);margin-bottom:5px"><?= clean($todayChallenge['title']) ?></h3>
    <p style="font-size:14px;color:var(--text-2)"><?= clean($todayChallenge['description']) ?></p>
  </div>
  <a href="challenges.php" class="btn btn-primary">Take Challenge <span>+<?= $todayChallenge['xp_reward'] ?> XP</span></a>
</div>
<?php endif; ?>

<!-- Action Cards -->
<h2 style="font-size:17px;margin-bottom:16px;color:var(--text-2);font-weight:600">What would you like to practice?</h2>
<div class="grid-3 mb-24">
  <a href="chat.php" class="action-card">
    <div class="ac-icon">💬</div>
    <div class="ac-title">AI Conversation Chat</div>
    <div class="ac-desc">Chat freely with your AI tutor. Get real-time corrections and learn natural expressions.</div>
    <span class="ac-badge badge-blue">+10 XP per message</span>
  </a>
  <a href="grammar.php" class="action-card">
    <div class="ac-icon">📝</div>
    <div class="ac-title">Grammar Checker</div>
    <div class="ac-desc">Paste any text and get detailed grammar analysis, corrections, and clear explanations.</div>
    <span class="ac-badge badge-green">+30 XP per session</span>
  </a>
  <a href="vocabulary.php" class="action-card">
    <div class="ac-icon">📚</div>
    <div class="ac-title">Vocabulary Builder</div>
    <div class="ac-desc">Learn new words with meanings, synonyms, and real-life example sentences.</div>
    <span class="ac-badge badge-purple">+20 XP per word</span>
  </a>
  <a href="challenges.php" class="action-card">
    <div class="ac-icon">⚡</div>
    <div class="ac-title">Daily Challenges</div>
    <div class="ac-desc">Fun grammar, writing, and vocabulary challenges to sharpen your skills every day.</div>
    <span class="ac-badge badge-yellow">Bonus XP daily</span>
  </a>
  <a href="speaking.php" class="action-card">
    <div class="ac-icon">🎤</div>
    <div class="ac-title">Speaking Practice</div>
    <div class="ac-desc">Use your microphone to speak English freely, read aloud, or practice pronunciation. Get instant AI feedback.</div>
    <span class="ac-badge badge-red">Mic + AI</span>
  </a>
  <a href="interview.php" class="action-card">
    <div class="ac-icon">👔</div>
    <div class="ac-title">Interview Practice</div>
    <div class="ac-desc">Simulate job interviews for IT, customer service, and more. Get scored on grammar and confidence.</div>
    <span class="ac-badge badge-teal">AI scoring</span>
  </a>
  <a href="progress.php" class="action-card">
    <div class="ac-icon">📊</div>
    <div class="ac-title">My Progress</div>
    <div class="ac-desc">Track your XP growth, grammar sessions, vocabulary, and overall English improvement journey.</div>
    <span class="ac-badge badge-blue">View stats</span>
  </a>
</div>

<!-- Recent Activity + Stats -->
<div class="grid-2">
  <!-- Recent XP Activity -->
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:16px;color:var(--text-1)">⚡ Recent Activity</h3>
    <?php if ($recentActivity && $recentActivity->num_rows > 0): ?>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <?php while ($act = $recentActivity->fetch_assoc()): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:var(--bg-base);border-radius:8px;">
        <span style="font-size:13px;color:var(--text-2)"><?= clean($act['reason']) ?></span>
        <span style="font-size:13px;font-weight:700;color:var(--green)">+<?= $act['amount'] ?> XP</span>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:30px 20px;">
      <span class="empty-icon">🌟</span>
      <p style="color:var(--text-3);font-size:14px">No activity yet. Start practicing to earn XP!</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Quick Stats -->
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:16px;color:var(--text-1)">📊 Your Stats</h3>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:14px;color:var(--text-2)">💬 Chat Sessions</span>
        <span style="font-weight:700;color:var(--blue)"><?= $chatCount ?></span>
      </div>
      <div class="divider" style="margin:0"></div>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:14px;color:var(--text-2)">📝 Grammar Checks</span>
        <span style="font-weight:700;color:var(--green)"><?= $grammarCount ?></span>
      </div>
      <div class="divider" style="margin:0"></div>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:14px;color:var(--text-2)">📚 Words Mastered</span>
        <span style="font-weight:700;color:var(--purple)"><?= $wordsLearned ?></span>
      </div>
      <div class="divider" style="margin:0"></div>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:14px;color:var(--text-2)">⭐ Challenges Done</span>
        <span style="font-weight:700;color:var(--yellow)"><?= $challengesDone ?></span>
      </div>
      <div class="divider" style="margin:0"></div>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:14px;color:var(--text-2)">🎯 English Level</span>
        <span style="font-weight:700;color:var(--teal);text-transform:capitalize"><?= $user['english_level'] ?></span>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
