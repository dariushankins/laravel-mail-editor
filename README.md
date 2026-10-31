# MailEclipse — Laravel Mail Editor

A modern email workspace for **Laravel 12 and 13**, with **PHP 8.4 or newer**. This is a modernization of [Yassine Qoraiche's MailEclipse](https://github.com/Qoraiche/laravel-mail-editor), retaining its PHP namespace, configuration keys, facade, and named routes.

## What's new

- Responsive Bootstrap 5 workspace with persistent light, dark, and system appearance.
- Search mailables and saved templates by name or content.
- Locally hosted HTML, Markdown, and plain text editors; no runtime CDN dependencies.
- Modern `envelope()` / `content()` mailables alongside legacy `build()` mailables.
- Modern Eloquent factories and configurable preview constructor data.
- Test sends redirect all recipients, including envelope/callback recipients, without changing the host application's mailer.
- Per-item validation: bad templates are flagged, diagnostics and repair remain accessible, and corrupt template indexes never take down the workspace or get silently overwritten.
- Private, temporary draft views; sandboxed email previews; application-view write boundaries.
- Composer/PHPUnit and browser regression tests, with CI for PHP 8.4/8.5 and Laravel 12/13.

## Install this fork

Until a release is published on Packagist, add this fork as a Composer VCS repository. Keep the original package name so Laravel discovery and existing integrations continue to work.

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/dariushankins/laravel-mail-editor" }
    ]
}
```

```bash
composer require qoraiche/laravel-mail-editor:dev-modernize-laravel-13
php artisan laravel-mail-editor:install
```

Open `/maileclipse`. Node is needed only to change and rebuild frontend assets, not to install the package. The compiled assets are included in the repository.

On upgrade, refresh the published assets:

```bash
php artisan vendor:publish --tag=maileclipse.assets --force
php artisan view:clear
```

If you have published custom package views, reconcile them with the new Bootstrap 5 views before upgrading. Existing application mailables and saved template Blade files remain in place.

## Configure

Publish `config/maileclipse.php` with the install command. The default allowed environments are local, development, staging, and testing. The editor can change executable Blade/PHP files: only enable it for trusted developers. If enabling it in a shared environment, configure authentication and an authorization middleware in `maileclipse.middlewares`.

Constructor defaults, including `0`, `false`, and empty strings, are preserved. Required scalar arguments get example values. Nullable arguments use `null`, enums use their first case, and Eloquent models use modern `HasFactory::factory()->make()` without saving records. Custom factory callbacks and mailable constructors are application code and may have their own side effects. No legacy global factories or automatic relationship queries are used; provide complex preview graphs explicitly:

```php
'preview_data' => [
    App\Mail\InvoiceMail::class => [
        'invoice' => new App\Models\Invoice(['number' => 'DEMO-001']),
        'count' => 0,
        'enabled' => false,
    ],
],
```

Unresolvable interfaces and intersection types need explicit preview data. Container bindings can resolve application services. The original `factory` toggle remains available; when false, model arguments use empty unsaved model instances. Retired legacy `relations` options are ignored.

## Editors and licenses

PHP code remains MIT, with the original copyright retained in [LICENSE](LICENSE). Browser libraries keep their upstream licenses in `public/licenses`.

The HTML editor uses self-hosted **TinyMCE 8**, defaulting to its GPL license option. Review the upstream TinyMCE license for your distribution/use; a commercial key can be configured with `MAILECLIPSE_EDITOR_LICENSE_KEY`. TinyMCE is loaded only on editor pages. Full HTML documents retain their document head and body attributes when editing body content; edit complex document structure in the Blade source as needed. Markdown editing uses EasyMDE, and plain text editing uses CodeMirror.

## Development

Requirements: PHP 8.4+, Composer 2, Node 22+.

```bash
composer update
composer test
composer lint
npm ci
npm run build
npx playwright install chromium
npm test
```

Browser tests start an isolated local Testbench app and use a temporary filesystem. `PHP_BINARY` and `CHROMIUM_PATH` can select alternate binaries. `SCREENSHOT_DIR` optionally saves browser QA screenshots. Preview validation runs with the configured sample data; an error can indicate missing preview data as well as a broken template. Existing invalid templates remain editable, and new syntax-invalid saves are rejected before writing.

Test mail uses Laravel's array transport; no external email is sent.

Laravel 9/10 and PHP 8.3 and older should continue using the appropriate upstream version. This branch is a major upgrade; test it in development before updating an application.

## Credits

Originally created by [Yassine Qoraiche](https://github.com/Qoraiche), with contributions from the upstream community.
