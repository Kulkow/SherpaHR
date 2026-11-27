<?php

declare(strict_types=1);

namespace ProjectDesk\Http;

final class Router
{
    private const array ROUTES = [
        'GET' => [
            '~\A/api/health\z~' => 'health',
            '~\A/api/context\z~' => 'context',
            '~\A/api/projects\z~' => 'projects',
            '~\A/api/attention\z~' => 'attention',
            '~\A/api/projects/(?<projectId>[0-9]+)/tasks\z~' => 'listTasks',
            '~\A/api/tasks/(?<taskId>[0-9]+)\z~' => 'showTask',
        ],
        'POST' => [
            '~\A/api/projects/(?<projectId>[0-9]+)/tasks\z~' => 'createTask',
            '~\A/api/tasks/(?<taskId>[0-9]+)/comments\z~' => 'createComment',
            '~\A/api/tasks/(?<taskId>[0-9]+)/comments/(?<commentId>[0-9]+)/resolve\z~' => 'resolveComment',
        ],
        'PATCH' => [
            '~\A/api/tasks/(?<taskId>[0-9]+)\z~' => 'updateTask',
        ],
    ];

    public function pathFromUri(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }

    public function isApiPath(string $path): bool
    {
        return $path === '/api' || str_starts_with($path, '/api/');
    }

    public static function positiveId(string $value): ?int
    {
        if (!ctype_digit($value)) {
            return null;
        }
        $id = filter_var(ltrim($value, '0'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($id) ? $id : null;
    }

    /** @return array{action: string, parameters: array<string, string>}|null */
    public function match(string $method, string $path): ?array
    {
        foreach (self::ROUTES[$method] ?? [] as $pattern => $action) {
            if (preg_match($pattern, $path, $matches) !== 1) {
                continue;
            }

            $parameters = [];
            foreach ($matches as $name => $value) {
                if (is_string($name)) {
                    $parameters[$name] = $value;
                }
            }

            return ['action' => $action, 'parameters' => $parameters];
        }

        return null;
    }
}
