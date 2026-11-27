<?php

declare(strict_types=1);

namespace ProjectDesk\Http;

use ProjectDesk\Application\ProjectDeskUseCases;
use ProjectDesk\Domain\User\User;

final readonly class ProjectController extends BaseController
{
    public function __construct(private ProjectDeskUseCases $useCases)
    {
    }

    public function index(User $actor): void
    {
        $this->respond($this->useCases->listProjects($actor));
    }
}
