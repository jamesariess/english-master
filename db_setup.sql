-- ============================================
-- EnglishMaster AI - Database Setup
-- Run this in phpMyAdmin SQL tab
-- ============================================

CREATE DATABASE IF NOT EXISTS english_master_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE english_master_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    level INT DEFAULT 1,
    xp INT DEFAULT 0,
    streak INT DEFAULT 0,
    last_active DATE,
    avatar VARCHAR(10) DEFAULT '🧑',
    english_level ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- XP activity log
CREATE TABLE IF NOT EXISTS xp_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Chat history
CREATE TABLE IF NOT EXISTS chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    content TEXT NOT NULL,
    session_id VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Vocabulary words
CREATE TABLE IF NOT EXISTS vocabulary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    word VARCHAR(100) NOT NULL,
    meaning TEXT NOT NULL,
    synonyms VARCHAR(255),
    antonyms VARCHAR(255),
    pronunciation VARCHAR(100),
    example_sentence TEXT,
    difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    category VARCHAR(50) DEFAULT 'general',
    tags VARCHAR(255) DEFAULT '',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User vocabulary progress
CREATE TABLE IF NOT EXISTS user_vocabulary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    word_id INT NOT NULL,
    status ENUM('new','learning','mastered') DEFAULT 'new',
    review_count INT DEFAULT 0,
    last_reviewed TIMESTAMP,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_word (user_id, word_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (word_id) REFERENCES vocabulary(id) ON DELETE CASCADE
);

-- Grammar sessions
CREATE TABLE IF NOT EXISTS grammar_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    original_text TEXT NOT NULL,
    corrected_text TEXT,
    ai_feedback TEXT,
    error_count INT DEFAULT 0,
    score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Daily challenges
CREATE TABLE IF NOT EXISTS challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('vocabulary','grammar','writing','speaking','listening') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    content TEXT,
    difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    tags VARCHAR(255) DEFAULT '',
    active TINYINT(1) DEFAULT 1,
    xp_reward INT DEFAULT 50,
    challenge_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User challenge completions
CREATE TABLE IF NOT EXISTS user_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    answer TEXT,
    ai_feedback TEXT,
    score INT DEFAULT 0,
    xp_earned INT DEFAULT 0,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
);

-- Practice lab items
CREATE TABLE IF NOT EXISTS practice_items (
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
);

CREATE TABLE IF NOT EXISTS user_practice_attempts (
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
);

-- Interview sessions
CREATE TABLE IF NOT EXISTS interview_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_type VARCHAR(100),
    conversation TEXT,
    grammar_score INT DEFAULT 0,
    confidence_score INT DEFAULT 0,
    ai_feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Achievements
CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(10),
    xp_reward INT DEFAULT 100,
    condition_type VARCHAR(50),
    condition_value INT
);

-- User achievements
CREATE TABLE IF NOT EXISTS user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
);

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Default admin account for local setup
-- Email: admin@englishmaster.local
-- Password: admin123
INSERT INTO users (name,email,password,english_level,role,avatar,last_active)
SELECT 'Admin','admin@englishmaster.local','$2y$10$8g7z0l1AjQAsXTQpvCV2FuYXQzHkVmRSPhy7ZWdrBIk21PlI7ovZG','advanced','admin','A',CURDATE()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='admin@englishmaster.local');

-- Sample vocabulary words
INSERT INTO vocabulary (word, meaning, synonyms, antonyms, pronunciation, example_sentence, difficulty, category) VALUES
('eloquent','Fluent and persuasive in speaking or writing','articulate, expressive, well-spoken','inarticulate, tongue-tied','EL-oh-kwent','She gave an eloquent speech that moved the entire audience.','intermediate','communication'),
('perseverance','Continued effort despite difficulty','persistence, determination, tenacity','laziness, giving up','per-suh-VEER-ants','His perseverance paid off when he finally passed the exam.','intermediate','character'),
('meticulous','Showing great attention to detail','precise, thorough, careful','careless, sloppy','meh-TIK-yoo-lus','She was meticulous in checking every detail of the report.','advanced','personality'),
('collaborate','To work jointly with others','cooperate, team up, partner','compete, hinder','kuh-LAB-oh-rayt','We need to collaborate to finish this project on time.','beginner','work'),
('innovative','Introducing new ideas; original','creative, inventive, pioneering','conventional, traditional','in-OH-vuh-tiv','The company is known for its innovative products.','intermediate','business'),
('resilient','Able to recover quickly from difficulties','tough, strong, adaptable','weak, fragile','reh-ZIL-yent','She is resilient and always bounces back from setbacks.','intermediate','character'),
('ambiguous','Open to more than one interpretation','unclear, vague, uncertain','clear, definite','am-BIG-yoo-us','The instructions were ambiguous, so I asked for clarification.','advanced','language'),
('diligent','Having or showing care in work','hardworking, industrious, thorough','lazy, careless','DIL-ih-jent','A diligent student always reviews notes after class.','beginner','character'),
('eloquent','Expressing ideas clearly and effectively','articulate, fluent, persuasive','inarticulate, mumbling','EL-oh-kwent','He was an eloquent speaker who captivated the crowd.','intermediate','communication'),
('negotiate','To discuss in order to reach agreement','bargain, discuss, mediate','refuse, disagree','neh-GOH-shee-ayt','We need to negotiate the terms of the contract.','beginner','business'),
('accomplish','To successfully complete a task','achieve, complete, fulfill','fail, abandon','uh-KOM-plish','I want to accomplish all my goals this year.','beginner','action'),
('confident','Feeling certain about your abilities','self-assured, bold, positive','insecure, doubtful','KON-fih-dent','She walked into the interview feeling confident.','beginner','emotion'),
('initiative','The ability to act independently','enterprise, drive, ambition','laziness, passivity','ih-NISH-uh-tiv','Taking initiative at work shows leadership potential.','intermediate','work'),
('proactive','Creating situations rather than reacting','prepared, forward-thinking, anticipatory','reactive, passive','pro-AK-tiv','Be proactive and address problems before they escalate.','intermediate','work'),
('concise','Giving information clearly in few words','brief, short, to-the-point','wordy, verbose','kun-SICE','Your email should be concise and professional.','intermediate','communication');

-- Sample challenges
INSERT INTO challenges (type, title, description, content, difficulty, xp_reward, challenge_date) VALUES
('grammar','Fix the Mistakes','Find and correct all grammar mistakes in the paragraph.','I go to school yesterday and I seen my friend. We was very happy to met each other after a long time.','beginner',50,CURDATE()),
('vocabulary','Word of the Day','Learn and use the word PERSEVERANCE in a sentence.','Write a sentence using the word "perseverance" correctly. Then explain what it means in your own words.','intermediate',75,CURDATE()),
('writing','Describe Your Day','Write 5-6 sentences about what you did today.','Describe your day so far in at least 5 sentences. Focus on using past tense correctly.','beginner',60,CURDATE()),
('grammar','Tense Challenge','Rewrite these sentences in the correct tense.','1. Yesterday I am eating lunch at 12pm.\n2. She will went to the store tomorrow.\n3. They has been working for 3 hours.','intermediate',80,CURDATE());

-- Practice lab samples
INSERT INTO practice_items (type,title,prompt,option_a,option_b,option_c,correct_option,explanation,difficulty,category,xp_reward) VALUES
('better_english','Choose the Better English','Which sentence sounds more natural and correct?','I am interested in learning English.','I am interesting to learn English.',NULL,'A','Use interested when you feel curiosity. Interesting describes the thing that causes curiosity.','beginner','grammar',25),
('grammar_choice','Past Tense Practice','Choose the correct sentence.','Yesterday I go to work.','Yesterday I went to work.','Yesterday I will go to work.','B','Use went for a completed action in the past.','beginner','tenses',25),
('vocabulary_quiz','Vocabulary in Context','Choose the best word: She gave a clear and ___ explanation.','confusing','concise','late','B','Concise means clear and expressed in few words.','intermediate','vocabulary',30),
('writing_prompt','Write a Strong Sentence','Write one professional sentence using the word "proactive".',NULL,NULL,NULL,NULL,'A strong answer uses proactive to mean taking action before problems happen.','intermediate','writing',35),
('speaking_prompt','Read Aloud: Clear Introduction','Read this aloud: Hello, my name is Anna. I am practicing English every day so I can speak more clearly at work.',NULL,NULL,NULL,NULL,'Focus on clear pacing and word endings.','beginner','speaking',25);

INSERT INTO practice_items (type,title,prompt,option_a,answer_key,explanation,difficulty,category,tags,xp_reward) VALUES
('sentence_rearrangement','Build a Simple Sentence','Drag the words into the correct order.','The bird can fly.','The bird can fly.','English sentences usually follow subject + helping verb + main verb.','beginner','grammar','word order, sentence structure',25);

INSERT INTO practice_items (type,title,prompt,option_a,option_b,option_c,option_d,correct_option,answer_key,explanation,difficulty,category,tags,xp_reward) VALUES
('fill_blank','Choose the Right Preposition','She is interested ____ learning English.','on','in','at','for','B','in','Use \"interested in\" before a noun or gerund.','beginner','grammar','prepositions, gerunds',25);

INSERT INTO practice_items (type,title,prompt,option_a,option_b,option_c,answer_key,explanation,difficulty,category,tags,xp_reward) VALUES
('reading_comprehension','Maria Practices Every Day','Maria wants to speak English more confidently at work. Every morning, she reads one short paragraph aloud before breakfast. At lunch, she writes five new words in her notebook and makes her own sentences. In the evening, she talks with an AI tutor for ten minutes. After one month, Maria notices that she can answer customers faster and explain ideas more clearly. She still makes mistakes, but she understands them and corrects them quickly.','Why does Maria practice English?','What does she do at lunch?','How does Maria improve after one month?','1. She wants to speak more confidently at work. 2. She writes five new words and makes sentences. 3. She answers customers faster and explains ideas more clearly.','Good answers should use details from the story.','beginner','reading','main idea, details',35);

INSERT INTO practice_items (type,title,prompt,option_a,option_b,option_c,option_d,correct_option,answer_key,explanation,difficulty,category,tags,xp_reward) VALUES
('tense_quiz','Choose the Correct Tense','Tomorrow, she ____ her English lesson.','attended','attends','will attend','is attended','C','will attend','Use future tense with tomorrow: she will attend her English lesson.','beginner','grammar','future tense, verb tense',25),
('synonyms_antonyms_quiz','Synonym of Happy','Choose the synonym of happy.','Sad','Joyful','Angry','Tired','B','Joyful','A synonym has a similar meaning. Joyful means very happy.','beginner','vocabulary','synonyms, emotions',25),
('sentence_meaning_quiz','Meaning of an Idiom','"He is on cloud nine" means:','He is sad','He is very happy','He is tired','He is sick','B','He is very happy','On cloud nine is an idiom that means extremely happy.','beginner','comprehension','idioms, sentence meaning',25);

-- Sample achievements
INSERT INTO achievements (name, description, icon, xp_reward, condition_type, condition_value) VALUES
('First Chat','Had your first AI conversation','💬',50,'chats',1),
('Grammar Hero','Completed 10 grammar sessions','📝',100,'grammar_sessions',10),
('Word Collector','Learned 25 vocabulary words','📚',150,'words_learned',25),
('Week Warrior','Maintained a 7-day streak','🔥',200,'streak',7),
('Interview Ready','Completed 5 mock interviews','👔',250,'interviews',5),
('Challenge Master','Completed 20 daily challenges','⭐',300,'challenges',20),
('XP Champion','Earned 1000 total XP','🏆',500,'total_xp',1000),
('Dedicated Learner','Used the app for 30 days','🎓',400,'days_active',30);

-- Speaking sessions
CREATE TABLE IF NOT EXISTS speaking_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mode ENUM('free','read_aloud','pronunciation') DEFAULT 'free',
    original_text TEXT,
    transcript TEXT,
    ai_feedback TEXT,
    grammar_score INT DEFAULT 0,
    fluency_score INT DEFAULT 0,
    overall_score INT DEFAULT 0,
    duration_seconds INT DEFAULT 0,
    word_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Read-aloud prompts (optional, app uses built-in fallbacks too)
CREATE TABLE IF NOT EXISTS speaking_prompts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text TEXT NOT NULL,
    topic VARCHAR(100) DEFAULT 'General',
    difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    category VARCHAR(50) DEFAULT 'general',
    tags VARCHAR(255) DEFAULT '',
    audio_url VARCHAR(255) DEFAULT '',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample prompts
INSERT INTO speaking_prompts (text, topic, difficulty, category) VALUES
('Good morning! Today is a beautiful day. I am very happy to practice speaking English with you.', 'Daily Life', 'beginner', 'general'),
('My name is Maria. I live in Manila. I work in an office and I love learning new things every day.', 'Introduction', 'beginner', 'general'),
('Hello! Can you tell me the way to the nearest supermarket? I need to buy some fruits and vegetables.', 'Directions', 'beginner', 'daily'),
('Technology has changed the way we communicate. Social media connects millions of people every single day around the world.', 'Technology', 'intermediate', 'general'),
('In my opinion, learning English is very important for career growth. It opens many doors and gives you more opportunities in life.', 'Career', 'intermediate', 'work'),
('Customer service is all about understanding what the client needs and providing the best possible solution quickly and professionally.', 'Work', 'intermediate', 'work'),
('The rapid advancement of artificial intelligence raises important ethical questions about privacy, employment, and human decision-making processes.', 'AI & Technology', 'advanced', 'academic'),
('Effective communication in a professional environment requires not only grammatical accuracy but also cultural awareness and emotional intelligence.', 'Communication', 'advanced', 'work'),
('Research consistently shows that bilingual individuals develop stronger cognitive flexibility and problem-solving abilities compared to monolingual speakers.', 'Education', 'advanced', 'academic');

-- Speaking daily challenges
INSERT INTO challenges (type, title, description, content, difficulty, xp_reward, challenge_date) VALUES
('speaking','1-Minute Speaking Challenge','Speak for 1 minute about your favorite food.','Go to the Speaking Practice page and speak freely for at least 1 minute about your favorite food. Describe the taste, smell, and why you love it. Focus on using descriptive adjectives!','beginner',80,CURDATE()),
('speaking','Pronunciation Challenge','Practice saying these difficult English words correctly.','Go to the Speaking Practice → Pronunciation tab and practice these words: Comfortable, Particularly, Vocabulary. Try each one 3 times!','intermediate',100,CURDATE());
