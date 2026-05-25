<?php
// ============================================
// EnglishMaster AI - Configuration
// ============================================

// --- Database Settings (XAMPP defaults) ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Change if you set a MySQL password
define('DB_NAME', 'english_master_db');

// --- Anthropic API Key ---
// Get your API key from: https://console.anthropic.com/
define('GROQ_API_KEY', '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');   // ← Get from https://console.groq.com/keys
define('GROQ_MODEL', 'openai/gpt-oss-120b');

// --- App Settings ---
define('APP_NAME', 'EnglishMaster AI');
define('APP_URL', 'http://localhost/english-master');

// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database Connection ---
function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="font-family:sans-serif;padding:40px;background:#fee2e2;color:#991b1b;border-radius:8px;margin:20px;">
                <h2>❌ Database Connection Failed</h2>
                <p><strong>Error:</strong> ' . $conn->connect_error . '</p>
                <p>Make sure XAMPP MySQL is running and you have run <strong>db_setup.sql</strong> in phpMyAdmin.</p>
            </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// --- Auth Guard ---
function auth() {
    if (empty($_SESSION['uid'])) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

// --- Get Current User ---
function currentUser() {
    if (empty($_SESSION['uid'])) return null;
    $db = db();
    $uid = (int)$_SESSION['uid'];
    $r = $db->query("SELECT * FROM users WHERE id = $uid LIMIT 1");
    return $r ? $r->fetch_assoc() : null;
}

// --- Sanitize Input ---
function clean($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

// --- Escape for DB ---
function esc($str) {
    return db()->real_escape_string($str);
}

// --- Add XP to User ---
function addXP($uid, $amount, $reason = '') {
    $db = db();
    $uid = (int)$uid;
    $amount = (int)$amount;
    $db->query("UPDATE users SET xp = xp + $amount, last_active = CURDATE() WHERE id = $uid");
    if ($reason) {
        $r = esc($reason);
        $db->query("INSERT INTO xp_log (user_id, amount, reason) VALUES ($uid, $amount, '$r')");
    }
    // Level up: every 500 XP = 1 level
    $res = $db->query("SELECT xp FROM users WHERE id = $uid");
    $u = $res->fetch_assoc();
    $newLevel = max(1, (int)floor($u['xp'] / 500) + 1);
    $db->query("UPDATE users SET level = $newLevel WHERE id = $uid");
}

// --- Update Streak ---
function updateStreak($uid) {
    $db = db();
    $uid = (int)$uid;
    $res = $db->query("SELECT last_active, streak FROM users WHERE id = $uid");
    $u = $res->fetch_assoc();
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    if ($u['last_active'] == $yesterday) {
        $db->query("UPDATE users SET streak = streak + 1, last_active = '$today' WHERE id = $uid");
    } elseif ($u['last_active'] != $today) {
        $db->query("UPDATE users SET streak = 1, last_active = '$today' WHERE id = $uid");
    }
}

function callAI($messages, $system = '', $maxTokens = 1500) {
    $key = GROQ_API_KEY;
  if (empty($key) || $key === 'your_groq_api_key_here') {
        return "⚠️ **Groq API Key Not Set**: Please put your real key in `config.php` (get it from https://console.groq.com/keys).";
    }

    // Prepare messages with system prompt
    $payloadMessages = $messages;
    if ($system) {
        array_unshift($payloadMessages, ['role' => 'system', 'content' => $system]);
    }

    $payload = [
        'model' => GROQ_MODEL,
        'messages' => $payloadMessages,
        'max_tokens' => $maxTokens,
        'temperature' => 0.7,
        'top_p' => 0.9
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        return "Network error: $curlErr";
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorMsg = $data['error']['message'] ?? $response;
        return "Groq API Error ({$httpCode}): " . $errorMsg;
    }

    return $data['choices'][0]['message']['content'] ?? 'No response from AI.';
}

// --- Auto-migrate: create new tables if they don't exist ---
// This runs once per session so old installs get upgraded automatically
function ensureNewTables() {
    if (!empty($_SESSION['tables_checked'])) return;
    $db = db();
    $db->query("CREATE TABLE IF NOT EXISTS speaking_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mode ENUM('free','read_aloud','pronunciation') DEFAULT 'free',
        original_text TEXT, transcript TEXT, ai_feedback TEXT,
        grammar_score INT DEFAULT 0, fluency_score INT DEFAULT 0,
        overall_score INT DEFAULT 0, duration_seconds INT DEFAULT 0,
        word_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $db->query("CREATE TABLE IF NOT EXISTS speaking_prompts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        text TEXT NOT NULL,
        topic VARCHAR(100) DEFAULT 'General',
        difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
        category VARCHAR(50) DEFAULT 'general',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    // Seed prompts if empty
    $r = $db->query("SELECT COUNT(*) AS c FROM speaking_prompts");
    if ($r && (int)$r->fetch_assoc()['c'] === 0) {
        $db->query("INSERT INTO speaking_prompts (text,topic,difficulty,category) VALUES
            ('Good morning! Today is a beautiful day. I am very happy to practice speaking English with you.','Daily Life','beginner','general'),
            ('My name is Maria. I live in Manila. I work in an office and I love learning new things every day.','Introduction','beginner','general'),
            ('Hello! Can you tell me the way to the nearest supermarket? I need to buy some fruits and vegetables.','Directions','beginner','daily'),
            ('Technology has changed the way we communicate. Social media connects millions of people every single day around the world.','Technology','intermediate','general'),
            ('In my opinion, learning English is very important for career growth. It opens many doors and gives you more opportunities.','Career','intermediate','work'),
            ('Customer service is all about understanding what the client needs and providing the best possible solution quickly.','Work','intermediate','work'),
            ('The rapid advancement of artificial intelligence raises important ethical questions about privacy and employment.','AI & Tech','advanced','academic'),
            ('Effective communication in a professional environment requires grammatical accuracy and cultural awareness.','Communication','advanced','work'),
            ('Every day I try to learn five new English words. I write them in my notebook and practice using them in sentences.','Study Habits','beginner','general'),
            ('Please hold the line while I transfer your call to the correct department. Thank you for your patience.','Call Center','intermediate','work')
        ");
    }
    $_SESSION['tables_checked'] = true;
}

// Run migration automatically for logged-in users
if (!empty($_SESSION['uid'])) {
    ensureNewTables();
}

// --- Level Name ---
function levelName($level) {
    if ($level <= 2)  return 'Newcomer';
    if ($level <= 5)  return 'Explorer';
    if ($level <= 10) return 'Learner';
    if ($level <= 20) return 'Practitioner';
    if ($level <= 35) return 'Communicator';
    if ($level <= 50) return 'Fluent Speaker';
    return 'English Master';
}

// --- XP for next level ---
function xpToNextLevel($xp) {
    $currentLevel = max(1, (int)floor($xp / 500) + 1);
    $nextLevelXP = $currentLevel * 500;
    $progress = (($xp % 500) / 500) * 100;
    return ['next' => $nextLevelXP, 'progress' => round($progress), 'needed' => $nextLevelXP - $xp];
}
?>
