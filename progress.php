<?php
require_once 'config.php';
auth();
$user = currentUser();
$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'My Progress';

// XP over last 14 days
$xpDays = $db->query("
    SELECT DATE(created_at) as day, SUM(amount) as total
    FROM xp_log WHERE user_id=$uid AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(created_at) ORDER BY day ASC
");
$xpData = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $xpData[$date] = 0;
}
if ($xpDays) { while ($r = $xpDays->fetch_assoc()) $xpData[$r['day']] = (int)$r['total']; }

// Grammar sessions over time
$grammarStats = $db->query("SELECT AVG(score) as avg_score, COUNT(*) as total FROM grammar_sessions WHERE user_id=$uid")->fetch_assoc();
$avgGrammar = round($grammarStats['avg_score'] ?? 0);
$totalGrammar = (int)($grammarStats['total'] ?? 0);

// Vocab stats
$vocabStats = $db->query("SELECT status, COUNT(*) as cnt FROM user_vocabulary WHERE user_id=$uid GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$vocabMap = array_column($vocabStats, 'cnt', 'status');
$totalVocab = array_sum(array_column($vocabStats, 'cnt'));

// Challenge stats
$challengeStats = $db->query("SELECT AVG(score) as avg_score, COUNT(*) as total, SUM(xp_earned) as total_xp FROM user_challenges WHERE user_id=$uid")->fetch_assoc();
$avgChallenge = round($challengeStats['avg_score'] ?? 0);
$totalChallenges = (int)($challengeStats['total'] ?? 0);

// Interview stats
$interviewStats = $db->query("SELECT COUNT(*) as total, AVG(grammar_score) as avg_grammar, AVG(confidence_score) as avg_conf FROM interview_sessions WHERE user_id=$uid")->fetch_assoc();

// Recent grammar sessions for chart
$recentGrammar = $db->query("SELECT score, created_at FROM grammar_sessions WHERE user_id=$uid ORDER BY created_at DESC LIMIT 10");
$grammarScores = [];
if ($recentGrammar) { while ($r = $recentGrammar->fetch_assoc()) $grammarScores[] = ['score' => $r['score'], 'date' => date('M d', strtotime($r['created_at']))]; }
$grammarScores = array_reverse($grammarScores);

// Ensure speaking_sessions exists (users who ran old db_setup.sql won't have it)
$db->query("CREATE TABLE IF NOT EXISTS speaking_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mode ENUM('free','read_aloud','pronunciation') DEFAULT 'free',
    original_text TEXT, transcript TEXT, ai_feedback TEXT,
    grammar_score INT DEFAULT 0, fluency_score INT DEFAULT 0,
    overall_score INT DEFAULT 0, duration_seconds INT DEFAULT 0,
    word_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Speaking stats
$speakStats = $db->query("SELECT COUNT(*) as total, AVG(overall_score) as avg_score, SUM(word_count) as total_words FROM speaking_sessions WHERE user_id=$uid")->fetch_assoc();

// XP log
$xpHistory = $db->query("SELECT * FROM xp_log WHERE user_id=$uid ORDER BY created_at DESC LIMIT 15");

// Calculate total activity days
$activeDays = $db->query("SELECT COUNT(DISTINCT DATE(created_at)) as days FROM xp_log WHERE user_id=$uid")->fetch_assoc()['days'] ?? 0;

$xpInfo = xpToNextLevel($user['xp']);

include 'includes/header.php';
?>

<div class="page-header">
  <h1>📊 My Progress</h1>
  <p>Track your English learning journey, XP growth, and skill improvements.</p>
</div>

<!-- Top Summary Cards -->
<div class="grid-4 mb-24">
  <div class="stat-card">
    <div class="stat-icon">🏆</div>
    <div>
      <div class="stat-val grad-text"><?= number_format($user['xp']) ?></div>
      <div class="stat-label">Total XP</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📅</div>
    <div>
      <div class="stat-val" style="color:var(--blue)"><?= $activeDays ?></div>
      <div class="stat-label">Days Active</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🔥</div>
    <div>
      <div class="stat-val" style="color:var(--yellow)"><?= $user['streak'] ?></div>
      <div class="stat-label">Current Streak</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⭐</div>
    <div>
      <div class="stat-val" style="color:var(--purple)"><?= $user['level'] ?></div>
      <div class="stat-label">Current Level</div>
    </div>
  </div>
</div>

<!-- Level Card -->
<div class="card mb-24" style="background:linear-gradient(135deg,#4f8ef710,#a78bfa08);">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
    <div>
      <div style="font-size:13px;color:var(--text-3);font-weight:600;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:4px">Your Rank</div>
      <h2 style="font-size:24px;margin-bottom:4px">Level <?= $user['level'] ?> — <span class="grad-text-purple"><?= levelName($user['level']) ?></span></h2>
      <div style="font-size:14px;color:var(--text-2)"><?= number_format($user['xp']) ?> XP · <?= $xpInfo['needed'] ?> XP to Level <?= $user['level']+1 ?></div>
    </div>
    <div style="text-align:right">
      <div style="font-size:36px;font-weight:800;color:var(--text-1)"><?= $xpInfo['progress'] ?>%</div>
      <div style="font-size:12px;color:var(--text-3)">to next level</div>
    </div>
  </div>
  <div style="height:10px;background:var(--bg-hover);border-radius:99px;overflow:hidden;margin-top:16px;">
    <div style="height:100%;width:<?= $xpInfo['progress'] ?>%;background:linear-gradient(90deg,var(--blue),var(--teal));border-radius:99px;"></div>
  </div>
</div>

<!-- XP Chart -->
<div class="card mb-24">
  <div class="flex-between mb-16">
    <h3 style="font-size:16px;color:var(--text-1)">⚡ XP Earned — Last 14 Days</h3>
    <span style="font-size:13px;color:var(--text-3)">Total: +<?= array_sum($xpData) ?> XP this period</span>
  </div>
  <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;" class="xp-chart-wrap">
  <div style="position:relative;height:160px;display:flex;align-items:flex-end;gap:6px;min-width:320px;">
    <?php
    $maxXP = max(array_values($xpData)) ?: 1;
    foreach ($xpData as $date => $xp):
      $height = max(4, round(($xp / $maxXP) * 140));
      $isToday = $date === date('Y-m-d');
      $label = date('d', strtotime($date));
    ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;" title="<?= $date ?>: <?= $xp ?> XP">
      <div style="font-size:10px;color:var(--text-3)"><?= $xp > 0 ? $xp : '' ?></div>
      <div style="width:100%;height:<?= $height ?>px;background:<?= $isToday ? 'var(--teal)' : ($xp > 0 ? 'var(--blue)' : 'var(--bg-hover)') ?>;border-radius:4px 4px 0 0;transition:opacity 0.2s;" 
           onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'"></div>
      <div style="font-size:9px;color:<?= $isToday ? 'var(--teal)' : 'var(--text-3)' ?>;font-weight:<?= $isToday ? '700' : '400' ?>"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  </div>
</div>

<!-- Skills Grid -->
<div class="grid-2 mb-24">

  <!-- Grammar Progress -->
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:20px;color:var(--text-1)">📝 Grammar Progress</h3>
    <div style="display:flex;gap:24px;align-items:center;margin-bottom:20px;">
      <!-- Circle indicator -->
      <div style="position:relative;width:80px;height:80px;flex-shrink:0;">
        <svg viewBox="0 0 80 80" width="80" height="80">
          <circle cx="40" cy="40" r="32" fill="none" stroke="var(--bg-hover)" stroke-width="8"/>
          <circle cx="40" cy="40" r="32" fill="none"
            stroke="<?= $avgGrammar >= 80 ? 'var(--green)' : ($avgGrammar >= 60 ? 'var(--yellow)' : 'var(--blue)') ?>"
            stroke-width="8" stroke-linecap="round"
            stroke-dasharray="<?= round(2 * 3.14159 * 32) ?>"
            stroke-dashoffset="<?= round(2 * 3.14159 * 32 * (1 - $avgGrammar/100)) ?>"
            transform="rotate(-90 40 40)"/>
        </svg>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:var(--text-1)"><?= $avgGrammar ?>%</div>
      </div>
      <div>
        <div style="font-size:22px;font-weight:800;color:var(--text-1)"><?= $totalGrammar ?></div>
        <div style="font-size:13px;color:var(--text-3)">Grammar sessions completed</div>
        <div style="font-size:13px;color:var(--text-2);margin-top:4px">Average score: <strong style="color:var(--blue)"><?= $avgGrammar ?>/100</strong></div>
      </div>
    </div>

    <?php if (!empty($grammarScores)): ?>
    <!-- Mini score chart -->
    <div style="display:flex;align-items:flex-end;gap:4px;height:50px;">
      <?php foreach ($grammarScores as $gs):
        $h = max(4, round($gs['score'] / 100 * 46));
        $c = $gs['score'] >= 80 ? 'var(--green)' : ($gs['score'] >= 60 ? 'var(--yellow)' : 'var(--red)');
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;" title="<?= $gs['date'] ?>: <?= $gs['score'] ?>">
        <div style="width:100%;height:<?= $h ?>px;background:<?= $c ?>;border-radius:3px 3px 0 0;opacity:0.8;"></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="font-size:11px;color:var(--text-3);margin-top:6px">Recent grammar session scores ↑</div>
    <?php else: ?>
    <div style="font-size:13px;color:var(--text-3);text-align:center;padding:16px 0">No grammar sessions yet. <a href="grammar.php">Try Grammar Check →</a></div>
    <?php endif; ?>
  </div>

  <!-- Vocabulary Progress -->
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:20px;color:var(--text-1)">📚 Vocabulary Progress</h3>

    <div style="display:flex;gap:24px;align-items:center;margin-bottom:20px;">
      <div style="position:relative;width:80px;height:80px;flex-shrink:0;">
        <?php $masteredPct = $totalVocab > 0 ? round(($vocabMap['mastered'] ?? 0)/$totalVocab*100) : 0; ?>
        <svg viewBox="0 0 80 80" width="80" height="80">
          <circle cx="40" cy="40" r="32" fill="none" stroke="var(--bg-hover)" stroke-width="8"/>
          <circle cx="40" cy="40" r="32" fill="none" stroke="var(--green)"
            stroke-width="8" stroke-linecap="round"
            stroke-dasharray="<?= round(2*3.14159*32) ?>"
            stroke-dashoffset="<?= round(2*3.14159*32*(1-$masteredPct/100)) ?>"
            transform="rotate(-90 40 40)"/>
        </svg>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:var(--text-1)"><?= $masteredPct ?>%</div>
      </div>
      <div>
        <div style="font-size:22px;font-weight:800;color:var(--text-1)"><?= $vocabMap['mastered'] ?? 0 ?></div>
        <div style="font-size:13px;color:var(--text-3)">Words mastered</div>
        <div style="font-size:13px;color:var(--text-2);margin-top:4px"><?= $vocabMap['learning'] ?? 0 ?> still learning</div>
      </div>
    </div>

    <!-- Vocab breakdown bars -->
    <?php
    $vItems = [
      ['Mastered', $vocabMap['mastered'] ?? 0, 'var(--green)'],
      ['Learning', $vocabMap['learning'] ?? 0, 'var(--yellow)'],
      ['New', $vocabMap['new'] ?? 0, 'var(--blue)'],
    ];
    $maxV = max(1, ...array_column($vItems, 1));
    foreach ($vItems as [$label, $val, $color]):
    ?>
    <div style="margin-bottom:10px;">
      <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-2);margin-bottom:4px;">
        <span><?= $label ?></span><span style="font-weight:700;color:<?= $color ?>"><?= $val ?> words</span>
      </div>
      <div style="height:6px;background:var(--bg-hover);border-radius:99px;overflow:hidden;">
        <div style="height:100%;width:<?= round($val/$maxV*100) ?>%;background:<?= $color ?>;border-radius:99px;"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Speaking Row -->
<div class="grid-3 mb-24">
  <div class="stat-card">
    <div class="stat-icon">🎤</div>
    <div>
      <div class="stat-val" style="color:var(--blue)" data-count="<?= (int)($speakStats['total'] ?? 0) ?>">0</div>
      <div class="stat-label">Speaking Sessions</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⭐</div>
    <div>
      <div class="stat-val" style="color:var(--teal)" data-count="<?= round($speakStats['avg_score'] ?? 0) ?>">0</div>
      <div class="stat-label">Avg Speaking Score</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💬</div>
    <div>
      <div class="stat-val" style="color:var(--purple)" data-count="<?= (int)($speakStats['total_words'] ?? 0) ?>">0</div>
      <div class="stat-label">Words Spoken</div>
    </div>
  </div>
</div>

<!-- Bottom Row -->
<div class="grid-2">

  <!-- Interview Stats -->
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:16px;color:var(--text-1)">👔 Interview Practice</h3>
    <?php if ((int)$interviewStats['total'] > 0): ?>
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
      <div style="flex:1;background:var(--bg-base);border-radius:10px;padding:14px;text-align:center;">
        <div style="font-size:26px;font-weight:800;color:var(--blue)"><?= $interviewStats['total'] ?></div>
        <div style="font-size:12px;color:var(--text-3)">Interviews Done</div>
      </div>
      <div style="flex:1;background:var(--bg-base);border-radius:10px;padding:14px;text-align:center;">
        <div style="font-size:26px;font-weight:800;color:var(--green)"><?= round($interviewStats['avg_grammar'] ?? 0) ?>/10</div>
        <div style="font-size:12px;color:var(--text-3)">Avg. Grammar</div>
      </div>
      <div style="flex:1;background:var(--bg-base);border-radius:10px;padding:14px;text-align:center;">
        <div style="font-size:26px;font-weight:800;color:var(--purple)"><?= round($interviewStats['avg_conf'] ?? 0) ?>/10</div>
        <div style="font-size:12px;color:var(--text-3)">Avg. Confidence</div>
      </div>
    </div>
    <a href="interview.php" class="btn btn-outline btn-sm btn-full">Practice Interview →</a>
    <?php else: ?>
    <div class="empty-state" style="padding:30px 20px;">
      <span class="empty-icon" style="font-size:36px">👔</span>
      <p style="color:var(--text-3);font-size:14px">No interviews practiced yet.</p>
      <a href="interview.php" class="btn btn-primary btn-sm mt-16">Start Practice →</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recent XP Activity -->
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:16px;color:var(--text-1)">⚡ Recent XP Activity</h3>
    <?php if ($xpHistory && $xpHistory->num_rows > 0): ?>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <?php while ($x = $xpHistory->fetch_assoc()): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--bg-base);border-radius:8px;">
        <div>
          <div style="font-size:13px;color:var(--text-1)"><?= clean($x['reason']) ?></div>
          <div style="font-size:11px;color:var(--text-3);margin-top:1px"><?= date('M d, g:i A', strtotime($x['created_at'])) ?></div>
        </div>
        <span style="font-weight:700;color:var(--green);font-size:14px">+<?= $x['amount'] ?></span>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:30px 20px;">
      <span class="empty-icon" style="font-size:36px">⚡</span>
      <p style="color:var(--text-3);font-size:14px">No activity yet. Start practicing!</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
