<?php
require_once 'config.php';
auth();
$user = currentUser();
$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Grammar Checker';

$result = null;
$originalText = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_grammar'])) {
    $originalText = trim($_POST['text'] ?? '');

    if ($originalText) {
        $system = "You are an expert English grammar teacher. Analyze the given text and respond ONLY with valid JSON (no markdown, no extra text).

Return this JSON structure:
{
  \"score\": <number 0-100>,
  \"corrected_text\": \"<fully corrected version>\",
  \"errors\": [
    {
      \"original\": \"<wrong phrase>\",
      \"corrected\": \"<correct phrase>\",
      \"type\": \"<error type: tense/spelling/punctuation/word usage/sentence structure>\",
      \"explanation\": \"<clear, simple explanation why it is wrong and why the correction is right>\"
    }
  ],
  \"overall_feedback\": \"<1-2 sentences of encouraging feedback and what the student should focus on>\",
  \"tip\": \"<one helpful grammar tip related to the mistakes found>\"
}

If there are no errors, return an empty errors array and score of 100.
Keep explanations simple and encouraging for English learners.";

        $aiResponse = callAI([['role' => 'user', 'content' => "Please check this text:\n\n" . $originalText]], $system, 1500);

        // Parse JSON response
        $cleaned = preg_replace('/```json|```/i', '', $aiResponse);
        $cleaned = trim($cleaned);
        $parsed = json_decode($cleaned, true);

        if ($parsed) {
            $result = $parsed;
            $score = (int)($result['score'] ?? 0);

            // Save to DB
            $o = esc($originalText);
            $c = esc($result['corrected_text'] ?? '');
            $f = esc($result['overall_feedback'] ?? '');
            $errorCount = count($result['errors'] ?? []);
            $db->query("INSERT INTO grammar_sessions (user_id, original_text, corrected_text, ai_feedback, error_count, score) VALUES ($uid,'$o','$c','$f',$errorCount,$score)");

            addXP($uid, 30, 'Grammar check session');
        } else {
            $result = ['error' => 'Could not parse AI response. Raw: ' . clean($aiResponse)];
        }
    }
}

// Recent sessions
$recentSessions = $db->query("SELECT * FROM grammar_sessions WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5");

include 'includes/header.php';
?>

<div class="page-header">
  <h1>📝 Grammar Checker</h1>
  <p>Paste any English text and get detailed corrections with clear explanations.</p>
</div>

<div class="grid-2" style="gap:24px;align-items:start;">
  <!-- Input Section -->
  <div>
    <div class="card mb-16">
      <form method="POST">
        <div class="form-group">
          <label class="form-label" style="font-size:15px;color:var(--text-1)">✍️ Enter your text to check</label>
          <textarea 
            name="text" 
            class="form-control" 
            rows="8" 
            placeholder="Type or paste your English text here...
Example: Yesterday I go to the market and buyed some fruits. I was very happy because the fruits is fresh."
            style="min-height:200px;font-size:14px;line-height:1.8"
          ><?= clean($originalText) ?></textarea>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button type="submit" name="check_grammar" class="btn btn-primary" id="grammarSubmitBtn" onclick="btnLoading(this,true)">
            🔍 Check Grammar (+30 XP)
          </button>
          <button type="button" class="btn btn-ghost" onclick="document.querySelector('textarea[name=text]').value=''">
            Clear
          </button>
        </div>
      </form>
    </div>

    <!-- Tips Card -->
    <div class="card" style="background:linear-gradient(135deg,#4f8ef710,#a78bfa10);">
      <h3 style="font-size:15px;margin-bottom:12px;color:var(--text-1)">💡 Grammar Tips</h3>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <div style="font-size:13px;color:var(--text-2)">🕐 Use <strong style="color:var(--blue)">past tense</strong> for things that already happened ("I went" not "I go")</div>
        <div style="font-size:13px;color:var(--text-2)">📝 Subject + Verb must <strong style="color:var(--blue)">agree</strong> ("She is" not "She are")</div>
        <div style="font-size:13px;color:var(--text-2)">🔤 Use <strong style="color:var(--blue)">articles</strong> correctly (a, an, the)</div>
        <div style="font-size:13px;color:var(--text-2)">✅ Check your <strong style="color:var(--blue)">prepositions</strong> (in, on, at, to, for)</div>
      </div>
    </div>
  </div>

  <!-- Results Section -->
  <div>
    <?php if ($result): ?>

      <?php if (isset($result['error'])): ?>
      <div class="alert alert-warn"><?= clean($result['error']) ?></div>

      <?php else:
        $score = (int)($result['score'] ?? 0);
        $scoreClass = $score >= 80 ? 'score-high' : ($score >= 60 ? 'score-mid' : 'score-low');
        $scoreEmoji = $score >= 80 ? '🌟' : ($score >= 60 ? '👍' : '💪');
        $errors = $result['errors'] ?? [];
      ?>

      <div class="grammar-result">
        <!-- Score Header -->
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
          <div>
            <div style="font-size:42px;font-weight:800;font-family:'Plus Jakarta Sans',sans-serif;
              color:<?= $score >= 80 ? 'var(--green)' : ($score >= 60 ? 'var(--yellow)' : 'var(--red)') ?>">
              <?= $score ?><span style="font-size:20px;opacity:0.6">/100</span>
            </div>
            <div style="font-size:13px;color:var(--text-3)">Grammar Score</div>
          </div>
          <div style="flex:1">
            <div style="font-size:14px;color:var(--text-1);margin-bottom:4px">
              <?= $scoreEmoji ?> <?= count($errors) === 0 ? 'Perfect! No errors found.' : count($errors) . ' error' . (count($errors) !== 1 ? 's' : '') . ' found' ?>
            </div>
            <div style="font-size:13px;color:var(--text-2);line-height:1.5">
              <?= clean($result['overall_feedback'] ?? '') ?>
            </div>
          </div>
        </div>

        <!-- Corrected text -->
        <?php if (!empty($result['corrected_text']) && $result['corrected_text'] !== $originalText): ?>
        <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px;">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--green);margin-bottom:8px">✅ Corrected Version</div>
          <div style="font-size:14px;color:var(--text-1);line-height:1.8;font-family:'DM Sans',sans-serif">
            <?= nl2br(clean($result['corrected_text'])) ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Errors list -->
        <?php if (!empty($errors)): ?>
        <div style="margin-bottom:16px;">
          <div style="font-size:13px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:12px">🔍 Detailed Corrections</div>
          <?php foreach ($errors as $err): ?>
          <div class="correction-block">
            <div style="font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:8px">
              <?= strtoupper(clean($err['type'] ?? 'grammar')) ?> ERROR
            </div>
            <div class="original">❌ "<?= clean($err['original'] ?? '') ?>"</div>
            <div class="corrected">✅ "<?= clean($err['corrected'] ?? '') ?>"</div>
            <div class="explanation">💡 <?= clean($err['explanation'] ?? '') ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Tip -->
        <?php if (!empty($result['tip'])): ?>
        <div style="background:var(--blue-glow);border:1px solid #4f8ef730;border-radius:10px;padding:14px 16px;">
          <div style="font-size:12px;font-weight:700;color:var(--blue);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:5px">📌 Grammar Tip</div>
          <div style="font-size:13px;color:var(--text-1);line-height:1.6"><?= clean($result['tip']) ?></div>
        </div>
        <?php endif; ?>

        <div style="margin-top:16px;color:var(--green);font-size:13px;font-weight:600">✅ +30 XP earned!</div>
      </div>
      <?php endif; ?>
      <?php
// Fire toast via inline script after DOM is ready
echo "<script>window.addEventListener('load',()=>emToast('+30 XP earned! Great work 📝','xp'));</script>";
?>

    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
      <div class="alert alert-warn">⚠️ Please enter some text to check.</div>

    <?php else: ?>
      <div class="card" style="text-align:center;padding:50px 30px;">
        <div style="font-size:56px;margin-bottom:20px">📝</div>
        <h3 style="font-size:18px;color:var(--text-1);margin-bottom:8px">Grammar Correction</h3>
        <p style="color:var(--text-2);font-size:14px;line-height:1.6;max-width:300px;margin:0 auto">
          Type or paste any English text on the left and click "Check Grammar" to get instant AI analysis.
        </p>
      </div>
    <?php endif; ?>

    <!-- Recent Sessions -->
    <?php if ($recentSessions && $recentSessions->num_rows > 0): ?>
    <div class="card mt-16">
      <h3 style="font-size:15px;margin-bottom:14px;color:var(--text-1)">📋 Recent Sessions</h3>
      <?php while ($s = $recentSessions->fetch_assoc()): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
        <div>
          <div style="font-size:13px;color:var(--text-1);max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            "<?= clean(substr($s['original_text'], 0, 50)) ?>..."
          </div>
          <div style="font-size:11px;color:var(--text-3);margin-top:2px"><?= date('M d, g:i A', strtotime($s['created_at'])) ?></div>
        </div>
        <div style="text-align:right">
          <div style="font-size:15px;font-weight:800;color:<?= $s['score'] >= 80 ? 'var(--green)' : ($s['score'] >= 60 ? 'var(--yellow)' : 'var(--red)') ?>"><?= $s['score'] ?></div>
          <div style="font-size:11px;color:var(--text-3)"><?= $s['error_count'] ?> errors</div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
