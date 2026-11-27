<?php

declare(strict_types=1);

namespace ProjectDesk\Http;

use ProjectDesk\Application\ProjectDeskUseCases;
use ProjectDesk\Domain\User\User;

final readonly class ContextController extends BaseController
{
    public function __construct(private ProjectDeskUseCases $useCases)
    {
    }

    public function show(User $actor): void
    {
        $this->respond($this->useCases->context($actor));
    }
}
