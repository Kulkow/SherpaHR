<?php
declare(strict_types=1);

// Black-box API regression checks against a disposable server and database.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "This PHP interpreter needs pdo_sqlite. Use the Compose test command from README.\n");
    exit(1);
}
$directory = sys_get_temp_dir() . '/project-desk-test-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$socket) {
    throw new RuntimeException($errstr);
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$base = 'http://' . $address;
$environment = getenv();
$environment['DB_PATH'] = $directory . '/test.sqlite';
$environment['SEED_NOW'] = '2026-11-11T08:00:00Z';
$process = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/../router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']],
    $pipes, dirname(__DIR__, 2), $environment);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot start test server');
}
fclose($pipes[0]);
$count = 0;
$exitStatus = 0;

function request(string $method, string $path, mixed $body = null, int $user = 1): array
{
    global $base;
    $options = ['http' => [
        'method' => $method, 'ignore_errors' => true, 'timeout' => 5,
        'header' => "Content-Type: application/json\r\nX-Demo-User: $user\r\n",
    ]];
    if ($body !== null) {
        $options['http']['content'] = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);
    }
    $raw = file_get_contents($base . $path, false, stream_context_create($options));
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
    return [(int) $match[1], json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
}

function check(bool $condition, string $message): void
{
    global $count;
    ++$count;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

try {
    $ready = false;
    for ($attempt = 0; $attempt < 50; ++$attempt) {
        if (@file_get_contents($base . '/api/health') !== false) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    check($ready, 'server starts with fresh SQLite');
    [$status, $response] = request('GET', '/api/context');
    check($status === 200 && $response['data']['user']['id'] === 1, 'default persona is Anna');
    check($response['data']['seeded_at'] === '2026-11-11T08:00:00Z', 'seed clock can be reproduced');
    [$status, $response] = request('GET', '/api/projects');
    check($status === 200 && array_column($response['data'], 'id') === [1, 2, 3, 4], 'Anna sees only her projects');
    check($response['data'][3]['archived'] === true, 'archive marker is boolean');
    [$status, $response] = request('GET', '/api/projects/2/tasks');
    check($status === 200 && array_column($response['data'], 'id') === [5, 6, 7]
        && $response['data'][2]['assignee_id'] === null, 'project list includes unassigned tasks');
    [$status, $response] = request('GET', '/api/projects', null, 2);
    check(array_column($response['data'], 'id') === [1, 2, 4], 'Boris has different membership');
    [$status, $response] = request('GET', '/api/projects', null, 3);
    check(array_column($response['data'], 'id') === [1, 3, 5], 'Vera can see her separate project');
    check(request('GET', '/api/projects', null, 999)[0] === 401, 'unknown persona rejected');
    check(request('GET', '/api/projects/5/tasks')[0] === 404, 'hidden project is not disclosed');
    check(request('GET', '/api/tasks/11')[0] === 404, 'hidden task is not disclosed');
    check(request('PATCH', '/api/tasks/11', ['title' => 'Forbidden', 'version' => 1])[0] === 404, 'hidden task cannot be edited');
    check(request('POST', '/api/tasks/11/comments', ['body' => 'Forbidden'])[0] === 404, 'hidden task cannot receive comments');
    check(request('POST', '/api/projects/5/tasks', ['title' => 'Forbidden'])[0] === 404, 'hidden project cannot receive tasks');
    [$status, $response] = request('GET', '/api/tasks/1');
    check($status === 200 && count($response['data']['comments']) === 2, 'task detail includes discussion');
    check($response['data']['due_at'] === '2026-11-12T08:00:00Z', 'deadline relative to initial seed');
    check($response['data']['allowed_statuses'] === ['open'], 'task detail exposes allowed status transitions');
    check(array_column($response['data']['comments'], 'attention') === ['requested', 'requested'],
        'seeded comments on a blocked task request attention');
    [$status, $response] = request('GET', '/api/tasks/7');
    check($status === 200 && $response['data']['comments'][0]['attention'] === 'none',
        'seeded comment on an open task has no attention mark');
    [$status, $response] = request('GET', '/api/tasks/10');
    check($status === 200 && $response['data']['comments'][0]['attention'] === 'requested',
        'seeded comment on a blocked archive task retains its mark');
    [$status, $overview] = request('GET', '/api/attention');
    $attentionByTask = array_column($overview['data'], 'reasons', 'id');
    check($status === 200 && in_array('blocked', $attentionByTask[5], true)
        && in_array('comment_attention', $attentionByTask[5], true)
        && in_array('unassigned', $attentionByTask[7], true)
        && !isset($attentionByTask[3]) && !isset($attentionByTask[10]) && !isset($attentionByTask[11]),
        'overview includes relevant tasks and excludes completed, archive and non-member tasks');
    [$status, $overview] = request('GET', '/api/attention', null, 2);
    check($status === 200 && $overview['data'] === [], 'participant does not receive other project owners overview');
    check(request('POST', '/api/projects/4/tasks', ['title' => 'No'])[0] === 409, 'archive rejects task creation');
    check(request('PATCH', '/api/tasks/10', ['title' => 'No', 'version' => 1])[0] === 409, 'archive rejects task edits');
    check(request('POST', '/api/tasks/10/comments', ['body' => 'No'])[0] === 409, 'archive rejects comments');
    check(request('POST', '/api/projects/1/tasks', ['title' => '  '])[0] === 422, 'empty title rejected');
    check(request('POST', '/api/projects/2/tasks', ['title' => 'Bad member', 'assignee_id' => 3])[0] === 422, 'assignee must be a project member');
    check(request('POST', '/api/projects/1/tasks', ['title' => 'Bad date', 'due_at' => '2026-02-31T00:00:00Z'])[0] === 422, 'impossible date rejected');
    check(request('POST', '/api/projects/1/tasks', ['title' => 'Bad status', 'status' => 'unknown'])[0] === 422, 'invalid status rejected');
    check(request('POST', '/api/projects/1/tasks', ['title' => 'Extra field', 'project_id' => 5])[0] === 422, 'cannot move task through extra field');
    check(request('POST', '/api/projects/1/tasks', '{broken')[0] === 400, 'malformed JSON returns 400');
    check(request('POST', '/api/projects/1/tasks', '[]')[0] === 400, 'body must be an object');
    foreach (['in_progress', 'blocked', 'done'] as $initialStatus) {
        check(request('POST', '/api/projects/1/tasks', ['title' => 'Задача', 'status' => $initialStatus])[0] === 422,
            "new task cannot start in {$initialStatus}");
    }
    check(request('POST', '/api/projects/1/tasks', str_repeat('x', 33000))[0] === 413, 'oversized body rejected');
    [$status, $response] = request('POST', '/api/projects/1/tasks', [
        'title' => 'Проверка сохранения', 'description' => 'Текст с <b>разметкой</b>',
        'assignee_id' => 2, 'due_at' => '2026-12-01T09:30:00Z', 'status' => 'open',
    ]);
    $task = $response['data'];
    check($status === 201 && $task['id'] > 12 && $task['status'] === 'open', 'create task with explicit open status');
    check(request('PATCH', '/api/tasks/' . $task['id'], ['title' => 'New'])[0] === 422, 'edit requires version');
    check(request('PATCH', '/api/tasks/' . $task['id'], ['status' => null, 'version' => 1])[0] === 422,
        'null status does not reset the task');
    check(request('PATCH', '/api/tasks/' . $task['id'], ['description' => null, 'version' => 1])[0] === 422,
        'null description does not reset the task');
    $taskPath = '/api/tasks/' . $task['id'];
    check(request('PATCH', $taskPath, ['status' => 'done', 'version' => 1])[0] === 422,
        'open task cannot skip in progress');
    [$status, $response] = request('PATCH', $taskPath, [
        'status' => 'in_progress', 'assignee_id' => null, 'due_at' => null, 'version' => 1,
    ]);
    check($status === 200 && $response['data']['version'] === 2 && $response['data']['due_at'] === null
        && $response['data']['title'] === $task['title'] && $response['data']['description'] === $task['description'],
        'partial edit preserves omitted fields, clears due date and increments version');
    check(request('PATCH', $taskPath, ['title' => 'Stale', 'version' => 1])[0] === 409,
        'stale write does not overwrite newer data');
    [$status, $response] = request('PATCH', $taskPath, ['status' => 'done', 'version' => 2]);
    check($status === 200 && $response['data']['status'] === 'done' && $response['data']['version'] === 3,
        'in-progress task can be completed');
    check(request('PATCH', $taskPath, ['status' => 'blocked', 'version' => 3])[0] === 422,
        'completed task can only be reopened');
    check(request('POST', '/api/tasks/' . $task['id'] . '/comments', ['body' => ' '])[0] === 422, 'empty comment rejected');
    [$status, $response] = request('POST', '/api/tasks/' . $task['id'] . '/comments', ['body' => '<script>window.pwned=1</script>'], 2);
    check($status === 201 && $response['data']['author'] === 'Борис · разработчик', 'comment records acting user');
    [$status, $response] = request('GET', '/api/tasks/' . $task['id']);
    check($response['data']['status'] === 'done' && $response['data']['version'] === 3
        && count($response['data']['comments']) === 1, 'comment persists without incrementing the task version');
    check($response['data']['comments'][0]['body'] === '<script>window.pwned=1</script>', 'comment content retained as plain data');
    check(request('POST', $taskPath . '/comments', ['body' => 'Запрос', 'requires_attention' => 'yes'], 2)[0] === 422,
        'attention flag must be boolean');
    [$status, $response] = request('POST', $taskPath . '/comments', ['body' => 'Запрос', 'requires_attention' => true], 2);
    check($status === 201 && $response['data']['attention'] === 'requested', 'participant requests owner attention');
    $resolvePath = $taskPath . '/comments/' . $response['data']['id'] . '/resolve';
    [$status, $overview] = request('GET', '/api/attention');
    $attentionByTask = array_column($overview['data'], 'reasons', 'id');
    check($status === 200 && ($attentionByTask[$task['id']] ?? []) === ['comment_attention'],
        'completed task with marked comment reaches owner');
    check(request('POST', $resolvePath, null, 2)[0] === 403, 'comment author cannot resolve attention as non-owner');
    check(request('POST', $resolvePath, null, 3)[0] === 403, 'other participant cannot resolve attention');
    check(request('POST', $resolvePath)[0] === 200, 'owner resolves comment attention');
    check(request('POST', $resolvePath)[0] === 200, 'resolving attention is idempotent');
    [$status, $detail] = request('GET', $taskPath);
    check($status === 200 && $detail['data']['version'] === 3 && $detail['data']['comments'][1]['attention'] === 'resolved',
        'resolved mark is saved without changing task version');
    [$status, $overview] = request('GET', '/api/attention');
    check($status === 200 && !in_array($task['id'], array_column($overview['data'], 'id'), true),
        'resolved mark removes completed task from overview');

    [$status, $response] = request('PATCH', $taskPath, ['status' => 'open', 'version' => 3]);
    check($status === 200 && $response['data']['status'] === 'open' && $response['data']['version'] === 4,
        'completed task can be reopened');
    check(request('PATCH', '/api/tasks/1', ['status' => 'in_progress', 'version' => 1])[0] === 422,
        'blocked task cannot go directly to in progress');
    [$status, $response] = request('PATCH', '/api/tasks/1', ['status' => 'open', 'version' => 1]);
    check($status === 200 && $response['data']['status'] === 'open' && $response['data']['version'] === 2,
        'blocked task can be reopened');
    [$status, $response] = request('GET', '/api/projects/1/tasks');
    check(count($response['data']) === 6, 'initialization does not duplicate or reset data');
    [$status, $response] = request('POST', '/api/projects/1/tasks', ['title' => 'Открыта по умолчанию']);
    check($status === 201 && $response['data']['status'] === 'open', 'create without status starts open');
    check(request('GET', '/api/tasks/9999')[0] === 404, 'missing task returns 404');
    check(request('GET', '/api/unknown')[0] === 404, 'unknown route returns 404');
    echo "PASS: $count API checks\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL . file_get_contents($directory . '/server.log'));
    $exitStatus = 1;
} finally {
    proc_terminate($process);
    proc_close($process);
    foreach (glob($directory . '/*') as $file) {
        unlink($file);
    }
    rmdir($directory);
}

exit($exitStatus);
