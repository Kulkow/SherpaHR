<?php
declare(strict_types=1);

namespace ProjectDesk;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    public static function connect(): PDO
    {
        $path = getenv('DB_PATH') ?: __DIR__ . '/../../var/project-desk.sqlite';
        if ($path !== ':memory:' && !is_dir(dirname($path))) {
            mkdir(dirname($path), 0770, true);
        }
        $db = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000');
        self::initialize($db);
        return $db;
    }

    private static function initialize(PDO $db): void
    {
        if (self::schemaVersion($db) === '2') {
            return;
        }

        $db->exec('BEGIN IMMEDIATE');
        try {
            $version = self::schemaVersion($db);
            if ($version === null) {
                $db->exec('CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
                self::executeSqlFile($db, __DIR__ . '/../schema.sql');
                self::seed($db);
            } elseif ($version === '1') {
                self::executeSqlFile($db, __DIR__ . '/../migrations/002-comment-attention.sql');
                $db->exec("UPDATE metadata SET value = '2' WHERE key = 'schema_version'");
            } elseif ($version !== '2') {
                throw new RuntimeException('Unsupported SQLite schema version.');
            }
            $db->exec('COMMIT');
        } catch (Throwable $error) {
            $db->exec('ROLLBACK');
            throw $error;
        }
    }

    private static function schemaVersion(PDO $db): ?string
    {
        $metadata = $db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'metadata'");
        if ($metadata === false || $metadata->fetchColumn() === false) {
            return null;
        }

        $statement = $db->query("SELECT value FROM metadata WHERE key = 'schema_version'");
        $version = $statement === false ? false : $statement->fetchColumn();
        if (!is_string($version)) {
            throw new RuntimeException('Missing SQLite schema version.');
        }

        return $version;
    }

    private static function executeSqlFile(PDO $db, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Cannot read SQL schema file.');
        }

        $db->exec($sql);
    }

    private static function seed(PDO $db): void
    {
        $now = new DateTimeImmutable(getenv('SEED_NOW') ?: 'now', new DateTimeZone('UTC'));
        $stamp = fn(string $offset) => $now->modify($offset)->format('Y-m-d\TH:i:s\Z');
        $db->exec("INSERT INTO users VALUES (1, 'Анна · руководитель'), (2, 'Борис · разработчик'), (3, 'Вера · аналитик')");
        $project = $db->prepare('INSERT INTO projects VALUES (?, ?, ?, ?, ?)');
        foreach ([
            [1, 'Запуск клиентского портала', 'Личный кабинет для клиентов: заявки и документы.', 1, 0],
            [2, 'Автоматизация отчётности', 'Еженедельные отчёты для финансового отдела.', 1, 0],
            [3, 'Обновление базы знаний', 'Инструкции для новой линии поддержки.', 1, 0],
            [4, 'Архив: осенний вебинар', 'Завершённый проект с сохранённой историей.', 1, 1],
            [5, 'Внутренний проект аналитики', 'Отдельная рабочая группа.', 3, 0],
        ] as $row) {
            $project->execute($row);
        }
        $db->exec('INSERT INTO project_members VALUES (1,1),(1,2),(1,3),(2,1),(2,2),(3,1),(3,3),(4,1),(4,2),(5,3)');
        $task = $db->prepare('INSERT INTO tasks (id, project_id, title, description, status, assignee_id, due_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $rows = [
            [1, 1, 'Согласовать схему обмена', 'Нужно решение по формату документов до подключения клиента.', 'blocked', 2, '+1 day', '-2 days'],
            [2, 1, 'Проверить загрузку документов', 'Проверить типовые документы в личном кабинете.', 'in_progress', 3, '-1 day', '-3 hours'],
            [3, 1, 'Обновить приветственное письмо', 'Согласованный текст уже передан в поддержку.', 'done', 2, '-4 days', '-2 days'],
            [4, 1, 'Подготовить демонстрацию', 'Собрать пользовательский сценарий для встречи.', 'open', 1, '+5 days', '-1 day'],
            [5, 2, 'Получить доступ к источнику', 'Финансовый отдел ждёт решение владельца источника.', 'blocked', 2, '-2 days', '-4 days'],
            [6, 2, 'Проверить сверку итогов', 'Сравнить агрегаты с утверждённым примером.', 'in_progress', 2, '+3 days', '-2 hours'],
            [7, 2, 'Определить владельца справочника', 'Справочник используется двумя командами.', 'open', null, null, '-3 days'],
            [8, 3, 'Опубликовать инструкцию оператора', 'Текст вычитан, публикация запланирована.', 'in_progress', 3, '+7 days', '-1 hour'],
            [9, 3, 'Согласовать структуру разделов', 'Новая структура принята командой поддержки.', 'done', 1, '-3 days', '-2 days'],
            [10, 4, 'Закрыть старые согласования', 'Историческая задача архивного проекта.', 'blocked', 2, '-30 days', '-31 days'],
            [11, 5, 'Подготовить внутреннюю выборку', 'Данные доступны только участникам проекта.', 'blocked', 3, '-1 day', '-2 days'],
            [12, 1, 'Запланировать проверку доступности', 'Обсудить дату с командой.', 'open', 2, null, '-1 hour'],
        ];
        foreach ($rows as $row) {
            $row[6] = $row[6] === null ? null : $stamp($row[6]);
            $row[7] = $stamp($row[7]);
            $task->execute($row);
        }
        $comment = $db->prepare(<<<'SQL'
            INSERT INTO comments (task_id, user_id, body, created_at, attention)
            VALUES (
                :task_id, :user_id, :body, :created_at,
                CASE (SELECT status FROM tasks WHERE id = :task_id)
                    WHEN 'blocked' THEN 'requested'
                    ELSE 'none'
                END
            )
            SQL);
        foreach ([
            [1, 2, 'Есть два варианта формата. Нужна помощь руководителя с выбором.', '-1 day'],
            [1, 3, 'Клиент может прислать пример завтра утром.', '-4 hours'],
            [2, 3, 'Осталось проверить большие документы; закончу сегодня.', '-2 hours'],
            [5, 2, 'Доступ запросил три дня назад. Пока ответа нет, без доступа не продолжить.', '-3 days'],
            [6, 2, 'Сверка идёт по плану, расхождений пока нет.', '-1 hour'],
            [7, 2, 'Не можем продолжить без владельца справочника. Анна, помоги определить ответственного между командами.', '-2 days'],
            [8, 3, 'Макет согласован, препятствий нет.', '-1 hour'],
            [10, 2, 'Сохраняем историю, проект уже закрыт.', '-29 days'],
        ] as [$taskId, $userId, $body, $offset]) {
            $comment->execute([
                'task_id' => $taskId,
                'user_id' => $userId,
                'body' => $body,
                'created_at' => $stamp($offset),
            ]);
        }
        $meta = $db->prepare('INSERT INTO metadata VALUES (?, ?)');
        $meta->execute(['schema_version', '2']);
        $meta->execute(['seeded_at', $stamp('+0 seconds')]);
    }
}
