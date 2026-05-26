<?php
require_once __DIR__ . '/../config.php';
auth();

$module = $module ?? 'vocabulary_lesson';
$moduleTitle = $moduleTitle ?? 'AI Exercise';
$moduleDescription = $moduleDescription ?? 'Generate a focused English learning exercise.';
$pageTitle = $moduleTitle;

$level = $_POST['level'] ?? 'Beginner';
$topic = trim($_POST['topic'] ?? '');
$answer = trim($_POST['answer'] ?? '');
$output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_module'])) {
    $prompt = learningModulePrompt($module, $level, $topic, $answer);
    $system = 'You are an advanced English Learning AI Tutor inside a web application. Follow the requested output format exactly. Be concise, educational, and never skip explanations.';
    $output = callAI([['role' => 'user', 'content' => $prompt]], $system, 1800);
    addXP((int)$_SESSION['uid'], 10, $moduleTitle . ' generated');
}

include __DIR__ . '/header.php';
?>

<div class="page-header flex-between" style="flex-wrap:wrap;gap:12px;">
  <div>
    <h1><?= clean($moduleTitle) ?></h1>
    <p><?= clean($moduleDescription) ?></p>
  </div>
  <a href="practice.php" class="btn btn-outline">Back to Practice Lab</a>
</div>

<div class="grid-2" style="gap:24px;align-items:start;">
  <div class="card">
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Difficulty</label>
        <select name="level" class="form-control">
          <?php foreach (['Beginner','Intermediate','Advanced'] as $lv): ?>
          <option value="<?= $lv ?>" <?= $level===$lv?'selected':'' ?>><?= $lv ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Topic or skill</label>
        <input name="topic" class="form-control" value="<?= clean($topic) ?>" placeholder="e.g., job interviews, past tense, travel, customer service">
      </div>
      <?php if ($module === 'analytical_english'): ?>
      <div class="form-group">
        <label class="form-label">Your answer for evaluation</label>
        <textarea name="answer" class="form-control" rows="5" placeholder="Optional: paste a 3-5 sentence answer here to receive scores, corrections, and an improved version."><?= clean($answer) ?></textarea>
      </div>
      <?php endif; ?>
      <button class="btn btn-primary" name="generate_module" onclick="btnLoading(this,true)">Generate Exercise</button>
    </form>
  </div>

  <div class="card">
    <h3 style="font-size:16px;margin-bottom:14px;color:var(--text-1)">Output</h3>
    <?php if ($output): ?>
      <div style="white-space:pre-wrap;line-height:1.8;color:var(--text-1);font-size:14px;"><?= clean($output) ?></div>
    <?php else: ?>
      <div style="color:var(--text-2);font-size:14px;line-height:1.7;">
        Choose a level, enter a topic, then generate. The AI will follow the strict format for this module.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
