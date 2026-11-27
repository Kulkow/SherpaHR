<?php

declare(strict_types=1);

namespace ProjectDesk\Http;

use LogicException;
use ProjectDesk\ApiError;
use ProjectDesk\Domain\User\User;
use ProjectDesk\Domain\User\UserId;

final readonly class FrontController
{
    public function __construct(
        private Router $router,
        private ControllerFactory $controllers,
    ) {
    }

    public function dispatch(string $method, string $path): void
    {
        $route = $this->router->match($method, $path);
        if ($route !== null && $route['action'] === 'health') {
            $this->controllers->health()->show();
            return;
        }

        $actor = $this->actor();
        if ($route === null) {
            throw new ApiError('Маршрут не найден', 404);
        }

        $parameters = $route['parameters'];
        match ($route['action']) {
            'context' => $this->controllers->context()->show($actor),
            'projects' => $this->controllers->projects()->index($actor),
            'attention' => $this->controllers->attention()->index($actor),
            'listTasks' => $this->controllers->tasks()->index($actor, self::parameter($parameters, 'projectId')),
            'createTask' => $this->controllers->tasks()->create($actor, self::parameter($parameters, 'projectId')),
            'showTask' => $this->controllers->tasks()->show($actor, self::parameter($parameters, 'taskId')),
            'updateTask' => $this->controllers->tasks()->update($actor, self::parameter($parameters, 'taskId')),
            'createComment' => $this->controllers->comments()->create($actor, self::parameter($parameters, 'taskId')),
            'resolveComment' => $this->controllers->comments()->resolve(
                $actor,
                self::parameter($parameters, 'taskId'),
                self::parameter($parameters, 'commentId'),
            ),
            default => throw new LogicException('Missing controller action for the matched route.'),
        };
    }

    private function actor(): User
    {
        $header = $_SERVER['HTTP_X_DEMO_USER'] ?? '1';
        $id = is_string($header) ? Router::positiveId($header) : null;
        if ($id === null) {
            throw new ApiError('Неизвестный демо-пользователь', 401);
        }

        return $this->controllers->users()->find(new UserId($id))
            ?? throw new ApiError('Неизвестный демо-пользователь', 401);
    }

    /** @param array<string, string> $parameters */
    private static function parameter(array $parameters, string $name): string
    {
        return $parameters[$name] ?? throw new LogicException("Missing route parameter: {$name}.");
    }
}
