<?php

declare(strict_types=1);

namespace ProjectDesk\Tests\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectDesk\Http\Router;

final class RouterTest extends TestCase
{
    /** @param array<string, string> $parameters */
    #[DataProvider('apiRoutes')]
    public function testMatchesApiRoutes(string $method, string $path, string $action, array $parameters): void
    {
        self::assertSame(['action' => $action, 'parameters' => $parameters], new Router()->match($method, $path));
    }

    /** @return iterable<string, array{string, string, string, array<string, string>}> */
    public static function apiRoutes(): iterable
    {
        yield 'health' => ['GET', '/api/health', 'health', []];
        yield 'context' => ['GET', '/api/context', 'context', []];
        yield 'overview' => ['GET', '/api/attention', 'attention', []];
        yield 'projects' => ['GET', '/api/projects', 'projects', []];
        yield 'task list' => ['GET', '/api/projects/4/tasks', 'listTasks', ['projectId' => '4']];
        yield 'create task' => ['POST', '/api/projects/4/tasks', 'createTask', ['projectId' => '4']];
        yield 'task detail' => ['GET', '/api/tasks/002', 'showTask', ['taskId' => '002']];
        yield 'update task' => ['PATCH', '/api/tasks/2', 'updateTask', ['taskId' => '2']];
        yield 'add comment' => ['POST', '/api/tasks/2/comments', 'createComment', ['taskId' => '2']];
        yield 'resolve comment' => ['POST', '/api/tasks/2/comments/7/resolve', 'resolveComment', [
            'taskId' => '2', 'commentId' => '7',
        ]];
    }

    #[DataProvider('unmatchedRequests')]
    public function testRejectsNonMatchingRoutes(string $method, string $path): void
    {
        self::assertNull(new Router()->match($method, $path));
    }

    /** @return iterable<string, array{string, string}> */
    public static function unmatchedRequests(): iterable
    {
        yield 'unsupported method' => ['DELETE', '/api/tasks/2'];
        yield 'wrong method for health' => ['POST', '/api/health'];
        yield 'wrong method for comment resolution' => ['GET', '/api/tasks/2/comments/7/resolve'];
        yield 'unknown path' => ['GET', '/api/unknown'];
        yield 'non-numeric ID' => ['GET', '/api/tasks/abc'];
        yield 'negative ID' => ['GET', '/api/tasks/-2'];
        yield 'encoded ID' => ['GET', '/api/tasks/%32'];
        yield 'trailing slash' => ['GET', '/api/projects/4/tasks/'];
        yield 'trailing newline' => ['GET', "/api/tasks/2\n"];
        yield 'frontend path' => ['GET', '/'];
    }

    public function testSeparatesApiPathsFromFrontendPaths(): void
    {
        $router = new Router();

        self::assertTrue($router->isApiPath('/api'));
        self::assertTrue($router->isApiPath('/api/unknown'));
        self::assertFalse($router->isApiPath('/apiculture'));
        self::assertFalse($router->isApiPath('/'));
    }

    public function testExtractsPathWithoutQueryString(): void
    {
        $router = new Router();
        $path = $router->pathFromUri('/api/projects/4/tasks?status=done');

        self::assertSame('/api/projects/4/tasks', $path);
        self::assertSame('listTasks', $router->match('GET', $path)['action'] ?? null);
        self::assertSame('', $router->pathFromUri('?status=done'));
    }
}
