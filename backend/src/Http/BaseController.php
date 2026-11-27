<?php

declare(strict_types=1);

namespace ProjectDesk\Http;

use JsonException;
use ProjectDesk\ApiError;
use ProjectDesk\Application\UseCaseException;
use ProjectDesk\Domain\Exception\DomainException;
use RuntimeException;
use Throwable;

abstract readonly class BaseController
{
    private const int MAX_BODY_BYTES = 32768;

    /** @param callable(): void $action */
    final public static function handle(callable $action): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        try {
            $action();
        } catch (ApiError $error) {
            self::error($error->getMessage(), $error->getCode());
        } catch (UseCaseException $error) {
            self::error($error->getMessage(), match ($error->getCode()) {
                UseCaseException::NotFound => 404,
                UseCaseException::Forbidden => 403,
                default => 409,
            });
        } catch (DomainException $error) {
            self::error($error->getMessage(), 422);
        } catch (Throwable $error) {
            error_log((string) $error);
            self::error('Внутренняя ошибка сервера', 500);
        }
    }

    protected function routeId(string $value, string $message): int
    {
        return Router::positiveId($value) ?? throw new ApiError($message, 404);
    }

    /** @return array<array-key, mixed> */
    protected function input(): array
    {
        $length = $_SERVER['CONTENT_LENGTH'] ?? null;
        if ((is_string($length) || is_int($length)) && (int) $length > self::MAX_BODY_BYTES) {
            throw new ApiError('Слишком большой запрос', 413);
        }

        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if ($raw === false) {
            throw new RuntimeException('Cannot read the HTTP request body.');
        }
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            throw new ApiError('Слишком большой запрос', 413);
        }
        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiError('Некорректный JSON', 400);
        }
        if (!is_array($data) || !str_starts_with(ltrim($raw), '{')) {
            throw new ApiError('Ожидается JSON-объект', 400);
        }

        return $data;
    }

    protected function respond(mixed $data, int $status = 200): void
    {
        $encoded = json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        http_response_code($status);
        echo $encoded;
    }

    private static function error(string $message, int $status): void
    {
        http_response_code($status);
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
