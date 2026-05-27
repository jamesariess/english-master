<?php
require_once 'config.php';
auth();

$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Grammar & Vocabulary Quizzes';
$quizTypes = [
    'tense_quiz' => 'Past, Present & Future Tense',
    'synonyms_antonyms_quiz' => 'Synonyms & Antonyms',
    'sentence_meaning_quiz' => 'Sentence Meaning'
];
$selected = $_GET['quiz'] ?? 'all';
if ($selected !== 'all' && !isset($quizTypes[$selected])) $selected = 'all';
$result = $_SESSION['quiz_result'] ?? null;
unset($_SESSION['quiz_result']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $answer = strtoupper(substr(trim($_POST['answer'] ?? ''), 0, 1));
    $returnQuiz = $_POST['quiz'] ?? $selected;
    if ($returnQuiz !== 'all' && !isset($quizTypes[$returnQuiz])) $returnQuiz = 'all';
    $res = $db->query("SELECT * FROM practice_items WHERE id=$itemId AND active=1 AND type IN ('tense_quiz','synonyms_antonyms_quiz','sentence_meaning_quiz') LIMIT 1");
    $item = $res ? $res->fetch_assoc() : null;

    if ($item && in_array($answer, ['A','B','C','D'], true)) {
        $correct = strtoupper(trim($item['correct_option'] ?? ''));
        $isCorrect = $answer === $correct ? 1 : 0;
        $feedback = $isCorrect ? 'Correct. ' : 'Not quite. ';
        $feedback .= $item['explanation'] ?: ($item['answer_key'] ? 'Answer: ' . $item['answer_key'] : '');
        $already = $db->query("SELECT id FROM user_practice_attempts WHERE user_id=$uid AND practice_item_id=$itemId AND xp_earned > 0 LIMIT 1");
        $canEarn = !$already || $already->num_rows === 0;
        $xpEarned = ($isCorrect && $canEarn) ? (int)$item['xp_reward'] : 0;
        $ansEsc = esc($answer);
        $fbEsc = esc($feedback);
        $db->query("INSERT INTO user_practice_attempts (user_id, practice_item_id, answer, is_correct, ai_feedback, xp_earned) VALUES ($uid,$itemId,'$ansEsc',$isCorrect,'$fbEsc',$xpEarned)");
        if ($xpEarned > 0) addXP($uid, $xpEarned, 'Quiz: ' . $item['title']);
        $_SESSION['quiz_result'] = [
            'ok' => $isCorrect,
            'xp' => $xpEarned,
            'title' => $item['title'],
            'feedback' => $feedback
        ];
    }

    header('Location: quizzes.php?quiz=' . urlencode($returnQuiz));
    exit;
}

$where = ["active=1", "type IN ('tense_quiz','synonyms_antonyms_quiz','sentence_meaning_quiz')"];
if ($selected !== 'all') $where[] = "type='" . esc($selected) . "'";
$whereSql = implode(' AND ', $where);
$items = $db->query("
    SELECT pi.*,
      (SELECT COUNT(*) FROM user_practice_attempts upa WHERE upa.practice_item_id=pi.id AND upa.user_id=$uid) AS attempts,
      (SELECT MAX(is_correct) FROM user_practice_attempts upa WHERE upa.practice_item_id=pi.id AND upa.user_id=$uid) AS mastered
    FROM practice_items pi
    WHERE $whereSql
    ORDER BY pi.type, pi.created_at DESC, pi.id DESC
    LIMIT 80
");
$stats = $db->query("SELECT COUNT(*) attempts, SUM(is_correct) correct, SUM(xp_earned) xp FROM user_practice_attempts upa JOIN practice_items pi ON pi.id=upa.practice_item_id WHERE upa.user_id=$uid AND pi.type IN ('tense_quiz','synonyms_antonyms_quiz','sentence_meaning_quiz')")->fetch_assoc();

include 'includes/header.php';
?>

<div class="page-header flex-between" style="flex-wrap:wrap;gap:12px;">
  <div>
    <h1>Grammar & Vocabulary Quizzes</h1>
    <p>Choose a quiz type, answer multiple-choice questions, and build tense control, vocabulary depth, and sentence understanding.</p>
  </div>
  <a href="practice.php" class="btn btn-outline">Practice Lab</a>
</div>

<?php if ($result): ?>
<div class="alert <?= $result['ok'] ? 'alert-success' : 'alert-warn' ?>" style="margin-bottom:24px;">
  <div>
    <div style="font-weight:800;margin-bottom:6px;"><?= $result['ok'] ? 'Correct' : 'Keep practicing' ?><?= $result['xp'] ? ' · +' . (int)$result['xp'] . ' XP' : '' ?></div>
    <div style="font-size:13px;line-height:1.6"><?= clean($result['feedback']) ?></div>
  </div>
</div>
<?php endif; ?>

<div class="grid-3 mb-24">
  <div class="stat-card"><div class="stat-icon">Q</div><div><div class="stat-val"><?= (int)($stats['attempts'] ?? 0) ?></div><div class="stat-label">Attempts</div></div></div>
  <div class="stat-card">
    <div class="stat-icon">%</div>
    <div>
      <?php $attempts = max(1, (int)($stats['attempts'] ?? 0)); $correct = (int)($stats['correct'] ?? 0); ?>
      <div class="stat-val" style="color:var(--green)"><?= round(($correct / $attempts) * 100) ?>%</div>
      <div class="stat-label">Quiz Accuracy</div>
    </div>
  </div>
  <div class="stat-card"><div class="stat-icon">XP</div><div><div class="stat-val" style="color:var(--yellow)"><?= (int)($stats['xp'] ?? 0) ?></div><div class="stat-label">Quiz XP</div></div></div>
</div>

<div class="card mb-24" style="padding:16px 20px;">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <select name="quiz" class="form-control" style="max-width:320px;">
      <option value="all" <?= $selected==='all'?'selected':'' ?>>All quiz types</option>
      <?php foreach ($quizTypes as $type => $label): ?>
      <option value="<?= clean($type) ?>" <?= $selected===$type?'selected':'' ?>><?= clean($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm">Show Quiz</button>
  </form>
</div>

<?php if ($items && $items->num_rows > 0): ?>
<div class="grid-2" style="gap:16px;align-items:start;">
  <?php while ($item = $items->fetch_assoc()):
    $label = $quizTypes[$item['type']] ?? ucwords(str_replace('_', ' ', $item['type']));
  ?>
  <div class="challenge-card <?= (int)$item['mastered'] ? 'completed' : 'active' ?>">
    <div class="challenge-header">
      <span class="challenge-type ac-badge badge-blue"><?= clean($label) ?></span>
      <div class="challenge-xp">+<?= (int)$item['xp_reward'] ?> XP</div>
    </div>
    <div class="challenge-title"><?= clean($item['title']) ?></div>
    <div class="challenge-desc" style="white-space:pre-wrap"><?= clean($item['prompt']) ?></div>
    <?php if ((int)$item['attempts'] > 0): ?>
    <div style="font-size:12px;color:var(--text-3);margin:10px 0;">Attempts: <?= (int)$item['attempts'] ?><?= (int)$item['mastered'] ? ' · mastered' : '' ?></div>
    <?php endif; ?>
    <form method="POST" style="margin-top:14px;">
      <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
      <input type="hidden" name="quiz" value="<?= clean($selected) ?>">
      <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
        <?php foreach (['A','B','C','D'] as $letter):
          $key = 'option_' . strtolower($letter);
          if (empty($item[$key])) continue;
        ?>
        <label style="display:flex;gap:10px;align-items:flex-start;background:var(--bg-base);border:1px solid var(--border);border-radius:10px;padding:12px;cursor:pointer;">
          <input type="radio" name="answer" value="<?= $letter ?>" required style="margin-top:3px;">
          <span><strong><?= $letter ?>.</strong> <?= clean($item[$key]) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" name="submit_quiz" class="btn btn-primary btn-sm">Check Answer</button>
    </form>
  </div>
  <?php endwhile; ?>
</div>
<?php else: ?>
<div class="empty-state">
  <span class="empty-icon">Q</span>
  <h3>No quizzes yet</h3>
  <p>Add quiz items in the Admin Panel or generate them with AI.</p>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
