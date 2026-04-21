<?php

declare(strict_types=1);

use Gantry\Request;
use Gantry\Response;
use Gantry\Router;

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/../src/' . str_replace('Gantry\\', '', $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$failures = 0;
$count = 0;

$check = static function (string $name, mixed $expected, mixed $actual) use (&$failures, &$count): void {
    $count++;
    if ($expected === $actual) {
        return;
    }
    $failures++;
    fwrite(STDERR, sprintf("FAIL %s\n  expected %s\n  actual   %s\n", $name, var_export($expected, true), var_export($actual, true)));
};

$router = new Router();
$trace = [];

$router->pipe(static function (Request $request, callable $next) use (&$trace): Response {
    $trace[] = 'outer:in';
    $response = $next($request->withAttribute('requestId', 'abc123'));
    $trace[] = 'outer:out';

    return $response->withHeader('X-Request-Id', 'abc123');
});

$auth = static function (Request $request, callable $next): Response {
    if ($request->header('Authorization') !== 'Bearer letmein') {
        return Response::json(['error' => 'unauthorised'], 401);
    }

    return $next($request);
};

$router->get('/', static fn (): string => 'home');
$router->get('/users/{id:\d+}', static fn (Request $r): array => ['id' => (int) $r->param('id')]);
$router->get('/files/{name}.txt', static fn (Request $r): string => $r->param('name'));
$router->post('/users', static fn (Request $r): Response => Response::json($r->json(), 201));
$router->get('/whoami', static fn (Request $r): string => $r->attribute('requestId'));
$router->delete('/users/{id:\d+}', static fn (): mixed => null);

$router->group('/admin', static function (Router $admin): void {
    $admin->get('/stats', static fn (): array => ['ok' => true]);
    $admin->group('/reports', static function (Router $reports): void {
        $reports->get('/{year:\d{4}}', static fn (Request $r): string => 'report ' . $r->param('year'));
    });
}, [$auth]);

$home = $router->dispatch(new Request('GET', '/'));
$check('home status', 200, $home->status);
$check('home body', 'home', $home->body);
$check('global middleware header', 'abc123', $home->headers['X-Request-Id']);
$check('middleware order', ['outer:in', 'outer:out'], $trace);

$user = $router->dispatch(new Request('get', '/users/42/'));
$check('param route', '{"id":42}', $user->body);
$check('json content type', 'application/json', $user->headers['Content-Type']);

$check('constraint rejects', 404, $router->dispatch(new Request('GET', '/users/abc'))->status);
$check('literal dot is quoted', 404, $router->dispatch(new Request('GET', '/files/notesXtxt'))->status);
$check('literal suffix', 'notes', $router->dispatch(new Request('GET', '/files/notes.txt'))->body);

$created = $router->dispatch(new Request('POST', '/users', body: '{"name":"Kylie"}'));
$check('post status', 201, $created->status);
$check('post echo', '{"name":"Kylie"}', $created->body);

$check('attribute from middleware', 'abc123', $router->dispatch(new Request('GET', '/whoami'))->body);
$check('null becomes 204', 204, $router->dispatch(new Request('DELETE', '/users/7'))->status);

$notAllowed = $router->dispatch(new Request('PUT', '/users/7'));
$check('405 status', 405, $notAllowed->status);
$check('405 allow header', 'GET, DELETE', $notAllowed->headers['Allow']);

$check('404 status', 404, $router->dispatch(new Request('GET', '/nowhere'))->status);

$denied = $router->dispatch(new Request('GET', '/admin/stats'));
$check('group middleware blocks', 401, $denied->status);

$allowedIn = $router->dispatch(new Request('GET', '/admin/stats', headers: ['authorization' => 'Bearer letmein']));
$check('group middleware passes', '{"ok":true}', $allowedIn->body);

$nested = $router->dispatch(new Request('GET', '/admin/reports/2026', headers: ['Authorization' => 'Bearer letmein']));
$check('nested group', 'report 2026', $nested->body);
$check('nested group inherits middleware', 401, $router->dispatch(new Request('GET', '/admin/reports/2026'))->status);

$check('route listing', '/admin/reports/{year:\d{4}}', $router->routes()[7]['pattern']);

$threw = false;
try {
    (new Router())->get('/a/{id}/b/{id}', static fn (): string => '');
} catch (InvalidArgumentException) {
    $threw = true;
}
$check('duplicate parameter rejected', true, $threw);

$check('redirect', '/login', Response::redirect('/login')->headers['Location']);

printf("%d checks, %d failed\n", $count, $failures);
exit($failures === 0 ? 0 : 1);

