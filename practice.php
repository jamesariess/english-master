<?php
require_once 'config.php';
auth();

$user = currentUser();
$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Practice Lab';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_practice'])) {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $answer = trim($_POST['answer'] ?? '');
    $item = null;

    if ($itemId && $answer !== '') {
        $res = $db->query("SELECT * FROM practice_items WHERE id=$itemId AND active=1 LIMIT 1");
        $item = $res ? $res->fetch_assoc() : null;
    }

    if ($item) {
        $isChoice = in_array($item['type'], ['better_english', 'grammar_choice', 'vocabulary_quiz'], true);
        $isCorrect = 0;
        $feedback = '';

        if ($isChoice) {
            $correct = strtoupper(trim($item['correct_option'] ?? ''));
            $given = strtoupper(substr($answer, 0, 1));
            $isCorrect = $given === $correct ? 1 : 0;
            $feedback = $isCorrect
                ? 'Correct. ' . ($item['explanation'] ?? '')
                : 'Not quite. ' . ($item['explanation'] ?? '');
        } else {
            $system = "You are an English coach. Grade the learner response. Return ONLY valid JSON: {\"score\":0-100,\"is_correct\":true/false,\"feedback\":\"2 clear sentences\",\"model_answer\":\"short example\"}.";
            $prompt = "Practice type: {$item['type']}\nPrompt: {$item['prompt']}\nLearner answer/transcript: {$answer}\nExpected guidance: {$item['explanation']}";
            $ai = callAI([['role' => 'user', 'content' => $prompt]], $system, 700);
            $graded = jsonFromAI($ai);
            $score = (int)($graded['score'] ?? 50);
            $isCorrect = $score >= 70 ? 1 : 0;
            $feedback = $graded['feedback'] ?? $ai;
            if (!empty($graded['model_answer'])) {
                $feedback .= "\nExample: " . $graded['model_answer'];
            }
        }

        $already = $db->query("SELECT id FROM user_practice_attempts WHERE user_id=$uid AND practice_item_id=$itemId AND xp_earned > 0 LIMIT 1");
        $canEarn = !$already || $already->num_rows === 0;
        $xpEarned = ($isCorrect && $canEarn) ? (int)$item['xp_reward'] : 0;
        $ansEsc = esc($answer);
        $fbEsc = esc($feedback);
        $db->query("INSERT INTO user_practice_attempts (user_id, practice_item_id, answer, is_correct, ai_feedback, xp_earned) VALUES ($uid,$itemId,'$ansEsc',$isCorrect,'$fbEsc',$xpEarned)");
        if ($xpEarned > 0) {
            addXP($uid, $xpEarned, 'Practice Lab: ' . $item['title']);
        }

        $_SESSION['practice_result'] = [
            'ok' => $isCorrect,
            'xp' => $xpEarned,
            'title' => $item['title'],
            'feedback' => $feedback
        ];
    }

    header('Location: practice.php');
    exit;
}

$result = $_SESSION['practice_result'] ?? null;
unset($_SESSION['practice_result']);

$type = $_GET['type'] ?? 'all';
$difficulty = $_GET['difficulty'] ?? 'all';
$where = ["active=1"];
if ($type !== 'all') $where[] = "type='" . esc($type) . "'";
if ($difficulty !== 'all') $where[] = "difficulty='" . esc($difficulty) . "'";
$whereSql = implode(' AND ', $where);

$items = $db->query("
    SELECT pi.*,
      (SELECT COUNT(*) FROM user_practice_attempts upa WHERE upa.practice_item_id=pi.id AND upa.user_id=$uid) AS attempts,
      (SELECT MAX(is_correct) FROM user_practice_attempts upa WHERE upa.practice_item_id=pi.id AND upa.user_id=$uid) AS mastered
    FROM practice_items pi
    WHERE $whereSql
    ORDER BY pi.created_at DESC, pi.id DESC
    LIMIT 60
");

$stats = $db->query("SELECT COUNT(*) attempts, SUM(is_correct) correct, SUM(xp_earned) xp FROM user_practice_attempts WHERE user_id=$uid")->fetch_assoc();

include 'includes/header.php';
?>

<div class="page-header flex-between" style="flex-wrap:wrap;gap:12px;">
  <div>
    <h1>Practice Lab</h1>
    <p>Fast English drills for grammar, vocabulary, writing, and speaking practice.</p>
  </div>
  <a href="speaking.php" class="btn btn-outline">Open Speaking Practice</a>
</div>

<?php if ($result): ?>
<div class="alert <?= $result['ok'] ? 'alert-success' : 'alert-warn' ?>" style="margin-bottom:24px;">
  <div>
    <div style="font-weight:800;margin-bottom:6px;">
      <?= $result['ok'] ? 'Correct' : 'Keep practicing' ?><?= $result['xp'] ? ' · +' . (int)$result['xp'] . ' XP' : '' ?>
    </div>
    <div style="font-size:13px;white-space:pre-wrap;line-height:1.6"><?= clean($result['feedback']) ?></div>
  </div>
</div>
<?php if ($result['xp']): ?>
<script>window.addEventListener('load',()=>emToast('+<?= (int)$result['xp'] ?> XP earned','xp'));</script>
<?php endif; ?>
<?php endif; ?>

<div class="grid-3 mb-24">
  <div class="stat-card">
    <div class="stat-icon">#</div>
    <div><div class="stat-val"><?= (int)($stats['attempts'] ?? 0) ?></div><div class="stat-label">Attempts</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">%</div>
    <div>
      <?php $attempts = max(1, (int)($stats['attempts'] ?? 0)); $correct = (int)($stats['correct'] ?? 0); ?>
      <div class="stat-val" style="color:var(--green)"><?= round(($correct / $attempts) * 100) ?>%</div>
      <div class="stat-label">Accuracy</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">XP</div>
    <div><div class="stat-val" style="color:var(--yellow)"><?= (int)($stats['xp'] ?? 0) ?></div><div class="stat-label">Practice XP</div></div>
  </div>
</div>

<div class="card mb-16" style="padding:16px 20px;">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <select name="type" class="form-control" style="width:220px">
      <option value="all" <?= $type==='all'?'selected':'' ?>>All practice types</option>
      <option value="better_english" <?= $type==='better_english'?'selected':'' ?>>Choose Better English</option>
      <option value="grammar_choice" <?= $type==='grammar_choice'?'selected':'' ?>>Grammar Choice</option>
      <option value="vocabulary_quiz" <?= $type==='vocabulary_quiz'?'selected':'' ?>>Vocabulary Quiz</option>
      <option value="writing_prompt" <?= $type==='writing_prompt'?'selected':'' ?>>Writing Prompt</option>
      <option value="speaking_prompt" <?= $type==='speaking_prompt'?'selected':'' ?>>Read Aloud</option>
    </select>
    <select name="difficulty" class="form-control" style="width:170px">
      <option value="all" <?= $difficulty==='all'?'selected':'' ?>>All levels</option>
      <option value="beginner" <?= $difficulty==='beginner'?'selected':'' ?>>Beginner</option>
      <option value="intermediate" <?= $difficulty==='intermediate'?'selected':'' ?>>Intermediate</option>
      <option value="advanced" <?= $difficulty==='advanced'?'selected':'' ?>>Advanced</option>
    </select>
    <button class="btn btn-primary btn-sm">Filter</button>
    <a href="practice.php" class="btn btn-ghost btn-sm">Clear</a>
  </form>
</div>

<?php if ($items && $items->num_rows > 0): ?>
<div class="grid-2" style="gap:16px;align-items:start;">
  <?php while ($item = $items->fetch_assoc()):
    $isChoice = in_array($item['type'], ['better_english', 'grammar_choice', 'vocabulary_quiz'], true);
    $typeLabel = ucwords(str_replace('_', ' ', $item['type']));
  ?>
  <div class="challenge-card <?= (int)$item['mastered'] ? 'completed' : 'active' ?>">
    <div class="challenge-header">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <span class="challenge-type ac-badge badge-blue"><?= clean($typeLabel) ?></span>
        <span class="challenge-type ac-badge diff-<?= clean($item['difficulty']) ?>"><?= ucfirst($item['difficulty']) ?></span>
      </div>
      <div class="challenge-xp">+<?= (int)$item['xp_reward'] ?> XP</div>
    </div>
    <div class="challenge-title"><?= clean($item['title']) ?></div>
    <div class="challenge-desc" style="white-space:pre-wrap"><?= clean($item['prompt']) ?></div>
    <?php if ((int)$item['attempts'] > 0): ?>
    <div style="font-size:12px;color:var(--text-3);margin:10px 0;">Attempts: <?= (int)$item['attempts'] ?><?= (int)$item['mastered'] ? ' · mastered' : '' ?></div>
    <?php endif; ?>

    <form method="POST" style="margin-top:14px;">
      <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
      <?php if ($isChoice): ?>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
          <?php foreach (['A','B','C'] as $letter):
            $key = 'option_' . strtolower($letter);
            if (empty($item[$key])) continue;
          ?>
          <label style="display:flex;gap:10px;align-items:flex-start;background:var(--bg-base);border:1px solid var(--border);border-radius:10px;padding:12px;cursor:pointer;">
            <input type="radio" name="answer" value="<?= $letter ?>" required style="margin-top:3px;">
            <span><strong><?= $letter ?>.</strong> <?= clean($item[$key]) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <textarea name="answer" class="form-control" rows="4" placeholder="<?= $item['type']==='speaking_prompt' ? 'Paste your read-aloud transcript or notes here...' : 'Write your answer here...' ?>" required></textarea>
      <?php endif; ?>
      <button type="submit" name="submit_practice" class="btn btn-primary btn-sm" onclick="btnLoading(this,true)">Submit</button>
    </form>
  </div>
  <?php endwhile; ?>
</div>
<?php else: ?>
<div class="empty-state">
  <span class="empty-icon">#</span>
  <h3>No practice items found</h3>
  <p>Ask an admin to add or generate more exercises.</p>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
