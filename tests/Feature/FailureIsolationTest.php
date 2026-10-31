<?php

namespace Qoraiche\MailEclipse\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Qoraiche\MailEclipse\MailEclipse;
use Qoraiche\MailEclipse\Support\TemplateValidator;
use Qoraiche\MailEclipse\Tests\TestCase;

class FailureIsolationTest extends TestCase
{
    public function test_bad_mailables_are_marked_and_do_not_block_good_mail_or_the_interface(): void
    {
        $root = sys_get_temp_dir().'/maileclipse-isolation-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($root);
        $namespace = 'Isolation'.bin2hex(random_bytes(6));
        $badView = resource_path('views/isolation-bad.blade.php');
        File::ensureDirectoryExists(dirname($badView));
        file_put_contents($badView, '@if(true) <p>Unclosed directive</p>');
        View::addNamespace('isolation', dirname($badView));
        $classes = [
            'GoodMail' => 'public function build() { return $this->html("<p>Working email</p>"); }',
            'BadBuildMail' => 'public function build() { throw new \\RuntimeException("Build failed <script>bad</script>"); }',
            'MissingViewMail' => 'public function build() { return $this->view("missing.no-such-view"); }',
            'BadBladeMail' => 'public function build() { return $this->view("isolation::isolation-bad"); }',
            'BadDataMail' => 'public function __construct(\\Iterator&\\Countable $required) {}',
            'BadPhpMail' => 'public function build( {',
        ];
        foreach ($classes as $class => $body) {
            file_put_contents($root.'/'.$class.'.php', '<?php namespace '.$namespace.'; class '.$class.' extends \\Illuminate\\Mail\\Mailable { '.$body.' }');
        }
        $loader = static function ($class) use ($namespace, $root) {
            if (str_starts_with($class, $namespace.'\\')) require $root.'/'.class_basename($class).'.php';
        };
        spl_autoload_register($loader);
        $this->app['config']->set('maileclipse.mailables_dir', $root.'/');
        try {
            $items = MailEclipse::getMailables()->keyBy('name');
            $this->assertCount(6, $items);
            $this->assertSame('valid', $items['GoodMail']['status']);
            foreach (array_diff(array_keys($classes), ['GoodMail']) as $name) {
                $this->assertSame('invalid', $items[$name]['status'], $name);
                $this->assertNotEmpty($items[$name]['error']);
            }
            $this->get('/maileclipse/mailables')->assertOk()->assertSee('Bad template')->assertSee('GoodMail')->assertDontSee('<script>bad</script>', false);
            $this->get('/maileclipse/mailables/view/BadBuildMail')->assertOk()->assertSee('Preview check failed');
            $this->get('/maileclipse/mailables/edit/template/BadBladeMail')->assertOk()->assertSee('marked as bad');
            $this->get('/maileclipse/mailables/preview/GoodMail')->assertOk()->assertSee('Working email');
            $this->get('/maileclipse/mailables/preview/BadBladeMail')->assertOk()->assertSee('needs attention');
            $this->postJson('/maileclipse/mailables/send-test', ['name' => 'BadBuildMail', 'email' => 'tester@example.com'])->assertUnprocessable();
        } finally {
            spl_autoload_unregister($loader);
            File::deleteDirectory($root);
            @unlink($badView);
        }
    }

    public function test_bad_saved_templates_are_flagged_but_can_be_opened_for_repair(): void
    {
        $root = sys_get_temp_dir().'/maileclipse-templates-'.bin2hex(random_bytes(6));
        $views = resource_path('views/vendor/maileclipse/templates');
        File::ensureDirectoryExists($root);
        File::ensureDirectoryExists($views);
        View::prependNamespace('maileclipse', dirname($views));
        $this->app['config']->set('maileclipse.mailables_dir', $root.'/');
        $records = [];
        foreach (['validFixture', 'invalidFixture', 'missingFixture'] as $slug) {
            $records[] = ['template_slug' => $slug, 'template_name' => $slug, 'template_description' => '', 'template_type' => 'markdown', 'template_view_name' => 'postmark', 'template_skeleton' => 'blank'];
        }
        file_put_contents($root.'/templates.json', json_encode($records));
        file_put_contents($views.'/validFixture.blade.php', '<p>Valid</p>');
        file_put_contents($views.'/invalidFixture.blade.php', '@if(true) Unclosed');
        try {
            $items = MailEclipse::getTemplates()->keyBy('template_slug');
            $this->assertSame('valid', $items['validFixture']->status);
            $this->assertSame('invalid', $items['invalidFixture']->status);
            $this->assertSame('invalid', $items['missingFixture']->status);
            $this->get('/maileclipse/templates')->assertOk()->assertSee('Bad template');
            $this->get('/maileclipse/templates/edit/invalidFixture')->assertOk()->assertSee('marked as bad');
            $this->get('/maileclipse/templates/edit/missingFixture')->assertOk()->assertSee('source file is missing');
            $this->postJson('/maileclipse/mailables/parse/template', ['template' => true, 'viewpath' => 'invalidFixture', 'markdown' => '<p>Repaired</p>'])->assertOk();
            $this->assertSame('valid', MailEclipse::getTemplates()->keyBy('template_slug')['invalidFixture']->status);
            $this->postJson('/maileclipse/mailables/parse/template', ['template' => true, 'viewpath' => 'validFixture', 'markdown' => '@if(true) Bad'])->assertUnprocessable();
            $this->assertSame('<p>Valid</p>', file_get_contents($views.'/validFixture.blade.php'));
        } finally {
            File::deleteDirectory($root);
            @unlink($views.'/validFixture.blade.php');
            @unlink($views.'/invalidFixture.blade.php');
        }
    }

    public function test_corrupt_template_index_still_loads_ui_and_is_never_overwritten(): void
    {
        $root = sys_get_temp_dir().'/maileclipse-index-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($root);
        $this->app['config']->set('maileclipse.mailables_dir', $root.'/');
        file_put_contents($root.'/templates.json', '{broken JSON');
        try {
            $this->get('/maileclipse/templates')->assertOk()->assertSee('template index could not be read');
            $this->postJson('/maileclipse/templates/new', [
                'template_name' => 'Example', 'template_type' => 'html', 'template_view_name' => 'plain',
                'template_skeleton' => 'plain', 'content' => '<p>Good</p>',
            ])->assertUnprocessable();
            $this->assertSame('{broken JSON', file_get_contents($root.'/templates.json'));
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_syntax_validation_does_not_evaluate_template_code(): void
    {
        $validator = app(TemplateValidator::class);
        $this->assertNull($validator->validate('{{ $notDefinedYet }}'));
        $this->assertNull($validator->validate('@php throw new \\RuntimeException("must not execute"); @endphp'));
        $this->assertNotNull($validator->validate('@if(true) Missing end'));
    }
}
