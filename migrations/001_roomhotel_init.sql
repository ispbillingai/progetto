-- RoomHotel: guest-to-staff requests for hotels. One fixed QR per room; the code
-- given at check-in changes at check-out. Requests go to the department of their type.

CREATE TABLE IF NOT EXISTS app_settings (
    k VARCHAR(64) NOT NULL PRIMARY KEY,
    v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hotels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#0786c4',
    welcome_text VARCHAR(500) NULL,
    info_text TEXT NULL,
    logo_file VARCHAR(100) NULL,
    code_length TINYINT NOT NULL DEFAULT 4,
    remind_after SMALLINT NOT NULL DEFAULT 120,
    escalate_after SMALLINT NOT NULL DEFAULT 300,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reception, housekeeping, maintenance, bar, kitchen... kind drives icons and defaults.
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    kind ENUM('reception','housekeeping','maintenance','bar','kitchen','other') NOT NULL DEFAULT 'other',
    name VARCHAR(60) NOT NULL,
    icon VARCHAR(8) NOT NULL DEFAULT '',
    open_from TIME NULL,
    open_to TIME NULL,
    sort SMALLINT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_departments_hotel (hotel_id),
    CONSTRAINT fk_departments_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff. hotel_id is NULL only for the superadmin. Users are never deleted, only disabled.
-- department_ids / zones: JSON lists of what the person follows (empty = everything).
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NULL,
    role ENUM('superadmin','manager','staff') NOT NULL DEFAULT 'staff',
    name VARCHAR(100) NOT NULL,
    username VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    department_ids TEXT NULL,
    zones TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_hotel (hotel_id),
    CONSTRAINT fk_users_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Remember me" logins, so the staff app (and its push notifications) stay signed in.
CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auth_selector (selector),
    KEY idx_auth_user (user_id),
    CONSTRAINT fk_auth_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    label VARCHAR(40) NOT NULL,
    zone VARCHAR(60) NULL,
    qr_token VARCHAR(16) NOT NULL,
    current_stay_id INT NULL,
    dnd TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rooms_token (qr_token),
    KEY idx_rooms_hotel (hotel_id),
    CONSTRAINT fk_rooms_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per guest stay: the code is valid from check-in to check-out.
CREATE TABLE IF NOT EXISTS stays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    room_id INT NOT NULL,
    code VARCHAR(8) NOT NULL,
    guest_name VARCHAR(100) NULL,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    closed_by INT NULL,
    KEY idx_stays_room (room_id),
    CONSTRAINT fk_stays_room FOREIGN KEY (room_id) REFERENCES rooms (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What a guest can ask for (the buttons on the room page), per hotel and department.
-- names: JSON {"it":..,"en":..,"de":..,"fr":..,"es":..}. ask_time: the guest picks a time
-- (wake-up call, breakfast); ask_items: picks items from menu_items of the department;
-- urgent: goes to everyone at once, no waiting. lead_minutes: how long before a scheduled
-- time the staff is alerted.
CREATE TABLE IF NOT EXISTS request_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    department_id INT NOT NULL,
    icon VARCHAR(8) NOT NULL DEFAULT '',
    names TEXT NOT NULL,
    hint VARCHAR(200) NULL,
    ask_time TINYINT(1) NOT NULL DEFAULT 0,
    ask_items TINYINT(1) NOT NULL DEFAULT 0,
    urgent TINYINT(1) NOT NULL DEFAULT 0,
    lead_minutes SMALLINT NOT NULL DEFAULT 0,
    sort SMALLINT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_types_hotel (hotel_id),
    CONSTRAINT fk_types_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id),
    CONSTRAINT fk_types_department FOREIGN KEY (department_id) REFERENCES departments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Room service / bar list: what the guest can order from a department.
CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    department_id INT NOT NULL,
    category VARCHAR(60) NULL,
    names TEXT NOT NULL,
    price DECIMAL(8,2) NULL,
    sort SMALLINT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_items_hotel (hotel_id),
    CONSTRAINT fk_items_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id),
    CONSTRAINT fk_items_department FOREIGN KEY (department_id) REFERENCES departments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A request of a guest. scheduled: waits until due_at minus lead, then becomes open and
-- the staff is alerted. items: JSON snapshot [{name, qty, price}] of what was ordered.
CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    room_id INT NOT NULL,
    stay_id INT NOT NULL,
    department_id INT NOT NULL,
    type_id INT NULL,
    type_name VARCHAR(120) NOT NULL,
    icon VARCHAR(8) NOT NULL DEFAULT '',
    note VARCHAR(500) NULL,
    items TEXT NULL,
    total DECIMAL(8,2) NULL,
    due_at DATETIME NULL,
    urgent TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('scheduled','open','taken','done','cancelled') NOT NULL DEFAULT 'open',
    repeat_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_call_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reminded_at DATETIME NULL,
    escalated_at DATETIME NULL,
    alerts INT NOT NULL DEFAULT 0,
    taken_by INT NULL,
    taken_at DATETIME NULL,
    done_by INT NULL,
    done_at DATETIME NULL,
    reply VARCHAR(300) NULL,
    replied_at DATETIME NULL,
    KEY idx_requests_hotel_status (hotel_id, status),
    KEY idx_requests_stay (stay_id),
    KEY idx_requests_hotel_created (hotel_id, created_at),
    CONSTRAINT fk_requests_room FOREIGN KEY (room_id) REFERENCES rooms (id),
    CONSTRAINT fk_requests_department FOREIGN KEY (department_id) REFERENCES departments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wrong codes typed by guests (brute-force limit).
CREATE TABLE IF NOT EXISTS code_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_ok_at DATETIME NULL,
    UNIQUE KEY uq_push_endpoint (endpoint_hash),
    KEY idx_push_user (user_id),
    CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
