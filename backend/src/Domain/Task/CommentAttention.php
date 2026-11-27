<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

enum CommentAttention: string
{
    case None = 'none';
    case Requested = 'requested';
    case Resolved = 'resolved';
}
