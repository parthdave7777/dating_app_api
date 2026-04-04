-- DATABASE: Use defaultdb (Aiven)
-- The USE command is removed to allow dynamic database selection via config.php.

-- ─── USERS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    phone_number          VARCHAR(20)   NOT NULL UNIQUE,
    full_name             VARCHAR(100)  DEFAULT NULL,
    age                   INT           DEFAULT NULL,
    gender                VARCHAR(20)   DEFAULT NULL,
    looking_for           VARCHAR(20)   DEFAULT NULL,
    bio                   TEXT          DEFAULT NULL,
    interests             TEXT          DEFAULT NULL,
    height                VARCHAR(20)   DEFAULT NULL,
    education             VARCHAR(100)  DEFAULT NULL,
    job_title             VARCHAR(100)  DEFAULT NULL,
    company               VARCHAR(100)  DEFAULT NULL,
    lifestyle_pets        VARCHAR(50)   DEFAULT NULL,
    lifestyle_drinking    VARCHAR(50)   DEFAULT NULL,
    lifestyle_smoking     VARCHAR(50)   DEFAULT NULL,
    lifestyle_workout     VARCHAR(50)   DEFAULT NULL,
    lifestyle_diet        VARCHAR(50)   DEFAULT NULL,
    lifestyle_schedule    VARCHAR(50)   DEFAULT NULL,
    communication_style   VARCHAR(50)   DEFAULT NULL,
    relationship_goal     VARCHAR(50)   DEFAULT NULL,
    latitude              DECIMAL(10,7) DEFAULT NULL,
    longitude             DECIMAL(10,7) DEFAULT NULL,
    city                  VARCHAR(100)  DEFAULT NULL,
    state                 VARCHAR(100)  DEFAULT NULL,
    country               VARCHAR(100)  DEFAULT NULL,
    is_verified           TINYINT(1)    DEFAULT 0,
    verification_status   TINYINT(1)    DEFAULT 0,
    profile_complete      TINYINT(1)    DEFAULT 0,
    setup_completed       TINYINT(1)    DEFAULT 0,
    fcm_token             TEXT          DEFAULT NULL,
    elo_score             INT           DEFAULT 1000,
    last_active           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    is_new_user_boost     TINYINT(1)    DEFAULT 1,
    new_user_boost_expires DATETIME     DEFAULT NULL,
    show_in_discovery     TINYINT(1)    DEFAULT 1,
    created_at            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ─── OTP ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS otp_codes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20)  NOT NULL UNIQUE,
    otp          VARCHAR(10)  NOT NULL,
    expires_at   DATETIME     NOT NULL,
    used         TINYINT(1)   DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone_number)
);

-- ─── PHOTOS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_photos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT           NOT NULL,
    photo_url  VARCHAR(1000) NOT NULL,   -- Cloudinary URLs can be long
    is_dp      TINYINT(1)    DEFAULT 0,
    is_verified TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);

-- ─── POSTS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_posts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT           NOT NULL,
    photo_url  VARCHAR(1000) NOT NULL,
    caption    TEXT          DEFAULT NULL,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);

-- ─── SWIPES ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS swipes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    swiper_id  INT         NOT NULL,
    swiped_id  INT         NOT NULL,
    action     ENUM('like','dislike','superlike') NOT NULL,
    created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_swipe (swiper_id, swiped_id),
    FOREIGN KEY (swiper_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (swiped_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── MATCHES ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS matches (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user1_id   INT       NOT NULL,
    user2_id   INT       NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_match (user1_id, user2_id),
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── CALL LOGS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS call_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    match_id     INT NOT NULL,
    caller_id    INT NOT NULL,
    callee_id    INT NOT NULL,
    status       ENUM('ringing','accepted','ended','missed','cancelled') DEFAULT 'ringing',
    started_at   DATETIME  DEFAULT NULL,
    ended_at     DATETIME  DEFAULT NULL,
    duration_sec INT       DEFAULT 0,
    message_id   INT       DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id)  REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (caller_id) REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (callee_id) REFERENCES users(id)   ON DELETE CASCADE
);

-- ─── MESSAGES ───────────────────────────────────────────────
-- IMPORTANT: type is VARCHAR(50), not ENUM, to support view-once types
CREATE TABLE IF NOT EXISTS messages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    match_id     INT          NOT NULL,
    sender_id    INT          NOT NULL,
    message      TEXT         DEFAULT NULL,
    type         VARCHAR(50)  DEFAULT 'text',   -- text|image|video|image_view_once|video_view_once|image_opened|video_opened|call_event|call_missed|call_ended
    is_read      TINYINT(1)   DEFAULT 0,
    is_saved     TINYINT(1)   DEFAULT 0,
    is_deleted   TINYINT(1)   DEFAULT 0,
    is_edited    TINYINT(1)   DEFAULT 0,
    deleted_by   INT          DEFAULT NULL,
    read_at      DATETIME     DEFAULT NULL,
    call_event   VARCHAR(50)  DEFAULT NULL,
    duration     INT          DEFAULT NULL,
    reply_to_id  INT          DEFAULT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    INDEX idx_match  (match_id),
    INDEX idx_sender (sender_id)
);

-- ─── PROFILE VIEWS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS profile_views (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    viewer_id INT       NOT NULL,
    viewed_id INT       NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_view (viewer_id, viewed_id),
    FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (viewed_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── REPORTS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reports (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id      INT          NOT NULL,
    reported_user_id INT          NOT NULL,
    reason           VARCHAR(100) NOT NULL,
    description      TEXT         DEFAULT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id)      REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── BLOCKS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS blocks (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id      INT       NOT NULL,
    blocked_user_id INT       NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_block (blocker_id, blocked_user_id),
    FOREIGN KEY (blocker_id)      REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── NOTIFICATIONS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    type       VARCHAR(50)  NOT NULL,
    title      VARCHAR(200) NOT NULL,
    body       TEXT         NOT NULL,
    data       TEXT         DEFAULT NULL,   -- JSON as TEXT (InfinityFree may not support JSON column)
    is_read    TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);
