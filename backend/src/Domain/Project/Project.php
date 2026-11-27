<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Project;

use ProjectDesk\Domain\Exception\DomainException;
use ProjectDesk\Domain\User\UserId;

final readonly class Project
{
    /** @var array<int, ProjectMember> */
    private array $membersByUserId;

    /** @param list<ProjectMember> $members */
    public function __construct(
        public ProjectId $id,
        public string $name,
        public string $description,
        public UserId $ownerId,
        public bool $archived,
        array $members,
    ) {
        $membersByUserId = [];
        foreach ($members as $member) {
            if ($member->projectId->value !== $id->value) {
                throw new DomainException('Участник относится к другому проекту.');
            }
            if (isset($membersByUserId[$member->userId->value])) {
                throw new DomainException('Пользователь уже состоит в проекте.');
            }
            $membersByUserId[$member->userId->value] = $member;
        }

        $this->membersByUserId = $membersByUserId;
    }

    /** @return list<ProjectMember> */
    public function members(): array
    {
        return array_values($this->membersByUserId);
    }

    public function hasMember(UserId $userId): bool
    {
        return isset($this->membersByUserId[$userId->value]);
    }

    public function assertWritableBy(UserId $actorId): void
    {
        if (!$this->hasMember($actorId)) {
            throw new DomainException('Пользователь не состоит в проекте.');
        }
        if ($this->archived) {
            throw new DomainException('Архивный проект доступен только для чтения.');
        }
    }

    public function assertAllowsAssignee(?UserId $assigneeId): void
    {
        if ($assigneeId !== null && !$this->hasMember($assigneeId)) {
            throw new DomainException('Исполнитель должен быть участником проекта.');
        }
    }
}
