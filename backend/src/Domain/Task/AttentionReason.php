<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\Task;

enum AttentionReason: string
{
    case CommentAttention = 'comment_attention';
    case Blocked = 'blocked';
    case Overdue = 'overdue';
    case Unassigned = 'unassigned';
}
