<?php
require_once 'config.php';
auth();

$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Word to 5 Sentences';
$word = $_SESSION['builder_word'] ?? null;
$lesson = $_SESSION['builder_lesson'] ?? null;
$result = null;

function sentenceCount($text) {
    $parts = preg_split('/[.!?]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    return count(array_filter(array_map('trim', $parts)));
}

if (!$word || isset($_GET['new'])) {
    $row = $db->query("SELECT * FROM vocabulary WHERE active=1 ORDER BY RAND() LIMIT 1")->fetch_assoc();
    if ($row) {
        $word = $row['word'];
        $lesson = [
            'meaning' => $row['meaning'],
            'example' => $row['example_sentence'],
            'tip' => 'Use this word naturally in real sentences. Keep each sentence complete with a subject and verb.'
        ];
    } else {
        $word = 'confident';
        $lesson = ['meaning' => 'feeling sure about your ability', 'example' => 'She feels confident when speaking English.', 'tip' => 'Use confident to describe a person who feels sure.'];
    }
    $_SESSION['builder_word'] = $word;
    $_SESSION['builder_lesson'] = $lesson;
    unset($_SESSION['builder_needs_extra']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sentences = trim($_POST['sentences'] ?? '');
    $extra = trim($_POST['extra_sentences'] ?? '');
    $needsExtra = !empty($_SESSION['builder_needs_extra']);
    $required = $needsExtra ? 2 : 5;
    $textToCheck = $needsExtra ? $extra : $sentences;

    if (sentenceCount($textToCheck) < $required) {
        $result = [
            'ok' => false,
            'score' => 0,
            'feedback' => "Please write at least $required complete sentence(s).",
            'corrections' => [],
            'improved' => ''
        ];
    } else {
        $system = 'You are an English tutor. Evaluate sentence writing. Return ONLY valid JSON: {"score":0-100,"all_use_word":true/false,"feedback":"short feedback","corrections":["..."],"improved_sentences":["..."]}. Explain mistakes clearly.';
        $prompt = "Target word: $word\nRequired sentences: $required\nLearner sentences:\n$textToCheck\n\nCheck grammar, natural usage, and whether every sentence uses the target word correctly.";
        $ai = callAI([['role' => 'user', 'content' => $prompt]], $system, 1000);
        $graded = jsonFromAI($ai);
        $score = (int)($graded['score'] ?? 50);
        $ok = $score >= 75 && !empty($graded['all_use_word']);
        if (!$ok && !$needsExtra) $_SESSION['builder_needs_extra'] = true;
        if ($ok) unset($_SESSION['builder_needs_extra']);

        $result = [
            'ok' => $ok,
            'score' => $score,
            'feedback' => $graded['feedback'] ?? $ai,
            'corrections' => $graded['corrections'] ?? [],
            'improved' => implode("\n", $graded['improved_sentences'] ?? [])
        ];
        $xp = $ok ? 30 : 0;
        if ($xp) addXP($uid, $xp, 'Word to 5 Sentences');
    }
}

include 'includes/header.php';
?>

<div class="page-header flex-between" style="gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Word to 5 Sentences</h1>
    <p>Write five original sentences using the target word. If some are wrong, write two new sentences to repair the skill.</p>
  </div>
  <a href="sentence_builder.php?new=1" class="btn btn-outline">New Word</a>
</div>

<div class="grid-2" style="gap:24px;align-items:start;">
  <div class="card">
    <div class="stat-card" style="margin-bottom:18px;">
      <div class="stat-icon">W</div>
      <div>
        <div class="stat-val" style="font-size:28px;color:var(--blue)"><?= clean($word) ?></div>
        <div class="stat-label"><?= clean($lesson['meaning'] ?? '') ?></div>
      </div>
    </div>
    <?php if (!empty($lesson['example'])): ?>
    <div class="vocab-example" style="margin-bottom:12px;"><?= clean($lesson['example']) ?></div>
    <?php endif; ?>
    <div style="font-size:13px;color:var(--text-2);line-height:1.7;"><?= clean($lesson['tip'] ?? '') ?></div>
  </div>

  <div class="card">
    <?php if ($result): ?>
    <div class="alert <?= $result['ok'] ? 'alert-success' : 'alert-warn' ?>" style="margin-bottom:18px;">
      <div>
        <strong>Score: <?= (int)$result['score'] ?>/100</strong>
        <div style="margin-top:6px;line-height:1.7;"><?= clean($result['feedback']) ?></div>
        <?php if ($result['corrections']): ?><div style="margin-top:8px;white-space:pre-wrap;"><?= clean(implode("\n", $result['corrections'])) ?></div><?php endif; ?>
        <?php if ($result['improved']): ?><div style="margin-top:8px;white-space:pre-wrap;"><strong>Better examples:</strong><br><?= clean($result['improved']) ?></div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <form method="POST">
      <?php if (!empty($_SESSION['builder_needs_extra'])): ?>
      <div class="form-group">
        <label class="form-label">Write 2 new sentences using "<?= clean($word) ?>"</label>
        <textarea name="extra_sentences" class="form-control" rows="5" placeholder="Write two new sentences you did not use before." required></textarea>
      </div>
      <?php else: ?>
      <div class="form-group">
        <label class="form-label">Write 5 sentences using "<?= clean($word) ?>"</label>
        <textarea name="sentences" class="form-control" rows="9" placeholder="1. ...
2. ...
3. ...
4. ...
5. ..." required></textarea>
      </div>
      <?php endif; ?>
      <button class="btn btn-primary" onclick="btnLoading(this,true)">Check My Sentences</button>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
