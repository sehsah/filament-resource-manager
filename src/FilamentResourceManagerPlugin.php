<?php

namespace MahmoudSehsah\FilamentResourceManager;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use MahmoudSehsah\FilamentResourceManager\Support\FilamentVersion;

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

    protected static ?self $instance = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): ?static
    {
        return static::$instance;
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
        static::$instance = $this;

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

    public static function isAuthorized(): bool
    {
        $plugin = static::get();

        if ($plugin?->authorizeUsing instanceof Closure) {
            return (bool) call_user_func($plugin->authorizeUsing);
        }

        $gate = config('filament-resource-manager.gate');

        if (is_string($gate) && $gate !== '') {
            return Gate::allows($gate);
        }

        return true;
    }
}
