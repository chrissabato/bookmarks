<?php
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . __DIR__ . '/bookmarks.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    return $pdo;
}

function init_schema(): void {
    $pdo = get_db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS page_sets (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            slug     TEXT    NOT NULL UNIQUE,
            title    TEXT    NOT NULL,
            position INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            page_set_id INTEGER NOT NULL REFERENCES page_sets(id) ON DELETE CASCADE,
            name        TEXT    NOT NULL,
            position    INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS bookmarks (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            label       TEXT    NOT NULL,
            url         TEXT    NOT NULL,
            position    INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_categories_set ON categories(page_set_id, position);
        CREATE INDEX IF NOT EXISTS idx_bookmarks_cat  ON bookmarks(category_id, position);
    ");
    // Add icon column to existing databases
    $cols = array_column($pdo->query("PRAGMA table_info(bookmarks)")->fetchAll(), 'name');
    if (!in_array('icon', $cols)) {
        $pdo->exec("ALTER TABLE bookmarks ADD COLUMN icon TEXT DEFAULT NULL");
    }
}
