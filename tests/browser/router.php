<?php

// Local-only browser fixture. Never register these fixtures in an application.
require dirname(__DIR__, 2).'/vendor/autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$prefix = '/vendor/maileclipse/';
if (str_starts_with($path, $prefix)) {
    $root = realpath(dirname(__DIR__, 2).'/public');
    $file = realpath($root.'/'.substr($path, strlen($prefix)));
    if (! $file || ! str_starts_with($file, $root.'/') || ! is_file($file)) {
        http_response_code(404);
        return;
    }
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf'];
    header('Content-Type: '.($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($file);
    return;
}

$data = getenv('MAILECLIPSE_BROWSER_DATA');
if (! $data) { throw new RuntimeException('Run with npm test; this fixture needs isolated storage.'); }
$app = \Orchestra\Testbench\Foundation\Application::create();
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->setBasePath($data);
$app->useStoragePath($data.'/storage');
$app['config']->set('view.paths', [$data.'/resources/views']);
$app['view']->getFinder()->setPaths([$data.'/resources/views']);
$app['config']->set('view.compiled', $data.'/storage/framework/views');
$app['config']->set('session.files', $data.'/storage/framework/sessions');
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('b', 32)));
$app['config']->set('session.driver', 'file');
$app['config']->set('mail.default', 'array');
$app['config']->set('app.debug', true);

\Illuminate\Foundation\AliasLoader::getInstance()->alias('MailEclipse', \Qoraiche\MailEclipse\Facades\MailEclipse::class);
$app->register(\Qoraiche\MailEclipse\MailEclipseServiceProvider::class);
$app['config']->set('maileclipse.mailables_dir', $data.'/Mail/');
$app['config']->set('maileclipse.allowed_environments', [$app->environment()]);
$app['view']->addNamespace('fixture', $data.'/resources/views/fixture');
$request = \Illuminate\Http\Request::capture();
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
if ($response->getStatusCode() >= 500) { error_log('FIXTURE ERROR: '.($response->exception ?? 'unknown')); }
$response->send();
$kernel->terminate($request, $response);
