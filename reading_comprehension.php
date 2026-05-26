<?php
require_once 'config.php';
auth();

$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Reading Practice';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_reading'])) {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $answers = [
        trim($_POST['answer_1'] ?? ''),
        trim($_POST['answer_2'] ?? ''),
        trim($_POST['answer_3'] ?? '')
    ];
    $item = $db->query("SELECT * FROM practice_items WHERE id=$itemId AND type='reading_comprehension' AND active=1 LIMIT 1")->fetch_assoc();
    if ($item) {
        $system = "You are an English reading tutor. Evaluate answers simply and clearly. Return ONLY valid JSON: {\"score\":0-100,\"feedback\":\"short feedback\",\"corrections\":[\"...\"],\"improved_answers\":[\"...\"]}. Always explain why.";
        $prompt = "Passage:\n{$item['prompt']}\n\nQuestions:\n1. {$item['option_a']}\n2. {$item['option_b']}\n3. {$item['option_c']}\n\nAnswer key:\n{$item['answer_key']}\n\nLearner answers:\n1. {$answers[0]}\n2. {$answers[1]}\n3. {$answers[2]}";
        $ai = callAI([['role' => 'user', 'content' => $prompt]], $system, 900);
        $graded = jsonFromAI($ai);
        $score = (int)($graded['score'] ?? 0);
        if (!$graded) {
            $score = 60;
            $graded = [
                'feedback' => 'AI grading was unavailable, so compare your answers with the answer key below.',
                'corrections' => [$item['explanation']],
                'improved_answers' => [$item['answer_key']]
            ];
        }
        $already = $db->query("SELECT id FROM user_practice_attempts WHERE user_id=$uid AND practice_item_id=$itemId AND xp_earned > 0 LIMIT 1");
        $xp = ($score >= 70 && (!$already || $already->num_rows === 0)) ? (int)$item['xp_reward'] : 0;
        $ansEsc = esc(implode("\n", $answers));
        $fbEsc = esc($graded['feedback'] ?? '');
        $db->query("INSERT INTO user_practice_attempts (user_id, practice_item_id, answer, is_correct, ai_feedback, xp_earned) VALUES ($uid,$itemId,'$ansEsc'," . ($score >= 70 ? 1 : 0) . ",'$fbEsc',$xp)");
        if ($xp) addXP($uid, $xp, 'Reading comprehension');
        $result = ['score' => $score, 'xp' => $xp, 'graded' => $graded];
    }
}

$id = (int)($_GET['id'] ?? ($_POST['item_id'] ?? 0));
$whereId = $id ? "AND id=$id" : "";
$item = $db->query("SELECT * FROM practice_items WHERE type='reading_comprehension' AND active=1 $whereId ORDER BY " . ($id ? "id" : "RAND()") . " LIMIT 1")->fetch_assoc();
$items = $db->query("SELECT id,title,difficulty FROM practice_items WHERE type='reading_comprehension' AND active=1 ORDER BY difficulty,title LIMIT 30");

include 'includes/header.php';
?>

<style>
.reading-shell { display:grid; grid-template-columns:minmax(0,1fr) 360px; gap:20px; align-items:start; }
.passage-box { background:var(--bg-base); border:1px solid var(--border); border-radius:14px; padding:22px; color:var(--text-1); line-height:1.9; font-size:16px; }
.question-block { border-top:1px solid var(--border); padding-top:16px; margin-top:16px; }
.result-panel { border-radius:14px; padding:16px; margin-bottom:18px; line-height:1.7; }
.result-panel.good { border:1px solid #34d39950; background:#34d39912; }
.result-panel.mid { border:1px solid #fbbf2450; background:#fbbf2412; }
@media (max-width:980px){ .reading-shell{grid-template-columns:1fr;} }
</style>

<div class="page-header">
  <h1>Reading Practice</h1>
  <p>Read the story, answer the questions, and get feedback based on the passage.</p>
</div>

<?php if (!$item): ?>
<div class="empty-state"><h3>No reading tasks yet</h3><p>An admin can add a story manually or generate one with AI from the Admin Panel.</p></div>
<?php else: ?>
<div class="reading-shell">
  <div class="card">
    <?php if ($result): ?>
    <div class="result-panel <?= $result['score'] >= 70 ? 'good' : 'mid' ?>">
      <div style="font-size:24px;font-weight:800;color:var(--text-1);">Score: <?= (int)$result['score'] ?>/100<?= $result['xp'] ? ' · +' . (int)$result['xp'] . ' XP' : '' ?></div>
      <div style="color:var(--text-2);margin-top:8px;"><?= clean($result['graded']['feedback'] ?? '') ?></div>
      <?php if (!empty($result['graded']['corrections'])): ?>
      <div style="margin-top:12px;color:var(--text-1);"><strong>Corrections:</strong><br><?= clean(implode("\n", (array)$result['graded']['corrections'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($result['graded']['improved_answers'])): ?>
      <div style="margin-top:12px;color:var(--text-1);"><strong>Improved answers:</strong><br><?= clean(implode("\n", (array)$result['graded']['improved_answers'])) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="challenge-header">
      <div><span class="ac-badge badge-blue"><?= clean(ucfirst($item['difficulty'])) ?></span></div>
      <div class="challenge-xp">+<?= (int)$item['xp_reward'] ?> XP</div>
    </div>
    <h2 style="font-size:24px;color:var(--text-1);margin:14px 0;"><?= clean($item['title']) ?></h2>
    <div class="passage-box"><?= nl2br(clean($item['prompt'])) ?></div>

    <form method="POST" style="margin-top:20px;">
      <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
      <?php foreach ([1 => 'option_a', 2 => 'option_b', 3 => 'option_c'] as $num => $key): if (empty($item[$key])) continue; ?>
      <div class="question-block">
        <label class="form-label">Question <?= $num ?></label>
        <div style="color:var(--text-1);font-weight:700;margin-bottom:10px;"><?= clean($item[$key]) ?></div>
        <textarea name="answer_<?= $num ?>" class="form-control" rows="3" placeholder="Answer using details from the story." required><?= clean($_POST['answer_' . $num] ?? '') ?></textarea>
      </div>
      <?php endforeach; ?>
      <button class="btn btn-primary mt-16" name="check_reading" onclick="btnLoading(this,true)">Check My Answers</button>
    </form>
  </div>

  <div class="card">
    <h3 style="font-size:16px;margin-bottom:14px;color:var(--text-1)">Stories</h3>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <?php while ($row = $items->fetch_assoc()): ?>
      <a class="btn btn-ghost btn-sm" style="justify-content:flex-start;" href="reading_comprehension.php?id=<?= (int)$row['id'] ?>"><?= clean($row['title']) ?></a>
      <?php endwhile; ?>
    </div>
    <?php if ($result && $item['answer_key']): ?>
    <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border);font-size:13px;color:var(--text-2);line-height:1.7;">
      <strong style="color:var(--text-1);">Teacher key</strong><br>
      <?= nl2br(clean($item['answer_key'])) ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
