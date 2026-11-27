<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Project;

use ProjectDesk\Domain\User\UserId;

final readonly class ProjectMember
{
    public function __construct(public ProjectId $projectId, public UserId $userId)
    {
    }
}
