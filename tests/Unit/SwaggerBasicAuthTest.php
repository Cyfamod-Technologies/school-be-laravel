<?php

use App\Http\Middleware\SwaggerBasicAuth;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

function swaggerRequest(?string $username = null, ?string $password = null): Request
{
    $server = [];

    if ($username !== null && $password !== null) {
        $server['HTTP_AUTHORIZATION'] = 'Basic '.base64_encode("{$username}:{$password}");
    }

    return Request::create('/api/docs', 'GET', server: $server);
}

it('protects Swagger documentation with configured basic auth credentials', function () {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->instance('request', swaggerRequest());
    $app->make(Kernel::class)->bootstrap();

    $middleware = $app->make(SwaggerBasicAuth::class);
    $next = fn () => response('Swagger UI');

    config()->set('l5-swagger.basic_auth.username', null);
    config()->set('l5-swagger.basic_auth.password', null);
    expect($middleware->handle(swaggerRequest(), $next)->getStatusCode())->toBe(503);

    config()->set('l5-swagger.basic_auth.username', 'docs-user');
    config()->set('l5-swagger.basic_auth.password', 'strong-password');

    $unauthorized = $middleware->handle(
        swaggerRequest('docs-user', 'wrong-password'),
        $next,
    );

    expect($unauthorized->getStatusCode())->toBe(401)
        ->and($unauthorized->headers->get('WWW-Authenticate'))
        ->toContain('School API Documentation');

    $authorized = $middleware->handle(
        swaggerRequest('docs-user', 'strong-password'),
        $next,
    );

    expect($authorized->getStatusCode())->toBe(200)
        ->and($authorized->getContent())->toBe('Swagger UI');
});
