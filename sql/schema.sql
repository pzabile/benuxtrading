-- BENUX Trading Dashboard - Database Schema
-- Compatible with MySQL / MariaDB (Hostinger)
-- Run this once on your database before first use, OR use install.php

CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(64)  NOT NULL UNIQUE,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    starting_balance DECIMAL(14,2) NOT NULL DEFAULT 10000.00,
    timezone        VARCHAR(64)  NOT NULL DEFAULT 'UTC',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trades (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    pair            VARCHAR(16)  NOT NULL,
    direction       ENUM('BUY','SELL') NOT NULL,
    lot_size        DECIMAL(10,4) NOT NULL DEFAULT 0.0100,
    entry_price     DECIMAL(14,5) NOT NULL,
    exit_price      DECIMAL(14,5) DEFAULT NULL,
    stop_loss       DECIMAL(14,5) DEFAULT NULL,
    take_profit     DECIMAL(14,5) DEFAULT NULL,
    pnl             DECIMAL(14,2) DEFAULT NULL,
    pnl_pips        DECIMAL(10,2) DEFAULT NULL,
    risk_pct        DECIMAL(6,3)  DEFAULT NULL,
    rr_planned      DECIMAL(6,2)  DEFAULT NULL,
    rr_actual       DECIMAL(6,2)  DEFAULT NULL,
    fees            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    outcome         ENUM('OPEN','TP','SL','BE','MANUAL') NOT NULL DEFAULT 'OPEN',
    session         ENUM('ASIAN','LONDON','NEWYORK','OVERLAP','OTHER') DEFAULT 'OTHER',
    strategy        VARCHAR(120) DEFAULT NULL,
    setup           VARCHAR(255) DEFAULT NULL,
    emotion         TINYINT      DEFAULT NULL,           -- 1..5 (calm..anxious)
    confidence      TINYINT      DEFAULT NULL,           -- 1..5
    tags            VARCHAR(255) DEFAULT NULL,
    notes           TEXT         DEFAULT NULL,
    mistakes        TEXT         DEFAULT NULL,
    open_time       DATETIME     NOT NULL,
    close_time      DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_open (user_id, open_time),
    INDEX idx_user_pair (user_id, pair),
    CONSTRAINT fk_trade_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS screenshots (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trade_id        INT UNSIGNED NOT NULL,
    file_path       VARCHAR(255) DEFAULT NULL,           -- relative path under /uploads
    url             VARCHAR(500) DEFAULT NULL,           -- external link (TradingView etc.)
    caption         VARCHAR(255) DEFAULT NULL,
    kind            ENUM('ENTRY','DURING','EXIT','OTHER') NOT NULL DEFAULT 'OTHER',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_trade (trade_id),
    CONSTRAINT fk_shot_trade FOREIGN KEY (trade_id) REFERENCES trades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
