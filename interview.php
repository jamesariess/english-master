<?php
require_once 'config.php';
auth();
$user = currentUser();
$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Interview Practice';

$jobTypes = [
    'customer_service' => ['label' => 'Customer Service', 'icon' => '📞', 'desc' => 'Call center, support desk'],
    'it_support'       => ['label' => 'IT Support', 'icon' => '💻', 'desc' => 'Technical helpdesk, IT help'],
    'office_admin'     => ['label' => 'Office Admin', 'icon' => '🗂️', 'desc' => 'Admin, secretary, clerk'],
    'sales'            => ['label' => 'Sales', 'icon' => '💼', 'desc' => 'Sales rep, account manager'],
    'bpo_agent'        => ['label' => 'BPO Agent', 'icon' => '🎧', 'desc' => 'Business process outsourcing'],
    'casual'           => ['label' => 'Casual Conversation', 'icon' => '😊', 'desc' => 'Practice general speaking'],
];

// Handle AJAX interview message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_interview'])) {
    header('Content-Type: application/json');
    $jobType = clean($_POST['job_type'] ?? 'customer_service');
    $userMsg = trim($_POST['message'] ?? '');
    $history = json_decode($_POST['history'] ?? '[]', true);
    $isFirst = empty($history);

    $jobLabel = $jobTypes[$jobType]['label'] ?? 'job';

    $system = "You are an experienced HR interviewer conducting a realistic mock job interview for a {$jobLabel} position in the Philippines. You're friendly but professional.

Interview style:
- Ask one question at a time
- After the candidate answers, give brief feedback (2 sentences max) on their English and answer quality
- Then ask the next question
- Score each answer secretly in your head
- Keep the interview realistic and professional
- Correct grammar mistakes politely (\"By the way, the correct way to say that is...\")
- Encourage the candidate: they are an English learner trying to improve
- After 5-6 exchanges, give a final summary score (grammar: X/10, confidence: X/10, content: X/10) and encouragement

Start with a warm greeting and introduce yourself.
The candidate's name is: {$user['name']}
Their English level: {$user['english_level']}";

    $messages = $history;
    if ($userMsg) $messages[] = ['role' => 'user', 'content' => $userMsg];
    elseif ($isFirst) $messages = [['role' => 'user', 'content' => "Let's start the interview for the {$jobLabel} position."]];

    $response = callAI($messages, $system, 700);

    if (!$userMsg) addXP($uid, 5, 'Started interview practice');
    else addXP($uid, 15, 'Interview answer given');

    echo json_encode(['response' => $response]);
    exit;
}

// Save completed interview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_interview'])) {
    $jobType = esc($_POST['job_type'] ?? '');
    $conv = esc($_POST['conversation'] ?? '');
    $gScore = (int)($_POST['grammar_score'] ?? 0);
    $cScore = (int)($_POST['confidence_score'] ?? 0);
    $db->query("INSERT INTO interview_sessions (user_id, job_type, conversation, grammar_score, confidence_score) VALUES ($uid,'$jobType','$conv',$gScore,$cScore)");
    addXP($uid, 50, 'Completed mock interview');
    echo json_encode(['ok' => 1, 'xp' => 50]);
    exit;
}

// Past interviews
$pastInterviews = $db->query("SELECT * FROM interview_sessions WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5");

include 'includes/header.php';
?>

<div class="page-header">
  <h1>👔 Interview Practice</h1>
  <p>Simulate realistic job interviews with AI scoring on grammar and confidence.</p>
</div>

<!-- Setup / Active Interview -->
<div id="setupPanel">
  <div class="interview-setup" style="max-width:100%;">
    <div style="font-size:56px;margin-bottom:16px">🎯</div>
    <h2 style="font-size:22px;margin-bottom:8px;color:var(--text-1)">Choose Interview Type</h2>
    <p style="color:var(--text-2);font-size:14px;margin-bottom:20px">Select the type of job you want to practice interviewing for.</p>

    <div class="job-grid">
      <?php foreach ($jobTypes as $key => $j): ?>
      <div class="job-btn" data-job="<?= $key ?>" onclick="selectJob('<?= $key ?>')">
        <span class="job-icon"><?= $j['icon'] ?></span>
        <div><?= $j['label'] ?></div>
        <div style="font-size:11px;color:var(--text-3);margin-top:4px;font-weight:400"><?= $j['desc'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <button class="btn btn-primary btn-lg" onclick="startInterview()" id="startBtn" disabled style="margin-top:8px">
      Start Interview →
    </button>

    <div class="alert alert-info mt-16" style="text-align:left">
      <div>
        <strong>💡 Tips for your interview practice:</strong><br>
        Answer in complete sentences. The AI will correct your grammar and give feedback on each answer. 
        After 5-6 questions, you'll receive a full score report!
      </div>
    </div>
  </div>
</div>

<!-- Interview Chat Panel (hidden initially) -->
<div id="interviewPanel" style="display:none;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <div>
      <h2 style="font-size:18px;color:var(--text-1)" id="interviewTitle">Mock Interview</h2>
      <div style="font-size:13px;color:var(--text-2)">Answer each question in English. Press Enter to submit.</div>
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline btn-sm" onclick="endInterview()">End & Save</button>
      <button class="btn btn-ghost btn-sm" onclick="resetInterview()">New Interview</button>
    </div>
  </div>

  <div class="card" style="padding:0;overflow:hidden;">
    <div class="chat-container">
      <div class="chat-messages" id="interviewMessages"></div>
      <div class="chat-input-bar">
        <textarea id="interviewInput" placeholder="Type your answer here..." rows="1"
          onkeydown="handleInterviewKey(event)"
          oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"></textarea>
        <button class="btn btn-primary" onclick="sendInterviewMsg()" id="interviewSendBtn">Send ↑</button>
      </div>
    </div>
  </div>
</div>

<!-- Past Interviews -->
<?php if ($pastInterviews && $pastInterviews->num_rows > 0): ?>
<div class="card mt-16" id="pastPanel">
  <h3 style="font-size:16px;margin-bottom:16px;color:var(--text-1)">📋 Past Interviews</h3>
  <table class="data-table">
    <thead>
      <tr><th>Job Type</th><th>Grammar</th><th>Confidence</th><th>Date</th></tr>
    </thead>
    <tbody>
      <?php while ($pi = $pastInterviews->fetch_assoc()): ?>
      <tr>
        <td style="text-transform:capitalize"><?= str_replace('_',' ',clean($pi['job_type'])) ?></td>
        <td style="color:var(--blue);font-weight:700"><?= $pi['grammar_score'] ?>/10</td>
        <td style="color:var(--green);font-weight:700"><?= $pi['confidence_score'] ?>/10</td>
        <td style="color:var(--text-3);font-size:12px"><?= date('M d, Y', strtotime($pi['created_at'])) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script>
let selectedJob = null;
let interviewHistory = [];
let jobTypes = <?= json_encode($jobTypes) ?>;

function selectJob(key) {
  selectedJob = key;
  document.querySelectorAll('.job-btn').forEach(b => b.classList.toggle('selected', b.dataset.job === key));
  document.getElementById('startBtn').disabled = false;
}

async function startInterview() {
  if (!selectedJob) return;
  document.getElementById('setupPanel').style.display = 'none';
  document.getElementById('interviewPanel').style.display = 'block';
  document.getElementById('pastPanel') && (document.getElementById('pastPanel').style.display = 'none');

  const jobLabel = jobTypes[selectedJob].label;
  document.getElementById('interviewTitle').textContent = `${jobTypes[selectedJob].icon} Mock Interview: ${jobLabel}`;

  interviewHistory = [];

  // Get opening question from AI
  await sendToAI('');
}

function resetInterview() {
  selectedJob = null;
  interviewHistory = [];
  document.getElementById('setupPanel').style.display = 'block';
  document.getElementById('interviewPanel').style.display = 'none';
  document.getElementById('interviewMessages').innerHTML = '';
  document.querySelectorAll('.job-btn').forEach(b => b.classList.remove('selected'));
  document.getElementById('startBtn').disabled = true;
}

async function endInterview() {
  const conv = JSON.stringify(interviewHistory);
  try {
    await fetch('interview.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `save_interview=1&job_type=${encodeURIComponent(selectedJob)}&conversation=${encodeURIComponent(conv)}&grammar_score=7&confidence_score=7`
    });
    emToast('✅ Interview saved! +50 XP earned!', 'xp', 3500);
  } catch(e) {}
  setTimeout(resetInterview, 1500);
}

function handleInterviewKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendInterviewMsg(); }
}

async function sendInterviewMsg() {
  const input = document.getElementById('interviewInput');
  const msg = input.value.trim();
  if (!msg) return;

  appendInterviewMsg('user', msg);
  interviewHistory.push({ role: 'user', content: msg });
  input.value = '';
  input.style.height = 'auto';

  await sendToAI(msg);
}

async function sendToAI(userMsg) {
  const btn = document.getElementById('interviewSendBtn');
  btn.disabled = true;

  // Show typing
  const typing = appendInterviewMsg('ai', '<div class="ai-thinking"><span></span><span></span><span></span></div>', true);

  try {
    const res = await fetch('interview.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `ajax_interview=1&job_type=${encodeURIComponent(selectedJob)}&message=${encodeURIComponent(userMsg)}&history=${encodeURIComponent(JSON.stringify(interviewHistory))}`
    });
    const data = await res.json();
    typing.remove();

    if (data.response) {
      appendInterviewMsg('ai', data.response);
      interviewHistory.push({ role: 'assistant', content: data.response });
    }
  } catch(e) {
    typing.remove();
    appendInterviewMsg('ai', '❌ Connection error. Please try again.');
  }

  btn.disabled = false;
  document.getElementById('interviewMessages').scrollTop = 9999;
}

function appendInterviewMsg(role, content, raw = false) {
  const msgs = document.getElementById('interviewMessages');
  const div = document.createElement('div');
  div.className = 'msg ' + (role === 'user' ? 'user' : 'ai');
  const avatar = role === 'user' ? '<?= addslashes($user['avatar']) ?>' : '👔';
  const formatted = raw ? content : content.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>');
  div.innerHTML = `<div class="msg-avatar">${avatar}</div><div class="msg-bubble"><div class="ai-text">${formatted}</div></div>`;
  msgs.appendChild(div);
  msgs.scrollTop = msgs.scrollHeight;
  return div;
}

function escapeInterviewHtml(t) { const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }
</script>

<?php include 'includes/footer.php'; ?>
