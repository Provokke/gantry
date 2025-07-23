<?php

declare(strict_types=1);

use Gantry\Request;
use Gantry\Response;
use Gantry\Router;

require __DIR__ . '/../vendor/autoload.php';

$router = new Router();

$router->pipe(static function (Request $request, callable $next): Response {
    $started = hrtime(true);
    $response = $next($request);
    $elapsed = (hrtime(true) - $started) / 1e6;

    return $response->withHeader('Server-Timing', sprintf('app;dur=%.2f', $elapsed));
});

$router->get('/', static fn (): string => "G'day from gantry\n");
$router->get('/hello/{name}', static fn (Request $r): array => ['hello' => $r->param('name')]);

$router->group('/api', static function (Router $api): void {
    $api->post('/echo', static fn (Request $r): Response => Response::json($r->json(), 201));
});

$router->dispatch(Request::fromGlobals())->send();
