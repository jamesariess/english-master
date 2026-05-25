-- ============================================================
-- EnglishMaster AI — Speaking Feature DB Patch
-- Run this in phpMyAdmin if you already ran db_setup.sql before
-- the speaking feature was added.
--
-- HOW TO RUN:
--   1. Open phpMyAdmin → select english_master_db
--   2. Click the SQL tab
--   3. Paste all of this and click Go
-- ============================================================

USE english_master_db;

-- Speaking sessions table
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
);

-- Read-aloud prompts table
CREATE TABLE IF NOT EXISTS speaking_prompts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    text       TEXT NOT NULL,
    topic      VARCHAR(100) DEFAULT 'General',
    difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    category   VARCHAR(50)  DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample prompts (skip if already exist)
INSERT IGNORE INTO speaking_prompts (id, text, topic, difficulty, category) VALUES
(1,  'Good morning! Today is a beautiful day. I am very happy to practice speaking English with you.',                                                                              'Daily Life',    'beginner',     'general'),
(2,  'My name is Maria. I live in Manila. I work in an office and I love learning new things every day.',                                                                           'Introduction',  'beginner',     'general'),
(3,  'Hello! Can you tell me the way to the nearest supermarket? I need to buy some fruits and vegetables.',                                                                        'Directions',    'beginner',     'daily'),
(4,  'Technology has changed the way we communicate. Social media connects millions of people every single day around the world.',                                                  'Technology',    'intermediate', 'general'),
(5,  'In my opinion, learning English is very important for career growth. It opens many doors and gives you more opportunities in life.',                                           'Career',        'intermediate', 'work'),
(6,  'Customer service is all about understanding what the client needs and providing the best possible solution quickly and professionally.',                                        'Work',          'intermediate', 'work'),
(7,  'The rapid advancement of artificial intelligence raises important ethical questions about privacy, employment, and human decision-making processes.',                          'AI & Tech',     'advanced',     'academic'),
(8,  'Effective communication in a professional environment requires not only grammatical accuracy but also cultural awareness and emotional intelligence.',                          'Communication', 'advanced',     'work'),
(9,  'Research consistently shows that bilingual individuals develop stronger cognitive flexibility and problem-solving abilities compared to monolingual speakers.',                 'Education',     'advanced',     'academic'),
(10, 'Please hold the line while I transfer your call to the correct department. Thank you for your patience.',                                                                      'Call Center',   'intermediate', 'work'),
(11, 'I would like to apply for the position. I have three years of experience in customer service and I am a fast learner.',                                                        'Interview',     'intermediate', 'work'),
(12, 'Every day I try to learn five new English words. I write them in my notebook and practice using them in sentences.',                                                           'Study Habits',  'beginner',     'general');

-- Add speaking challenges for today (safe to run multiple times)
INSERT INTO challenges (type, title, description, content, difficulty, xp_reward, challenge_date)
SELECT 'speaking',
       '1-Minute Speaking Challenge',
       'Speak for 1 minute about your favorite food.',
       'Go to the Speaking Practice page and speak freely for at least 1 minute about your favorite food. Describe the taste, smell, and why you love it!',
       'beginner', 80, CURDATE()
WHERE NOT EXISTS (
    SELECT 1 FROM challenges
    WHERE type = 'speaking' AND challenge_date = CURDATE() AND title = '1-Minute Speaking Challenge'
);

SELECT 'speaking_sessions table OK' AS status;
SELECT 'speaking_prompts table OK'  AS status;
SELECT COUNT(*) AS prompts_inserted FROM speaking_prompts;
