<?php

namespace MahmoudSehsah\FilamentResourceManager;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use MahmoudSehsah\FilamentResourceManager\Support\FilamentVersion;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceDiscovery;

/**
 * Register on a panel:
 *
 *     use MahmoudSehsah\FilamentResourceManager\FilamentResourceManagerPlugin;
 *
 *     $panel->plugin(
 *         FilamentResourceManagerPlugin::make()
 *             ->authorize(fn (): bool => auth()->user()?->is_admin ?? false)
 *     );
 *
 * The Filament\Contracts\Plugin interface is byte-for-byte identical in v3, v4
 * and v5, so this class needs no version handling.
 */
class FilamentResourceManagerPlugin implements Plugin
{
    protected ?Closure $authorizeUsing = null;

    /** @var array<string, self> */
    protected static array $instances = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(?Panel $panel = null): ?static
    {
        $panel ??= ResourceDiscovery::panel();

        if ($panel instanceof Panel) {
            $plugin = static::$instances[$panel->getId()] ?? null;

            return $plugin instanceof self ? $plugin : null;
        }

        if (count(static::$instances) !== 1) {
            return null;
        }

        $plugin = reset(static::$instances);

        return $plugin instanceof self ? $plugin : null;
    }

    public function getId(): string
    {
        return 'filament-resource-manager';
    }

    /**
     * Decide who may open the manager. Without a callback the package falls back
     * to the "gate" ability in the config file, and failing that allows anyone
     * who can already reach the panel.
     */
    public function authorize(?Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function register(Panel $panel): void
    {
        static::$instances[$panel->getId()] = $this;

        $panel->resources([
            static::resourceClass(),
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * The manager resource for the installed Filament major.
     *
     * @return class-string
     */
    public static function resourceClass(): string
    {
        return FilamentVersion::isSchemaBased()
            ? Filament\V4\ResourceSettingResource::class
            : Filament\V3\ResourceSettingResource::class;
    }

    public static function isAuthorized(?Panel $panel = null): bool
    {
        $plugin = static::get($panel);

        if ($plugin?->authorizeUsing instanceof Closure) {
            return (bool) call_user_func($plugin->authorizeUsing);
        }

        // If plugins were registered but none belongs to the current panel,
        // fail closed instead of borrowing another panel's authorization rule.
        if ($plugin === null && static::$instances !== []) {
            return false;
        }

        $gate = config('filament-resource-manager.gate');

        if (is_string($gate) && $gate !== '') {
            return Gate::allows($gate);
        }

        return true;
    }
}
