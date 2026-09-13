<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds attributes introduced after the first release.
 *
 * Fresh installations already receive these columns from the create migration.
 * The guards keep this migration safe for both fresh and upgraded databases.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    protected array $columns = [
        'active_icon',
        'navigation_parent_item',
        'badge',
        'badge_color',
        'badge_tooltip',
        'default_navigation_group',
    ];

    public function up(): void
    {
        $table = $this->tableName();

        if (! Schema::hasTable($table)) {
            return;
        }

        $missing = $this->missingColumns($table);

        if ($missing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing): void {
            foreach ($missing as $column) {
                $blueprint->string($column)->nullable();
            }
        });
    }

    public function down(): void
    {
        $table = $this->tableName();

        if (! Schema::hasTable($table)) {
            return;
        }

        $present = array_values(array_diff($this->columns, $this->missingColumns($table)));

        if ($present === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($present): void {
            $blueprint->dropColumn($present);
        });
    }

    /**
     * @return array<int, string>
     */
    protected function missingColumns(string $table): array
    {
        return array_values(array_filter(
            $this->columns,
            fn (string $column): bool => ! Schema::hasColumn($table, $column),
        ));
    }

    protected function tableName(): string
    {
        return config('filament-resource-manager.table_name', 'filament_resource_settings');
    }
};
