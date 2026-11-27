<?php

declare(strict_types=1);

namespace ProjectDesk\Domain\User;


final readonly class User
{
    public function __construct(public UserId $id, public string $name)
    {
    }
}
