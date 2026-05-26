<?php
require_once 'config.php';
auth();

$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Sentence Rearrangement';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    header('Content-Type: application/json');
    $itemId = (int)($_POST['item_id'] ?? 0);
    $answer = trim($_POST['answer'] ?? '');
    $isCorrect = (int)($_POST['is_correct'] ?? 0);
    $item = $db->query("SELECT * FROM practice_items WHERE id=$itemId AND type='sentence_rearrangement' AND active=1 LIMIT 1")->fetch_assoc();
    if (!$item) { echo json_encode(['ok' => 0]); exit; }

    $already = $db->query("SELECT id FROM user_practice_attempts WHERE user_id=$uid AND practice_item_id=$itemId AND xp_earned > 0 LIMIT 1");
    $xp = ($isCorrect && (!$already || $already->num_rows === 0)) ? (int)$item['xp_reward'] : 0;
    $ansEsc = esc($answer);
    $fbEsc = esc($item['explanation'] ?? '');
    $db->query("INSERT INTO user_practice_attempts (user_id, practice_item_id, answer, is_correct, ai_feedback, xp_earned) VALUES ($uid,$itemId,'$ansEsc',$isCorrect,'$fbEsc',$xp)");
    if ($xp) addXP($uid, $xp, 'Sentence rearrangement');
    echo json_encode(['ok' => 1, 'xp' => $xp, 'feedback' => $item['explanation']]);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$whereId = $id ? "AND id=$id" : "";
$item = $db->query("SELECT * FROM practice_items WHERE type='sentence_rearrangement' AND active=1 $whereId ORDER BY " . ($id ? "id" : "RAND()") . " LIMIT 1")->fetch_assoc();
$items = $db->query("SELECT id,title,difficulty FROM practice_items WHERE type='sentence_rearrangement' AND active=1 ORDER BY difficulty,title LIMIT 30");

include 'includes/header.php';
?>

<style>
.task-shell { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:20px; align-items:start; }
.task-board { background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:24px; }
.word-bank,.answer-bank { display:flex; flex-wrap:wrap; gap:10px; min-height:72px; padding:14px; border:1px dashed #4f8ef760; border-radius:12px; background:var(--bg-base); }
.answer-bank { border-style:solid; margin-top:10px; }
.word-chip { border:1px solid var(--border); background:var(--bg-hover); color:var(--text-1); border-radius:10px; padding:10px 14px; cursor:grab; font-weight:800; user-select:none; }
.word-chip:active { cursor:grabbing; }
.feedback-box { display:none; margin-top:16px; padding:14px 16px; border-radius:12px; line-height:1.7; }
.feedback-box.good { display:block; border:1px solid #34d39950; background:#34d39912; color:var(--green); }
.feedback-box.bad { display:block; border:1px solid #f8717150; background:#f8717112; color:var(--red); }
@media (max-width:900px){ .task-shell{grid-template-columns:1fr;} }
</style>

<div class="page-header">
  <h1>Sentence Rearrangement</h1>
  <p>Drag the words into the correct order, then check your sentence.</p>
</div>

<?php if (!$item): ?>
<div class="empty-state"><h3>No sentence tasks yet</h3><p>An admin can add one manually or generate it with AI from the Admin Panel.</p></div>
<?php else:
  $correct = trim($item['answer_key'] ?: $item['option_a']);
  $words = preg_split('/\s+/', preg_replace('/[.?!,;:]+/', '', $correct));
  shuffle($words);
?>
<div class="task-shell">
  <div class="task-board">
    <div class="challenge-header">
      <div>
        <span class="ac-badge badge-blue"><?= clean(ucfirst($item['difficulty'])) ?></span>
        <?php if ($item['tags']): ?><span class="ac-badge badge-purple"><?= clean($item['tags']) ?></span><?php endif; ?>
      </div>
      <div class="challenge-xp">+<?= (int)$item['xp_reward'] ?> XP</div>
    </div>
    <h2 style="font-size:22px;color:var(--text-1);margin:14px 0 8px;"><?= clean($item['title']) ?></h2>
    <p style="color:var(--text-2);line-height:1.7;margin-bottom:20px;"><?= clean($item['prompt']) ?></p>

    <div class="form-label">Word Bank</div>
    <div id="wordBank" class="word-bank">
      <?php foreach ($words as $i => $word): ?>
      <button type="button" class="word-chip" draggable="true" data-word="<?= clean($word) ?>"><?= clean($word) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="form-label" style="margin-top:18px;">Your Sentence</div>
    <div id="answerBank" class="answer-bank"></div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
      <button class="btn btn-primary" type="button" onclick="checkSentence()">Check Answer</button>
      <button class="btn btn-ghost" type="button" onclick="resetWords()">Reset</button>
      <a class="btn btn-outline" href="sentence_rearrangement.php">Next Task</a>
    </div>

    <div id="feedback" class="feedback-box"></div>
  </div>

  <div class="card">
    <h3 style="font-size:16px;margin-bottom:14px;color:var(--text-1)">Available Tasks</h3>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <?php while ($row = $items->fetch_assoc()): ?>
      <a class="btn btn-ghost btn-sm" style="justify-content:flex-start;" href="sentence_rearrangement.php?id=<?= (int)$row['id'] ?>"><?= clean($row['title']) ?></a>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<script>
const correctAnswer = <?= json_encode($correct) ?>;
const itemId = <?= (int)$item['id'] ?>;
const explanation = <?= json_encode($item['explanation'] ?? '') ?>;
const wordBank = document.getElementById('wordBank');
const answerBank = document.getElementById('answerBank');
let dragged = null;

function norm(s) {
  return (s || '').toLowerCase().replace(/[^\w\s']/g, '').replace(/\s+/g, ' ').trim();
}
function wireChips() {
  document.querySelectorAll('.word-chip').forEach(chip => {
    chip.onclick = () => (chip.parentElement.id === 'wordBank' ? answerBank : wordBank).appendChild(chip);
    chip.ondragstart = e => { dragged = chip; e.dataTransfer.effectAllowed = 'move'; };
  });
}
function enableDrop(zone) {
  zone.ondragover = e => e.preventDefault();
  zone.ondrop = e => { e.preventDefault(); if (dragged) zone.appendChild(dragged); };
}
function answerText() {
  return [...answerBank.querySelectorAll('.word-chip')].map(x => x.dataset.word).join(' ');
}
async function checkSentence() {
  const answer = answerText();
  const ok = norm(answer) === norm(correctAnswer);
  const fb = document.getElementById('feedback');
  fb.className = 'feedback-box ' + (ok ? 'good' : 'bad');
  fb.innerHTML = ok
    ? `<strong>Correct.</strong><br>${explanation || 'Your word order is correct.'}`
    : `<strong>Not quite.</strong><br>Correct answer: <strong>${correctAnswer}</strong><br>${explanation || 'Check the subject, verb, and object order.'}`;
  const res = await fetch('sentence_rearrangement.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({action:'complete', item_id:itemId, answer, is_correct: ok ? 1 : 0})
  });
  const data = await res.json();
  if (ok && data.xp > 0) emToast('+' + data.xp + ' XP earned', 'xp');
}
function resetWords() {
  [...answerBank.querySelectorAll('.word-chip')].forEach(chip => wordBank.appendChild(chip));
  document.getElementById('feedback').className = 'feedback-box';
}
wireChips();
enableDrop(wordBank);
enableDrop(answerBank);
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
