<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use BladeUI\Icons\Factory;
use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * The icons available to pick from, read out of the icon sets Blade Icons has
 * registered - so the list is whatever the application actually installed,
 * Heroicons or otherwise, with no icon names hardcoded here.
 *
 * Blade Icons resolves a name by stripping the set's prefix and turning the
 * remainder's dots into directory separators, so `heroicon-o-users` is
 * `o-users.svg` under the heroicons path. This walks that mapping backwards:
 * every .svg under a registered path becomes the name that resolves to it.
 *
 * Scanning touches a few thousand files, so the result is cached and memoised.
 * Every failure path returns an empty list, and the form falls back to a plain
 * text field when that happens rather than offering an empty picker.
 */
class IconCatalog
{
    /** @var array<int, string>|null */
    protected static ?array $names = null;

    /** @var array<string, string> */
    protected static array $rendered = [];

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        if (static::$names !== null) {
            return static::$names;
        }

        $resolve = static fn (): array => static::scan();

        if (! config('filament-resource-manager.icons.cache', true)) {
            return static::$names = $resolve();
        }

        try {
            return static::$names = Cache::store(config('filament-resource-manager.cache.store'))
                ->remember(
                    (string) config('filament-resource-manager.icons.cache_key', 'filament-resource-manager.icons'),
                    (int) config('filament-resource-manager.icons.cache_ttl', 86400),
                    $resolve,
                );
        } catch (Throwable) {
            return static::$names = $resolve();
        }
    }

    /**
     * Clears both the in-process memo and the cached catalogue. Worth calling
     * from a deploy hook if an application adds an icon set.
     */
    public static function flush(): void
    {
        static::$names = null;
        static::$rendered = [];

        try {
            Cache::store(config('filament-resource-manager.cache.store'))
                ->forget((string) config('filament-resource-manager.icons.cache_key', 'filament-resource-manager.icons'));
        } catch (Throwable) {
            // A missing cache store must not turn into an error.
        }
    }

    /**
     * The starting list, shown before anything is typed.
     *
     * @return array<string, string>
     */
    public static function options(?int $limit = null): array
    {
        return static::label(array_slice(static::names(), 0, static::limit($limit)));
    }

    /**
     * Names matching a search term, best first.
     *
     * Ranking has to work around the fact that every name begins with its set's
     * prefix, so nothing a person types ever starts the string: searching "user"
     * would otherwise return plain alphabetical order, burying `heroicon-o-user`
     * beneath `heroicon-c-users`. Matches are ranked on hyphen boundaries
     * instead - a whole segment beats the start of a segment, which beats a
     * match anywhere - and shorter names win inside a tier, which reliably
     * floats the plain icon above its variants.
     *
     * @return array<string, string>
     */
    public static function search(?string $term, ?int $limit = null): array
    {
        $needle = mb_strtolower(trim((string) $term));

        if ($needle === '') {
            return static::options($limit);
        }

        $tiers = [[], [], [], []];

        foreach (static::names() as $name) {
            $haystack = mb_strtolower($name);
            $padded = '-'.$haystack.'-';

            $tier = match (true) {
                $haystack === $needle => 0,
                str_contains($padded, '-'.$needle.'-') => 1,
                str_contains($padded, '-'.$needle) => 2,
                str_contains($haystack, $needle) => 3,
                default => null,
            };

            if ($tier !== null) {
                $tiers[$tier][] = $name;
            }
        }

        $matches = [];

        foreach ($tiers as $tier) {
            usort($tier, static fn (string $a, string $b): int => [mb_strlen($a), $a] <=> [mb_strlen($b), $b]);

            $matches = [...$matches, ...$tier];
        }

        return static::label(array_slice($matches, 0, static::limit($limit)));
    }

    /**
     * One name, or a list of them, rendered as the icon beside its name.
     *
     * @param  string|array<int, string>|null  $name
     * @return ($name is array ? array<string, string> : string|null)
     */
    public static function label(string|array|null $name): string|array|null
    {
        if (is_array($name)) {
            $labels = [];

            foreach ($name as $one) {
                $labels[$one] = (string) static::label($one);
            }

            return $labels;
        }

        if (! is_string($name) || $name === '') {
            return null;
        }

        if (array_key_exists($name, static::$rendered)) {
            return static::$rendered[$name];
        }

        $svg = '';

        try {
            $svg = app(Factory::class)
                ->svg($name, '', ['style' => 'width:1.25rem;height:1.25rem;flex-shrink:0;'])
                ->toHtml();
        } catch (Throwable) {
            // An icon that will not render still gets its name listed.
            $svg = '';
        }

        return static::$rendered[$name] = '<span style="display:inline-flex;align-items:center;gap:0.5rem;">'
            .$svg
            .'<span>'.e($name).'</span></span>';
    }

    /**
     * @return array<int, string>
     */
    protected static function scan(): array
    {
        if (! class_exists(Factory::class)) {
            return [];
        }

        try {
            $factory = app(Factory::class);

            if (! method_exists($factory, 'all')) {
                return [];
            }

            $only = array_filter((array) config('filament-resource-manager.icons.sets', []));
            $max = (int) config('filament-resource-manager.icons.max', 5000);
            $names = [];

            foreach ($factory->all() as $set) {
                $prefix = is_array($set) ? ($set['prefix'] ?? null) : null;

                if (! is_string($prefix) || $prefix === '') {
                    continue;
                }

                if ($only !== [] && ! in_array($prefix, $only, true)) {
                    continue;
                }

                foreach (static::pathsOf($set) as $path) {
                    foreach (static::relativeSvgPaths($path) as $relative) {
                        $names[] = $prefix.'-'.str_replace('/', '.', $relative);

                        if (count($names) >= $max) {
                            break 3;
                        }
                    }
                }
            }

            $names = array_values(array_unique($names));
            sort($names);

            return $names;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $set
     * @return array<int, string>
     */
    protected static function pathsOf(array $set): array
    {
        $paths = $set['paths'] ?? $set['path'] ?? [];

        return array_values(array_filter(
            (array) $paths,
            static fn ($path): bool => is_string($path) && is_dir($path),
        ));
    }

    /**
     * Every .svg beneath a path, relative to it and without the extension.
     *
     * @return array<int, string>
     */
    protected static function relativeSvgPaths(string $path): array
    {
        $root = rtrim($path, '/\\');
        $found = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || mb_strtolower($file->getExtension()) !== 'svg') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($root) + 1, -4);

                if ($relative !== false && $relative !== '') {
                    $found[] = str_replace('\\', '/', $relative);
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $found;
    }

    protected static function limit(?int $limit): int
    {
        $limit ??= (int) config('filament-resource-manager.icons.limit', 50);

        return max(1, $limit);
    }
}
