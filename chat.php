<?php
require_once 'config.php';
auth();
$user = currentUser();
$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'AI Chat';

// Handle new session
$sessionId = $_GET['session'] ?? $_SESSION['chat_session'] ?? null;
if (!$sessionId) {
    $sessionId = bin2hex(random_bytes(8));
    $_SESSION['chat_session'] = $sessionId;
}

// Load conversation history for this session
$sid = esc($sessionId);
$history = $db->query("SELECT role, content FROM chat_history WHERE user_id=$uid AND session_id='$sid' ORDER BY created_at ASC");
$messages = [];
if ($history) {
    while ($row = $history->fetch_assoc()) {
        $messages[] = ['role' => $row['role'], 'content' => $row['content']];
    }
}

// Handle AJAX message post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $userMsg = trim($_POST['message'] ?? '');
    if (!$userMsg) { echo json_encode(['error' => 'Empty message']); exit; }

    // Save user message
    $m = esc($userMsg);
    $db->query("INSERT INTO chat_history (user_id, role, content, session_id) VALUES ($uid,'user','$m','$sid')");
    addXP($uid, 10, 'Chat conversation');

    // Build messages for AI
    $aiMessages = $messages;
    $aiMessages[] = ['role' => 'user', 'content' => $userMsg];

    $system = "You are EnglishMaster AI, a friendly, encouraging English tutor and conversation partner. Your personality is warm, patient, and motivating.

Your job is to:
1. Respond naturally to what the user said (keep the conversation flowing)
2. If the user made grammar mistakes, politely correct them using this format:
   - Show the corrected sentence: ✏️ **Correction:** \"[correct version]\"
   - Briefly explain why: 💡 **Why:** [simple explanation]
3. Suggest more natural or professional expressions when appropriate
4. Encourage the user when they do well
5. Keep responses conversational and not too long (2-4 sentences usually)
6. Occasionally teach vocabulary or useful expressions naturally

The user's English level is: {$user['english_level']}.
Always be positive and make learning feel fun and safe. Never make the user feel bad about mistakes.";

    $response = callAI($aiMessages, $system, 800);

    // Save AI response
    $r = esc($response);
    $db->query("INSERT INTO chat_history (user_id, role, content, session_id) VALUES ($uid,'assistant','$r','$sid')");

    echo json_encode(['response' => $response, 'xp' => 10]);
    exit;
}

// Start new session
if (isset($_GET['new'])) {
    $sessionId = bin2hex(random_bytes(8));
    $_SESSION['chat_session'] = $sessionId;
    header('Location: chat.php');
    exit;
}

include 'includes/header.php';
?>

<div class="page-header flex-between" style="flex-wrap:wrap;gap:12px;">
  <div>
    <h1>💬 AI Conversation Chat</h1>
    <p>Talk freely with your AI tutor. Get corrections and learn naturally.</p>
  </div>
  <a href="chat.php?new=1" class="btn btn-outline btn-sm">+ New Chat</a>
</div>

<!-- Chat Container -->
<div class="card" style="padding:0;overflow:hidden;">
  <div class="chat-container">
    <div class="chat-messages" id="chatMessages">

      <!-- Welcome message -->
      <?php if (empty($messages)): ?>
      <div class="msg ai">
        <div class="msg-avatar">E</div>
        <div class="msg-bubble">
          <div class="ai-text">
            <p>Hello, <strong><?= clean($user['name']) ?></strong>! 👋 I'm your EnglishMaster AI tutor.</p>
            <p>I'm here to help you practice English, correct your grammar, and build your confidence. Just talk to me like a friend!</p>
            <p>You can tell me about your day, ask me questions, practice a job interview, or anything else. I'll gently correct any mistakes and help you speak more naturally. 😊</p>
            <p style="color:var(--teal);font-size:13px">💡 <em>Try starting with: "Hello! Can you tell me about yourself?"</em></p>
          </div>
        </div>
      </div>
      <?php else: ?>
        <?php foreach ($messages as $msg): ?>
        <div class="msg <?= $msg['role'] === 'user' ? 'user' : 'ai' ?>">
          <div class="msg-avatar">
            <?= $msg['role'] === 'user' ? clean($user['avatar']) : 'E' ?>
          </div>
          <div class="msg-bubble">
            <div class="ai-text"><?= nl2br(clean($msg['content'])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- AI typing indicator (hidden) -->
      <div class="msg ai" id="typingIndicator" style="display:none">
        <div class="msg-avatar">E</div>
        <div class="msg-bubble">
          <div class="ai-thinking">
            <span></span><span></span><span></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Input Bar -->
    <div class="chat-input-bar">
      <!-- Mic button -->
      <button class="mic-chat-btn" id="chatMicBtn" onclick="toggleChatMic()" title="Speak your message">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3z"/>
          <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
          <line x1="12" y1="19" x2="12" y2="22"/>
          <line x1="8"  y1="22" x2="16" y2="22"/>
        </svg>
      </button>
      <textarea 
        id="chatInput" 
        placeholder="Type or 🎤 speak in English... (Enter to send)" 
        rows="1"
        onkeydown="handleKey(event)"
        oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"
      ></textarea>
      <button class="btn btn-primary" onclick="sendMessage()" id="sendBtn">
        Send ↑
      </button>
    </div>
    <!-- Mic recording indicator bar -->
    <div id="chatMicBar" style="display:none;background:var(--bg-card);border-top:1px solid var(--border);padding:10px 20px;display:none;align-items:center;gap:12px;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:8px;">
        <div style="width:10px;height:10px;background:#e74c3c;border-radius:50%;animation:micRecordPulse 1.2s infinite;flex-shrink:0;"></div>
        <span style="font-size:13px;color:var(--red);font-weight:600">Listening...</span>
      </div>
      <div id="chatMicTranscript" style="flex:1;font-size:13px;color:var(--text-2);font-style:italic;min-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Speak now in English...</div>
      <button class="btn btn-sm btn-outline" onclick="stopChatMic(true)" style="font-size:12px;flex-shrink:0;">✅ Use This</button>
      <button class="btn btn-sm btn-ghost" onclick="stopChatMic(false)" style="font-size:12px;flex-shrink:0;">✕ Cancel</button>
    </div>
  </div>
</div>

<!-- Conversation Tips -->
<div class="grid-3 mt-16" style="gap:12px;">
  <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:14px;cursor:pointer;" onclick="setPrompt(this.dataset.msg)" data-msg="Tell me about your day and what you learned today.">
    <div style="font-size:11px;color:var(--text-3);text-transform:uppercase;font-weight:700;margin-bottom:4px">💬 Starter</div>
    <div style="font-size:13px;color:var(--text-2)">Ask about my day</div>
  </div>
  <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:14px;cursor:pointer;" onclick="setPrompt(this.dataset.msg)" data-msg="Can we practice job interview questions for a customer service position?">
    <div style="font-size:11px;color:var(--text-3);text-transform:uppercase;font-weight:700;margin-bottom:4px">👔 Practice</div>
    <div style="font-size:13px;color:var(--text-2)">Interview practice mode</div>
  </div>
  <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:14px;cursor:pointer;" onclick="setPrompt(this.dataset.msg)" data-msg="Can you teach me some common English phrases used in office conversations?">
    <div style="font-size:11px;color:var(--text-3);text-transform:uppercase;font-weight:700;margin-bottom:4px">📖 Learn</div>
    <div style="font-size:13px;color:var(--text-2)">Office English phrases</div>
  </div>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const sendBtn = document.getElementById('sendBtn');
const typingIndicator = document.getElementById('typingIndicator');

// Scroll to bottom on load
chatMessages.scrollTop = chatMessages.scrollHeight;

function setPrompt(msg) {
  chatInput.value = msg;
  chatInput.focus();
}

function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function formatAIText(text) {
  // Basic markdown-like formatting
  text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
  text = text.replace(/`(.*?)`/g, '<code>$1</code>');
  text = text.replace(/\n/g, '<br>');
  return text;
}

async function sendMessage() {
  const msg = chatInput.value.trim();
  if (!msg || sendBtn.disabled) return;

  appendMessage('user', msg);
  chatInput.value = '';
  chatInput.style.height = 'auto';

  btnLoading(sendBtn, true);
  typingIndicator.style.display = 'flex';
  chatMessages.scrollTop = chatMessages.scrollHeight;

  try {
    const res = await fetch('chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'ajax=1&message=' + encodeURIComponent(msg)
    });
    const data = await res.json();
    typingIndicator.style.display = 'none';

    if (data.response) {
      appendMessage('ai', data.response);
      emToast('+' + data.xp + ' XP earned!', 'xp');
    } else if (data.error) {
      appendMessage('ai', '⚠️ Error: ' + data.error);
    }
  } catch (err) {
    typingIndicator.style.display = 'none';
    appendMessage('ai', '❌ Connection error. Please try again.');
    emToast('Connection failed', 'err');
  }

  btnLoading(sendBtn, false);
  sendBtn.textContent = 'Send ↑';
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function appendMessage(role, content) {
  const div = document.createElement('div');
  div.className = 'msg ' + (role === 'user' ? 'user' : 'ai');
  const avatar = role === 'user' ? '<?= addslashes($user['avatar']) ?>' : 'E';
  div.innerHTML = `
    <div class="msg-avatar">${avatar}</div>
    <div class="msg-bubble">
      <div class="ai-text">${role === 'ai' ? formatAIText(content) : escapeHtml(content).replace(/\n/g,'<br>')}</div>
    </div>`;
  chatMessages.insertBefore(div, typingIndicator);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function escapeHtml(text) {
  const d = document.createElement('div');
  d.textContent = text;
  return d.innerHTML;
}

/* ── In-Chat Microphone ── */
const SpeechRecChat = window.SpeechRecognition || window.webkitSpeechRecognition;
let chatRecognizer = null;
let chatMicActive  = false;
let chatMicFinal   = '';

function toggleChatMic() {
  if (chatMicActive) stopChatMic(true);
  else startChatMic();
}

function startChatMic() {
  if (!SpeechRecChat) {
    emToast('Use Chrome or Edge for microphone support', 'warn');
    return;
  }
  chatMicActive = true;
  chatMicFinal  = '';

  const btn = document.getElementById('chatMicBtn');
  const bar = document.getElementById('chatMicBar');
  if (btn) btn.classList.add('active');
  if (bar) bar.style.display = 'flex';

  chatRecognizer = new SpeechRecChat();
  chatRecognizer.lang = 'en-US';
  chatRecognizer.continuous = true;
  chatRecognizer.interimResults = true;

  chatRecognizer.onresult = (e) => {
    let interim = '';
    for (let i = e.resultIndex; i < e.results.length; i++) {
      if (e.results[i].isFinal) chatMicFinal += e.results[i][0].transcript + ' ';
      else interim += e.results[i][0].transcript;
    }
    const el = document.getElementById('chatMicTranscript');
    if (el) el.textContent = (chatMicFinal + interim).trim() || 'Speak now in English...';
  };

  chatRecognizer.onerror = (e) => {
    if (e.error === 'not-allowed') {
      emToast('Microphone access denied. Allow it in browser settings.', 'err');
      stopChatMic(false);
      return;
    }
    if (e.error !== 'no-speech') emToast('Mic error: ' + e.error, 'warn');
  };

  chatRecognizer.onend = () => {
    if (chatMicActive) {
      try { chatRecognizer.start(); } catch(e) {}
    }
  };

  try { chatRecognizer.start(); }
  catch(e) { emToast('Could not start microphone', 'err'); chatMicActive = false; }
}

function stopChatMic(useText) {
  chatMicActive = false;
  if (chatRecognizer) { try { chatRecognizer.stop(); } catch(e) {} chatRecognizer = null; }

  const btn = document.getElementById('chatMicBtn');
  const bar = document.getElementById('chatMicBar');
  if (btn) btn.classList.remove('active');
  if (bar) bar.style.display = 'none';

  const text = chatMicFinal.trim();
  if (useText && text) {
    const input = document.getElementById('chatInput');
    if (input) {
      input.value = text;
      input.style.height = 'auto';
      input.style.height = input.scrollHeight + 'px';
      input.focus();
    }
    emToast('Speech captured! Press Enter to send.', 'info');
  }
  chatMicFinal = '';
  const el = document.getElementById('chatMicTranscript');
  if (el) el.textContent = 'Speak now in English...';
}

</script>

<?php include 'includes/footer.php'; ?>
