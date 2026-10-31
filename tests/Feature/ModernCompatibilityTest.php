<?php

namespace Qoraiche\MailEclipse\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Qoraiche\MailEclipse\MailEclipse;
use Qoraiche\MailEclipse\MailEclipseMailables;
use Qoraiche\MailEclipse\Tests\Fixtures\Mail\ModernMail;
use Qoraiche\MailEclipse\Tests\Fixtures\Mail\LegacyMail;
use Qoraiche\MailEclipse\Tests\TestCase;

class ModernCompatibilityTest extends TestCase
{
    public function test_discovers_legacy_and_modern_content_and_envelope_mailables(): void
    {
        $mailables = MailEclipse::getMailables()->keyBy('name');
        $this->assertSame('Welcome to your workspace', $mailables['ModernMail']['subject']);
        $this->assertSame('Legacy welcome', $mailables['LegacyMail']['subject']);
        $this->assertSame('fixture::markdown', $mailables['MarkdownMail']['markdown']);
        $this->assertSame('fixture::plain', $mailables['ModernMail']['text_view']);
        $this->assertCount(3, MailEclipseMailables::all());
        $this->assertContains('intro', array_column($mailables['ModernMail']['view_data'], 'key'));
    }

    public function test_previews_render_modern_html_and_markdown(): void
    {
        $this->get('/maileclipse/mailables/preview/ModernMail')->assertOk()->assertSee('Modern content data');
        $this->get('/maileclipse/mailables/preview/LegacyMail')->assertOk()->assertSee('Legacy content data');
        $this->get('/maileclipse/mailables/preview/MarkdownMail')->assertOk()->assertSee('Weekly digest');
    }

    public function test_markdown_editor_endpoint_renders_sample_data(): void
    {
        $source = file_get_contents(__DIR__.'/../Fixtures/views/markdown.blade.php');
        $this->post('/maileclipse/mailables/preview/template', [
            'markdown' => $source, 'name' => 'MarkdownMail',
            'namespace' => \Qoraiche\MailEclipse\Tests\Fixtures\Mail\MarkdownMail::class,
        ])->assertOk()->assertSee('Weekly digest');
    }

    public function test_details_and_editor_pages_render(): void
    {
        $this->get('/maileclipse/mailables')->assertOk()->assertSee('workspace-search')->assertSee('ModernMail');
        $this->get('/maileclipse/mailables/view/ModernMail')->assertOk();
        $this->get('/maileclipse/mailables/edit/template/ModernMail')->assertOk()->assertSee('license_key', false);
        $this->get('/maileclipse/mailables/edit/template/MarkdownMail')->assertOk()->assertSee('new EasyMDE', false);
    }

    public function test_constructor_preserves_false_zero_and_empty_string_and_needs_no_database(): void
    {
        $this->app['config']->set('database.default', 'missing');
        foreach ([ModernMail::class, LegacyMail::class] as $class) {
            $instance = MailEclipse::buildMailable($class);
            $this->assertSame(0, $instance->count);
            $this->assertFalse($instance->enabled);
            $this->assertSame('', $instance->label);
        }
    }

    public function test_preview_does_not_change_existing_transaction_depth(): void
    {
        DB::beginTransaction();
        $before = DB::transactionLevel();
        MailEclipse::getMailables();
        $this->assertSame($before, DB::transactionLevel());
        DB::rollBack();
    }

    public function test_test_mail_overrides_envelope_and_callback_recipients_without_changing_global_mailer(): void
    {
        $mailer = Mail::mailer();
        $transport = $mailer->getSymfonyTransport();
        MailEclipse::sendTest('ModernMail', 'tester@example.com');
        $message = $transport->messages()->last()->getOriginalMessage();
        $this->assertSame(['tester@example.com'], array_map(fn ($a) => $a->getAddress(), $message->getTo()));
        $this->assertSame([], $message->getCc());
        $this->assertSame([], $message->getBcc());
        Mail::send(new ModernMail);
        $message = $transport->messages()->last()->getOriginalMessage();
        $this->assertContains('customer@example.com', array_map(fn ($a) => $a->getAddress(), $message->getTo()));
        $this->assertNotEmpty($message->getCc());
    }

    public function test_draft_preview_uses_private_temporary_storage_and_cleans_up(): void
    {
        $html = MailEclipse::previewMarkdownViewContent(true, '<p>{{ $count }}</p>', '../../outside', false, ModernMail::class);
        $this->assertSame('<p>0</p>', $html);
        $this->assertSame([], glob(storage_path('framework/maileclipse/*.blade.php')));
    }

    public function test_path_traversal_cannot_delete_files(): void
    {
        $this->postJson('/maileclipse/mailables/delete', ['mailablename' => '../ModernMail'])->assertUnprocessable();
        $this->assertFileExists(__DIR__.'/../Fixtures/Mail/ModernMail.php');
    }

    public function test_template_save_cannot_write_outside_application_views(): void
    {
        $this->postJson('/maileclipse/mailables/parse/template', [
            'viewpath' => base64_encode(__FILE__), 'markdown' => 'replacement',
        ])->assertUnprocessable();
    }

    public function test_templates_and_skeleton_picker_render_with_unique_modal_ids(): void
    {
        $this->get('/maileclipse/templates')->assertOk()->assertSee('Template library');
        $response = $this->get('/maileclipse/templates/new')->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'id="selectairmailModal"'));
        $this->get('/maileclipse/templates/new/html/plain/plain')->assertOk()->assertSee('MailEclipseEditor.init', false);
        $this->get('/maileclipse/templates/new/markdown/postmark/welcome')->assertOk()->assertSee('new EasyMDE', false);
    }

    public function test_all_routes_respect_environment_restriction(): void
    {
        $this->app['config']->set('maileclipse.allowed_environments', ['production']);
        foreach (['mailables', 'templates', 'mailables/preview/ModernMail'] as $path) {
            $this->get('/maileclipse/'.$path)->assertForbidden();
        }
    }

    public function test_unknown_mailable_test_send_returns_not_found(): void
    {
        $this->postJson('/maileclipse/mailables/send-test', ['name' => 'Missing', 'email' => 'tester@example.com'])->assertNotFound();
    }
}
