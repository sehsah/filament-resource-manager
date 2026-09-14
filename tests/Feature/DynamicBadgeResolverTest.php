<?php

namespace MahmoudSehsah\FilamentResourceManager\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use MahmoudSehsah\FilamentResourceManager\Support\DynamicBadgeResolver;
use MahmoudSehsah\FilamentResourceManager\Support\ModelCatalog;
use MahmoudSehsah\FilamentResourceManager\Tests\Fixtures\BadgeRecord;
use MahmoudSehsah\FilamentResourceManager\Tests\TestCase;

class DynamicBadgeResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filament-resource-manager.dynamic_badges.models', [
            BadgeRecord::class => 'Badge records',
        ]);

        Schema::create('badge_records', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
            $table->unsignedInteger('priority')->default(0);
            $table->timestamp('closed_at')->nullable();
        });

        BadgeRecord::query()->insert([
            ['status' => 'pending', 'priority' => 1, 'closed_at' => null],
            ['status' => 'pending', 'priority' => 5, 'closed_at' => null],
            ['status' => 'closed', 'priority' => 5, 'closed_at' => now()],
        ]);
    }

    public function test_it_counts_a_model_using_multiple_safe_conditions(): void
    {
        $badge = DynamicBadgeResolver::resolve([
            'badge_type' => 'dynamic',
            'badge_model' => BadgeRecord::class,
            'badge_conditions' => [
                ['column' => 'status', 'operator' => 'equals', 'value' => 'pending'],
                ['column' => 'priority', 'operator' => 'greater_than', 'value' => 2],
                ['column' => 'closed_at', 'operator' => 'is_null', 'value' => null],
            ],
        ]);

        $this->assertSame(1, $badge);
    }

    public function test_it_counts_every_record_when_conditions_are_empty(): void
    {
        $this->assertSame(3, DynamicBadgeResolver::resolve([
            'badge_type' => 'dynamic',
            'badge_model' => BadgeRecord::class,
            'badge_conditions' => [],
        ]));
    }

    public function test_it_rejects_columns_that_do_not_belong_to_the_selected_model(): void
    {
        $this->assertNull(DynamicBadgeResolver::resolve([
            'badge_type' => 'dynamic',
            'badge_model' => BadgeRecord::class,
            'badge_conditions' => [
                ['column' => 'not_a_column', 'operator' => 'equals', 'value' => 'x'],
            ],
        ]));
    }

    public function test_model_catalog_exposes_configured_models_and_real_columns(): void
    {
        $this->assertSame('Badge records', ModelCatalog::options()[BadgeRecord::class]);
        $this->assertArrayHasKey('status', ModelCatalog::columns(BadgeRecord::class));
    }
}
