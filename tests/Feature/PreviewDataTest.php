<?php

namespace Qoraiche\MailEclipse\Tests\Feature;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Qoraiche\MailEclipse\Support\MailableResolver;
use Qoraiche\MailEclipse\Tests\TestCase;

class PreviewModel extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected static function newFactory(): Factory
    {
        return new class extends Factory {
            protected $model = PreviewModel::class;
            public function definition(): array { return ['name' => 'Preview customer']; }
        };
    }
}

enum PreviewState { case Ready; }
class RequiredArguments
{
    public function __construct(public int $count, public bool $enabled, public string $label, public array $items, public PreviewModel $customer, public PreviewState $state, public int|string $reference) {}
}

class PreviewDataTest extends TestCase
{
    public function test_modern_factories_resolve_without_database_and_preserve_explicit_falsey_values(): void
    {
        $this->app['config']->set('database.default', 'missing');
        $this->app['config']->set('maileclipse.preview_data', [RequiredArguments::class => ['count' => 0, 'enabled' => false, 'label' => '']]);
        $value = app(MailableResolver::class)->resolve(RequiredArguments::class);
        $this->assertSame(0, $value->count);
        $this->assertFalse($value->enabled);
        $this->assertSame('', $value->label);
        $this->assertSame([], $value->items);
        $this->assertSame('Preview customer', $value->customer->name);
        $this->assertFalse($value->customer->exists);
        $this->assertSame(PreviewState::Ready, $value->state);
        $this->assertTrue(is_int($value->reference) || is_string($value->reference));
    }
}
