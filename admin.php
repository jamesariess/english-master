<?php
require_once 'config.php';
auth();
requireAdmin();

$user = currentUser();
$db = db();
$uid = (int)$_SESSION['uid'];
$pageTitle = 'Admin Panel';
$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);

function adminRedirect($msg, $type = 'success') {
    $_SESSION['admin_message'] = ['text' => $msg, 'type' => $type];
    header('Location: admin.php');
    exit;
}

function validDifficulty($value) {
    return in_array($value, ['beginner', 'intermediate', 'advanced'], true) ? $value : 'beginner';
}

function validPracticeType($value) {
    $types = practiceTypes();
    return in_array($value, $types, true) ? $value : 'better_english';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_role') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        if ($targetId && $targetId !== $uid) {
            $db->query("UPDATE users SET role='" . esc($role) . "' WHERE id=$targetId");
            adminRedirect('User role updated.');
        }
        adminRedirect('You cannot change your own role here.', 'warn');
    }

    if ($action === 'add_challenge') {
        $type = in_array($_POST['type'] ?? '', ['vocabulary','grammar','writing','speaking','listening'], true) ? $_POST['type'] : 'grammar';
        $title = esc($_POST['title'] ?? '');
        $description = esc($_POST['description'] ?? '');
        $content = esc($_POST['content'] ?? '');
        $difficulty = esc(validDifficulty($_POST['difficulty'] ?? 'beginner'));
        $tags = esc($_POST['tags'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $xp = max(10, min(300, (int)($_POST['xp_reward'] ?? 50)));
        $date = esc($_POST['challenge_date'] ?: date('Y-m-d'));
        if ($title && $content) {
            $db->query("INSERT INTO challenges (type,title,description,content,difficulty,tags,active,xp_reward,challenge_date) VALUES ('$type','$title','$description','$content','$difficulty','$tags',$active,$xp,'$date')");
            adminRedirect('Challenge added.');
        }
        adminRedirect('Challenge title and content are required.', 'warn');
    }

    if ($action === 'add_vocab') {
        $word = esc($_POST['word'] ?? '');
        $meaning = esc($_POST['meaning'] ?? '');
        $synonyms = esc($_POST['synonyms'] ?? '');
        $antonyms = esc($_POST['antonyms'] ?? '');
        $pronunciation = esc($_POST['pronunciation'] ?? '');
        $example = esc($_POST['example_sentence'] ?? '');
        $difficulty = esc(validDifficulty($_POST['difficulty'] ?? 'beginner'));
        $category = esc($_POST['category'] ?? 'general');
        $tags = esc($_POST['tags'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        if ($word && $meaning) {
            $db->query("INSERT INTO vocabulary (word,meaning,synonyms,antonyms,pronunciation,example_sentence,difficulty,category,tags,active) VALUES ('$word','$meaning','$synonyms','$antonyms','$pronunciation','$example','$difficulty','$category','$tags',$active)");
            adminRedirect('Vocabulary word added.');
        }
        adminRedirect('Word and meaning are required.', 'warn');
    }

    if ($action === 'add_speaking_prompt') {
        $text = esc($_POST['text'] ?? '');
        $topic = esc($_POST['topic'] ?? 'General');
        $difficulty = esc(validDifficulty($_POST['difficulty'] ?? 'beginner'));
        $category = esc($_POST['category'] ?? 'general');
        $tags = esc($_POST['tags'] ?? '');
        $audioUrl = esc($_POST['audio_url'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        if ($text) {
            $db->query("INSERT INTO speaking_prompts (text,topic,difficulty,category,tags,audio_url,active) VALUES ('$text','$topic','$difficulty','$category','$tags','$audioUrl',$active)");
            adminRedirect('Read-aloud prompt added.');
        }
        adminRedirect('Prompt text is required.', 'warn');
    }

    if ($action === 'add_practice') {
        $type = esc(validPracticeType($_POST['type'] ?? 'better_english'));
        $title = esc($_POST['title'] ?? '');
        $prompt = esc($_POST['prompt'] ?? '');
        $optionA = esc($_POST['option_a'] ?? '');
        $optionB = esc($_POST['option_b'] ?? '');
        $optionC = esc($_POST['option_c'] ?? '');
        $correct = strtoupper(substr(trim($_POST['correct_option'] ?? ''), 0, 1));
        $correct = in_array($correct, ['A','B','C'], true) ? $correct : null;
        $explanation = esc($_POST['explanation'] ?? '');
        $difficulty = esc(validDifficulty($_POST['difficulty'] ?? 'beginner'));
        $category = esc($_POST['category'] ?? 'general');
        $tags = esc($_POST['tags'] ?? '');
        $audioUrl = esc($_POST['audio_url'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $xp = max(5, min(200, (int)($_POST['xp_reward'] ?? 25)));

        if ($title && $prompt) {
            $correctSql = $correct ? "'" . esc($correct) . "'" : "NULL";
            $db->query("INSERT INTO practice_items (type,title,prompt,option_a,option_b,option_c,correct_option,explanation,difficulty,category,tags,audio_url,xp_reward,active,created_by) VALUES ('$type','$title','$prompt','$optionA','$optionB','$optionC',$correctSql,'$explanation','$difficulty','$category','$tags','$audioUrl',$xp,$active,$uid)");
            adminRedirect('Practice item added.');
        }
        adminRedirect('Practice title and prompt are required.', 'warn');
    }

    if ($action === 'ai_generate') {
        $target = $_POST['target'] ?? 'practice';
        $topic = trim($_POST['topic'] ?? 'daily English');
        $difficulty = validDifficulty($_POST['difficulty'] ?? 'beginner');
        $count = max(1, min(10, (int)($_POST['count'] ?? 3)));

        $system = "You generate English learning content for a PHP app. Return ONLY valid JSON. No markdown.";
        $prompt = "Generate $count $difficulty English learning items about $topic for target=$target.";
        if ($target === 'vocabulary') {
            $prompt .= " JSON format: {\"items\":[{\"word\":\"\",\"meaning\":\"\",\"synonyms\":\"comma list\",\"antonyms\":\"comma list\",\"pronunciation\":\"simple guide\",\"example_sentence\":\"\",\"category\":\"\"}]}";
        } elseif ($target === 'challenge') {
            $prompt .= " JSON format: {\"items\":[{\"type\":\"grammar|vocabulary|writing|speaking|listening\",\"title\":\"\",\"description\":\"\",\"content\":\"\",\"xp_reward\":50}]}";
        } elseif ($target === 'speaking') {
            $prompt .= " JSON format: {\"items\":[{\"text\":\"read aloud text\",\"topic\":\"\",\"category\":\"\"}]}";
        } elseif ($target === 'strict_module') {
            $module = $_POST['module'] ?? 'vocabulary_lesson';
            $prompt = learningModulePrompt($module, ucfirst($difficulty), $topic);
            $prompt .= "\n\nReturn this as one saved item in JSON too: {\"items\":[{\"type\":\"" . esc(validPracticeType($module === 'vocabulary_lesson' ? 'word_sentence_builder' : $module)) . "\",\"title\":\"\",\"prompt\":\"full formatted exercise text\",\"option_a\":\"\",\"option_b\":\"\",\"option_c\":\"\",\"correct_option\":\"\",\"explanation\":\"teaching notes\",\"category\":\"$topic\",\"tags\":\"$topic\",\"xp_reward\":25}]}";
        } else {
            $prompt .= " JSON format: {\"items\":[{\"type\":\"better_english|grammar_choice|vocabulary_quiz|writing_prompt|speaking_prompt|sentence_rearrangement|fill_blank|reading_comprehension|daily_challenge_set|scenario_roleplay|analytical_english|word_sentence_builder\",\"title\":\"\",\"prompt\":\"\",\"option_a\":\"\",\"option_b\":\"\",\"option_c\":\"\",\"correct_option\":\"A|B|C or empty\",\"explanation\":\"\",\"category\":\"\",\"tags\":\"comma skill tags\",\"xp_reward\":25}]}";
        }

        $ai = callAI([['role' => 'user', 'content' => $prompt]], $system, 2000);
        $data = jsonFromAI($ai);
        $items = $data['items'] ?? [];
        $created = 0;

        foreach ($items as $item) {
            if ($target === 'vocabulary' && !empty($item['word']) && !empty($item['meaning'])) {
                $word = esc($item['word']); $meaning = esc($item['meaning']);
                $syn = esc($item['synonyms'] ?? ''); $ant = esc($item['antonyms'] ?? '');
                $pro = esc($item['pronunciation'] ?? ''); $ex = esc($item['example_sentence'] ?? '');
                $cat = esc($item['category'] ?? $topic); $diff = esc($difficulty);
                $db->query("INSERT INTO vocabulary (word,meaning,synonyms,antonyms,pronunciation,example_sentence,difficulty,category,tags,active) VALUES ('$word','$meaning','$syn','$ant','$pro','$ex','$diff','$cat','$cat',1)");
                $created++;
            } elseif ($target === 'challenge' && !empty($item['title']) && !empty($item['content'])) {
                $type = in_array($item['type'] ?? '', ['vocabulary','grammar','writing','speaking','listening'], true) ? $item['type'] : 'grammar';
                $title = esc($item['title']); $desc = esc($item['description'] ?? '');
                $content = esc($item['content']); $diff = esc($difficulty);
                $xp = max(10, min(300, (int)($item['xp_reward'] ?? 50)));
                $db->query("INSERT INTO challenges (type,title,description,content,difficulty,tags,active,xp_reward,challenge_date) VALUES ('$type','$title','$desc','$content','$diff','$topic',1,$xp,CURDATE())");
                $created++;
            } elseif ($target === 'speaking' && !empty($item['text'])) {
                $text = esc($item['text']); $topicEsc = esc($item['topic'] ?? $topic);
                $cat = esc($item['category'] ?? 'speaking'); $diff = esc($difficulty);
                $db->query("INSERT INTO speaking_prompts (text,topic,difficulty,category) VALUES ('$text','$topicEsc','$diff','$cat')");
                $created++;
            } elseif ($target === 'practice' && !empty($item['title']) && !empty($item['prompt'])) {
                $type = esc(validPracticeType($item['type'] ?? 'better_english'));
                $title = esc($item['title']); $promptEsc = esc($item['prompt']);
                $a = esc($item['option_a'] ?? ''); $b = esc($item['option_b'] ?? ''); $c = esc($item['option_c'] ?? '');
                $correct = strtoupper(substr(trim($item['correct_option'] ?? ''), 0, 1));
                $correctSql = in_array($correct, ['A','B','C'], true) ? "'" . esc($correct) . "'" : "NULL";
                $explanation = esc($item['explanation'] ?? '');
                $cat = esc($item['category'] ?? $topic); $tags = esc($item['tags'] ?? $topic); $diff = esc($difficulty);
                $xp = max(5, min(200, (int)($item['xp_reward'] ?? 25)));
                $db->query("INSERT INTO practice_items (type,title,prompt,option_a,option_b,option_c,correct_option,explanation,difficulty,category,tags,xp_reward,active,created_by) VALUES ('$type','$title','$promptEsc','$a','$b','$c',$correctSql,'$explanation','$diff','$cat','$tags',$xp,1,$uid)");
                $created++;
            }
        }

        adminRedirect($created ? "AI generated $created item(s)." : 'AI response could not be saved. Check the API key or try again.', $created ? 'success' : 'warn');
    }
}

$counts = [
    'users' => $db->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0,
    'vocabulary' => $db->query("SELECT COUNT(*) c FROM vocabulary")->fetch_assoc()['c'] ?? 0,
    'challenges' => $db->query("SELECT COUNT(*) c FROM challenges")->fetch_assoc()['c'] ?? 0,
    'practice' => $db->query("SELECT COUNT(*) c FROM practice_items")->fetch_assoc()['c'] ?? 0,
];
$users = $db->query("SELECT id,name,email,role,english_level,xp,created_at FROM users ORDER BY created_at DESC LIMIT 20");
$recentPractice = $db->query("SELECT title,type,difficulty,created_at FROM practice_items ORDER BY created_at DESC LIMIT 8");

include 'includes/header.php';
?>

<style>
.admin-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.admin-tab { border:1px solid var(--border); background:var(--bg-card); color:var(--text-2); border-radius:10px; padding:10px 14px; cursor:pointer; font-weight:700; }
.admin-tab.active { background:var(--blue); color:#fff; border-color:var(--blue); }
.admin-panel { display:none; }
.admin-panel.active { display:block; }
.btn-ai { background:linear-gradient(135deg,var(--blue),var(--teal)); box-shadow:0 10px 28px #2dd4bf30; }
</style>

<div class="page-header">
  <h1>Admin Panel</h1>
  <p>Create content manually or generate English exercises with AI.</p>
</div>

<?php if ($message): ?>
<div class="alert <?= $message['type'] === 'warn' ? 'alert-warn' : 'alert-success' ?>" style="margin-bottom:24px;">
  <?= clean($message['text']) ?>
</div>
<?php endif; ?>

<div class="grid-4 mb-24">
  <div class="stat-card"><div class="stat-icon">U</div><div><div class="stat-val"><?= (int)$counts['users'] ?></div><div class="stat-label">Users</div></div></div>
  <div class="stat-card"><div class="stat-icon">V</div><div><div class="stat-val"><?= (int)$counts['vocabulary'] ?></div><div class="stat-label">Words</div></div></div>
  <div class="stat-card"><div class="stat-icon">C</div><div><div class="stat-val"><?= (int)$counts['challenges'] ?></div><div class="stat-label">Challenges</div></div></div>
  <div class="stat-card"><div class="stat-icon">P</div><div><div class="stat-val"><?= (int)$counts['practice'] ?></div><div class="stat-label">Practice Items</div></div></div>
</div>

<div class="card mb-24" style="background:linear-gradient(135deg,#4f8ef710,#2dd4bf10);">
  <h3 style="font-size:17px;margin-bottom:14px;color:var(--text-1)">AI Generator</h3>
  <form method="POST" class="grid-4" style="align-items:end;">
    <input type="hidden" name="action" value="ai_generate">
    <div class="form-group">
      <label class="form-label">Create</label>
      <select name="target" class="form-control">
        <option value="practice">Practice Lab items</option>
        <option value="strict_module">Strict format module</option>
        <option value="vocabulary">Vocabulary words</option>
        <option value="challenge">Daily challenges</option>
        <option value="speaking">Read-aloud prompts</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Module</label>
      <select name="module" class="form-control">
        <option value="vocabulary_lesson">Vocabulary Lesson</option>
        <option value="sentence_rearrangement">Sentence Rearrangement</option>
        <option value="fill_blank">Fill in the Blank</option>
        <option value="reading_comprehension">Reading Comprehension</option>
        <option value="daily_challenge_set">Daily Challenge Set</option>
        <option value="scenario_roleplay">Real-Life Scenario</option>
        <option value="analytical_english">Analytical English</option>
        <option value="word_sentence_builder">Word to 5 Sentences</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Topic</label>
      <input name="topic" class="form-control" placeholder="work, travel, call center, daily life" required>
    </div>
    <div class="form-group">
      <label class="form-label">Level</label>
      <select name="difficulty" class="form-control">
        <option value="beginner">Beginner</option>
        <option value="intermediate">Intermediate</option>
        <option value="advanced">Advanced</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Count</label>
      <input type="number" name="count" min="1" max="10" value="3" class="form-control">
    </div>
    <button class="btn btn-primary btn-ai" onclick="btnLoading(this,true)">Generate with AI</button>
  </form>
</div>

<div class="admin-tabs">
  <button class="admin-tab active" type="button" data-admin-tab="practice">Practice Items</button>
  <button class="admin-tab" type="button" data-admin-tab="challenge">Daily Challenges</button>
  <button class="admin-tab" type="button" data-admin-tab="vocab">Vocabulary</button>
  <button class="admin-tab" type="button" data-admin-tab="speaking">Read-Alouds</button>
</div>

<div class="admin-panel active" data-admin-panel="practice">
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:14px;color:var(--text-1)">Add Practice Item</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_practice">
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-control"><option value="better_english">Choose Better English</option><option value="grammar_choice">Grammar Choice</option><option value="vocabulary_quiz">Vocabulary Quiz</option><option value="writing_prompt">Writing Prompt</option><option value="speaking_prompt">Read Aloud</option><option value="sentence_rearrangement">Sentence Rearrangement</option><option value="fill_blank">Fill in the Blank</option><option value="reading_comprehension">Reading Comprehension</option><option value="daily_challenge_set">Daily Challenge Set</option><option value="scenario_roleplay">Real-Life Scenario</option><option value="analytical_english">Analytical English</option><option value="word_sentence_builder">Word to 5 Sentences</option></select></div>
        <div class="form-group"><label class="form-label">Level</label><select name="difficulty" class="form-control"><option>beginner</option><option>intermediate</option><option>advanced</option></select></div>
      </div>
      <div class="form-group"><label class="form-label">Title</label><input name="title" class="form-control" placeholder="e.g., Choose the correct sentence structure" required></div>
      <div class="form-group"><label class="form-label">Prompt</label><textarea name="prompt" class="form-control" rows="3" placeholder="Write the question, passage, scenario, or read-aloud text." required></textarea></div>
      <div class="grid-3">
        <div class="form-group"><label class="form-label">Option A</label><textarea name="option_a" class="form-control" rows="2"></textarea></div>
        <div class="form-group"><label class="form-label">Option B</label><textarea name="option_b" class="form-control" rows="2"></textarea></div>
        <div class="form-group"><label class="form-label">Option C</label><textarea name="option_c" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="grid-3">
        <div class="form-group"><label class="form-label">Correct Option</label><select name="correct_option" class="form-control"><option value="">Open-ended</option><option>A</option><option>B</option><option>C</option></select></div>
        <div class="form-group"><label class="form-label">Category</label><input name="category" class="form-control" value="general" placeholder="grammar, work, travel"></div>
        <div class="form-group"><label class="form-label">XP</label><input type="number" name="xp_reward" class="form-control" value="25" min="5" max="200"></div>
      </div>
      <div class="grid-3">
        <div class="form-group"><label class="form-label">Tags / Skills</label><input name="tags" class="form-control" placeholder="tenses, idioms, customer-service"></div>
        <div class="form-group"><label class="form-label">Audio URL</label><input name="audio_url" class="form-control" placeholder="optional pronunciation audio URL"></div>
        <div class="form-group"><label class="form-label">Status</label><label style="display:flex;gap:8px;align-items:center;color:var(--text-2);padding-top:12px;"><input type="checkbox" name="active" checked> Published</label></div>
      </div>
      <div class="form-group"><label class="form-label">Explanation / Expected Answer</label><textarea name="explanation" class="form-control" rows="3"></textarea></div>
      <button class="btn btn-primary">Save Practice Item</button>
    </form>
  </div>
</div>

<div class="admin-panel" data-admin-panel="challenge">
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:14px;color:var(--text-1)">Add Daily Challenge</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_challenge">
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-control"><option>grammar</option><option>vocabulary</option><option>writing</option><option>speaking</option><option>listening</option></select></div>
        <div class="form-group"><label class="form-label">Date</label><input type="date" name="challenge_date" value="<?= date('Y-m-d') ?>" class="form-control"></div>
      </div>
      <div class="form-group"><label class="form-label">Title</label><input name="title" class="form-control" placeholder="e.g., Past tense correction challenge" required></div>
      <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2" placeholder="Short instruction users see before starting."></textarea></div>
      <div class="form-group"><label class="form-label">Content</label><textarea name="content" class="form-control" rows="4" placeholder="Full question, prompt, or challenge instructions." required></textarea></div>
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Level</label><select name="difficulty" class="form-control"><option>beginner</option><option>intermediate</option><option>advanced</option></select></div>
        <div class="form-group"><label class="form-label">XP</label><input type="number" name="xp_reward" value="50" class="form-control"></div>
      </div>
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Tags / Skills</label><input name="tags" class="form-control" placeholder="tenses, writing, speaking"></div>
        <div class="form-group"><label class="form-label">Status</label><label style="display:flex;gap:8px;align-items:center;color:var(--text-2);padding-top:12px;"><input type="checkbox" name="active" checked> Published</label></div>
      </div>
      <button class="btn btn-primary">Save Challenge</button>
    </form>
  </div>
</div>

<div class="admin-panel" data-admin-panel="vocab">
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:14px;color:var(--text-1)">Add Vocabulary</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_vocab">
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Word</label><input name="word" class="form-control" placeholder="e.g., concise" required></div>
        <div class="form-group"><label class="form-label">Pronunciation</label><input name="pronunciation" class="form-control" placeholder="kun-SICE"></div>
      </div>
      <div class="form-group"><label class="form-label">Meaning</label><textarea name="meaning" class="form-control" rows="3" placeholder="Simple learner-friendly definition." required></textarea></div>
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Synonyms</label><input name="synonyms" class="form-control"></div>
        <div class="form-group"><label class="form-label">Antonyms</label><input name="antonyms" class="form-control"></div>
      </div>
      <div class="form-group"><label class="form-label">Example Sentence</label><textarea name="example_sentence" class="form-control" rows="2" placeholder="Use the word naturally in one clear sentence."></textarea></div>
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Level</label><select name="difficulty" class="form-control"><option>beginner</option><option>intermediate</option><option>advanced</option></select></div>
        <div class="form-group"><label class="form-label">Category</label><input name="category" class="form-control" value="general"></div>
      </div>
      <div class="grid-2">
        <div class="form-group"><label class="form-label">Tags / Skills</label><input name="tags" class="form-control" placeholder="business, idioms, pronunciation"></div>
        <div class="form-group"><label class="form-label">Status</label><label style="display:flex;gap:8px;align-items:center;color:var(--text-2);padding-top:12px;"><input type="checkbox" name="active" checked> Published</label></div>
      </div>
      <button class="btn btn-primary">Save Word</button>
    </form>
  </div>
</div>

<div class="admin-panel" data-admin-panel="speaking">
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:14px;color:var(--text-1)">Add Read-Aloud Prompt</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_speaking_prompt">
      <div class="form-group"><label class="form-label">Text to Read</label><textarea name="text" class="form-control" rows="5" placeholder="Write a short passage the learner should read aloud." required></textarea></div>
      <div class="grid-3">
        <div class="form-group"><label class="form-label">Topic</label><input name="topic" class="form-control" value="General"></div>
        <div class="form-group"><label class="form-label">Level</label><select name="difficulty" class="form-control"><option>beginner</option><option>intermediate</option><option>advanced</option></select></div>
        <div class="form-group"><label class="form-label">Category</label><input name="category" class="form-control" value="general"></div>
      </div>
      <div class="grid-3">
        <div class="form-group"><label class="form-label">Tags / Skills</label><input name="tags" class="form-control" placeholder="pronunciation, fluency, work"></div>
        <div class="form-group"><label class="form-label">Audio URL</label><input name="audio_url" class="form-control" placeholder="optional TTS or recording URL"></div>
        <div class="form-group"><label class="form-label">Status</label><label style="display:flex;gap:8px;align-items:center;color:var(--text-2);padding-top:12px;"><input type="checkbox" name="active" checked> Published</label></div>
      </div>
      <button class="btn btn-primary">Save Prompt</button>
    </form>
  </div>
</div>

<div class="grid-2 mt-16" style="gap:24px;align-items:start;">
  <div class="card">
    <h3 style="font-size:16px;margin-bottom:14px;color:var(--text-1)">Users</h3>
    <table class="data-table">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>XP</th><th>Action</th></tr></thead>
      <tbody>
      <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
          <td><?= clean($u['name']) ?></td>
          <td><?= clean($u['email']) ?></td>
          <td><span class="ac-badge <?= $u['role']==='admin'?'badge-purple':'badge-blue' ?>"><?= clean($u['role']) ?></span></td>
          <td><?= (int)$u['xp'] ?></td>
          <td>
            <form method="POST" style="display:flex;gap:6px;align-items:center;">
              <input type="hidden" name="action" value="update_role">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <select name="role" class="form-control" style="width:90px;padding:6px 8px;font-size:12px;">
                <option value="user" <?= $u['role']==='user'?'selected':'' ?>>user</option>
                <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>admin</option>
              </select>
              <button class="btn btn-ghost btn-sm" <?= (int)$u['id']===$uid?'disabled':'' ?>>Save</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3 style="font-size:16px;margin-bottom:14px;color:var(--text-1)">Recent Practice Items</h3>
    <table class="data-table">
      <thead><tr><th>Title</th><th>Type</th><th>Level</th><th>Created</th></tr></thead>
      <tbody>
      <?php while ($p = $recentPractice->fetch_assoc()): ?>
        <tr>
          <td><?= clean($p['title']) ?></td>
          <td><?= clean(str_replace('_', ' ', $p['type'])) ?></td>
          <td><span class="ac-badge diff-<?= clean($p['difficulty']) ?>"><?= clean($p['difficulty']) ?></span></td>
          <td><?= date('M d', strtotime($p['created_at'])) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.querySelectorAll('[data-admin-tab]').forEach(btn => {
  btn.addEventListener('click', () => {
    const tab = btn.dataset.adminTab;
    document.querySelectorAll('[data-admin-tab]').forEach(b => b.classList.toggle('active', b === btn));
    document.querySelectorAll('[data-admin-panel]').forEach(panel => {
      panel.classList.toggle('active', panel.dataset.adminPanel === tab);
    });
  });
});
</script>

<?php include 'includes/footer.php'; ?>
