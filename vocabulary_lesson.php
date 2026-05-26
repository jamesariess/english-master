<?php
require_once 'config.php';
auth();

$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Vocabulary Lesson';
$result = null;

if (isset($_GET['new']) || empty($_SESSION['vocab_lesson_words'])) {
    $rows = [];
    $res = $db->query("SELECT * FROM vocabulary WHERE active=1 ORDER BY RAND() LIMIT 5");
    while ($res && $row = $res->fetch_assoc()) $rows[] = $row;
    if (count($rows) < 5) {
        $rows = [
            ['word'=>'confident','meaning'=>'sure about your ability','example_sentence'=>'She feels confident when speaking English.','pronunciation'=>'KON-fi-dent'],
            ['word'=>'improve','meaning'=>'to become better','example_sentence'=>'I improve my English every day.','pronunciation'=>'im-PROOV'],
            ['word'=>'clear','meaning'=>'easy to understand','example_sentence'=>'Please give a clear answer.','pronunciation'=>'kleer'],
            ['word'=>'practice','meaning'=>'to do something repeatedly to get better','example_sentence'=>'Practice helps you speak faster.','pronunciation'=>'PRAK-tis'],
            ['word'=>'natural','meaning'=>'normal and comfortable','example_sentence'=>'That sentence sounds natural.','pronunciation'=>'NACH-uh-ral']
        ];
    }
    $_SESSION['vocab_lesson_words'] = $rows;
}
$words = $_SESSION['vocab_lesson_words'];

$paragraphWords = implode(', ', array_map(fn($w) => $w['word'], $words));
$paragraph = "A strong English learner practices every day. When you learn words like $paragraphWords, you should use them in real sentences, short conversations, and simple paragraphs. This helps the words become natural instead of memorized only for a quiz.";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_vocab_lesson'])) {
    $sentences = $_POST['sentences'] ?? [];
    $quiz = $_POST['quiz'] ?? [];
    $quizCorrect = 0;
    foreach ($words as $idx => $w) {
        if (strtolower(trim($quiz[$idx] ?? '')) === strtolower($w['word'])) $quizCorrect++;
    }
    $system = 'You are an English vocabulary tutor. Return ONLY valid JSON: {"score":0-100,"feedback":"clear feedback","corrections":["..."],"improved_sentences":["..."]}. Check whether each learner sentence uses the assigned word correctly.';
    $payload = [];
    foreach ($words as $idx => $w) {
        $payload[] = $w['word'] . ': ' . trim($sentences[$idx] ?? '');
    }
    $ai = callAI([['role'=>'user','content'=>"Evaluate these vocabulary sentences:\n" . implode("\n", $payload)]], $system, 1200);
    $graded = jsonFromAI($ai);
    $score = (int)($graded['score'] ?? 50);
    $finalScore = (int)round(($score * 0.75) + (($quizCorrect / max(1, count($words))) * 100 * 0.25));
    $ok = $finalScore >= 70;
    $result = [
        'score' => $finalScore,
        'quiz' => $quizCorrect,
        'feedback' => $graded['feedback'] ?? $ai,
        'corrections' => $graded['corrections'] ?? [],
        'improved' => $graded['improved_sentences'] ?? []
    ];
    if ($ok) addXP($uid, 35, 'Vocabulary Lesson');
}

include 'includes/header.php';
?>

<div class="page-header flex-between" style="gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Vocabulary Lesson</h1>
    <p>Learn five words, read them in a paragraph, answer quizzes, then write your own sentences.</p>
  </div>
  <a href="vocabulary_lesson.php?new=1" class="btn btn-outline">New 5 Words</a>
</div>

<?php if ($result): ?>
<div class="alert <?= $result['score'] >= 70 ? 'alert-success' : 'alert-warn' ?>" style="margin-bottom:20px;">
  <div>
    <strong>Score: <?= (int)$result['score'] ?>/100 · Quiz <?= (int)$result['quiz'] ?>/5</strong>
    <div style="margin-top:6px;line-height:1.7;"><?= clean($result['feedback']) ?></div>
    <?php if ($result['corrections']): ?><div style="margin-top:8px;white-space:pre-wrap;"><?= clean(implode("\n", $result['corrections'])) ?></div><?php endif; ?>
    <?php if ($result['improved']): ?><div style="margin-top:8px;white-space:pre-wrap;"><strong>Improved examples:</strong><br><?= clean(implode("\n", $result['improved'])) ?></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card mb-24">
  <h3 style="font-size:18px;margin-bottom:12px;color:var(--text-1)">Paragraph Using Today&apos;s Words</h3>
  <div style="line-height:1.9;color:var(--text-2);font-size:15px;"><?= clean($paragraph) ?></div>
</div>

<form method="POST">
  <div class="grid-2" style="gap:18px;align-items:start;">
    <?php foreach ($words as $idx => $w): ?>
    <div class="card">
      <div class="vocab-word"><?= clean($w['word']) ?></div>
      <div class="vocab-pron">/<?= clean($w['pronunciation'] ?? '') ?>/</div>
      <div class="vocab-meaning"><?= clean($w['meaning']) ?></div>
      <?php if (!empty($w['example_sentence'])): ?><div class="vocab-example"><?= clean($w['example_sentence']) ?></div><?php endif; ?>
      <div style="font-size:13px;color:var(--text-2);line-height:1.7;margin:12px 0;">
        <strong style="color:var(--text-1);">How to use it:</strong>
        Use "<?= clean($w['word']) ?>" in a complete sentence that shows the meaning clearly.
      </div>
      <div class="form-group">
        <label class="form-label">Quiz: Which word means "<?= clean($w['meaning']) ?>"?</label>
        <select name="quiz[<?= $idx ?>]" class="form-control" required>
          <option value="">Choose word</option>
          <?php foreach ($words as $ow): ?><option value="<?= clean($ow['word']) ?>"><?= clean($ow['word']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Your sentence using "<?= clean($w['word']) ?>"</label>
        <textarea name="sentences[<?= $idx ?>]" class="form-control" rows="3" required><?= clean($_POST['sentences'][$idx] ?? '') ?></textarea>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-primary mt-16" name="check_vocab_lesson" onclick="btnLoading(this,true)">Check Lesson</button>
</form>

<?php include 'includes/footer.php'; ?>
