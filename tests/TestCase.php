<?php

namespace Qoraiche\MailEclipse\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Qoraiche\MailEclipse\MailEclipseServiceProvider;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();
        $this->app['view']->addNamespace('fixture', __DIR__.'/Fixtures/views');
    }

    protected function getPackageProviders($app)
    {
        return [
            MailEclipseServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('maileclipse.mailables_dir', __DIR__.'/Fixtures/Mail/');
        $app['config']->set('database.default', 'sqlite');

        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
