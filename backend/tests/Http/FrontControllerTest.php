<?php

declare(strict_types=1);

namespace ProjectDesk\Tests\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ProjectDesk\Http\AttentionController;
use ProjectDesk\Http\CommentController;
use ProjectDesk\Http\ContextController;
use ProjectDesk\Http\ControllerFactory;
use ProjectDesk\Http\FrontController;
use ProjectDesk\Http\HealthController;
use ProjectDesk\Http\ProjectController;
use ProjectDesk\Http\Router;
use ProjectDesk\Http\TaskController;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FrontControllerTest extends TestCase
{
    #[DataProvider('routes')]
    public function testLoadsOnlyTheMatchedController(string $path, string $controller, string $response): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'project-desk-http-');
        if ($databasePath === false) {
            throw new RuntimeException('Cannot create a temporary SQLite file.');
        }

        putenv('DB_PATH=' . $databasePath);
        $_SERVER['HTTP_X_DEMO_USER'] = '2';

        try {
            $front = new FrontController(new Router(), new ControllerFactory());
            $this->expectOutputString($response);
            $front->dispatch('GET', $path);

            foreach ([
                AttentionController::class,
                CommentController::class,
                ContextController::class,
                HealthController::class,
                ProjectController::class,
                TaskController::class,
            ] as $candidate) {
                self::assertSame($candidate === $controller, class_exists($candidate, false), $candidate);
            }
        } finally {
            unset($front);
            putenv('DB_PATH');
            unlink($databasePath);
        }
    }

    /** @return iterable<string, array{string, class-string, string}> */
    public static function routes(): iterable
    {
        yield 'anonymous health' => ['/api/health', HealthController::class, '{"data":{"status":"ok"}}'];
        yield 'authenticated attention' => ['/api/attention', AttentionController::class, '{"data":[]}'];
    }
}
