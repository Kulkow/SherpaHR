<?php

declare(strict_types=1);

namespace ProjectDesk\Http;

use ProjectDesk\Application\AttentionOverview;
use ProjectDesk\Domain\Task\UtcTimestamp;
use ProjectDesk\Domain\User\User;

final readonly class AttentionController extends BaseController
{
    public function __construct(private AttentionOverview $overview)
    {
    }

    public function index(User $actor): void
    {
        $this->respond($this->overview->forUser($actor, new UtcTimestamp(gmdate('Y-m-d\TH:i:s\Z'))));
    }
}
