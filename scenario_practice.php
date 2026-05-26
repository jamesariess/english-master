<?php
require_once 'config.php';
auth();

$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Real-Life Scenario Practice';
$scenario = $_SESSION['scenario_task'] ?? null;
$result = null;

function generateScenario($topic = 'daily English') {
    $system = 'You create English speaking scenarios. Return ONLY valid JSON: {"title":"","situation":"","user_task":"","best_response_example":"","criteria":["..."]}.';
    $prompt = "Create one short real-life English scenario about $topic. Make it practical for an English learner.";
    $data = jsonFromAI(callAI([['role'=>'user','content'=>$prompt]], $system, 900));
    if (!$data) {
        $data = [
            'title' => 'Ordering Food',
            'situation' => 'You are at a cafe. The cashier asks what you would like to order.',
            'user_task' => 'Reply politely and clearly. Include the item, size, and one extra request.',
            'best_response_example' => 'I would like a medium coffee, please. Could you add less sugar?',
            'criteria' => ['Politeness', 'Clarity', 'Complete request']
        ];
    }
    return $data;
}

if (isset($_GET['new']) || !$scenario) {
    $topic = trim($_GET['topic'] ?? 'daily English');
    $scenario = generateScenario($topic);
    $_SESSION['scenario_task'] = $scenario;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_scenario'])) {
    $answer = trim($_POST['answer'] ?? '');
    if ($answer === '') {
        $result = ['score'=>0,'feedback'=>'Please type or record your response first.','corrections'=>[],'improved'=>''];
    } else {
        $system = 'You are an English conversation coach. Return ONLY valid JSON: {"score":0-100,"feedback":"short feedback","corrections":["..."],"improved_response":"...","criteria":{"grammar":0,"clarity":0,"naturalness":0,"politeness":0}}.';
        $prompt = "Scenario: {$scenario['situation']}\nUser task: {$scenario['user_task']}\nBest example: {$scenario['best_response_example']}\nLearner response: $answer\nEvaluate grammar, clarity, naturalness, and politeness. Explain mistakes.";
        $graded = jsonFromAI(callAI([['role'=>'user','content'=>$prompt]], $system, 1000));
        $result = [
            'score' => (int)($graded['score'] ?? 50),
            'feedback' => $graded['feedback'] ?? 'Response checked.',
            'corrections' => $graded['corrections'] ?? [],
            'improved' => $graded['improved_response'] ?? '',
            'criteria' => $graded['criteria'] ?? []
        ];
        if ($result['score'] >= 70) addXP($uid, 30, 'Scenario Practice');
    }
}

include 'includes/header.php';
?>

<div class="page-header flex-between" style="gap:12px;flex-wrap:wrap;">
  <div>
    <h1>Real-Life Scenario Practice</h1>
    <p>Read the situation, answer naturally by typing or voice, then get AI feedback.</p>
  </div>
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
    <input name="topic" class="form-control" style="width:220px" placeholder="topic: airport, work, food">
    <button class="btn btn-outline" name="new" value="1">New Scenario</button>
  </form>
</div>

<div class="grid-2" style="gap:24px;align-items:start;">
  <div class="card">
    <span class="ac-badge badge-blue">Scenario</span>
    <h2 style="font-size:24px;color:var(--text-1);margin:14px 0;"><?= clean($scenario['title'] ?? 'Scenario') ?></h2>
    <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:14px;padding:18px;line-height:1.8;color:var(--text-1);">
      <?= clean($scenario['situation'] ?? '') ?>
    </div>
    <div style="margin-top:16px;color:var(--text-2);line-height:1.7;">
      <strong style="color:var(--text-1);">Your task:</strong> <?= clean($scenario['user_task'] ?? '') ?>
    </div>
    <?php if (!empty($scenario['criteria'])): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
      <?php foreach ((array)$scenario['criteria'] as $c): ?><span class="ac-badge badge-purple"><?= clean($c) ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <?php if ($result): ?>
    <div class="alert <?= $result['score'] >= 70 ? 'alert-success' : 'alert-warn' ?>" style="margin-bottom:18px;">
      <div>
        <strong>Score: <?= (int)$result['score'] ?>/100</strong>
        <div style="margin-top:6px;line-height:1.7;"><?= clean($result['feedback']) ?></div>
        <?php if (!empty($result['criteria'])): ?>
        <div style="margin-top:8px;font-size:13px;">
          <?php foreach ($result['criteria'] as $k => $v): ?><span class="ac-badge badge-blue"><?= clean($k) ?>: <?= (int)$v ?></span> <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($result['corrections']): ?><div style="margin-top:8px;white-space:pre-wrap;"><?= clean(implode("\n", $result['corrections'])) ?></div><?php endif; ?>
        <?php if ($result['improved']): ?><div style="margin-top:8px;"><strong>Improved response:</strong><br><?= clean($result['improved']) ?></div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Your response</label>
        <textarea id="answerBox" name="answer" class="form-control" rows="7" placeholder="Type your natural response here..." required><?= clean($_POST['answer'] ?? '') ?></textarea>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-primary" name="check_scenario" onclick="btnLoading(this,true)">Check Response</button>
        <button class="btn btn-outline" type="button" onclick="startVoice()">Voice Input</button>
      </div>
    </form>
  </div>
</div>

<script>
function startVoice() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) { emToast('Voice input is not supported in this browser.', 'warn'); return; }
  const rec = new SpeechRecognition();
  rec.lang = 'en-US';
  rec.interimResults = false;
  rec.onresult = e => document.getElementById('answerBox').value = e.results[0][0].transcript;
  rec.start();
}
</script>

<?php include 'includes/footer.php'; ?>
