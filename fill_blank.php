<?php
require_once 'config.php';
auth();

$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Fill the Blank';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    header('Content-Type: application/json');
    $itemId = (int)($_POST['item_id'] ?? 0);
    $answer = trim($_POST['answer'] ?? '');
    $isCorrect = (int)($_POST['is_correct'] ?? 0);
    $item = $db->query("SELECT * FROM practice_items WHERE id=$itemId AND type='fill_blank' AND active=1 LIMIT 1")->fetch_assoc();
    if (!$item) { echo json_encode(['ok' => 0]); exit; }
    $already = $db->query("SELECT id FROM user_practice_attempts WHERE user_id=$uid AND practice_item_id=$itemId AND xp_earned > 0 LIMIT 1");
    $xp = ($isCorrect && (!$already || $already->num_rows === 0)) ? (int)$item['xp_reward'] : 0;
    $ansEsc = esc($answer);
    $fbEsc = esc($item['explanation'] ?? '');
    $db->query("INSERT INTO user_practice_attempts (user_id, practice_item_id, answer, is_correct, ai_feedback, xp_earned) VALUES ($uid,$itemId,'$ansEsc',$isCorrect,'$fbEsc',$xp)");
    if ($xp) addXP($uid, $xp, 'Fill the blank');
    echo json_encode(['ok' => 1, 'xp' => $xp]);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$whereId = $id ? "AND id=$id" : "";
$item = $db->query("SELECT * FROM practice_items WHERE type='fill_blank' AND active=1 $whereId ORDER BY " . ($id ? "id" : "RAND()") . " LIMIT 1")->fetch_assoc();
$items = $db->query("SELECT id,title,difficulty FROM practice_items WHERE type='fill_blank' AND active=1 ORDER BY difficulty,title LIMIT 30");

include 'includes/header.php';
?>

<style>
.blank-sentence { font-size:24px; line-height:1.7; color:var(--text-1); background:var(--bg-base); border:1px solid var(--border); border-radius:14px; padding:22px; }
.blank-drop { display:inline-flex; align-items:center; justify-content:center; min-width:120px; min-height:44px; border:2px dashed #4f8ef780; border-radius:10px; margin:0 5px; padding:4px 10px; vertical-align:middle; }
.choice-bank { display:flex; flex-wrap:wrap; gap:10px; margin-top:18px; }
.choice-chip { border:1px solid var(--border); background:var(--bg-hover); color:var(--text-1); border-radius:10px; padding:10px 16px; cursor:grab; font-weight:800; }
.feedback-box { display:none; margin-top:16px; padding:14px 16px; border-radius:12px; line-height:1.7; }
.feedback-box.good { display:block; border:1px solid #34d39950; background:#34d39912; color:var(--green); }
.feedback-box.bad { display:block; border:1px solid #f8717150; background:#f8717112; color:var(--red); }
.task-shell { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:20px; align-items:start; }
@media (max-width:900px){ .task-shell{grid-template-columns:1fr;} }
</style>

<div class="page-header">
  <h1>Fill the Blank</h1>
  <p>Drag the correct word into the blank, then check your answer.</p>
</div>

<?php if (!$item): ?>
<div class="empty-state"><h3>No fill-in-the-blank tasks yet</h3><p>An admin can add one manually or generate it with AI from the Admin Panel.</p></div>
<?php else:
  $options = [];
  foreach (['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'] as $letter => $key) {
      if (!empty($item[$key])) $options[$letter] = $item[$key];
  }
  $correctLetter = strtoupper(trim($item['correct_option'] ?? ''));
  $correctWord = $item['answer_key'] ?: ($options[$correctLetter] ?? '');
  $sentenceHtml = clean($item['prompt']);
  $sentenceHtml = str_replace(['____','_____','{blank}','[blank]'], '<span id="blankDrop" class="blank-drop" data-answer="">Drop word</span>', $sentenceHtml);
  if (strpos($sentenceHtml, 'blankDrop') === false) $sentenceHtml .= ' <span id="blankDrop" class="blank-drop" data-answer="">Drop word</span>';
?>
<div class="task-shell">
  <div class="card">
    <div class="challenge-header">
      <div><span class="ac-badge badge-blue"><?= clean(ucfirst($item['difficulty'])) ?></span></div>
      <div class="challenge-xp">+<?= (int)$item['xp_reward'] ?> XP</div>
    </div>
    <h2 style="font-size:22px;color:var(--text-1);margin:14px 0;"><?= clean($item['title']) ?></h2>
    <div class="blank-sentence"><?= $sentenceHtml ?></div>

    <div class="choice-bank" id="choiceBank">
      <?php foreach ($options as $letter => $word): ?>
      <button type="button" class="choice-chip" draggable="true" data-letter="<?= $letter ?>" data-word="<?= clean($word) ?>"><?= clean($word) ?></button>
      <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
      <button class="btn btn-primary" type="button" onclick="checkBlank()">Check Answer</button>
      <button class="btn btn-ghost" type="button" onclick="resetBlank()">Reset</button>
      <a class="btn btn-outline" href="fill_blank.php">Next Task</a>
    </div>
    <div id="feedback" class="feedback-box"></div>
  </div>

  <div class="card">
    <h3 style="font-size:16px;margin-bottom:14px;color:var(--text-1)">Available Tasks</h3>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <?php while ($row = $items->fetch_assoc()): ?>
      <a class="btn btn-ghost btn-sm" style="justify-content:flex-start;" href="fill_blank.php?id=<?= (int)$row['id'] ?>"><?= clean($row['title']) ?></a>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<script>
const itemId = <?= (int)$item['id'] ?>;
const correctLetter = <?= json_encode($correctLetter) ?>;
const correctWord = <?= json_encode($correctWord) ?>;
const explanation = <?= json_encode($item['explanation'] ?? '') ?>;
let dragged = null;
const blank = document.getElementById('blankDrop');
const bank = document.getElementById('choiceBank');

document.querySelectorAll('.choice-chip').forEach(chip => {
  chip.onclick = () => placeChip(chip);
  chip.ondragstart = e => { dragged = chip; e.dataTransfer.effectAllowed = 'move'; };
});
blank.ondragover = e => e.preventDefault();
blank.ondrop = e => { e.preventDefault(); if (dragged) placeChip(dragged); };

function placeChip(chip) {
  if (blank.querySelector('.choice-chip')) bank.appendChild(blank.querySelector('.choice-chip'));
  blank.textContent = '';
  blank.dataset.answer = chip.dataset.letter;
  blank.appendChild(chip);
}
function resetBlank() {
  if (blank.querySelector('.choice-chip')) bank.appendChild(blank.querySelector('.choice-chip'));
  blank.textContent = 'Drop word';
  blank.dataset.answer = '';
  document.getElementById('feedback').className = 'feedback-box';
}
async function checkBlank() {
  const chosen = blank.dataset.answer || '';
  const chosenWord = blank.querySelector('.choice-chip')?.dataset.word || '';
  const ok = chosen === correctLetter || chosenWord.toLowerCase() === correctWord.toLowerCase();
  const fb = document.getElementById('feedback');
  fb.className = 'feedback-box ' + (ok ? 'good' : 'bad');
  fb.innerHTML = ok
    ? `<strong>Correct.</strong><br>${explanation}`
    : `<strong>Not quite.</strong><br>Correct answer: <strong>${correctWord}</strong><br>${explanation}`;
  const res = await fetch('fill_blank.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({action:'complete', item_id:itemId, answer:chosenWord, is_correct: ok ? 1 : 0})
  });
  const data = await res.json();
  if (ok && data.xp > 0) emToast('+' + data.xp + ' XP earned', 'xp');
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
