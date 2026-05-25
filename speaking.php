<?php
require_once 'config.php';
auth();
$user = currentUser();
$db   = db();
$uid  = (int)$_SESSION['uid'];
$pageTitle = 'Speaking Practice';

/* ── Auto-create speaking tables if they don't exist ─────────── */
$db->query("
    CREATE TABLE IF NOT EXISTS speaking_sessions (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        user_id          INT NOT NULL,
        mode             ENUM('free','read_aloud','pronunciation') DEFAULT 'free',
        original_text    TEXT,
        transcript       TEXT,
        ai_feedback      TEXT,
        grammar_score    INT DEFAULT 0,
        fluency_score    INT DEFAULT 0,
        overall_score    INT DEFAULT 0,
        duration_seconds INT DEFAULT 0,
        word_count       INT DEFAULT 0,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");
$db->query("
    CREATE TABLE IF NOT EXISTS speaking_prompts (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        text       TEXT NOT NULL,
        topic      VARCHAR(100) DEFAULT 'General',
        difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
        category   VARCHAR(50)  DEFAULT 'general',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

/* Seed prompts if table is empty */
$promptCount = $db->query("SELECT COUNT(*) AS c FROM speaking_prompts")->fetch_assoc()['c'] ?? 0;
if ((int)$promptCount === 0) {
    $db->query("INSERT INTO speaking_prompts (text, topic, difficulty, category) VALUES
        ('Good morning! Today is a beautiful day. I am very happy to practice speaking English with you.', 'Daily Life', 'beginner', 'general'),
        ('My name is Maria. I live in Manila. I work in an office and I love learning new things every day.', 'Introduction', 'beginner', 'general'),
        ('Hello! Can you tell me the way to the nearest supermarket? I need to buy some fruits and vegetables.', 'Directions', 'beginner', 'daily'),
        ('Technology has changed the way we communicate. Social media connects millions of people every single day around the world.', 'Technology', 'intermediate', 'general'),
        ('In my opinion, learning English is very important for career growth. It opens many doors and gives you more opportunities in life.', 'Career', 'intermediate', 'work'),
        ('Customer service is all about understanding what the client needs and providing the best possible solution quickly and professionally.', 'Work', 'intermediate', 'work'),
        ('The rapid advancement of artificial intelligence raises important ethical questions about privacy, employment, and human decision-making.', 'AI & Tech', 'advanced', 'academic'),
        ('Effective communication in a professional environment requires grammatical accuracy and cultural awareness.', 'Communication', 'advanced', 'work'),
        ('Every day I try to learn five new English words. I write them in my notebook and practice using them in sentences.', 'Study Habits', 'beginner', 'general'),
        ('Please hold the line while I transfer your call to the correct department. Thank you for your patience.', 'Call Center', 'intermediate', 'work'),
        ('I would like to apply for the position. I have experience in customer service and I am a fast learner.', 'Interview', 'intermediate', 'work'),
        ('Research consistently shows that bilingual individuals develop stronger cognitive flexibility and problem-solving abilities.', 'Education', 'advanced', 'academic')
    ");
}
/* ─────────────────────────────────────────────────────────────── */

/* ── AJAX: Analyse transcript ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_speak'])) {
    header('Content-Type: application/json');

    $mode         = in_array($_POST['mode'] ?? '', ['free','read_aloud','pronunciation']) ? $_POST['mode'] : 'free';
    $transcript   = trim($_POST['transcript']   ?? '');
    $originalText = trim($_POST['original_text'] ?? '');
    $duration     = (int)($_POST['duration'] ?? 0);
    $wordCount    = str_word_count($transcript);

    if (!$transcript) { echo json_encode(['error' => 'No speech detected. Please try again.']); exit; }

    /* ── Build prompt by mode ── */
    if ($mode === 'read_aloud' && $originalText) {
        $system = "You are an English pronunciation coach. Compare the student's spoken transcript to the original text and return ONLY valid JSON (no markdown):
{
  \"accuracy_score\":   <0-100>,
  \"fluency_score\":    <0-100>,
  \"words_correct\":    <number>,
  \"words_total\":      <number>,
  \"missed_words\":     [\"word1\",\"word2\"],
  \"extra_words\":      [\"word1\"],
  \"feedback\":         \"<2-3 sentences: what was good, what to improve>\",
  \"pronunciation_tip\":\"<one specific tip>\",
  \"encouragement\":    \"<motivating sentence>\"
}";
        $prompt = "Original text:\n\"{$originalText}\"\n\nStudent said:\n\"{$transcript}\"\n\nPlease analyse.";

    } elseif ($mode === 'pronunciation') {
        $system = "You are an English pronunciation and speaking fluency coach. Analyse the spoken text for pronunciation accuracy, word stress, and natural delivery. Return ONLY valid JSON:
{
  \"pronunciation_score\": <0-100>,
  \"fluency_score\":        <0-100>,
  \"difficult_words\":      [{\"word\":\"...\",\"correct_pronunciation\":\"...\",\"tip\":\"...\"}],
  \"feedback\":             \"<specific feedback on how they spoke>\",
  \"practice_tip\":         \"<one actionable tip>\",
  \"encouragement\":        \"<motivating sentence>\"
}";
        $prompt = "Analyse the pronunciation in this speech transcript:\n\"{$transcript}\"";

    } else { /* free */
        $system = "You are a friendly English speaking coach. Analyse this speech transcript from a learner and return ONLY valid JSON (no markdown):
{
  \"grammar_score\":    <0-100>,
  \"fluency_score\":    <0-100>,
  \"overall_score\":    <0-100>,
  \"grammar_corrections\": [{\"wrong\":\"...\",\"correct\":\"...\",\"explanation\":\"...\"}],
  \"vocabulary_tips\":  [\"<tip1>\",\"<tip2>\"],
  \"better_phrases\":   [{\"said\":\"...\",\"natural\":\"...\"}],
  \"overall_feedback\": \"<2-3 sentences about their speaking>\",
  \"encouragement\":    \"<warm, motivating sentence>\"
}
Keep corrections simple and encouraging. Max 4 corrections.";
        $prompt = "Please analyse this English speech transcript:\n\"{$transcript}\"";
    }

    $aiRaw   = callAI([['role'=>'user','content'=>$prompt]], $system, 1200);
    $cleaned = trim(preg_replace('/```json|```/i', '', $aiRaw));
    $result  = json_decode($cleaned, true);

    if (!$result) { $result = ['error' => 'Could not parse response.', 'raw' => $aiRaw]; }

    /* ── Save to DB (safe — table auto-created on page load) ── */
    $overallScore = (int)(
        $result['overall_score'] ??
        $result['accuracy_score'] ??
        $result['pronunciation_score'] ??
        round((($result['grammar_score'] ?? 70) + ($result['fluency_score'] ?? 70)) / 2)
    );
    $gScore = (int)($result['grammar_score'] ?? $result['accuracy_score'] ?? $result['pronunciation_score'] ?? $overallScore);
    $fScore = (int)($result['fluency_score'] ?? $overallScore);
    $tr  = esc($transcript);
    $ot  = esc($originalText);
    $fb  = esc(json_encode($result));
    $m   = esc($mode);

    /* Ensure table exists before inserting (handles users who ran old db_setup.sql) */
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

    $insertOk = $db->query("INSERT INTO speaking_sessions (user_id,mode,original_text,transcript,ai_feedback,grammar_score,fluency_score,overall_score,duration_seconds,word_count) VALUES ($uid,'$m','$ot','$tr','$fb',$gScore,$fScore,$overallScore,$duration,$wordCount)");
    if (!$insertOk) {
        error_log("speaking_sessions insert failed: " . $db->error);
    }

    $xpEarned = max(15, min(80, $overallScore / 2 + $wordCount / 2));
    addXP($uid, (int)$xpEarned, 'Speaking practice session');

    $result['xp'] = (int)$xpEarned;
    echo json_encode($result);
    exit;
}

/* ── AJAX: get a random read-aloud prompt ───────────────────── */
if (isset($_GET['get_prompt'])) {
    header('Content-Type: application/json');
    $diff = esc($_GET['diff'] ?? 'beginner');
    $r    = $db->query("SELECT * FROM speaking_prompts WHERE difficulty='$diff' ORDER BY RAND() LIMIT 1");
    $row  = $r ? $r->fetch_assoc() : null;
    if (!$row) {
        /* fallback built-in prompts */
        $fallbacks = [
          'beginner'     => ["Good morning! Today is a beautiful day. I am very happy to practice speaking English with you.", "My name is Maria. I live in Manila. I work in an office and I love learning new things every day.", "Hello! Can you tell me the way to the nearest supermarket? I need to buy some fruits and vegetables."],
          'intermediate' => ["Technology has changed the way we communicate. Social media connects millions of people every single day around the world.", "In my opinion, learning English is very important for career growth. It opens many doors and gives you more opportunities.", "Customer service is all about understanding what the client needs and providing the best possible solution quickly."],
          'advanced'     => ["The rapid advancement of artificial intelligence raises important ethical questions about privacy, employment, and human decision-making processes.", "Effective communication in a professional environment requires not only grammatical accuracy but also cultural awareness and emotional intelligence.", "Research consistently shows that bilingual individuals develop stronger cognitive flexibility and problem-solving abilities compared to monolingual speakers."],
        ];
        $options = $fallbacks[$diff] ?? $fallbacks['beginner'];
        echo json_encode(['text' => $options[array_rand($options)], 'topic' => 'General English', 'difficulty' => $diff]);
    } else {
        echo json_encode($row);
    }
    exit;
}

/* ── Load past sessions ─────────────────────────────────────── */
/* Safe stats queries */
$sessions  = $db->query("SELECT * FROM speaking_sessions WHERE user_id=$uid ORDER BY created_at DESC LIMIT 8");
$statsRow  = $db->query("SELECT COUNT(*) as total, COALESCE(AVG(overall_score),0) as avg_score, COALESCE(SUM(word_count),0) as total_words, COALESCE(SUM(duration_seconds),0) as total_sec FROM speaking_sessions WHERE user_id=$uid");
$stats     = $statsRow ? $statsRow->fetch_assoc() : ['total'=>0,'avg_score'=>0,'total_words'=>0,'total_sec'=>0];

include 'includes/header.php';
?>

<div class="page-header">
  <h1>🎤 Speaking Practice</h1>
  <p>Use your microphone to practice speaking English. Get instant AI feedback on your grammar, fluency, and pronunciation.</p>
</div>

<!-- Browser Notice -->
<div class="alert alert-info mb-16 show-mobile" style="display:none">
  📱 <strong>Tip:</strong> For best results on mobile, use <strong>Chrome</strong> browser and allow microphone access when prompted.
</div>

<div id="browserNotice" class="alert alert-info mb-16" style="display:none">
  ⚠️ <strong>Microphone not supported.</strong> Please use <strong>Google Chrome</strong> or <strong>Microsoft Edge</strong> for speech recognition features.
</div>

<!-- Stats Row -->
<div class="grid-4 mb-24">
  <div class="stat-card">
    <div class="stat-icon">🎤</div>
    <div>
      <div class="stat-val" style="color:var(--blue)" data-count="<?= (int)$stats['total'] ?>">0</div>
      <div class="stat-label">Sessions Done</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⭐</div>
    <div>
      <div class="stat-val" style="color:var(--green)" data-count="<?= round($stats['avg_score'] ?? 0) ?>">0</div>
      <div class="stat-label">Avg. Score</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💬</div>
    <div>
      <div class="stat-val" style="color:var(--purple)" data-count="<?= (int)$stats['total_words'] ?>">0</div>
      <div class="stat-label">Words Spoken</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⏱️</div>
    <div>
      <div class="stat-val" style="color:var(--yellow)" data-count="<?= round(($stats['total_sec'] ?? 0)/60) ?>">0</div>
      <div class="stat-label">Minutes Practiced</div>
    </div>
  </div>
</div>

<!-- Mode Tabs -->
<div class="tabs mb-24">
  <button class="tab active" onclick="switchMode('free',this)">🗣️ Free Speaking</button>
  <button class="tab" onclick="switchMode('read_aloud',this)">📖 Read Aloud</button>
  <button class="tab" onclick="switchMode('pronunciation',this)">🔤 Pronunciation</button>
</div>

<!-- ═══════════════════════════════════════════════
     FREE SPEAKING MODE
═══════════════════════════════════════════════ -->
<div id="panel-free">
  <div class="grid-2" style="gap:24px;align-items:start">
    <div>
      <div class="card mb-16" style="text-align:center;padding:36px 24px;">
        <p style="font-size:14px;color:var(--text-2);margin-bottom:28px;max-width:340px;margin-left:auto;margin-right:auto;line-height:1.6">
          Speak freely about any topic in English. The AI will analyse your grammar, fluency, and suggest more natural expressions.
        </p>

        <!-- Mic Button -->
        <div class="mic-wrap" id="micWrap-free">
          <div class="mic-rings">
            <div class="mic-ring r1"></div>
            <div class="mic-ring r2"></div>
            <div class="mic-ring r3"></div>
          </div>
          <button class="mic-btn" id="micBtn-free" onclick="toggleRecording('free')">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3z"/>
              <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
              <line x1="12" y1="19" x2="12" y2="22"/>
              <line x1="8"  y1="22" x2="16" y2="22"/>
            </svg>
          </button>
        </div>

        <div class="mic-status" id="micStatus-free">Tap the microphone to start speaking</div>
        <div class="mic-timer" id="micTimer-free" style="display:none">⏱️ <span id="timerVal-free">0:00</span></div>

        <!-- Waveform Canvas -->
        <canvas id="waveform-free" width="360" height="60" style="display:none;margin:16px auto 0;border-radius:8px;background:var(--bg-base);max-width:100%"></canvas>
      </div>

      <!-- Transcript Box -->
      <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:10px">📝 Your Speech Transcript</div>
        <div id="transcript-free" style="min-height:80px;font-size:14px;color:var(--text-1);line-height:1.8;background:var(--bg-base);border:1px solid var(--border);border-radius:10px;padding:12px 14px;">
          <span style="color:var(--text-3);font-style:italic">Your words will appear here as you speak...</span>
        </div>
        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
          <button class="btn btn-primary btn-sm" id="analyseBtn-free" onclick="analyseTranscript('free')" style="display:none">
            🤖 Analyse My Speech
          </button>
          <button class="btn btn-ghost btn-sm" onclick="clearTranscript('free')">Clear</button>
        </div>
      </div>
    </div>

    <!-- Results Panel (Free) -->
    <div id="results-free">
      <div class="card" style="text-align:center;padding:50px 20px;">
        <div style="font-size:52px;margin-bottom:16px">🎙️</div>
        <h3 style="font-size:17px;color:var(--text-2);margin-bottom:8px">AI Feedback Appears Here</h3>
        <p style="font-size:13px;color:var(--text-3);max-width:260px;margin:0 auto;line-height:1.6">
          Speak for at least 5-10 seconds, then click "Analyse My Speech" for detailed feedback.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     READ ALOUD MODE
═══════════════════════════════════════════════ -->
<div id="panel-read_aloud" style="display:none">
  <div class="grid-2" style="gap:24px;align-items:start">
    <div>
      <!-- Prompt Card -->
      <div class="card mb-16">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px">
          <div style="font-size:13px;font-weight:700;color:var(--text-2)">📖 Read This Text Aloud</div>
          <div style="display:flex;gap:8px">
            <select id="promptDiff" class="form-control" style="width:140px;padding:6px 10px;font-size:13px">
              <option value="beginner">🌱 Beginner</option>
              <option value="intermediate">📖 Intermediate</option>
              <option value="advanced">🚀 Advanced</option>
            </select>
            <button class="btn btn-outline btn-sm" onclick="loadPrompt()">🔄 New Text</button>
          </div>
        </div>
        <div id="promptText" style="font-size:16px;color:var(--text-1);line-height:1.9;background:var(--bg-base);border:1px solid var(--blue);border-radius:10px;padding:16px 18px;font-weight:500;letter-spacing:0.2px">
          <div class="ai-thinking"><span></span><span></span><span></span></div>
        </div>
        <div style="margin-top:10px;display:flex;gap:10px;align-items:center">
          <button class="btn btn-ghost btn-sm" onclick="speakPrompt()" id="listenBtn">🔊 Listen First</button>
          <span style="font-size:12px;color:var(--text-3)">Tip: Listen, then try to say it yourself!</span>
        </div>
      </div>

      <!-- Mic for Read Aloud -->
      <div class="card" style="text-align:center;padding:28px 24px;">
        <div class="mic-wrap" id="micWrap-read_aloud">
          <div class="mic-rings"><div class="mic-ring r1"></div><div class="mic-ring r2"></div><div class="mic-ring r3"></div></div>
          <button class="mic-btn" id="micBtn-read_aloud" onclick="toggleRecording('read_aloud')">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3z"/>
              <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
              <line x1="12" y1="19" x2="12" y2="22"/>
              <line x1="8"  y1="22" x2="16" y2="22"/>
            </svg>
          </button>
        </div>
        <div class="mic-status" id="micStatus-read_aloud">Tap to start reading aloud</div>
        <div class="mic-timer" id="micTimer-read_aloud" style="display:none">⏱️ <span id="timerVal-read_aloud">0:00</span></div>
        <canvas id="waveform-read_aloud" width="360" height="60" style="display:none;margin:16px auto 0;border-radius:8px;background:var(--bg-base);max-width:100%"></canvas>
        <div id="transcript-read_aloud" style="display:none;margin-top:12px;font-size:13px;color:var(--text-2);background:var(--bg-base);border:1px solid var(--border);border-radius:8px;padding:10px 12px;line-height:1.7;text-align:left">
          <span style="color:var(--text-3);font-style:italic">Your spoken words will appear here...</span>
        </div>
        <button class="btn btn-primary btn-sm mt-16" id="analyseBtn-read_aloud" onclick="analyseTranscript('read_aloud')" style="display:none">
          🤖 Check My Reading
        </button>
      </div>
    </div>

    <div id="results-read_aloud">
      <div class="card" style="text-align:center;padding:50px 20px;">
        <div style="font-size:52px;margin-bottom:16px">📖</div>
        <h3 style="font-size:17px;color:var(--text-2);margin-bottom:8px">Read the text, then get scored</h3>
        <p style="font-size:13px;color:var(--text-3);max-width:260px;margin:0 auto;line-height:1.6">
          The AI will compare what you said to the original text and score your accuracy.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     PRONUNCIATION MODE
═══════════════════════════════════════════════ -->
<div id="panel-pronunciation" style="display:none">
  <div class="grid-2" style="gap:24px;align-items:start">
    <div>
      <!-- Word/Phrase drill picker -->
      <div class="card mb-16">
        <div style="font-size:13px;font-weight:700;color:var(--text-2);margin-bottom:14px">🔤 Choose What to Practice</div>
        <div id="pronunciationWords" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">
          <?php
          $pronWords = [
            ['word'=>'Comfortable',  'syllables'=>'comf·ter·buhl',    'ipa'=>'/ˈkʌmf.tɚ.bəl/',    'tip'=>'Many people skip the middle syllable'],
            ['word'=>'February',     'syllables'=>'Feb·roo·er·ee',    'ipa'=>'/ˈfeb.ru.er.i/',     'tip'=>'Don\'t forget the first "r"'],
            ['word'=>'Particularly', 'syllables'=>'par·tic·u·lar·ly', 'ipa'=>'/pɚˈtɪk.jʊ.lɚ.li/', 'tip'=>'Stress falls on the 2nd syllable'],
            ['word'=>'Vocabulary',   'syllables'=>'vo·cab·u·lar·y',   'ipa'=>'/voʊˈkæb.jʊ.ler.i/', 'tip'=>'The "a" in the middle is short'],
            ['word'=>'Pronunciation','syllables'=>'pruh·nun·see·ay·shun','ipa'=>'/prəˌnʌn.siˈeɪ.ʃən/','tip'=>'Not "pro-noun-ciation"!'],
            ['word'=>'Thoroughly',   'syllables'=>'thur·oh·lee',      'ipa'=>'/ˈθɝː.ə.li/',        'tip'=>'The "gh" is silent'],
            ['word'=>'Entrepreneur', 'syllables'=>'on·truh·pruh·nur', 'ipa'=>'/ˌɒn.trə.prəˈnɜːr/', 'tip'=>'French origin — final "r" is soft'],
            ['word'=>'Athlete',      'syllables'=>'ath·leet',         'ipa'=>'/ˈæθ.liːt/',         'tip'=>'Only 2 syllables, not "ath-a-lete"'],
          ];
          foreach ($pronWords as $i => $pw):
          ?>
          <div class="pron-word-btn <?= $i===0?'selected':'' ?>" data-word="<?= $pw['word'] ?>" data-syllables="<?= $pw['syllables'] ?>" data-ipa="<?= $pw['ipa'] ?>" data-tip="<?= addslashes($pw['tip']) ?>" onclick="selectPronWord(this)">
            <div style="font-weight:700;font-size:15px;color:var(--text-1)"><?= $pw['word'] ?></div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--teal);margin-top:2px"><?= $pw['syllables'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div>
      <!-- Selected word display + mic -->
      <div class="card mb-16" style="text-align:center;padding:28px 24px">
        <div id="selectedWord" style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:36px;color:var(--text-1);margin-bottom:6px">Comfortable</div>
        <div id="selectedSyllables" style="font-size:18px;color:var(--blue);letter-spacing:4px;margin-bottom:4px;font-family:'JetBrains Mono',monospace">comf·ter·buhl</div>
        <div id="selectedIPA" style="font-size:13px;color:var(--text-3);margin-bottom:8px;font-family:'JetBrains Mono',monospace">/ˈkʌmf.tɚ.bəl/</div>
        <div id="selectedTip" style="font-size:13px;color:var(--yellow);background:#fbbf2415;border:1px solid #fbbf2430;border-radius:8px;padding:8px 12px;margin-bottom:20px">💡 Many people skip the middle syllable</div>

        <button class="btn btn-outline btn-sm mb-16" onclick="speakSelectedWord()">🔊 Hear Pronunciation</button>

        <!-- Mic -->
        <div class="mic-wrap" id="micWrap-pronunciation">
          <div class="mic-rings"><div class="mic-ring r1"></div><div class="mic-ring r2"></div><div class="mic-ring r3"></div></div>
          <button class="mic-btn" id="micBtn-pronunciation" onclick="toggleRecording('pronunciation')">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3z"/>
              <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
              <line x1="12" y1="19" x2="12" y2="22"/>
              <line x1="8"  y1="22" x2="16" y2="22"/>
            </svg>
          </button>
        </div>
        <div class="mic-status" id="micStatus-pronunciation">Tap mic and say the word</div>
        <canvas id="waveform-pronunciation" width="320" height="50" style="display:none;margin:12px auto 0;border-radius:8px;background:var(--bg-base);max-width:100%"></canvas>
        <div id="transcript-pronunciation" style="display:none;font-size:14px;color:var(--text-1);margin-top:12px;font-weight:600"></div>
        <button class="btn btn-primary btn-sm mt-16" id="analyseBtn-pronunciation" onclick="analyseTranscript('pronunciation')" style="display:none">
          🤖 Check Pronunciation
        </button>
      </div>

      <div id="results-pronunciation">
        <div class="card" style="text-align:center;padding:36px 20px;">
          <div style="font-size:48px;margin-bottom:12px">🔤</div>
          <p style="font-size:13px;color:var(--text-3);line-height:1.6">Say the word aloud, then get pronunciation feedback from the AI.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Past Sessions ── -->
<?php if ($sessions && $sessions->num_rows > 0): ?>
<div class="card mt-24">
  <h3 style="font-size:16px;margin-bottom:16px;color:var(--text-1)">🕐 Recent Speaking Sessions</h3>
  <table class="data-table">
    <thead>
      <tr><th>Mode</th><th>Transcript Preview</th><th>Score</th><th>Words</th><th>Duration</th><th>Date</th></tr>
    </thead>
    <tbody>
      <?php while ($s = $sessions->fetch_assoc()):
        $modeLabels = ['free'=>'🗣️ Free','read_aloud'=>'📖 Read','pronunciation'=>'🔤 Pronun.'];
        $scoreColor = $s['overall_score'] >= 80 ? 'var(--green)' : ($s['overall_score'] >= 60 ? 'var(--yellow)' : 'var(--red)');
      ?>
      <tr>
        <td><span class="ac-badge badge-blue" style="font-size:11px"><?= $modeLabels[$s['mode']] ?? $s['mode'] ?></span></td>
        <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;color:var(--text-2)"><?= clean(substr($s['transcript'],0,60)) ?>...</td>
        <td><span style="font-weight:800;color:<?= $scoreColor ?>"><?= $s['overall_score'] ?></span><span style="color:var(--text-3);font-size:11px">/100</span></td>
        <td style="color:var(--text-2)"><?= $s['word_count'] ?></td>
        <td style="color:var(--text-2)"><?= $s['duration_seconds'] ?>s</td>
        <td style="color:var(--text-3);font-size:12px"><?= date('M d, g:i A', strtotime($s['created_at'])) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════
     JAVASCRIPT ENGINE
═══════════════════════════════════════════════ -->
<script>
const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
if (!SpeechRec) document.getElementById('browserNotice').style.display = 'flex';

let recognizers = {};   // mode -> SpeechRecognition instance
let isRecording  = {};  // mode -> bool
let transcripts  = { free: '', read_aloud: '', pronunciation: '' };
let timers       = {};  // mode -> setInterval
let timerSecs    = {};  // mode -> seconds
let audioCtx, analyser, rafId, micStream;
let currentMode  = 'free';
let readAloudText = '';

/* ── Mode switching ── */
function switchMode(mode, btnEl) {
  currentMode = mode;
  ['free','read_aloud','pronunciation'].forEach(m => {
    document.getElementById('panel-' + m).style.display = m === mode ? 'block' : 'none';
  });
  document.querySelectorAll('.tab').forEach((t,i) => t.classList.toggle('active', t === btnEl));
  if (mode === 'read_aloud' && !readAloudText) loadPrompt();
}

/* ── Load read-aloud prompt ── */
async function loadPrompt() {
  const diff = document.getElementById('promptDiff')?.value || 'beginner';
  const el   = document.getElementById('promptText');
  if (el) el.innerHTML = '<div class="ai-thinking"><span></span><span></span><span></span></div>';
  try {
    const res  = await fetch(`speaking.php?get_prompt=1&diff=${diff}`);
    const data = await res.json();
    readAloudText = data.text || '';
    if (el) el.textContent = readAloudText;
  } catch(e) {
    readAloudText = 'Good morning! Today I would like to practice my English speaking skills and improve my fluency every day.';
    if (el) el.textContent = readAloudText;
  }
}

/* ── Pronunciation word select ── */
function selectPronWord(el) {
  document.querySelectorAll('.pron-word-btn').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('selectedWord').textContent = el.dataset.word;
  document.getElementById('selectedSyllables').textContent = el.dataset.syllables;
  document.getElementById('selectedIPA').textContent = el.dataset.ipa;
  document.getElementById('selectedTip').innerHTML = '💡 ' + el.dataset.tip;
  // reset transcript
  transcripts.pronunciation = '';
  const tr = document.getElementById('transcript-pronunciation');
  if (tr) { tr.style.display='none'; tr.textContent=''; }
  document.getElementById('analyseBtn-pronunciation').style.display = 'none';
  document.getElementById('results-pronunciation').innerHTML = `
    <div class="card" style="text-align:center;padding:36px 20px;">
      <div style="font-size:48px;margin-bottom:12px">🔤</div>
      <p style="font-size:13px;color:var(--text-3);line-height:1.6">Say the word aloud, then get pronunciation feedback from the AI.</p>
    </div>`;
}

/* ── Text-to-Speech (browser built-in) ── */
function speakText(text) {
  if (!window.speechSynthesis) return;
  window.speechSynthesis.cancel();
  const u = new SpeechSynthesisUtterance(text);
  u.lang  = 'en-US'; u.rate = 0.88; u.pitch = 1.05;
  // Prefer a natural English voice
  const voices = window.speechSynthesis.getVoices();
  const preferred = voices.find(v => v.lang === 'en-US' && v.name.includes('Google')) ||
                    voices.find(v => v.lang === 'en-US') || voices[0];
  if (preferred) u.voice = preferred;
  window.speechSynthesis.speak(u);
}
function speakPrompt()       { speakText(readAloudText); }
function speakSelectedWord() {
  const word = document.getElementById('selectedWord')?.textContent || '';
  speakText(word);
}
// Ensure voices load
window.speechSynthesis && window.speechSynthesis.addEventListener?.('voiceschanged', () => {});

/* ── Waveform Visualizer (Web Audio API) ── */
async function startWaveform(mode) {
  const canvas = document.getElementById('waveform-' + mode);
  if (!canvas) return;
  canvas.style.display = 'block';
  try {
    micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    audioCtx  = new (window.AudioContext || window.webkitAudioContext)();
    analyser  = audioCtx.createAnalyser();
    analyser.fftSize = 128;
    audioCtx.createMediaStreamSource(micStream).connect(analyser);
    const bufLen = analyser.frequencyBinCount;
    const dataArr = new Uint8Array(bufLen);
    const ctx = canvas.getContext('2d');

    function draw() {
      rafId = requestAnimationFrame(draw);
      analyser.getByteFrequencyData(dataArr);
      ctx.clearRect(0,0,canvas.width,canvas.height);
      const barW = (canvas.width / bufLen) * 2.2;
      let x = 0;
      for (let i=0; i<bufLen; i++) {
        const h = (dataArr[i] / 255) * canvas.height;
        const hue = 200 + (i/bufLen)*60;
        ctx.fillStyle = `hsla(${hue},80%,60%,0.85)`;
        const y = (canvas.height - h) / 2;
        ctx.beginPath();
        ctx.roundRect ? ctx.roundRect(x, y, Math.max(2,barW-1), h, 2) : ctx.rect(x, y, Math.max(2,barW-1), h);
        ctx.fill();
        x += barW + 1;
      }
    }
    draw();
  } catch(e) { console.warn('Waveform error:', e); }
}

function stopWaveform(mode) {
  cancelAnimationFrame(rafId);
  if (audioCtx) { try { audioCtx.close(); } catch(e){} audioCtx = null; }
  if (micStream) { micStream.getTracks().forEach(t => t.stop()); micStream = null; }
  const canvas = document.getElementById('waveform-' + mode);
  if (canvas) {
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0,0,canvas.width,canvas.height);
  }
}

/* ── Timer ── */
function startTimer(mode) {
  timerSecs[mode] = 0;
  const valEl = document.getElementById('timerVal-' + mode);
  const wrapEl = document.getElementById('micTimer-' + mode);
  if (wrapEl) wrapEl.style.display = 'block';
  timers[mode] = setInterval(() => {
    timerSecs[mode]++;
    const m = Math.floor(timerSecs[mode]/60);
    const s = timerSecs[mode]%60;
    if (valEl) valEl.textContent = `${m}:${s.toString().padStart(2,'0')}`;
  }, 1000);
}
function stopTimer(mode) {
  clearInterval(timers[mode]);
  const wrapEl = document.getElementById('micTimer-' + mode);
  if (wrapEl) wrapEl.style.display = 'none';
}

/* ── Speech Recognition Toggle ── */
function toggleRecording(mode) {
  if (isRecording[mode]) stopRecording(mode);
  else startRecording(mode);
}

function startRecording(mode) {
  if (!SpeechRec) { emToast('Please use Chrome or Edge for microphone features', 'warn'); return; }

  transcripts[mode] = '';
  isRecording[mode] = true;

  /* Visual state */
  const btn = document.getElementById('micBtn-' + mode);
  const wrap = document.getElementById('micWrap-' + mode);
  const status = document.getElementById('micStatus-' + mode);
  if (btn)    btn.classList.add('recording');
  if (wrap)   wrap.classList.add('active');
  if (status) status.textContent = '🔴 Recording... Speak clearly in English';

  startTimer(mode);
  startWaveform(mode);

  /* Setup recognizer */
  const rec = new SpeechRec();
  rec.lang = 'en-US';
  rec.continuous = true;
  rec.interimResults = true;
  rec.maxAlternatives = 1;
  recognizers[mode] = rec;

  let finalTranscript = '';
  let interimTranscript = '';

  rec.onresult = (e) => {
    interimTranscript = '';
    for (let i = e.resultIndex; i < e.results.length; i++) {
      const t = e.results[i][0].transcript;
      if (e.results[i].isFinal) finalTranscript += t + ' ';
      else interimTranscript += t;
    }
    transcripts[mode] = finalTranscript;
    updateTranscriptDisplay(mode, finalTranscript, interimTranscript);
  };

  rec.onerror = (e) => {
    if (e.error === 'no-speech') return;
    if (e.error === 'not-allowed') { emToast('Microphone access denied. Please allow it in browser settings.', 'err'); stopRecording(mode); return; }
    emToast('Speech error: ' + e.error, 'warn');
  };

  rec.onend = () => {
    if (isRecording[mode]) {
      // Auto-restart to keep listening (browser stops after silence)
      try { rec.start(); } catch(e) {}
    }
  };

  try { rec.start(); } catch(e) { emToast('Could not start microphone: ' + e.message, 'err'); isRecording[mode] = false; }
}

function stopRecording(mode) {
  isRecording[mode] = false;
  if (recognizers[mode]) { try { recognizers[mode].stop(); } catch(e){} }

  const btn    = document.getElementById('micBtn-' + mode);
  const wrap   = document.getElementById('micWrap-' + mode);
  const status = document.getElementById('micStatus-' + mode);
  if (btn)    btn.classList.remove('recording');
  if (wrap)   wrap.classList.remove('active');

  stopTimer(mode);
  stopWaveform(mode);

  const hasText = transcripts[mode].trim().length > 3;
  if (status) status.textContent = hasText
    ? '✅ Recording stopped. Click "Analyse" for feedback.'
    : 'No speech detected. Tap the mic and try again.';

  // Show waveform canvas hidden after stop
  const canvas = document.getElementById('waveform-' + mode);
  if (canvas) canvas.style.display = 'none';

  // Show analyse button if we have text
  if (hasText) {
    const aBtn = document.getElementById('analyseBtn-' + mode);
    if (aBtn) aBtn.style.display = 'inline-flex';
  }
}

function updateTranscriptDisplay(mode, final, interim) {
  if (mode === 'free') {
    const el = document.getElementById('transcript-free');
    if (el) el.innerHTML = `<span style="color:var(--text-1)">${escapeHtml(final)}</span><span style="color:var(--text-3);font-style:italic">${escapeHtml(interim)}</span>`;
  } else if (mode === 'read_aloud') {
    const el = document.getElementById('transcript-read_aloud');
    if (el) { el.style.display = 'block'; el.innerHTML = `<span style="color:var(--text-1)">${escapeHtml(final)}</span><span style="color:var(--text-3);font-style:italic">${escapeHtml(interim)}</span>`; }
  } else if (mode === 'pronunciation') {
    const el = document.getElementById('transcript-pronunciation');
    if (el) { el.style.display = 'block'; el.innerHTML = `<span style="color:var(--blue);font-size:16px;font-weight:700">"${escapeHtml(final + interim)}"</span>`; }
  }
}

function clearTranscript(mode) {
  transcripts[mode] = '';
  if (mode === 'free') {
    const el = document.getElementById('transcript-free');
    if (el) el.innerHTML = '<span style="color:var(--text-3);font-style:italic">Your words will appear here as you speak...</span>';
    document.getElementById('analyseBtn-free').style.display = 'none';
    document.getElementById('results-free').innerHTML = `<div class="card" style="text-align:center;padding:50px 20px;"><div style="font-size:52px;margin-bottom:16px">🎙️</div><h3 style="font-size:17px;color:var(--text-2)">AI Feedback Appears Here</h3></div>`;
  }
}

/* ── Send to AI for Analysis ── */
async function analyseTranscript(mode) {
  const transcript = transcripts[mode]?.trim();
  if (!transcript || transcript.length < 4) { emToast('Please speak more before analysing!', 'warn'); return; }

  const aBtn = document.getElementById('analyseBtn-' + mode);
  btnLoading(aBtn, true);

  const originalText = mode === 'read_aloud' ? readAloudText : (mode === 'pronunciation' ? document.getElementById('selectedWord')?.textContent || '' : '');
  const duration = timerSecs[mode] || 0;

  try {
    const res  = await fetch('speaking.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `ajax_speak=1&mode=${encodeURIComponent(mode)}&transcript=${encodeURIComponent(transcript)}&original_text=${encodeURIComponent(originalText)}&duration=${duration}`
    });
    const data = await res.json();

    btnLoading(aBtn, false);
    if (aBtn) aBtn.textContent = '🤖 ' + (mode==='read_aloud' ? 'Check My Reading' : mode==='pronunciation' ? 'Check Pronunciation' : 'Analyse My Speech');

    if (data.error) { emToast(data.error, 'err'); return; }

    renderResults(mode, data, transcript);
    emToast(`+${data.xp} XP — Great speaking practice! 🎤`, 'xp');

  } catch(e) {
    btnLoading(aBtn, false);
    emToast('Connection error. Please try again.', 'err');
  }
}

/* ── Render AI Results ── */
function renderResults(mode, data, transcript) {
  const container = document.getElementById('results-' + mode);
  if (!container) return;

  let html = '';

  if (mode === 'free') {
    const overall = data.overall_score ?? Math.round(((data.grammar_score||70)+(data.fluency_score||70))/2);
    const grammar  = data.grammar_score  ?? overall;
    const fluency  = data.fluency_score  ?? overall;
    const scoreColor = overall >= 80 ? 'var(--green)' : overall >= 60 ? 'var(--yellow)' : 'var(--red)';
    const scoreEmoji = overall >= 80 ? '🌟' : overall >= 60 ? '👍' : '💪';

    html = `
    <div class="card speak-result-card">
      <div class="speak-score-header">
        <div class="speak-big-score" style="color:${scoreColor}">${overall}<span style="font-size:18px;opacity:0.6">/100</span></div>
        <div>
          <div style="font-size:18px;font-weight:700;color:var(--text-1);margin-bottom:6px">${scoreEmoji} ${overall>=80?'Excellent!':overall>=60?'Good job!':'Keep practicing!'}</div>
          <div style="font-size:13px;color:var(--text-2);line-height:1.5">${escapeHtml(data.overall_feedback||data.encouragement||'')}</div>
        </div>
      </div>
      <div style="display:flex;gap:12px;margin:16px 0;flex-wrap:wrap">
        ${scoreBar('Grammar', grammar, 'var(--blue)')}
        ${scoreBar('Fluency', fluency, 'var(--teal)')}
      </div>
      ${(data.grammar_corrections||[]).length ? `
      <div style="margin-bottom:14px">
        <div class="result-section-title">✏️ Grammar Corrections</div>
        ${(data.grammar_corrections||[]).map(c=>`
          <div class="correction-block">
            <div class="original">❌ "${escapeHtml(c.wrong||'')}"</div>
            <div class="corrected">✅ "${escapeHtml(c.correct||'')}"</div>
            <div class="explanation">💡 ${escapeHtml(c.explanation||'')}</div>
          </div>`).join('')}
      </div>` : ''}
      ${(data.better_phrases||[]).length ? `
      <div style="margin-bottom:14px">
        <div class="result-section-title">💬 More Natural Phrases</div>
        ${(data.better_phrases||[]).map(p=>`
          <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:8px;font-size:13px">
            <span style="color:var(--text-3)">You said: </span><em>${escapeHtml(p.said||'')}</em><br>
            <span style="color:var(--green);font-weight:600">→ More natural: </span>"${escapeHtml(p.natural||'')}"
          </div>`).join('')}
      </div>` : ''}
      ${(data.vocabulary_tips||[]).length ? `
      <div>
        <div class="result-section-title">📚 Vocabulary Tips</div>
        ${(data.vocabulary_tips||[]).map(t=>`<div style="font-size:13px;color:var(--text-2);margin-bottom:6px;padding-left:12px;border-left:3px solid var(--purple)">• ${escapeHtml(t)}</div>`).join('')}
      </div>` : ''}
      <div style="margin-top:14px;background:var(--blue-glow);border:1px solid #4f8ef730;border-radius:10px;padding:12px 14px;font-size:13px;color:var(--text-1)">
        💪 ${escapeHtml(data.encouragement||'')}
      </div>
    </div>`;

  } else if (mode === 'read_aloud') {
    const accuracy = data.accuracy_score ?? 70;
    const fluency  = data.fluency_score  ?? 70;
    const scoreColor = accuracy >= 80 ? 'var(--green)' : accuracy >= 60 ? 'var(--yellow)' : 'var(--red)';
    html = `
    <div class="card speak-result-card">
      <div class="speak-score-header">
        <div class="speak-big-score" style="color:${scoreColor}">${accuracy}<span style="font-size:18px;opacity:0.6">/100</span></div>
        <div>
          <div style="font-size:16px;font-weight:700;color:var(--text-1);margin-bottom:4px">Reading Accuracy</div>
          <div style="font-size:13px;color:var(--text-2)">${data.words_correct||0} / ${data.words_total||0} words correct</div>
        </div>
      </div>
      <div style="display:flex;gap:12px;margin:16px 0;flex-wrap:wrap">
        ${scoreBar('Accuracy', accuracy, 'var(--green)')}
        ${scoreBar('Fluency',  fluency,  'var(--teal)')}
      </div>
      ${(data.missed_words||[]).length ? `
      <div style="margin-bottom:12px">
        <div class="result-section-title">⚠️ Missed / Wrong Words</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">${(data.missed_words||[]).map(w=>`<span style="background:#f8717120;color:var(--red);padding:3px 10px;border-radius:99px;font-size:13px">${escapeHtml(w)}</span>`).join('')}</div>
      </div>` : ''}
      <div style="font-size:13px;color:var(--text-2);line-height:1.6;margin-bottom:12px">${escapeHtml(data.feedback||'')}</div>
      ${data.pronunciation_tip ? `<div style="background:#34d39920;border:1px solid #34d39940;border-radius:8px;padding:10px 12px;font-size:13px;color:var(--green)">📌 ${escapeHtml(data.pronunciation_tip)}</div>` : ''}
      <div style="margin-top:12px;background:var(--blue-glow);border:1px solid #4f8ef730;border-radius:10px;padding:12px 14px;font-size:13px;color:var(--text-1)">💪 ${escapeHtml(data.encouragement||'')}</div>
    </div>`;

  } else { /* pronunciation */
    const pScore = data.pronunciation_score ?? data.fluency_score ?? 70;
    const scoreColor = pScore >= 80 ? 'var(--green)' : pScore >= 60 ? 'var(--yellow)' : 'var(--red)';
    html = `
    <div class="card speak-result-card">
      <div class="speak-score-header">
        <div class="speak-big-score" style="color:${scoreColor}">${pScore}<span style="font-size:18px;opacity:0.6">/100</span></div>
        <div>
          <div style="font-size:16px;font-weight:700;color:var(--text-1);margin-bottom:4px">Pronunciation Score</div>
          <div style="font-size:13px;color:var(--text-2);line-height:1.5">${escapeHtml(data.feedback||'')}</div>
        </div>
      </div>
      ${(data.difficult_words||[]).length ? `
      <div style="margin:14px 0">
        <div class="result-section-title">🔤 Word Breakdown</div>
        ${(data.difficult_words||[]).map(w=>`
          <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:8px">
            <div style="font-weight:700;font-size:15px;color:var(--text-1)">${escapeHtml(w.word||'')}</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--teal);margin:3px 0">${escapeHtml(w.correct_pronunciation||'')}</div>
            <div style="font-size:13px;color:var(--text-2)">💡 ${escapeHtml(w.tip||'')}</div>
          </div>`).join('')}
      </div>` : ''}
      ${data.practice_tip ? `<div style="background:#34d39920;border:1px solid #34d39940;border-radius:8px;padding:10px 12px;font-size:13px;color:var(--green);margin-bottom:10px">📌 ${escapeHtml(data.practice_tip)}</div>` : ''}
      <div style="background:var(--blue-glow);border:1px solid #4f8ef730;border-radius:10px;padding:12px 14px;font-size:13px;color:var(--text-1)">💪 ${escapeHtml(data.encouragement||'')}</div>
    </div>`;
  }

  container.innerHTML = html;
  container.querySelector('.speak-result-card')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  // Animate score bars
  requestAnimationFrame(() => {
    container.querySelectorAll('.score-bar-fill').forEach(el => {
      const w = el.dataset.width;
      setTimeout(() => { el.style.width = w + '%'; }, 100);
    });
  });
}

function scoreBar(label, value, color) {
  return `<div style="flex:1;min-width:120px">
    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-2);margin-bottom:5px">
      <span>${label}</span><span style="font-weight:700;color:${color}">${value}/100</span>
    </div>
    <div style="height:8px;background:var(--bg-hover);border-radius:99px;overflow:hidden">
      <div class="score-bar-fill" data-width="${value}" style="height:100%;width:0%;background:${color};border-radius:99px;transition:width 1.2s cubic-bezier(.4,0,.2,1)"></div>
    </div>
  </div>`;
}

function escapeHtml(t) { const d=document.createElement('div'); d.textContent=String(t||''); return d.innerHTML; }

// Init
document.addEventListener('DOMContentLoaded', () => {
  loadPrompt();
  // Load voices for TTS
  if (window.speechSynthesis) window.speechSynthesis.getVoices();
});
</script>

<?php include 'includes/footer.php'; ?>
