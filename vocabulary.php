<?php
require_once 'config.php';
auth();
$user = currentUser();
$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Vocabulary Builder';

// Mark word as learning/mastered
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_word'])) {
    $wordId = (int)($_POST['word_id'] ?? 0);
    $status = in_array($_POST['status'], ['learning','mastered','new']) ? $_POST['status'] : 'new';
    $db->query("INSERT INTO user_vocabulary (user_id, word_id, status, review_count, last_reviewed) VALUES ($uid,$wordId,'$status',1,NOW()) ON DUPLICATE KEY UPDATE status='$status', review_count=review_count+1, last_reviewed=NOW()");
    if ($status === 'mastered') addXP($uid, 20, 'Mastered a vocabulary word');
    if (isset($_POST['ajax'])) { echo json_encode(['ok' => 1]); exit; }
}

// Request AI word explanation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_explain'])) {
    header('Content-Type: application/json');
    $word = clean($_POST['word'] ?? '');
    if (!$word) { echo json_encode(['error' => 'No word provided']); exit; }

    $response = callAI(
        [['role' => 'user', 'content' => "Explain the word '$word' in a fun, simple way for an English learner. Include: meaning, pronunciation guide (syllables), 2 example sentences in different contexts, 2 synonyms, and a memory tip to remember it. Keep it brief and encouraging."]],
        "You are a friendly English vocabulary coach. Respond in plain text with clear sections. Keep it concise.",
        600
    );
    addXP($uid, 5, 'Vocabulary word lookup');
    echo json_encode(['explanation' => $response]);
    exit;
}

// Filters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$difficulty = $_GET['difficulty'] ?? 'all';

// Build query
$where = ['v.active=1'];
if ($difficulty !== 'all') $where[] = "v.difficulty='" . esc($difficulty) . "'";
if ($search) $where[] = "(v.word LIKE '%" . esc($search) . "%' OR v.meaning LIKE '%" . esc($search) . "%')";
$whereStr = implode(' AND ', $where);

$vocab = $db->query("
    SELECT v.*, uv.status, uv.review_count 
    FROM vocabulary v
    LEFT JOIN user_vocabulary uv ON v.id=uv.word_id AND uv.user_id=$uid
    WHERE $whereStr
    ORDER BY v.word ASC
    LIMIT 50
");

// User's stats
$stats = $db->query("SELECT status, COUNT(*) as cnt FROM user_vocabulary WHERE user_id=$uid GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$statMap = array_column($stats, 'cnt', 'status');

include 'includes/header.php';
?>

<div class="page-header flex-between" style="flex-wrap:wrap;gap:12px;">
  <div>
    <h1>📚 Vocabulary Builder</h1>
    <p>Learn new words with meanings, examples, and AI-powered explanations.</p>
  </div>
</div>

<!-- Stats Row -->
<div class="grid-3 mb-24">
  <div class="stat-card">
    <div class="stat-icon">📖</div>
    <div>
      <div class="stat-val" style="color:var(--blue)"><?= ($statMap['learning'] ?? 0) + ($statMap['mastered'] ?? 0) ?></div>
      <div class="stat-label">Words Started</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⭐</div>
    <div>
      <div class="stat-val" style="color:var(--green)"><?= $statMap['mastered'] ?? 0 ?></div>
      <div class="stat-label">Words Mastered</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🎯</div>
    <div>
      <div class="stat-val" style="color:var(--yellow)"><?= $statMap['learning'] ?? 0 ?></div>
      <div class="stat-label">Still Learning</div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card mb-16" style="padding:16px 20px;">
  <form method="GET" class="vocab-filter-bar" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <input type="text" name="search" class="form-control" style="flex:1;min-width:180px;max-width:280px" placeholder="🔍 Search words..." value="<?= clean($search) ?>">
    <select name="difficulty" class="form-control" style="width:160px">
      <option value="all" <?= $difficulty==='all'?'selected':'' ?>>All Levels</option>
      <option value="beginner" <?= $difficulty==='beginner'?'selected':'' ?>>🌱 Beginner</option>
      <option value="intermediate" <?= $difficulty==='intermediate'?'selected':'' ?>>📖 Intermediate</option>
      <option value="advanced" <?= $difficulty==='advanced'?'selected':'' ?>>🚀 Advanced</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <?php if ($search || $difficulty !== 'all'): ?>
    <a href="vocabulary.php" class="btn btn-ghost btn-sm">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Vocabulary Grid -->
<?php if ($vocab && $vocab->num_rows > 0): ?>
<div class="grid-3" style="gap:16px;">
  <?php while ($w = $vocab->fetch_assoc()):
    $status = $w['status'] ?? 'new';
    $statusColor = $status === 'mastered' ? 'var(--green)' : ($status === 'learning' ? 'var(--yellow)' : 'var(--text-3)');
    $statusLabel = $status === 'mastered' ? '✅ Mastered' : ($status === 'learning' ? '📖 Learning' : '+ Add');
  ?>
  <div class="vocab-card" id="card-<?= $w['id'] ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
      <div>
        <div class="vocab-word"><?= clean($w['word']) ?></div>
        <?php if ($w['pronunciation']): ?>
        <div class="vocab-pron">/<?= clean($w['pronunciation']) ?>/</div>
        <?php endif; ?>
      </div>
      <span style="font-size:11px;padding:3px 9px;border-radius:99px;background:var(--bg-hover);" 
            class="diff-<?= $w['difficulty'] ?>">
        <?= ucfirst($w['difficulty']) ?>
      </span>
    </div>

    <div class="vocab-meaning"><?= clean($w['meaning']) ?></div>

    <?php if ($w['example_sentence']): ?>
    <div class="vocab-example"><?= clean($w['example_sentence']) ?></div>
    <?php endif; ?>

    <div class="vocab-tags">
      <?php if ($w['synonyms']): ?>
      <span class="vocab-tag">≈ <?= clean($w['synonyms']) ?></span>
      <?php endif; ?>
      <?php if ($w['antonyms']): ?>
      <span class="vocab-tag">↔ <?= clean($w['antonyms']) ?></span>
      <?php endif; ?>
    </div>

    <!-- Action buttons -->
    <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;">
      <?php if ($status !== 'mastered'): ?>
      <button class="btn btn-outline btn-sm" onclick="markWord(<?= $w['id'] ?>,'learning')" style="font-size:12px" id="btn-learn-<?= $w['id'] ?>">
        📖 Learning
      </button>
      <button class="btn btn-success btn-sm" onclick="markWord(<?= $w['id'] ?>,'mastered')" style="font-size:12px" id="btn-master-<?= $w['id'] ?>">
        ✅ Mastered
      </button>
      <?php else: ?>
      <span style="font-size:12px;color:var(--green);font-weight:700">✅ Mastered! +20 XP</span>
      <?php endif; ?>
      <button class="btn btn-ghost btn-sm" onclick="aiExplain('<?= addslashes($w['word']) ?>',<?= $w['id'] ?>)" style="font-size:12px">
        🤖 AI Explain
      </button>
    </div>

    <div id="ai-explain-<?= $w['id'] ?>" style="display:none;margin-top:12px;background:var(--bg-base);border:1px solid var(--border);border-radius:10px;padding:14px;">
      <div style="font-size:13px;color:var(--text-2);line-height:1.7" id="ai-text-<?= $w['id'] ?>">
        <div class="ai-thinking"><span></span><span></span><span></span></div>
      </div>
    </div>
  </div>
  <?php endwhile; ?>
</div>

<?php else: ?>
<div class="empty-state">
  <span class="empty-icon">📚</span>
  <h3>No words found</h3>
  <p>Try a different search or filter.</p>
</div>
<?php endif; ?>

<script>
async function markWord(wordId, status) {
  try {
    await fetch('vocabulary.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `mark_word=1&word_id=${wordId}&status=${status}&ajax=1`
    });

    const card = document.getElementById('card-' + wordId);
    if (status === 'mastered') {
      const btns = card.querySelectorAll('[id^="btn-learn-"],[id^="btn-master-"]');
      btns.forEach(b => b.remove());
      const actionDiv = card.querySelector('[style*="display:flex;gap:8px"]');
      const span = document.createElement('span');
      span.style.cssText = 'font-size:12px;color:var(--green);font-weight:700';
      span.textContent = '✅ Mastered! +20 XP';
      actionDiv.prepend(span);
      emToast('+20 XP — Word mastered! 📚', 'xp');
    } else {
      const btn = document.getElementById('btn-learn-' + wordId);
      if (btn) { btn.style.color = 'var(--yellow)'; btn.textContent = '📖 Learning'; }
      emToast('Added to learning list 📖', 'info');
    }
  } catch(e) { console.error(e); }
}

async function aiExplain(word, wordId) {
  const area = document.getElementById('ai-explain-' + wordId);
  const textEl = document.getElementById('ai-text-' + wordId);

  if (area.style.display === 'block') {
    area.style.display = 'none';
    return;
  }

  area.style.display = 'block';
  textEl.innerHTML = '<div class="ai-thinking"><span></span><span></span><span></span></div>';

  try {
    const res = await fetch('vocabulary.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'ai_explain=1&word=' + encodeURIComponent(word)
    });
    const data = await res.json();
    if (data.explanation) {
      textEl.innerHTML = data.explanation.replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    } else {
      textEl.textContent = data.error || 'Could not load explanation.';
    }
  } catch(e) {
    textEl.textContent = 'Error loading explanation.';
  }
}
</script>

<?php include 'includes/footer.php'; ?>
