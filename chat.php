<?php
require_once 'config.php';
auth();
$user = currentUser();
$db   = db();
$uid  = (int)$_SESSION['uid'];
$pageTitle = 'AI Chat';

/* ── Auto-create chat_history table if missing ── */
$db->query("
    CREATE TABLE IF NOT EXISTS chat_history (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        role       ENUM('user','assistant') NOT NULL,
        content    TEXT NOT NULL,
        session_id VARCHAR(32) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

/* ── Session ID ── */
$sessionId = $_GET['session'] ?? $_SESSION['chat_session'] ?? null;
if (!$sessionId) {
    $sessionId = bin2hex(random_bytes(8));
    $_SESSION['chat_session'] = $sessionId;
}
$sid = esc($sessionId);

/* ══════════════════════════════════════════════
   AJAX — receive message, call AI, return reply
   ══════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {

    /* Buffer any stray PHP output so it never corrupts the JSON */
    ob_start();

    header('Content-Type: application/json; charset=utf-8');

    $userMsg = trim($_POST['message'] ?? '');
    if ($userMsg === '') {
        ob_end_clean();
        echo json_encode(['ok' => false, 'error' => 'Empty message']);
        exit;
    }

    /* ── Load full conversation history for this session ── */
    $history = $db->query("
        SELECT role, content
        FROM   chat_history
        WHERE  user_id = $uid AND session_id = '$sid'
        ORDER  BY created_at ASC
        LIMIT  40
    ");
    $prevMessages = [];
    if ($history) {
        while ($row = $history->fetch_assoc()) {
            $prevMessages[] = ['role' => $row['role'], 'content' => $row['content']];
        }
    }

    /* ── Save user message first ── */
    $m = esc($userMsg);
    $db->query("INSERT INTO chat_history (user_id, role, content, session_id)
                VALUES ($uid, 'user', '$m', '$sid')");
    addXP($uid, 10, 'Chat conversation');

    /* ── Build message array for AI ── */
    $aiMessages   = $prevMessages;
    $aiMessages[] = ['role' => 'user', 'content' => $userMsg];

    /* ── System prompt ── */
    $level  = $user['english_level'] ?? 'beginner';
    $name   = clean($user['name']);
    $system = "You are EnglishMaster AI — a warm, encouraging English tutor and conversation partner for {$name}.

Your job every reply:
1. Continue the conversation naturally like a friendly human (ask a follow-up question or respond to what they said).
2. If the user made a grammar mistake, GENTLY correct it ONCE using this exact format:
   ✏️ **Correction:** \"[corrected sentence]\"
   💡 **Why:** [simple one-sentence explanation]
3. Suggest a more natural phrase if they used an awkward one.
4. Keep replies SHORT — 2 to 4 sentences max, then ask one question to keep the conversation going.
5. NEVER end a reply without asking something or continuing the topic — the conversation must FLOW.
6. Be warm and encouraging. Make learning feel safe and fun.

User's English level: {$level}.
Language: English only. Never translate. If they write in another language, kindly ask them to try in English.";

    /* ── Call AI ── */
    $aiReply = callAI($aiMessages, $system, 600);

    /* Fallback if somehow blank */
    if (trim($aiReply) === '') {
        $aiReply = "I'm here and listening! Could you tell me a little more about what you meant? 😊";
    }

    /* ── Save AI reply ── */
    $r = esc($aiReply);
    $db->query("INSERT INTO chat_history (user_id, role, content, session_id)
                VALUES ($uid, 'assistant', '$r', '$sid')");

    /* ── Return JSON ── */
    $strayOutput = ob_get_clean();
    if ($strayOutput) {
        error_log('[chat.php] Stray output before JSON: ' . $strayOutput);
    }

    echo json_encode([
        'ok'       => true,
        'reply'    => $aiReply,
        'xp'       => 10,
        'debug'    => $strayOutput ?: null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── New session request ── */
if (isset($_GET['new'])) {
    $_SESSION['chat_session'] = bin2hex(random_bytes(8));
    header('Location: chat.php');
    exit;
}

/* ── Load existing messages for page render ── */
$history = $db->query("
    SELECT role, content
    FROM   chat_history
    WHERE  user_id = $uid AND session_id = '$sid'
    ORDER  BY created_at ASC
");
$messages = [];
if ($history) {
    while ($row = $history->fetch_assoc()) {
        $messages[] = $row;
    }
}

include 'includes/header.php';
?>

<div class="page-header flex-between" style="flex-wrap:wrap;gap:12px;">
  <div>
    <h1>💬 AI Conversation Chat</h1>
    <p>Talk freely with your AI tutor. Get corrections and learn naturally.</p>
  </div>
  <a href="chat.php?new=1" class="btn btn-outline btn-sm" data-noloader>+ New Chat</a>
</div>

<!-- Chat Card -->
<div class="card" style="padding:0;overflow:hidden;">
  <div class="chat-container">

    <!-- Messages list -->
    <div class="chat-messages" id="chatMessages">

      <?php if (empty($messages)): ?>
      <!-- Welcome bubble -->
      <div class="msg ai" id="welcomeMsg">
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
        <div class="msg-avatar"><?= $msg['role'] === 'user' ? clean($user['avatar']) : 'E' ?></div>
        <div class="msg-bubble">
          <div class="ai-text"><?= nl2br(clean($msg['content'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

    </div><!-- /chat-messages -->

    <!-- Input bar -->
    <div class="chat-input-bar">
      <!-- Mic button -->
      <button class="mic-chat-btn" id="chatMicBtn" onclick="toggleChatMic()" title="Click to speak">
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
        oninput="autoResize(this)"
      ></textarea>

      <button class="btn btn-primary" id="sendBtn" onclick="sendMessage()">
        Send ↑
      </button>
    </div>

    <!-- Mic recording bar -->
    <div id="chatMicBar" style="display:none;background:var(--bg-card2);border-top:1px solid var(--border);padding:12px 16px;align-items:flex-start;gap:10px;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;padding-top:2px;">
        <div style="width:10px;height:10px;background:#e74c3c;border-radius:50%;animation:micRecordPulse 1.2s infinite;flex-shrink:0;"></div>
        <span style="font-size:12px;color:var(--red);font-weight:700;white-space:nowrap;">Listening...</span>
      </div>
      <div id="chatMicTranscript"
           style="flex:1;min-width:120px;font-size:14px;color:var(--text-3);
                  font-style:italic;line-height:1.5;word-break:break-word;
                  padding:6px 10px;background:var(--bg-base);border-radius:8px;
                  border:1px solid var(--border);min-height:34px;">
        Speak now in English...
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0;">
        <button class="btn btn-success btn-sm" onclick="stopChatMic(true)"  style="font-size:12px;">✅ Use This</button>
        <button class="btn btn-ghost   btn-sm" onclick="stopChatMic(false)" style="font-size:12px;">✕ Cancel</button>
      </div>
    </div>

  </div><!-- /chat-container -->
</div><!-- /card -->

<!-- Quick-start prompts -->
<div class="grid-3 mt-16" style="gap:12px;">
  <div class="action-card" style="cursor:pointer;padding:14px;" onclick="usePrompt('Tell me about your day and what you did today.')">
    <div style="font-size:11px;color:var(--text-3);font-weight:700;text-transform:uppercase;margin-bottom:4px;">💬 Starter</div>
    <div style="font-size:13px;color:var(--text-2);">Ask about my day</div>
  </div>
  <div class="action-card" style="cursor:pointer;padding:14px;" onclick="usePrompt('Can we practice job interview questions for a customer service position?')">
    <div style="font-size:11px;color:var(--text-3);font-weight:700;text-transform:uppercase;margin-bottom:4px;">👔 Practice</div>
    <div style="font-size:13px;color:var(--text-2);">Interview practice mode</div>
  </div>
  <div class="action-card" style="cursor:pointer;padding:14px;" onclick="usePrompt('Can you teach me some common English phrases used in office conversations?')">
    <div style="font-size:11px;color:var(--text-3);font-weight:700;text-transform:uppercase;margin-bottom:4px;">📖 Learn</div>
    <div style="font-size:13px;color:var(--text-2);">Office English phrases</div>
  </div>
</div>

<script>
/* ── emToast fallback (in case header.php doesn't define it) ── */
if (typeof emToast === 'undefined') {
  window.emToast = function(msg, type, duration) {
    duration = duration || 3500;
    const colors = { info:'#4f8ef7', warn:'#fbbf24', err:'#f87171', xp:'#34d399' };
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:10px 18px;' +
      'border-radius:10px;font-size:13px;font-weight:600;color:#fff;background:' +
      (colors[type] || '#4f8ef7') + ';box-shadow:0 4px 20px rgba(0,0,0,0.25);' +
      'transition:opacity 0.4s;max-width:320px;word-break:break-word;';
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, duration);
  };
}

/* ═══════════════════════════════════════════════════
   Chat Engine  — clean rebuild
   ═══════════════════════════════════════════════════ */

const chatBox   = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const sendBtn   = document.getElementById('sendBtn');

/* Scroll to bottom on load */
chatBox.scrollTop = chatBox.scrollHeight;

/* ── Helpers ── */
function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function usePrompt(text) {
  chatInput.value = text;
  chatInput.focus();
  autoResize(chatInput);
}

function scrollToBottom() {
  chatBox.scrollTop = chatBox.scrollHeight;
}

/* ── Append a message bubble ── */
function addBubble(role, html) {
  const wrap = document.createElement('div');
  wrap.className = 'msg ' + (role === 'user' ? 'user' : 'ai');

  const avatar = document.createElement('div');
  avatar.className = 'msg-avatar';
  avatar.textContent = role === 'user'
    ? '<?= addslashes(clean($user['avatar'])) ?>'
    : 'E';

  const bubble = document.createElement('div');
  bubble.className = 'msg-bubble';

  const inner = document.createElement('div');
  inner.className = 'ai-text';
  inner.innerHTML = html;

  bubble.appendChild(inner);
  wrap.appendChild(avatar);
  wrap.appendChild(bubble);
  chatBox.appendChild(wrap);       /* ← simple appendChild, no insertBefore */
  scrollToBottom();
  return wrap;
}

/* ── Typing indicator bubble ── */
let typingBubble = null;
function showTyping() {
  typingBubble = addBubble('ai',
    '<div class="ai-thinking"><span></span><span></span><span></span></div>');
}
function hideTyping() {
  if (typingBubble) { typingBubble.remove(); typingBubble = null; }
}

/* ── Format AI markdown-light text ── */
function fmt(text) {
  return text
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g,     '<em>$1</em>')
    .replace(/`(.*?)`/g,       '<code>$1</code>')
    .replace(/\n/g,            '<br>');
}

/* ── Disable / re-enable send ── */
function lockSend()   { sendBtn.disabled = true;  sendBtn.style.opacity = '0.5'; }
function unlockSend() { sendBtn.disabled = false; sendBtn.style.opacity = ''; sendBtn.textContent = 'Send ↑'; }

/* ══════════════════════════════════════════════
   MAIN: sendMessage
   ══════════════════════════════════════════════ */
async function sendMessage() {
  const msg = chatInput.value.trim();
  if (!msg || sendBtn.disabled) return;

  /* Show user bubble */
  addBubble('user', fmt(msg));
  chatInput.value = '';
  chatInput.style.height = 'auto';

  /* Lock button + show typing dots */
  lockSend();
  showTyping();

  try {
    const fetchUrl = window.location.pathname.replace(/\/[^/]*$/, '') + '/chat.php';
    const resp = await fetch(fetchUrl, {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    'ajax=1&message=' + encodeURIComponent(msg)
    });

    /* Read raw text first so we can debug bad responses */
    const rawText = await resp.text();

    hideTyping();

    /* Try parsing JSON */
    let data;
    try {
      data = JSON.parse(rawText);
    } catch(parseErr) {
      /* PHP probably output a warning — show it */
      addBubble('ai',
        '<span style="color:var(--red)">⚠️ Server error. Check your API key in config.php.</span>' +
        (rawText.length < 400 ? '<br><code style="font-size:11px">' + fmt(rawText) + '</code>' : ''));
      unlockSend();
      return;
    }

    /* Show AI reply */
    if (data.reply && data.reply.trim() !== '') {
      addBubble('ai', fmt(data.reply));
      emToast('+' + (data.xp || 10) + ' XP earned!', 'xp');
    } else if (data.error) {
      addBubble('ai',
        '<span style="color:var(--yellow)">⚠️ ' + fmt(data.error) + '</span>');
    } else {
      /* Catch-all — show whatever came back */
      addBubble('ai',
        '<span style="color:var(--yellow)">⚠️ Unexpected response. ' +
        'Make sure your Anthropic API key is set in config.php.</span>');
    }

  } catch(networkErr) {
    hideTyping();
    console.error('[chat fetch error]', networkErr);
    const isLocal = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    const hint = isLocal
      ? 'Make sure XAMPP Apache is running and you are visiting via <strong>http://localhost/...</strong> (not file://).'
      : 'The server could not be reached. Check your internet connection or server status.';
    addBubble('ai',
      '<span style="color:var(--red)">❌ Could not reach the server.</span><br>' +
      '<span style="font-size:12px;color:var(--text-2)">' + hint + '</span>');
    emToast('Connection failed — ' + networkErr.message, 'err', 5000);
  }

  unlockSend();
}

/* ═══════════════════════════════════════════════════
   In-Chat Microphone  (v3 — no getUserMedia pre-check)
   ═══════════════════════════════════════════════════ */
const SpeechRecChat = window.SpeechRecognition || window.webkitSpeechRecognition;
let chatRec       = null;
let chatMicActive = false;
let chatFinal     = '';
let chatInterim   = '';

function toggleChatMic() {
  if (chatMicActive) stopChatMic(true);
  else startChatMic();
}

function startChatMic() {
  if (!SpeechRecChat) {
    alert('Speech recognition needs Chrome or Edge browser.\nFirefox is not supported.');
    return;
  }
  chatFinal = ''; chatInterim = '';
  chatMicActive = true;

  const btn  = document.getElementById('chatMicBtn');
  const bar  = document.getElementById('chatMicBar');
  const trEl = document.getElementById('chatMicTranscript');
  if (btn)  btn.classList.add('active');
  if (bar)  bar.style.display = 'flex';
  if (trEl) { trEl.textContent = 'Speak now in English...'; trEl.style.color = 'var(--text-3)'; }

  chatRec = new SpeechRecChat();
  chatRec.lang            = 'en-US';
  chatRec.continuous      = true;
  chatRec.interimResults  = true;
  chatRec.maxAlternatives = 1;

  chatRec.onresult = function(event) {
    chatInterim = '';
    for (let i = event.resultIndex; i < event.results.length; i++) {
      const piece = event.results[i][0].transcript;
      if (event.results[i].isFinal) chatFinal += (chatFinal ? ' ' : '') + piece.trim();
      else chatInterim += piece;
    }
    const el = document.getElementById('chatMicTranscript');
    if (el) {
      const show = (chatFinal + (chatInterim ? ' ' + chatInterim : '')).trim();
      el.textContent = show || 'Speak now in English...';
      el.style.color = show ? 'var(--text-1)' : 'var(--text-3)';
    }
  };

  chatRec.onerror = function(event) {
    if (event.error === 'not-allowed') {
      chatMicActive = false;
      _chatMicReset();
      alert('Microphone access denied.\n\nFix:\n1. Click the 🔒 lock icon in the address bar\n2. Set Microphone → Allow\n3. Refresh the page');
    } else if (event.error === 'network') {
      emToast('Speech API needs internet (Chrome sends audio to Google).', 'warn', 4000);
    }
    /* no-speech and aborted are normal — ignore */
  };

  chatRec.onend = function() {
    if (chatMicActive) {
      try { chatRec.start(); } catch(e) {}   /* keep listening */
    }
  };

  try { chatRec.start(); }
  catch(e) { chatMicActive = false; _chatMicReset(); emToast('Mic error: ' + e.message, 'err'); }
}

function stopChatMic(useText) {
  chatMicActive = false;
  if (chatRec) { chatRec.onend = null; try { chatRec.stop(); } catch(e) {} chatRec = null; }
  _chatMicReset();

  const text = (chatFinal + ' ' + chatInterim).trim();
  if (useText && text) {
    chatInput.value = text;
    autoResize(chatInput);
    chatInput.focus();
    emToast('✅ Speech ready — press Enter to send!', 'info', 2500);
  } else if (useText) {
    emToast('Nothing heard — speak louder or closer to the mic.', 'warn', 3000);
  }
  chatFinal = ''; chatInterim = '';
  const trEl = document.getElementById('chatMicTranscript');
  if (trEl) { trEl.textContent = 'Speak now in English...'; trEl.style.color = 'var(--text-3)'; }
}

function _chatMicReset() {
  document.getElementById('chatMicBtn')?.classList.remove('active');
  const bar = document.getElementById('chatMicBar');
  if (bar) bar.style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>
