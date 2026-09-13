<?php

namespace MahmoudSehsah\FilamentResourceManager;

use Filament\Navigation\NavigationManager;
use Illuminate\Support\ServiceProvider;
use MahmoudSehsah\FilamentResourceManager\Commands\SyncResourcesCommand;
use MahmoudSehsah\FilamentResourceManager\Navigation\ManagedNavigationManager;

class FilamentResourceManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-resource-manager.php',
            'filament-resource-manager',
        );
    }

    public function boot(): void
    {
        $this->bindNavigationManager();
        $this->registerTranslations();
        $this->registerPublishing();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncResourcesCommand::class,
            ]);
        }
    }

    /**
     * Filament registers Filament\Navigation\NavigationManager as a scoped
     * binding and resolves it from the container when a panel builds its
     * navigation. Re-registering it with the same lifetime swaps in the subclass
     * that applies the stored overrides.
     *
     * This runs in boot() so it lands after Filament's own service provider,
     * and long before anything renders navigation.
     */
    protected function bindNavigationManager(): void
    {
        if (! class_exists(NavigationManager::class)) {
            return;
        }

        $this->app->scoped(NavigationManager::class, fn (): ManagedNavigationManager => new ManagedNavigationManager);
    }

    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-resource-manager');
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/filament-resource-manager.php' => config_path('filament-resource-manager.php'),
        ], 'filament-resource-manager-config');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->langPath('vendor/filament-resource-manager'),
        ], 'filament-resource-manager-translations');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'filament-resource-manager-migrations');
    }

    protected function langPath(string $path): string
    {
        return function_exists('lang_path')
            ? lang_path($path)
            : resource_path('lang/'.$path);
    }

    /**
     * publishesMigrations() arrived in Laravel 11. Older versions fall back to
     * a plain publish.
     */
    protected function publishesMigrations(array $paths, $groups = null): void
    {
        if (method_exists(ServiceProvider::class, 'publishesMigrations')) {
            parent::publishesMigrations($paths, $groups);

            return;
        }

        $this->publishes($paths, $groups);
    }
}
