<?php

declare(strict_types=1);

namespace ProjectDesk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ProjectDesk\Database;
use RuntimeException;

final class DatabaseTest extends TestCase
{
    public function testReopeningSqliteKeepsExistingData(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'project-desk-');
        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary SQLite file.');
        }

        $previousPath = getenv('DB_PATH');
        putenv('DB_PATH=' . $path);
        $db = null;

        try {
            $db = Database::connect();
            $db->exec("UPDATE users SET name = 'Updated user' WHERE id = 1");
            $db = null;

            $db = Database::connect();
            $statement = $db->query('SELECT name FROM users WHERE id = 1');
            self::assertNotFalse($statement);
            self::assertSame('Updated user', $statement->fetchColumn());
            unset($statement);
        } finally {
            $db = null;
            putenv($previousPath === false ? 'DB_PATH' : 'DB_PATH=' . $previousPath);
            unlink($path);
        }
    }

    public function testMigrationPreservesCommentsFromVersionOne(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'project-desk-v1-');
        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary SQLite file.');
        }

        $previousPath = getenv('DB_PATH');
        putenv('DB_PATH=' . $path);
        $db = null;

        try {
            $db = Database::connect();
            $db->exec('DROP INDEX comments_pending_attention');
            $db->exec('ALTER TABLE comments DROP COLUMN attention');
            $db->exec("UPDATE metadata SET value = '1' WHERE key = 'schema_version'");
            $db = null;

            $db = Database::connect();
            $statement = $db->query("SELECT attention, body FROM comments WHERE id = 1");
            self::assertNotFalse($statement);
            $comment = $statement->fetch();
            self::assertIsArray($comment);
            self::assertSame('none', $comment['attention']);
            self::assertSame('Есть два варианта формата. Нужна помощь руководителя с выбором.', $comment['body']);
            $version = $db->query("SELECT value FROM metadata WHERE key = 'schema_version'");
            self::assertNotFalse($version);
            self::assertSame('2', $version->fetchColumn());
        } finally {
            unset($statement, $version);
            $db = null;
            putenv($previousPath === false ? 'DB_PATH' : 'DB_PATH=' . $previousPath);
            unlink($path);
        }
    }
}
