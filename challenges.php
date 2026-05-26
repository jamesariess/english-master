<?php
require_once 'config.php';
auth();
$user = currentUser();
$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Daily Challenges';

// Submit challenge answer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_challenge'])) {
    $challengeId = (int)($_POST['challenge_id'] ?? 0);
    $answer = trim($_POST['answer'] ?? '');

    if ($challengeId && $answer) {
        // Get challenge details
        $ch = $db->query("SELECT * FROM challenges WHERE id=$challengeId")->fetch_assoc();

        if ($ch) {
            $system = "You are a friendly English teacher grading a student's challenge answer. Be encouraging and constructive.
            
Respond ONLY in valid JSON:
{
  \"score\": <0-100>,
  \"is_correct\": <true or false>,
  \"feedback\": \"<2-3 sentences of specific, encouraging feedback explaining what was good and what to improve>\",
  \"model_answer\": \"<show a good example answer if the student needs improvement>\"
}";

            $prompt = "Challenge Type: {$ch['type']}\nChallenge: {$ch['content']}\nStudent Answer: $answer\n\nPlease grade this answer.";
            $aiResp = callAI([['role' => 'user', 'content' => $prompt]], $system, 600);
            $cleaned = trim(preg_replace('/```json|```/i', '', $aiResp));
            $graded = json_decode($cleaned, true);

            $score = (int)($graded['score'] ?? 50);
            $xpEarned = max(10, (int)round(($ch['xp_reward'] ?? 50) * $score / 100));
            $feedback = esc($graded['feedback'] ?? $aiResp);
            $modelAnswer = esc($graded['model_answer'] ?? '');
            $ansEsc = esc($answer);

            $db->query("INSERT INTO user_challenges (user_id, challenge_id, answer, ai_feedback, score, xp_earned) VALUES ($uid,$challengeId,'$ansEsc','$feedback - Model: $modelAnswer',$score,$xpEarned)");
            addXP($uid, $xpEarned, "Daily challenge: {$ch['title']}");

            $_SESSION['ch_result'] = ['score' => $score, 'feedback' => $graded['feedback'] ?? '', 'model' => $graded['model_answer'] ?? '', 'xp' => $xpEarned, 'challenge_id' => $challengeId];
        }
    }
    header('Location: challenges.php');
    exit;
}

// Get today's challenges
$todayChallenges = $db->query("SELECT c.*, uc.score as user_score, uc.xp_earned, uc.completed_at FROM challenges c LEFT JOIN user_challenges uc ON c.id=uc.challenge_id AND uc.user_id=$uid WHERE c.challenge_date=CURDATE() AND c.active=1 ORDER BY c.id");

// Get result from session
$chResult = $_SESSION['ch_result'] ?? null;
unset($_SESSION['ch_result']);

// Challenge history
$history = $db->query("SELECT uc.*, c.title, c.type FROM user_challenges uc JOIN challenges c ON uc.challenge_id=c.id WHERE uc.user_id=$uid ORDER BY uc.completed_at DESC LIMIT 10");

include 'includes/header.php';
?>

<div class="page-header">
  <h1>⚡ Daily Challenges</h1>
  <p>Complete today's challenges to earn XP and improve your English skills.</p>
</div>

<!-- Result notification -->
<?php if ($chResult): ?>
<div class="alert <?= $chResult['score'] >= 70 ? 'alert-success' : 'alert-warn' ?>" style="margin-bottom:24px;">
  <div>
    <div style="font-weight:700;font-size:16px;margin-bottom:4px">
      <?= $chResult['score'] >= 80 ? '🌟 Excellent!' : ($chResult['score'] >= 60 ? '👍 Good job!' : '💪 Keep practicing!') ?>
      Score: <?= $chResult['score'] ?>/100 · +<?= $chResult['xp'] ?> XP earned
    </div>
    <div style="font-size:13px"><?= clean($chResult['feedback']) ?></div>
    <?php if (!empty($chResult['model'])): ?>
    <div style="margin-top:8px;font-size:13px;background:#00000020;padding:8px 12px;border-radius:6px;">
      <strong>Example Answer:</strong> <?= clean($chResult['model']) ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>window.addEventListener('load',()=>emToast('+<?= $chResult['xp'] ?> XP — Challenge complete! ⚡','xp'));</script>
<?php endif; ?>

<!-- Today's challenges -->
<h2 style="font-size:17px;margin-bottom:16px;color:var(--text-1)">📅 Today's Challenges</h2>

<?php if (!$todayChallenges || $todayChallenges->num_rows === 0): ?>
<div class="card" style="text-align:center;padding:50px;">
  <div style="font-size:48px;margin-bottom:16px">🗓️</div>
  <h3 style="color:var(--text-2)">No challenges for today yet</h3>
  <p style="color:var(--text-3);font-size:14px;margin-top:8px">Check back later or ask your admin to add challenges in phpMyAdmin.</p>
</div>

<?php else: ?>
<div style="display:flex;flex-direction:column;gap:16px;margin-bottom:32px;">
  <?php while ($ch = $todayChallenges->fetch_assoc()):
    $done = !empty($ch['completed_at']);
    $typeColors = ['vocabulary'=>'badge-blue','grammar'=>'badge-green','writing'=>'badge-purple','speaking'=>'badge-teal','listening'=>'badge-yellow'];
    $tc = $typeColors[$ch['type']] ?? 'badge-blue';
  ?>
  <div class="challenge-card <?= $done ? 'completed' : 'active' ?>">
    <div class="challenge-header">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <span class="challenge-type ac-badge <?= $tc ?>"><?= ucfirst($ch['type']) ?></span>
        <span class="challenge-type ac-badge diff-<?= $ch['difficulty'] ?>"><?= ucfirst($ch['difficulty']) ?></span>
      </div>
      <div class="challenge-xp">🏆 +<?= $ch['xp_reward'] ?> XP</div>
    </div>

    <div class="challenge-title"><?= clean($ch['title']) ?></div>
    <div class="challenge-desc mb-16"><?= clean($ch['description']) ?></div>

    <?php if ($done): ?>
    <div style="display:flex;align-items:center;gap:12px;">
      <span style="background:#34d39920;color:var(--green);padding:5px 14px;border-radius:99px;font-size:13px;font-weight:700">
        ✅ Completed · Score: <?= $ch['user_score'] ?>/100 · +<?= $ch['xp_earned'] ?> XP
      </span>
    </div>

    <?php else: ?>
    <!-- Challenge form -->
    <div>
      <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:14px;">
        <div style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:8px">📋 Challenge</div>
        <div style="font-size:14px;color:var(--text-1);line-height:1.8;white-space:pre-wrap"><?= clean($ch['content']) ?></div>
      </div>
      <form method="POST">
        <input type="hidden" name="challenge_id" value="<?= $ch['id'] ?>">
        <div class="form-group">
          <label class="form-label">Your Answer:</label>
          <textarea name="answer" class="form-control" rows="4" placeholder="Write your answer here..." required style="min-height:100px"></textarea>
        </div>
        <button type="submit" name="submit_challenge" class="btn btn-primary" onclick="btnLoading(this,true)">
          Submit Answer 🚀
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endwhile; ?>
</div>
<?php endif; ?>

<!-- Challenge History -->
<?php if ($history && $history->num_rows > 0): ?>
<div class="card">
  <h3 style="font-size:16px;margin-bottom:16px;color:var(--text-1)">📋 Challenge History</h3>
  <table class="data-table">
    <thead>
      <tr>
        <th>Challenge</th>
        <th>Type</th>
        <th>Score</th>
        <th>XP</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($h = $history->fetch_assoc()): ?>
      <tr>
        <td><?= clean($h['title']) ?></td>
        <td><span class="ac-badge badge-blue" style="font-size:11px"><?= ucfirst($h['type']) ?></span></td>
        <td>
          <span style="font-weight:700;color:<?= $h['score'] >= 80 ? 'var(--green)' : ($h['score'] >= 60 ? 'var(--yellow)' : 'var(--red)') ?>">
            <?= $h['score'] ?>/100
          </span>
        </td>
        <td style="color:var(--green);font-weight:700">+<?= $h['xp_earned'] ?></td>
        <td style="color:var(--text-3);font-size:12px"><?= date('M d, Y', strtotime($h['completed_at'])) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
