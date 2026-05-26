<?php
require_once 'config.php';
auth();

$uid = (int)$_SESSION['uid'];
$pageTitle = 'Analytical English';
$question = $_SESSION['analytical_question'] ?? null;
$result = null;

function countSentencesStrict($text) {
    $parts = preg_split('/[.!?]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    return count(array_filter(array_map('trim', $parts)));
}

function generateAnalyticalQuestion($topic = 'daily life') {
    $system = 'Create critical thinking questions for English learners. Return ONLY valid JSON: {"question":"","criteria":["Grammar","Clarity","Logic","Vocabulary"]}.';
    $data = jsonFromAI(callAI([['role'=>'user','content'=>"Create one real-life thinking question about $topic. The answer should require 3-5 sentences."]], $system, 700));
    return $data ?: [
        'question' => 'Do you think practicing English every day is more effective than studying for many hours once a week? Explain your answer.',
        'criteria' => ['Grammar', 'Clarity', 'Logic', 'Vocabulary']
    ];
}

if (isset($_GET['new']) || !$question) {
    $question = generateAnalyticalQuestion(trim($_GET['topic'] ?? 'daily life'));
    $_SESSION['analytical_question'] = $question;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_analytical'])) {
    $answer = trim($_POST['answer'] ?? '');
    $count = countSentencesStrict($answer);
    if ($count < 3 || $count > 5) {
        $result = [
            'accepted' => false,
            'feedback' => "Your answer has $count sentence(s). Please answer in 3 to 5 complete sentences.",
            'scores' => []
        ];
    } else {
        $system = 'You are an analytical English tutor. Return ONLY valid JSON: {"grammar":0-100,"clarity":0-100,"logic":0-100,"vocabulary":0-100,"feedback":"overall feedback","corrections":["..."],"improved_answer":"...","mistake_explanations":["..."]}. Never skip explanations.';
        $prompt = "Question: {$question['question']}\nLearner answer:\n$answer\nEvaluate the answer using grammar, clarity, logic, and vocabulary. Correct mistakes and improve the answer.";
        $graded = jsonFromAI(callAI([['role'=>'user','content'=>$prompt]], $system, 1200));
        $scores = [
            'Grammar' => (int)($graded['grammar'] ?? 50),
            'Clarity' => (int)($graded['clarity'] ?? 50),
            'Logic' => (int)($graded['logic'] ?? 50),
            'Vocabulary' => (int)($graded['vocabulary'] ?? 50)
        ];
        $avg = (int)round(array_sum($scores) / 4);
        $result = [
            'accepted' => true,
            'score' => $avg,
            'scores' => $scores,
            'feedback' => $graded['feedback'] ?? 'Answer evaluated.',
            'corrections' => $graded['corrections'] ?? [],
            'improved' => $graded['improved_answer'] ?? '',
            'explanations' => $graded['mistake_explanations'] ?? []
        ];
        if ($avg >= 70) addXP($uid, 40, 'Analytical English');
    }
}

include 'includes/header.php';
?>

<div class="page-header flex-between" style="gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Analytical English</h1>
    <p>Answer a real-life thinking question in 3 to 5 sentences and receive scored feedback.</p>
  </div>
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
    <input name="topic" class="form-control" style="width:220px" placeholder="topic: work, school, goals">
    <button class="btn btn-outline" name="new" value="1">New Question</button>
  </form>
</div>

<div class="grid-2" style="gap:24px;align-items:start;">
  <div class="card">
    <span class="ac-badge badge-purple">Critical Thinking</span>
    <h2 style="font-size:22px;color:var(--text-1);margin:16px 0;line-height:1.4;"><?= clean($question['question']) ?></h2>
    <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:12px;padding:16px;color:var(--text-2);line-height:1.7;">
      User Task: Answer in 3-5 sentences. Your answer must have a clear opinion, supporting reason, and conclusion.
    </div>
    <div class="grid-4 mt-16">
      <?php foreach (['Grammar','Clarity','Logic','Vocabulary'] as $c): ?>
      <div class="stat-card"><div class="stat-icon"><?= substr($c,0,2) ?></div><div><div class="stat-label"><?= $c ?></div><div class="stat-val" style="font-size:18px">0-100</div></div></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <?php if ($result): ?>
    <div class="alert <?= !empty($result['accepted']) && ($result['score'] ?? 0) >= 70 ? 'alert-success' : 'alert-warn' ?>" style="margin-bottom:18px;">
      <div>
        <?php if (!empty($result['accepted'])): ?>
          <strong>Overall Score: <?= (int)$result['score'] ?>/100</strong>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
            <?php foreach ($result['scores'] as $k => $v): ?><span class="ac-badge badge-blue"><?= clean($k) ?>: <?= (int)$v ?></span><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div style="margin-top:8px;line-height:1.7;"><?= clean($result['feedback']) ?></div>
        <?php if (!empty($result['corrections'])): ?><div style="margin-top:8px;white-space:pre-wrap;"><strong>Corrections</strong><br><?= clean(implode("\n", $result['corrections'])) ?></div><?php endif; ?>
        <?php if (!empty($result['improved'])): ?><div style="margin-top:8px;"><strong>Improved version</strong><br><?= clean($result['improved']) ?></div><?php endif; ?>
        <?php if (!empty($result['explanations'])): ?><div style="margin-top:8px;white-space:pre-wrap;"><strong>Explanation of mistakes</strong><br><?= clean(implode("\n", $result['explanations'])) ?></div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Your 3-5 sentence answer</label>
        <textarea name="answer" class="form-control" rows="8" placeholder="Write 3 to 5 complete sentences." required><?= clean($_POST['answer'] ?? '') ?></textarea>
      </div>
      <button class="btn btn-primary" name="check_analytical" onclick="btnLoading(this,true)">Evaluate Answer</button>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
