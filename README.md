# 🎓 EnglishMaster AI — Setup Guide

A full-featured AI-powered English Learning System built with PHP + MySQL for XAMPP.

---

## 📋 Requirements

- **XAMPP** (v7.4 or newer) — Apache + MySQL + PHP
- **PHP 7.4+** with cURL enabled
- **Anthropic API Key** — Get one free at https://console.anthropic.com/
- Internet connection (for AI features)

---

## 🚀 Installation Steps

### Step 1 — Copy Files to XAMPP

1. Open your XAMPP folder (usually `C:\xampp\htdocs\` on Windows)
2. Create a new folder called `english-master`
3. Copy **all files** from this project into `C:\xampp\htdocs\english-master\`

Your folder should look like:
```
htdocs/
└── english-master/
    ├── index.php
    ├── dashboard.php
    ├── chat.php
    ├── grammar.php
    ├── vocabulary.php
    ├── challenges.php
    ├── interview.php
    ├── progress.php
    ├── logout.php
    ├── config.php
    ├── db_setup.sql
    ├── includes/
    │   ├── header.php
    │   └── footer.php
    └── assets/
        └── style.css
```

---

### Step 2 — Start XAMPP

1. Open **XAMPP Control Panel**
2. Click **Start** for **Apache**
3. Click **Start** for **MySQL**

---

### Step 3 — Set Up Database

1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **"New"** in the left sidebar to create a new database
3. Name it: `english_master_db` → Click **Create**
4. Click on `english_master_db` in the left sidebar
5. Click the **"SQL"** tab at the top
6. Open the file `db_setup.sql` from this project
7. Copy **all** the SQL content and paste it into the SQL box
8. Click **Go** to run it

✅ You should see all tables created successfully.

---

### Step 4 — Configure the App

Open `config.php` in a text editor and update these settings:

```php
// Change this to your Anthropic API key:
define('ANTHROPIC_API_KEY', 'sk-ant-YOUR_KEY_HERE');

// If your MySQL password is different from default:
define('DB_PASS', '');  // Leave empty for XAMPP default

// App URL - change if your folder name is different:
define('APP_URL', 'http://localhost/english-master');
```

---

### Step 5 — Get Your Anthropic API Key

1. Go to https://console.anthropic.com/
2. Sign up / Log in
3. Go to **API Keys** → Click **Create Key**
4. Copy the key (starts with `sk-ant-...`)
5. Paste it into `config.php` as shown above

---

### Step 6 — Open the App

In your browser, go to:
```
http://localhost/english-master
```

You should see the **EnglishMaster AI** login page! 🎉

---

## 🎮 Features & Pages

| Page | URL | Feature |
|------|-----|---------|
| Landing / Login | `/index.php` | Register & Sign in |
| Dashboard | `/dashboard.php` | Overview, stats, quick links |
| AI Chat | `/chat.php` | Free conversation with AI tutor |
| Grammar Checker | `/grammar.php` | Paste text → get corrections |
| Vocabulary | `/vocabulary.php` | Learn & track new words |
| Daily Challenges | `/challenges.php` | Grammar & writing tasks |
| Interview Practice | `/interview.php` | Mock job interviews |
| Progress | `/progress.php` | Charts & skill tracking |

---

## 🗄️ Database Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts, XP, level, streak |
| `xp_log` | All XP activity history |
| `chat_history` | AI conversation logs |
| `vocabulary` | Word bank with meanings |
| `user_vocabulary` | Per-user word learning status |
| `grammar_sessions` | Grammar check history |
| `challenges` | Daily challenge content |
| `user_challenges` | Challenge completion records |
| `interview_sessions` | Interview practice history |
| `achievements` | Achievement definitions |
| `user_achievements` | Per-user earned badges |

---

## 📚 Adding Daily Challenges in phpMyAdmin

Go to phpMyAdmin → `english_master_db` → `challenges` table → **Insert**:

| Field | Example Value |
|-------|--------------|
| type | grammar |
| title | Fix the Tense Mistakes |
| description | Correct all verb tense errors in the sentences below. |
| content | 1. Yesterday I am eating lunch. 2. She will went shopping tomorrow. |
| difficulty | beginner |
| xp_reward | 50 |
| challenge_date | 2026-05-21 *(use today's date)* |

---

## 🛠️ Troubleshooting

**❌ "Database Connection Failed"**
→ Make sure XAMPP MySQL is running and you ran `db_setup.sql`

**❌ AI says "Please set your API key"**
→ Open `config.php` and paste your Anthropic API key

**❌ Page not found / 404**
→ Make sure folder is named `english-master` and is inside `htdocs`

**❌ AI not responding**
→ Check that PHP cURL is enabled in `php.ini` (XAMPP → Config → php.ini → find `extension=curl` and remove the `;`)

**❌ "Call to undefined function" errors**
→ Make sure all files are present, especially `config.php`

---

## 🔒 Security Notes

This app is designed for **local use with XAMPP** (learning/personal use). Before deploying to a public server:
- Enable HTTPS
- Use environment variables for API keys
- Add rate limiting to AI endpoints
- Use prepared statements throughout (or a proper ORM)

---

## 💡 Customization

- **Add vocabulary words**: Insert into `vocabulary` table in phpMyAdmin
- **Add challenges**: Insert into `challenges` table with `challenge_date = CURDATE()`  
- **Change XP rewards**: Edit the `addXP()` calls in each PHP file
- **Change AI behavior**: Edit the `$system` prompt strings in each file
- **Add new job types**: Edit the `$jobTypes` array in `interview.php`

---

## 📞 Tech Stack

- **Frontend**: Pure HTML5, CSS3, Vanilla JavaScript
- **Backend**: PHP 7.4+
- **Database**: MySQL (via phpMyAdmin/XAMPP)
- **AI**: Anthropic Claude API (claude-sonnet-4-20250514)
- **Fonts**: Google Fonts (Plus Jakarta Sans, DM Sans, JetBrains Mono)

---

Made with ❤️ for English learners everywhere. Good luck on your journey to fluency! 🚀

---

## 🎤 Speaking Practice Feature

### How it works
The speaking feature uses the **Web Speech API** built into modern browsers — no extra API keys needed for speech recognition.

### 3 Practice Modes

| Mode | What You Do | What AI Checks |
|------|-------------|----------------|
| 🗣️ Free Speaking | Speak freely on any topic | Grammar, fluency, natural phrases |
| 📖 Read Aloud | Read the displayed text out loud | Accuracy, missed/wrong words |
| 🔤 Pronunciation | Say a specific word | Pronunciation score + tips |

### Microphone in Chat
The AI Chat page also has a 🎤 mic button. Click it to speak your message instead of typing — great for building real speaking confidence!

### Browser Requirements
**IMPORTANT:** Speech recognition ONLY works in:
- ✅ Google Chrome
- ✅ Microsoft Edge
- ❌ Firefox (not supported)
- ❌ Safari (limited support)

This is a browser limitation, not an app limitation.

### New Database Tables Added
Run the updated `db_setup.sql` to create:
- `speaking_sessions` — stores all speaking session records
- `speaking_prompts` — read-aloud prompt library

### Troubleshooting Microphone
- **"Microphone access denied"** → Click the 🔒 padlock in the URL bar → Allow microphone
- **No text appearing** → Make sure you're using Chrome/Edge, speak clearly and not too fast
- **Stops after silence** → Normal behaviour — the app auto-restarts listening
