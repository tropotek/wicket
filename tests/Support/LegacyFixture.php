<?php

namespace Tests\Support;

use PDO;

/**
 * A small tkwiki database in an SQLite file, shaped like the real (MariaDB) schema the importer
 * reads. Tests import from it with --from=sqlite:///path.
 */
class LegacyFixture
{
    public readonly string $path;

    public readonly PDO $pdo;

    public function __construct()
    {
        $this->path = storage_path('framework/testing/legacy-'.uniqid().'.sqlite');
        @mkdir(dirname($this->path), 0775, true);
        $this->pdo = new PDO('sqlite:'.$this->path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->schema();
    }

    public function dsn(): string
    {
        return 'sqlite://'.$this->path;
    }

    public function drop(): void
    {
        @unlink($this->path);
    }

    /** Insert a row; returns its id. */
    public function insert(string $table, array $row): int
    {
        $cols = array_keys($row);
        $sql = sprintf('INSERT INTO "%s" (%s) VALUES (%s)', $table,
            implode(', ', array_map(fn ($c) => '"'.$c.'"', $cols)),
            implode(', ', array_fill(0, count($cols), '?')));
        $this->pdo->prepare($sql)->execute(array_values($row));

        return (int) $this->pdo->lastInsertId();
    }

    private function schema(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE auth (auth_id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT DEFAULT '', fkey TEXT DEFAULT '', fid INTEGER DEFAULT 0,
                permissions INTEGER DEFAULT 0, username TEXT DEFAULT '', password TEXT DEFAULT '', email TEXT DEFAULT '',
                timezone TEXT DEFAULT '', active INTEGER DEFAULT 1, external TEXT DEFAULT '', session_id TEXT DEFAULT '',
                last_login TEXT NULL, modified TEXT DEFAULT CURRENT_TIMESTAMP, created TEXT DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE user (user_id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT DEFAULT '', title TEXT DEFAULT '',
                given_name TEXT DEFAULT '', family_name TEXT DEFAULT '', phone TEXT DEFAULT '', address TEXT DEFAULT '',
                city TEXT DEFAULT '', state TEXT DEFAULT '', postcode TEXT DEFAULT '', country TEXT DEFAULT '',
                modified TEXT DEFAULT CURRENT_TIMESTAMP, created TEXT DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE page (page_id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, template TEXT DEFAULT '',
                category TEXT DEFAULT '', title TEXT DEFAULT '', url TEXT NULL, views INTEGER DEFAULT 0, permission INTEGER DEFAULT 0,
                title_visible INTEGER DEFAULT 1, publish INTEGER DEFAULT 1, modified TEXT DEFAULT CURRENT_TIMESTAMP, created TEXT DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE content (content_id INTEGER PRIMARY KEY AUTOINCREMENT, page_id INTEGER DEFAULT 0, user_id INTEGER NULL,
                html TEXT NOT NULL DEFAULT '', keywords TEXT DEFAULT '', description TEXT DEFAULT '', css TEXT, js TEXT,
                created TEXT DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE links (page_id INTEGER DEFAULT 0, linked_id INTEGER DEFAULT 0, PRIMARY KEY (page_id, linked_id));
            CREATE TABLE menu_item (menu_item_id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INTEGER NULL, page_id INTEGER NULL,
                order_id INTEGER DEFAULT 0, type TEXT DEFAULT 'item', name TEXT DEFAULT '');
            CREATE TABLE secret (secret_id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER DEFAULT 0, permission INTEGER DEFAULT 0,
                name TEXT DEFAULT '', url TEXT DEFAULT '', username TEXT DEFAULT '', password TEXT DEFAULT '', otp TEXT DEFAULT '',
                publish INTEGER DEFAULT 1, "keys" TEXT NULL, notes TEXT NULL, modified TEXT DEFAULT CURRENT_TIMESTAMP, created TEXT DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE registry ("key" TEXT PRIMARY KEY, value TEXT);
        SQL);
    }
}
