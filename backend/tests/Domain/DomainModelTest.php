<?php

declare(strict_types=1);

namespace ProjectDesk\Tests\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectDesk\Domain\Exception\DomainException;
use ProjectDesk\Domain\Project\Project;
use ProjectDesk\Domain\Project\ProjectId;
use ProjectDesk\Domain\Project\ProjectMember;
use ProjectDesk\Domain\Task\AttentionReason;
use ProjectDesk\Domain\Task\Comment;
use ProjectDesk\Domain\Task\CommentBody;
use ProjectDesk\Domain\Task\CommentId;
use ProjectDesk\Domain\Task\DueAt;
use ProjectDesk\Domain\Task\Task;
use ProjectDesk\Domain\Task\TaskDescription;
use ProjectDesk\Domain\Task\TaskId;
use ProjectDesk\Domain\Task\TaskStatus;
use ProjectDesk\Domain\Task\TaskTitle;
use ProjectDesk\Domain\Task\TaskVersion;
use ProjectDesk\Domain\Task\UtcTimestamp;
use ProjectDesk\Domain\User\User;
use ProjectDesk\Domain\User\UserId;

final class DomainModelTest extends TestCase
{
    private const string NOW = '2026-11-11T08:00:00Z';

    public function testTaskKeepsItsIdentityAndIncrementsVersionOnRevision(): void
    {
        $project = self::project();
        $member = new User(new UserId(2), 'Борис');
        $now = new UtcTimestamp(self::NOW);
        $new = Task::create($project, $member->id, new TaskTitle('  Задача  '), $now);

        self::assertNull($new->id);
        self::assertSame(TaskStatus::Open, $new->status);
        self::assertSame(1, $new->version->value);
        self::assertSame('Задача', $new->title->value);
        self::assertNull($new->assigneeId);

        $task = self::savedTask($project);
        $updated = $task->revise(
            project: $project,
            actorId: $member->id,
            expectedVersion: new TaskVersion(1),
            title: $new->title,
            description: new TaskDescription('Уточнение'),
            status: TaskStatus::InProgress,
            assigneeId: $member->id,
            dueAt: new DueAt(new UtcTimestamp('2026-11-12T08:00:00Z')),
            updatedAt: $now,
        );

        self::assertSame($task->id, $updated->id);
        self::assertSame($task->projectId, $updated->projectId);
        self::assertSame(2, $updated->version->value);
        self::assertSame(1, $task->version->value);
        self::assertSame($member->id, $updated->assigneeId);
        self::assertSame('2026-11-12T08:00:00Z', $updated->dueAt?->timestamp->value);
    }

    /** @param list<TaskStatus> $targets */
    #[DataProvider('statusTransitions')]
    public function testStatusListsPermittedTargetsAndLabel(TaskStatus $status, array $targets, string $label): void
    {
        self::assertSame($targets, $status->allowTransactions());
        self::assertSame($label, $status->label());

        foreach (TaskStatus::cases() as $target) {
            self::assertSame(in_array($target, $targets, true), $status->canTransaction($target));
        }
    }

    /** @return iterable<string, array{TaskStatus, list<TaskStatus>, string}> */
    public static function statusTransitions(): iterable
    {
        yield 'open' => [TaskStatus::Open, [TaskStatus::InProgress], 'Открыта'];
        yield 'in progress' => [TaskStatus::InProgress, [TaskStatus::Open, TaskStatus::Blocked, TaskStatus::Done], 'В работе'];
        yield 'blocked' => [TaskStatus::Blocked, [TaskStatus::Open], 'Заблокирована'];
        yield 'done' => [TaskStatus::Done, [TaskStatus::Open], 'Готово'];
    }

    public function testRevisionCanKeepTheCurrentStatus(): void
    {
        $project = self::project();
        $task = self::savedTask($project);

        $updated = $task->revise(
            $project,
            new UserId(2),
            $task->version,
            new TaskTitle('Другое название'),
            $task->description,
            $task->status,
            null,
            null,
            $task->updatedAt,
        );

        self::assertSame($task->status, $updated->status);
        self::assertSame('Другое название', $updated->title->value);
    }

    public function testRevisionWithStaleVersionIsRejected(): void
    {
        $project = self::project();
        $task = self::savedTask($project);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Версия задачи устарела.');
        $task->revise(
            $project,
            new UserId(2),
            new TaskVersion(2),
            $task->title,
            $task->description,
            $task->status,
            null,
            null,
            $task->updatedAt,
        );
    }

    #[DataProvider('invalidRevisions')]
    public function testInvalidRevisionLeavesTheOriginalTaskIntact(string $scenario, string $message): void
    {
        $project = self::project();
        $now = new UtcTimestamp(self::NOW);
        $task = $scenario === 'unsaved'
            ? Task::create($project, new UserId(2), new TaskTitle('Задача'), $now)
            : self::savedTask($project);

        $writeProject = match ($scenario) {
            'archive' => self::project(true),
            'different project' => new Project(
                new ProjectId(9),
                'Другой проект',
                '',
                new UserId(2),
                false,
                [new ProjectMember(new ProjectId(9), new UserId(2))],
            ),
            default => $project,
        };

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage($message);
        try {
            $task->revise(
                project: $writeProject,
                actorId: new UserId($scenario === 'outsider' ? 9 : 2),
                expectedVersion: new TaskVersion(1),
                title: new TaskTitle('Изменённое название'),
                description: new TaskDescription('Изменённое описание'),
                status: TaskStatus::Done,
                assigneeId: $scenario === 'invalid assignee' ? new UserId(9) : null,
                dueAt: null,
                updatedAt: new UtcTimestamp('2026-11-12T09:00:00Z'),
            );
        } finally {
            self::assertSame('Задача', $task->title->value);
            self::assertSame(TaskStatus::Open, $task->status);
            self::assertSame(1, $task->version->value);
            self::assertSame(self::NOW, $task->updatedAt->value);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidRevisions(): iterable
    {
        yield 'unsaved' => ['unsaved', 'Сначала сохраните задачу.'];
        yield 'archived' => ['archive', 'Архивный проект доступен только для чтения.'];
        yield 'wrong project' => ['different project', 'Задача относится к другому проекту.'];
        yield 'non-member' => ['outsider', 'Пользователь не состоит в проекте.'];
        yield 'non-member assignee' => ['invalid assignee', 'Исполнитель должен быть участником проекта.'];
        yield 'cannot skip in progress' => ['invalid transition', 'Переход к выбранному статусу задачи недоступен.'];
    }

    public function testRevisionCanClearAssigneeAndDeadlineOnRestoredTask(): void
    {
        $project = self::project();
        $dueAt = new DueAt(new UtcTimestamp('2026-12-01T12:30:01Z'));
        $task = Task::restore(
            new TaskId(17),
            new ProjectId($project->id->value),
            new TaskTitle('Задача'),
            new TaskDescription('Описание'),
            TaskStatus::InProgress,
            new UserId(2),
            $dueAt,
            new UtcTimestamp(self::NOW),
            new TaskVersion(7),
        );
        $updatedAt = new UtcTimestamp('2026-11-12T09:00:00Z');

        $updated = $task->revise(
            $project,
            new UserId(2),
            new TaskVersion(7),
            $task->title,
            $task->description,
            TaskStatus::Blocked,
            null,
            null,
            $updatedAt,
        );

        self::assertSame(17, $updated->id?->value);
        self::assertSame(8, $updated->version->value);
        self::assertSame(TaskStatus::Blocked, $updated->status);
        self::assertSame($updatedAt, $updated->updatedAt);
        self::assertNull($updated->assigneeId);
        self::assertNull($updated->dueAt);
        self::assertSame(2, $task->assigneeId?->value);
        self::assertSame($dueAt, $task->dueAt);
    }

    public function testTaskCannotBeAssignedToAnOutsider(): void
    {
        $this->expectException(DomainException::class);
        Task::create(
            self::project(),
            new UserId(2),
            new TaskTitle('Задача'),
            new UtcTimestamp(self::NOW),
            assigneeId: new UserId(3),
        );
    }

    public function testOwnershipAloneDoesNotGrantWriteAccess(): void
    {
        $this->expectException(DomainException::class);
        Task::create(self::project(), new UserId(1), new TaskTitle('Задача'), new UtcTimestamp(self::NOW));
    }

    public function testArchivedProjectRejectsTaskCreation(): void
    {
        $this->expectException(DomainException::class);
        Task::create(self::project(true), new UserId(2), new TaskTitle('Задача'), new UtcTimestamp(self::NOW));
    }

    public function testArchivedProjectPreservesMembersAndOwner(): void
    {
        $project = self::project(true);
        $user = new User(new UserId(2), 'Борис');

        self::assertSame('Борис', $user->name);
        self::assertTrue($project->archived);
        self::assertSame(1, $project->ownerId->value);
        self::assertTrue($project->hasMember($user->id));
        self::assertFalse($project->hasMember(new UserId(1)));
        self::assertEquals(
            [new ProjectMember(new ProjectId(1), new UserId(2))],
            $project->members(),
        );
    }

    public function testProjectRejectsDuplicateMembership(): void
    {
        $id = new ProjectId(1);
        $this->expectException(DomainException::class);
        new Project($id, 'Проект', '', new UserId(1), false, [
            new ProjectMember($id, new UserId(2)),
            new ProjectMember($id, new UserId(2)),
        ]);
    }

    public function testProjectRejectsMembershipFromAnotherProject(): void
    {
        $this->expectException(DomainException::class);
        new Project(new ProjectId(1), 'Проект', '', new UserId(1), false, [
            new ProjectMember(new ProjectId(2), new UserId(2)),
        ]);
    }

    public function testCommentHasItsOwnIdentityAndDoesNotChangeTaskVersion(): void
    {
        $project = self::project();
        $task = self::savedTask($project);
        $author = new UserId(2);
        $now = new UtcTimestamp(self::NOW);
        $comment = Comment::add($task, $project, $author, new CommentBody('  Ответ  '), $now);

        self::assertNull($comment->id);
        self::assertSame($task->id, $comment->taskId);
        self::assertSame($author, $comment->userId);
        self::assertSame('Ответ', $comment->body->value);
        self::assertSame(1, $task->version->value);
        self::assertSame(8, Comment::restore(new CommentId(8), $comment->taskId, $author, $comment->body, $now)->id?->value);
    }

    public function testOutsiderCannotAddCommentToAnActiveProject(): void
    {
        $project = self::project();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Пользователь не состоит в проекте.');
        Comment::add(
            self::savedTask($project),
            $project,
            new UserId(9),
            new CommentBody('Ответ'),
            new UtcTimestamp(self::NOW),
        );
    }

    public function testRestoredCommentPreservesAuthorAndServerTimestamp(): void
    {
        $taskId = new TaskId(17);
        $authorId = new UserId(9);
        $body = new CommentBody('Первоначальный комментарий');
        $createdAt = new UtcTimestamp(self::NOW);

        $comment = Comment::restore(new CommentId(8), $taskId, $authorId, $body, $createdAt);

        self::assertSame(8, $comment->id?->value);
        self::assertSame($taskId, $comment->taskId);
        self::assertSame($authorId, $comment->userId);
        self::assertSame($body, $comment->body);
        self::assertSame($createdAt, $comment->createdAt);
    }

    public function testCommentCannotBeAddedUsingAnotherProject(): void
    {
        $this->expectException(DomainException::class);
        $project = new Project(new ProjectId(2), 'Другой проект', '', new UserId(2), false, [
            new ProjectMember(new ProjectId(2), new UserId(2)),
        ]);

        Comment::add(self::savedTask(self::project()), $project, new UserId(2), new CommentBody('Ответ'), new UtcTimestamp(self::NOW));
    }

    public function testArchivedTaskCanBeRestoredButRejectsNewComment(): void
    {
        $project = self::project(true);
        $task = self::savedTask($project);

        $this->expectException(DomainException::class);
        Comment::add($task, $project, new UserId(2), new CommentBody('Ответ'), new UtcTimestamp(self::NOW));
    }

    public function testCommentCannotBeAddedBeforeTaskIsSaved(): void
    {
        $project = self::project();
        $member = new UserId(2);
        $now = new UtcTimestamp(self::NOW);
        $task = Task::create($project, $member, new TaskTitle('Задача'), $now);

        $this->expectException(DomainException::class);
        Comment::add($task, $project, $member, new CommentBody('Ответ'), $now);
    }

    public function testAcceptsExactUtf8ByteLimitsAndStrictUtcValues(): void
    {
        $title = str_repeat('я', 100);
        $description = str_repeat('я', 5000);
        $body = str_repeat('я', 2500);

        self::assertSame($title, new TaskTitle($title)->value);
        self::assertEquals(new TaskTitle('Задача'), new TaskTitle(' Задача '));
        self::assertSame($description, new TaskDescription($description)->value);
        self::assertSame($body, new CommentBody($body)->value);
        self::assertSame(self::NOW, new UtcTimestamp(self::NOW)->value);
        self::assertSame(TaskStatus::Blocked, TaskStatus::fromCode('blocked'));
        self::assertSame(
            ['comment_attention', 'blocked', 'overdue', 'unassigned'],
            array_map(static fn (AttentionReason $reason): string => $reason->value, AttentionReason::cases()),
        );
        self::assertSame('', new TaskDescription('   ')->value);
        self::assertSame('2028-02-29T23:59:59Z', new UtcTimestamp('2028-02-29T23:59:59Z')->value);
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValueThrowsDomainException(callable $create): void
    {
        $this->expectException(DomainException::class);
        $create();
    }

    /** @return iterable<string, array{callable(): object}> */
    public static function invalidValues(): iterable
    {
        yield 'user id' => [static fn () => new UserId(0)];
        yield 'project id' => [static fn () => new ProjectId(-1)];
        yield 'task id' => [static fn () => new TaskId(0)];
        yield 'comment id' => [static fn () => new CommentId(-1)];
        yield 'version' => [static fn () => new TaskVersion(0)];
        yield 'version overflow' => [static fn () => new TaskVersion(PHP_INT_MAX)->next()];
        yield 'empty title' => [static fn () => new TaskTitle('   ')];
        yield 'title bytes before trimming' => [static fn () => new TaskTitle(str_repeat('я', 100) . ' ')];
        yield 'invalid UTF-8' => [static fn () => new TaskTitle("\xFF")];
        yield 'description bytes' => [static fn () => new TaskDescription(str_repeat('я', 5001))];
        yield 'empty comment' => [static fn () => new CommentBody(" \n ")];
        yield 'comment bytes' => [static fn () => new CommentBody(str_repeat('я', 2501))];
        yield 'invalid date' => [static fn () => new UtcTimestamp('2026-02-30T08:00:00Z')];
        yield 'offset instead of UTC format' => [static fn () => new UtcTimestamp('2026-11-11T08:00:00+00:00')];
        yield 'invalid timestamp contents' => [static fn () => new UtcTimestamp(self::NOW . "\0")];
        yield 'status' => [static fn () => TaskStatus::fromCode('unknown')];
    }

    private static function project(bool $archived = false): Project
    {
        $id = new ProjectId(1);
        return new Project($id, 'Проект', '', new UserId(1), $archived, [
            new ProjectMember($id, new UserId(2)),
        ]);
    }

    private static function savedTask(Project $project): Task
    {
        return Task::restore(
            new TaskId(1),
            $project->id,
            new TaskTitle('Задача'),
            new TaskDescription(''),
            TaskStatus::Open,
            null,
            null,
            new UtcTimestamp(self::NOW),
            new TaskVersion(1),
        );
    }
}
