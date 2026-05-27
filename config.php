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

// --- Admin Guard ---
function isAdmin($user = null) {
    if ($user === null) $user = currentUser();
    return $user && (($user['role'] ?? 'user') === 'admin');
}

function requireAdmin() {
    $user = currentUser();
    if (!isAdmin($user)) {
        header('Location: ' . APP_URL . '/dashboard.php');
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

function jsonFromAI($text) {
    $cleaned = trim(preg_replace('/```json|```/i', '', $text));
    $data = json_decode($cleaned, true);
    if ($data !== null) return $data;

    $start = strpos($cleaned, '{');
    $end = strrpos($cleaned, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $data = json_decode(substr($cleaned, $start, $end - $start + 1), true);
        if ($data !== null) return $data;
    }

    $start = strpos($cleaned, '[');
    $end = strrpos($cleaned, ']');
    if ($start !== false && $end !== false && $end > $start) {
        $data = json_decode(substr($cleaned, $start, $end - $start + 1), true);
        if ($data !== null) return $data;
    }

    return null;
}

function practiceTypes() {
    return [
        'better_english',
        'grammar_choice',
        'vocabulary_quiz',
        'writing_prompt',
        'speaking_prompt',
        'sentence_rearrangement',
        'fill_blank',
        'reading_comprehension',
        'daily_challenge_set',
        'scenario_roleplay',
        'analytical_english',
        'word_sentence_builder',
        'tense_quiz',
        'synonyms_antonyms_quiz',
        'sentence_meaning_quiz'
    ];
}

function learningModulePrompt($module, $level, $topic = '', $answer = '') {
    $level = in_array($level, ['Beginner', 'Intermediate', 'Advanced'], true) ? $level : 'Beginner';
    $topic = trim($topic) ?: 'daily English';
    $base = "You are an advanced English Learning AI Tutor inside a web application. Adjust difficulty to $level. Keep responses educational, structured, concise, and clear. Never skip explanations.";

    $templates = [
        'vocabulary_lesson' => "TASK: Generate Vocabulary Lesson about $topic.\nOUTPUT FORMAT:\nWord: {word}\nLevel: {$level}\nMeaning:\n{simple definition}\n\nSynonyms:\n- ...\n- ...\n\nAntonyms:\n- ...\n- ...\n\nExample Sentences (VERY IMPORTANT):\n1. {sentence using word}\n2. {sentence using word}\n3. {sentence using word}\n4. {sentence using word}\n5. {sentence using word}\n\nPronunciation Tip:\n{simple pronunciation guide}",
        'sentence_rearrangement' => "TASK: Create scrambled sentence about $topic.\nOUTPUT FORMAT:\nScrambled Words:\n{shuffled words}\n\nDifficulty: {$level}\n\nHint (optional):\n{grammar clue}\n\nCorrect Answer (for system validation only):\n{correct sentence}",
        'fill_blank' => "TASK: Generate fill in the blank question about $topic.\nOUTPUT FORMAT:\nSentence:\n{sentence with blank}\n\nOptions:\nA. {option}\nB. {option}\nC. {option}\nD. {option}\n\nCorrect Answer: {letter}\n\nExplanation:\n{why correct answer is right}",
        'reading_comprehension' => "TASK: Generate passage + questions about $topic.\nOUTPUT FORMAT:\nTitle: {title}\n\nPassage:\n{short paragraph 100-200 words}\n\nQuestions:\n1. {question}\n2. {question}\n3. {question}\n\nAnswers:\n1. {answer + explanation}\n2. {answer + explanation}\n3. {answer + explanation}",
        'daily_challenge_set' => "TASK: Generate daily practice set about $topic.\nOUTPUT FORMAT:\nDaily Challenge - Level: {$level}\n\nGrammar (5 questions)\n1. ...\n2. ...\n3. ...\n4. ...\n5. ...\n\nVocabulary (1 word)\n\nWord: {word}\n\nSpeaking Task\n\nSpeak or type:\n\"{prompt}\"\n\nConversation Task\n\nReply naturally to:\n\"{scenario}\"",
        'scenario_roleplay' => "TASK: Roleplay situation about $topic.\nOUTPUT FORMAT:\nScenario: {example}\n\nSituation:\n{short description}\n\nUser Task:\nChoose or type your response\n\nAI Response Options:\nA. {bad answer}\nB. {okay answer}\nC. {best answer}\n\nBest Answer Explanation:\n{why it is best}",
        'analytical_english' => $answer
            ? "TASK: Evaluate this analytical English answer.\nQuestion/topic: $topic\nLearner answer: $answer\nOUTPUT FORMAT:\nQuestion:\n{real-life thinking question}\n\nUser Task:\nAnswer in 3-5 sentences\n\nEvaluation Criteria:\n\nGrammar (0-100): {score}\nClarity (0-100): {score}\nLogic (0-100): {score}\nVocabulary (0-100): {score}\n\nAI Feedback:\n\nCorrections\n{corrections}\n\nImproved version of answer\n{improved answer}\n\nExplanation of mistakes\n{explanation}"
            : "TASK: Critical thinking in English about $topic.\nOUTPUT FORMAT:\nQuestion:\n{real-life thinking question}\n\nUser Task:\nAnswer in 3-5 sentences\n\nEvaluation Criteria:\n\nGrammar (0-100)\nClarity (0-100)\nLogic (0-100)\nVocabulary (0-100)\n\nAI Feedback:\n\nCorrections\nImproved version of answer\nExplanation of mistakes",
        'word_sentence_builder' => "TASK: Generate vocabulary + practice sentences about $topic.\nOUTPUT FORMAT:\nWord: {word}\nLevel: {$level}\n\nMeaning:\n{simple meaning}\n\nMake 5 Sentences Using the Word:\n1. {sentence 1}\n2. {sentence 2}\n3. {sentence 3}\n4. {sentence 4}\n5. {sentence 5}\n\nUsage Tip:\n\n{how to use the word naturally in real life}"
    ];

    return $base . "\n\n" . ($templates[$module] ?? $templates['vocabulary_lesson']);
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

// --- Call Anthropic Claude API ---
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
    $schemaVersion = '2026-05-27-quiz-types';
    if (($_SESSION['tables_checked'] ?? '') === $schemaVersion) return;
    $db = db();
    $col = $db->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE users ADD role ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER english_level");
    }
    $col = $db->query("SHOW COLUMNS FROM vocabulary LIKE 'tags'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE vocabulary ADD tags VARCHAR(255) DEFAULT '' AFTER category");
    }
    $col = $db->query("SHOW COLUMNS FROM vocabulary LIKE 'active'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE vocabulary ADD active TINYINT(1) DEFAULT 1 AFTER tags");
    }
    $col = $db->query("SHOW COLUMNS FROM challenges LIKE 'tags'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE challenges ADD tags VARCHAR(255) DEFAULT '' AFTER difficulty");
    }
    $col = $db->query("SHOW COLUMNS FROM challenges LIKE 'active'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE challenges ADD active TINYINT(1) DEFAULT 1 AFTER tags");
    }
    $db->query("ALTER TABLE practice_items MODIFY type ENUM('better_english','grammar_choice','vocabulary_quiz','writing_prompt','speaking_prompt','sentence_rearrangement','fill_blank','reading_comprehension','daily_challenge_set','scenario_roleplay','analytical_english','word_sentence_builder','tense_quiz','synonyms_antonyms_quiz','sentence_meaning_quiz') NOT NULL DEFAULT 'better_english'");
    $col = $db->query("SHOW COLUMNS FROM practice_items LIKE 'tags'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE practice_items ADD tags VARCHAR(255) DEFAULT '' AFTER category");
    }
    $col = $db->query("SHOW COLUMNS FROM practice_items LIKE 'audio_url'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE practice_items ADD audio_url VARCHAR(255) DEFAULT '' AFTER tags");
    }
    $col = $db->query("SHOW COLUMNS FROM speaking_prompts LIKE 'tags'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE speaking_prompts ADD tags VARCHAR(255) DEFAULT '' AFTER category");
    }
    $col = $db->query("SHOW COLUMNS FROM speaking_prompts LIKE 'audio_url'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE speaking_prompts ADD audio_url VARCHAR(255) DEFAULT '' AFTER tags");
    }
    $col = $db->query("SHOW COLUMNS FROM speaking_prompts LIKE 'active'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE speaking_prompts ADD active TINYINT(1) DEFAULT 1 AFTER audio_url");
    }
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
        tags VARCHAR(255) DEFAULT '',
        audio_url VARCHAR(255) DEFAULT '',
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->query("CREATE TABLE IF NOT EXISTS practice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('better_english','grammar_choice','vocabulary_quiz','writing_prompt','speaking_prompt','sentence_rearrangement','fill_blank','reading_comprehension','daily_challenge_set','scenario_roleplay','analytical_english','word_sentence_builder','tense_quiz','synonyms_antonyms_quiz','sentence_meaning_quiz') NOT NULL DEFAULT 'better_english',
        title VARCHAR(200) NOT NULL,
        prompt TEXT NOT NULL,
        option_a TEXT,
        option_b TEXT,
        option_c TEXT,
        option_d TEXT,
        correct_option CHAR(1),
        answer_key TEXT,
        explanation TEXT,
        difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
        category VARCHAR(80) DEFAULT 'general',
        tags VARCHAR(255) DEFAULT '',
        audio_url VARCHAR(255) DEFAULT '',
        xp_reward INT DEFAULT 25,
        active TINYINT(1) DEFAULT 1,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    $col = $db->query("SHOW COLUMNS FROM practice_items LIKE 'option_d'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE practice_items ADD option_d TEXT AFTER option_c");
    }
    $col = $db->query("SHOW COLUMNS FROM practice_items LIKE 'answer_key'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE practice_items ADD answer_key TEXT AFTER correct_option");
    }
    $db->query("CREATE TABLE IF NOT EXISTS user_practice_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        practice_item_id INT NOT NULL,
        answer TEXT,
        is_correct TINYINT(1) DEFAULT 0,
        ai_feedback TEXT,
        xp_earned INT DEFAULT 0,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (practice_item_id) REFERENCES practice_items(id) ON DELETE CASCADE
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
    $r = $db->query("SELECT COUNT(*) AS c FROM practice_items WHERE type='sentence_rearrangement'");
    if ($r && (int)$r->fetch_assoc()['c'] === 0) {
        $db->query("INSERT INTO practice_items (type,title,prompt,option_a,answer_key,explanation,difficulty,category,tags,xp_reward) VALUES
            ('sentence_rearrangement','Build a Simple Sentence','Drag the words into the correct order.','The bird can fly.','The bird can fly.','English sentences usually follow subject + helping verb + main verb. Here, \"The bird\" is the subject, \"can\" is the helping verb, and \"fly\" is the action.','beginner','grammar','word order, sentence structure',25)
        ");
    }
    $r = $db->query("SELECT COUNT(*) AS c FROM practice_items WHERE type='fill_blank'");
    if ($r && (int)$r->fetch_assoc()['c'] === 0) {
        $db->query("INSERT INTO practice_items (type,title,prompt,option_a,option_b,option_c,option_d,correct_option,answer_key,explanation,difficulty,category,tags,xp_reward) VALUES
            ('fill_blank','Choose the Right Preposition','She is interested ____ learning English.','on','in','at','for','B','in','Use \"interested in\" before a noun or gerund. The natural phrase is \"interested in learning English.\"','beginner','grammar','prepositions, gerunds',25)
        ");
    }
    $r = $db->query("SELECT COUNT(*) AS c FROM practice_items WHERE type='reading_comprehension'");
    if ($r && (int)$r->fetch_assoc()['c'] === 0) {
        $db->query("INSERT INTO practice_items (type,title,prompt,option_a,option_b,option_c,answer_key,explanation,difficulty,category,tags,xp_reward) VALUES
            ('reading_comprehension','Maria Practices Every Day','Maria wants to speak English more confidently at work. Every morning, she reads one short paragraph aloud before breakfast. At lunch, she writes five new words in her notebook and makes her own sentences. In the evening, she talks with an AI tutor for ten minutes. After one month, Maria notices that she can answer customers faster and explain ideas more clearly. She still makes mistakes, but she understands them and corrects them quickly.','Why does Maria practice English?','What does she do at lunch?','How does Maria improve after one month?','1. She wants to speak more confidently at work. 2. She writes five new words and makes sentences. 3. She answers customers faster and explains ideas more clearly.','Good answers should use details from the story. The key idea is that small daily habits help Maria improve her workplace English.','beginner','reading','main idea, details',35)
        ");
    }
    $r = $db->query("SELECT COUNT(*) AS c FROM practice_items WHERE type IN ('tense_quiz','synonyms_antonyms_quiz','sentence_meaning_quiz')");
    if ($r && (int)$r->fetch_assoc()['c'] === 0) {
        $db->query("INSERT INTO practice_items (type,title,prompt,option_a,option_b,option_c,option_d,correct_option,answer_key,explanation,difficulty,category,tags,xp_reward) VALUES
            ('tense_quiz','Choose the Correct Tense','Tomorrow, she ____ her English lesson.','attended','attends','will attend','is attended','C','will attend','Use future tense with tomorrow: she will attend her English lesson.','beginner','grammar','future tense, verb tense',25),
            ('synonyms_antonyms_quiz','Synonym of Happy','Choose the synonym of happy.','Sad','Joyful','Angry','Tired','B','Joyful','A synonym has a similar meaning. Joyful means very happy.','beginner','vocabulary','synonyms, emotions',25),
            ('sentence_meaning_quiz','Meaning of an Idiom','\"He is on cloud nine\" means:','He is sad','He is very happy','He is tired','He is sick','B','He is very happy','On cloud nine is an idiom that means extremely happy.','beginner','comprehension','idioms, sentence meaning',25)
        ");
    }
    $r = $db->query("SELECT COUNT(*) AS c FROM practice_items");
    if ($r && (int)$r->fetch_assoc()['c'] === 0) {
        $db->query("INSERT INTO practice_items (type,title,prompt,option_a,option_b,option_c,correct_option,explanation,difficulty,category,xp_reward) VALUES
            ('better_english','Choose the Better English','Which sentence sounds more natural and correct?','I am interested in learning English.','I am interesting to learn English.',NULL,'A','Use interested when you feel curiosity. Interesting describes the thing that causes curiosity.','beginner','grammar',25),
            ('grammar_choice','Past Tense Practice','Choose the correct sentence.','Yesterday I go to work.','Yesterday I went to work.','Yesterday I will go to work.','B','Use went for a completed action in the past.','beginner','tenses',25),
            ('vocabulary_quiz','Vocabulary in Context','Choose the best word: She gave a clear and ___ explanation.','confusing','concise','late','B','Concise means clear and expressed in few words.','intermediate','vocabulary',30),
            ('writing_prompt','Write a Strong Sentence','Write one professional sentence using the word \"proactive\".',NULL,NULL,NULL,NULL,'A strong answer uses proactive to mean taking action before problems happen.','intermediate','writing',35),
            ('speaking_prompt','Read Aloud: Clear Introduction','Read this aloud: Hello, my name is Anna. I am practicing English every day so I can speak more clearly at work.',NULL,NULL,NULL,NULL,'Focus on clear pacing and word endings.','beginner','speaking',25)
        ");
    }
    $adminEmail = esc('admin@englishmaster.local');
    $admin = $db->query("SELECT id FROM users WHERE email='$adminEmail' LIMIT 1");
    if (!$admin || $admin->num_rows === 0) {
        $pass = esc(password_hash('admin123', PASSWORD_BCRYPT));
        $db->query("INSERT INTO users (name,email,password,english_level,role,avatar,last_active) VALUES ('Admin','admin@englishmaster.local','$pass','advanced','admin','A',CURDATE())");
    }
    $_SESSION['tables_checked'] = $schemaVersion;
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
