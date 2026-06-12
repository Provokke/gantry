<div align="center">

# gantry

A small HTTP router and middleware pipeline for PHP 8.2 and later. Four classes, no dependencies.

![License](https://img.shields.io/badge/LICENSE-MIT-blue?style=for-the-badge)  ![Language](https://img.shields.io/badge/PHP-777bb4?style=for-the-badge&logo=php&logoColor=white)  ![Tests](https://img.shields.io/badge/TESTS-23-brightgreen?style=for-the-badge)

Refer to [LICENSE](LICENSE) for the legal bits.

</div>

## What's in it

- **Router** with named parameters (`/users/{id}`) and optional regex constraints (`/users/{id:\d+}`). A constraint can hold one level of braces, so `{year:\d{4}}` works. Reusing a parameter name in one pattern throws.
- **Route groups** with a shared prefix and shared middleware. They nest, and the prefix and middleware are restored afterwards even if the callback throws.
- **Middleware** in the usual `fn (Request $request, callable $next): Response` shape, both global (`pipe`) and per route.
- **Immutable `Request` and `Response`** value objects. `Request::fromGlobals()` builds one from the PHP superglobals.
- **Correct `404` and `405`**, with an `Allow` header listing the methods that do exist for the path.
- **Handler results are normalised**: a `Response` passes through, a string is sent as plain text, an array as JSON and `null` as `204`.

```php
use Gantry\Request;
use Gantry\Router;

$router = new Router();

$router->get('/hello/{name}', fn (Request $r) => ['hello' => $r->param('name')]);

$router->group('/admin', function (Router $admin) {
    $admin->get('/stats', fn () => ['ok' => true]);
}, [$requireLogin]);

$router->dispatch(Request::fromGlobals())->send();
```

## 🏗️ Architecture

```
 Request::fromGlobals()
        |
        v
 +-----------------------------------------------+
 | Router::dispatch                              |
 |   Pipeline::run(global middleware)            |
 |        |                                      |
 |        v                                      |
 |   route(): linear scan of $routes[]           |
 |     regex match -> method check               |
 |        |                                      |
 |        v                                      |
 |   Pipeline::run(group + route middleware)     |
 |        |                                      |
 |        v                                      |
 |   handler -> normalise() -> Response          |
 +-----------------------------------------------+
 src/Router.php  src/Pipeline.php  src/Request.php  src/Response.php
 tests/run.php   examples/index.php
```

- `Router` compiles each pattern to a named-group regex when you register it and keeps the routes in order. Matching is a linear scan, which is fine for tens or a few hundred routes. A big API would want a combined regex or a prefix tree.
- `Pipeline` folds the middleware list into nested closures around the core handler. It's a single static method.
- `Request` normalises on the way in: the method is upper-cased, the path is normalised to a single leading slash, and header lookup is case-insensitive. `withParams` and `withAttribute` return copies. The body is read once, and there's no form or multipart parsing because PHP already gives you `$_POST` and `$_FILES`.
- `Response` has `text`, `html`, `json`, `redirect` and `empty` constructors, plus `withHeader` and `withStatus`.
- This isn't PSR-7 or PSR-15. The interfaces are deliberately smaller.

## 🚀 Quick start

```
composer require provokke/gantry
php tests/run.php
composer install && php -S localhost:8080 examples/index.php
```

A quick taste, once the example is running:

```
$ curl localhost:8080/hello/Kylie
{"hello":"Kylie"}
```

## 🧪 Testing

23 checks, all passing. The tests are one script with a small `$check` helper, no framework behind it. Run it with `php tests/run.php`, which prints `23 checks, 0 failed` and exits non-zero on any failure.

- Global middleware: status, body, header injection and ordering.
- Parameters: a constrained numeric route, a trailing slash, a rejected constraint, a literal dot being quoted and a literal suffix after a parameter.
- Bodies: JSON decoding and echo with a 201, and an attribute set by middleware and read by the handler.
- Normalisation: `null` becomes `204`.
- Errors: `405` with the right `Allow` header, `404`, and a duplicate parameter being rejected.
- Groups: group middleware blocking and passing, nested groups, inherited middleware and the route listing.
- Responses: redirect `Location`.

## Licence

MIT.

