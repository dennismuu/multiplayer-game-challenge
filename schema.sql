CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(30) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_username_unique (username),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question VARCHAR(500) NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('a', 'b', 'c', 'd') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    score TINYINT UNSIGNED NOT NULL,
    total TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY attempts_user_created (user_id, created_at),
    CONSTRAINT attempts_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code CHAR(6) NOT NULL,
    host_user_id INT UNSIGNED NOT NULL,
    status ENUM('lobby', 'playing', 'finished') NOT NULL DEFAULT 'lobby',
    current_question TINYINT UNSIGNED NOT NULL DEFAULT 0,
    question_started_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY rooms_code_unique (code),
    KEY rooms_status_created (status, created_at),
    CONSTRAINT rooms_host_fk FOREIGN KEY (host_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_players (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY room_players_room_user_unique (room_id, user_id),
    KEY room_players_user (user_id),
    CONSTRAINT room_players_room_fk FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE,
    CONSTRAINT room_players_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id BIGINT UNSIGNED NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    question VARCHAR(500) NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('a', 'b', 'c', 'd') NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY room_questions_position_unique (room_id, position),
    CONSTRAINT room_questions_room_fk FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_question_id BIGINT UNSIGNED NOT NULL,
    room_player_id BIGINT UNSIGNED NOT NULL,
    selected_option ENUM('a', 'b', 'c', 'd') NOT NULL,
    awarded_points TINYINT UNSIGNED NOT NULL DEFAULT 0,
    answered_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY room_answers_player_question_unique (room_question_id, room_player_id),
    CONSTRAINT room_answers_question_fk FOREIGN KEY (room_question_id) REFERENCES room_questions (id) ON DELETE CASCADE,
    CONSTRAINT room_answers_player_fk FOREIGN KEY (room_player_id) REFERENCES room_players (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO questions (question, option_a, option_b, option_c, option_d, correct_option) VALUES
('Which planet is known as the Red Planet?', 'Venus', 'Mars', 'Jupiter', 'Mercury', 'b'),
('How many sides does a hexagon have?', 'Five', 'Seven', 'Six', 'Eight', 'c'),
('What is the largest ocean on Earth?', 'Atlantic Ocean', 'Indian Ocean', 'Arctic Ocean', 'Pacific Ocean', 'd'),
('Which animal is the tallest in the world?', 'Giraffe', 'Elephant', 'Ostrich', 'Camel', 'a'),
('What is the capital city of Japan?', 'Seoul', 'Tokyo', 'Beijing', 'Bangkok', 'b'),
('How many colors are traditionally in a rainbow?', 'Five', 'Six', 'Seven', 'Eight', 'c'),
('Which gas do plants absorb from the atmosphere?', 'Oxygen', 'Nitrogen', 'Hydrogen', 'Carbon dioxide', 'd'),
('What is the fastest land animal?', 'Cheetah', 'Lion', 'Horse', 'Gazelle', 'a'),
('Which instrument has keys, pedals, and strings?', 'Flute', 'Piano', 'Drum', 'Trumpet', 'b'),
('How many minutes are in two hours?', '100', '110', '120', '140', 'c');
