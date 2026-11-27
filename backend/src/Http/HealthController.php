<?php

declare(strict_types=1);

namespace ProjectDesk\Http;

final readonly class HealthController extends BaseController
{
    public function show(): void
    {
        $this->respond(['status' => 'ok']);
    }
}
