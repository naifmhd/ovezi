<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

if (getenv('APP_ENV') !== 'testing') {
    exit(64);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
while (microtime(true) < $input['start_at']) {
    usleep(1000);
}
$kernel = $app->make(Kernel::class);
$request = Request::create($input['path'], 'POST', [], [], [], [
    'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.$input['token'], 'HTTP_IDEMPOTENCY_KEY' => $input['key'],
], json_encode($input['body'], JSON_THROW_ON_ERROR));
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
