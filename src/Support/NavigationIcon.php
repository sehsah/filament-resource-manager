<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Filament\Navigation\NavigationItem;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;

/**
 * A navigation icon stored in one of four forms.
 *
 * - icon:  picked from the installed Blade Icons sets, e.g. heroicon-o-users
 * - code:  an icon name typed by hand, for sets the picker cannot enumerate
 * - svg:   raw SVG markup, sanitised before it is ever rendered
 * - image: an uploaded image (or an absolute URL) shown as an <img>
 *
 * Each icon slot ("icon" and "active_icon") keeps its payload in sibling
 * columns: `{slot}` holds an icon name, `{slot}_svg` the markup and
 * `{slot}_image` the stored path, with `{slot}_type` saying which is live. A
 * row without a type - anything saved before this existed - reads as "icon",
 * so older data keeps behaving exactly as it did.
 */
class NavigationIcon
{
    public const TYPE_ICON = 'icon';

    public const TYPE_CODE = 'code';

    public const TYPE_SVG = 'svg';

    public const TYPE_IMAGE = 'image';

    public const TYPES = [
        self::TYPE_ICON,
        self::TYPE_CODE,
        self::TYPE_SVG,
        self::TYPE_IMAGE,
    ];

    public const SLOTS = ['icon', 'active_icon'];

    /**
     * The columns this feature adds, beside the original `icon` and
     * `active_icon` name columns.
     */
    public const ATTRIBUTES = [
        'icon_type',
        'icon_svg',
        'icon_image',
        'active_icon_type',
        'active_icon_svg',
        'active_icon_image',
    ];

    /**
     * SVG elements that draw something. Anything else - script, foreignObject,
     * style, animation, embedded images, links - is removed with its children.
     */
    protected const SVG_ELEMENTS = [
        'svg', 'g', 'path', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'rect', 'defs', 'lineargradient', 'radialgradient', 'stop', 'clippath',
        'mask', 'use', 'symbol', 'title', 'desc', 'text', 'tspan', 'pattern',
    ];

    protected static ?bool $supportsHtmlable = null;

    /** @var array<string, string|null> */
    protected static array $sanitized = [];

    /**
     * The icon types an administrator may choose from, in display order.
     *
     * @return array<int, string>
     */
    public static function enabledTypes(): array
    {
        $configured = array_values(array_intersect(
            (array) config('filament-resource-manager.icons.types', self::TYPES),
            self::TYPES,
        ));

        return $configured === [] ? [self::TYPE_ICON] : $configured;
    }

    public static function normalizeType(mixed $type): string
    {
        return is_string($type) && in_array($type, self::TYPES, true) ? $type : self::TYPE_ICON;
    }

    /**
     * @param  array<string, mixed>|object  $source  a model, or an override array
     */
    public static function type(array|object $source, string $slot = 'icon'): string
    {
        return static::normalizeType(data_get($source, "{$slot}_type"));
    }

    /**
     * The stored payload of whichever type is live: an icon name, SVG markup,
     * or an image path.
     *
     * @param  array<string, mixed>|object  $source
     */
    public static function value(array|object $source, string $slot = 'icon'): ?string
    {
        $value = match (static::type($source, $slot)) {
            self::TYPE_SVG => data_get($source, "{$slot}_svg"),
            self::TYPE_IMAGE => data_get($source, "{$slot}_image"),
            default => data_get($source, $slot),
        };

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param  array<string, mixed>|object  $source */
    public static function hasOverride(array|object $source, string $slot = 'icon'): bool
    {
        return static::value($source, $slot) !== null;
    }

    /**
     * What to hand NavigationItem::icon() / activeIcon(), or null to leave the
     * resource's own icon in place.
     *
     * @param  array<string, mixed>|object  $source
     */
    public static function resolve(array|object $source, string $slot = 'icon'): string|Htmlable|null
    {
        $value = static::value($source, $slot);

        if ($value === null) {
            return null;
        }

        return match (static::type($source, $slot)) {
            self::TYPE_SVG => static::svgIcon($value),
            self::TYPE_IMAGE => static::imageUrl($value),
            default => $value,
        };
    }

    /**
     * Inline markup for previewing an icon in the manager's own screens - the
     * table and the Navigation Studio - falling back to the resource's default
     * icon when nothing is overridden.
     *
     * @param  array<string, mixed>|object  $source
     */
    public static function previewHtml(array|object $source, string $slot = 'icon', ?string $fallback = null): ?string
    {
        $value = static::value($source, $slot);

        if ($value === null) {
            return filled($fallback) ? static::previewName($fallback) : null;
        }

        return match (static::type($source, $slot)) {
            self::TYPE_SVG => static::sanitizeSvg($value),
            self::TYPE_IMAGE => static::imageTag(static::imageUrl($value)),
            default => static::previewName($value),
        };
    }

    /**
     * Blank out the payloads of the types that are not live, so switching an
     * item from SVG back to a named icon does not leave stale markup behind.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function normalize(array $attributes, string $slot = 'icon'): array
    {
        $type = static::type($attributes, $slot);

        return [
            $slot => in_array($type, [self::TYPE_ICON, self::TYPE_CODE], true)
                ? static::blankToNull($attributes[$slot] ?? null)
                : null,
            "{$slot}_svg" => $type === self::TYPE_SVG
                ? static::blankToNull($attributes["{$slot}_svg"] ?? null)
                : null,
            "{$slot}_image" => $type === self::TYPE_IMAGE
                ? static::blankToNull($attributes["{$slot}_image"] ?? null)
                : null,
        ];
    }

    /**
     * Apply normalize() to a model about to be saved. Skipped for a table
     * that has not been migrated yet, where writing the new columns would be
     * an SQL error.
     */
    public static function normalizeModel(Model $model): void
    {
        foreach (self::SLOTS as $slot) {
            if (! TableColumns::has($model->getTable(), ["{$slot}_type", "{$slot}_svg", "{$slot}_image"])) {
                continue;
            }

            foreach (static::normalize($model->getAttributes(), $slot) as $key => $value) {
                if ($model->getAttribute($key) !== $value) {
                    $model->setAttribute($key, $value);
                }
            }
        }
    }

    /**
     * The icon columns of a row, for copying between the settings table and
     * profile items.
     *
     * @return array<string, mixed>
     */
    public static function copyValues(?object $row): array
    {
        $values = [];

        foreach (self::ATTRIBUTES as $attribute) {
            $values[$attribute] = $row?->{$attribute};
        }

        return $values;
    }

    /**
     * SVG markup with everything executable stripped, sized to fill whatever
     * box renders it. Null when the markup is not a well-formed <svg>.
     */
    public static function sanitizeSvg(?string $markup): ?string
    {
        if (! is_string($markup) || trim($markup) === '') {
            return null;
        }

        $markup = trim($markup);
        $key = md5($markup);

        if (array_key_exists($key, static::$sanitized)) {
            return static::$sanitized[$key];
        }

        return static::$sanitized[$key] = static::sanitizeUncached($markup);
    }

    public static function imageUrl(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        try {
            return Storage::disk(static::uploadDisk())->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    public static function uploadDisk(): string
    {
        return (string) (config('filament-resource-manager.icons.upload.disk') ?: 'public');
    }

    public static function uploadDirectory(): string
    {
        return (string) (config('filament-resource-manager.icons.upload.directory') ?: 'navigation-icons');
    }

    public static function uploadMaxSize(): int
    {
        return (int) (config('filament-resource-manager.icons.upload.max_size') ?: 1024);
    }

    public static function svgMaxLength(): int
    {
        return (int) (config('filament-resource-manager.icons.svg_max_length') ?: 50000);
    }

    public static function flush(): void
    {
        static::$sanitized = [];
        static::$supportsHtmlable = null;
    }

    /**
     * Filament v4 and v5 render an Htmlable icon inline, so the SVG inherits
     * the sidebar's text colour. Filament releases whose NavigationItem only
     * takes a string get the same SVG as a data URI, which they render as an
     * <img> - same shape, fixed colours.
     */
    protected static function svgIcon(string $markup): string|Htmlable|null
    {
        $svg = static::sanitizeSvg($markup);

        if ($svg === null) {
            return null;
        }

        if (static::navigationSupportsHtmlable()) {
            return new HtmlString($svg);
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    protected static function navigationSupportsHtmlable(): bool
    {
        if (static::$supportsHtmlable !== null) {
            return static::$supportsHtmlable;
        }

        try {
            $type = (new ReflectionMethod(NavigationItem::class, 'icon'))->getParameters()[0]->getType();
            $names = match (true) {
                $type instanceof ReflectionUnionType => array_map(
                    fn ($named): string => $named instanceof ReflectionNamedType ? $named->getName() : '',
                    $type->getTypes(),
                ),
                $type instanceof ReflectionNamedType => [$type->getName()],
                default => [Htmlable::class],
            };

            return static::$supportsHtmlable = in_array(Htmlable::class, $names, true);
        } catch (Throwable) {
            return static::$supportsHtmlable = false;
        }
    }

    protected static function previewName(string $name): ?string
    {
        if (str_contains($name, '/')) {
            return static::imageTag(static::imageUrl($name) ?? $name);
        }

        if (! function_exists('svg')) {
            return null;
        }

        try {
            return svg($name, '', ['style' => 'width:100%;height:100%'])->toHtml();
        } catch (Throwable) {
            return null;
        }
    }

    protected static function imageTag(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return '<img src="'.e($url).'" alt="" style="width:100%;height:100%;object-fit:contain;" />';
    }

    protected static function sanitizeUncached(string $markup): ?string
    {
        // A DOCTYPE is the doorway to entity expansion and external entities.
        // No icon needs one.
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $markup) || ! class_exists(DOMDocument::class)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;

            if (! $document->loadXML($markup, LIBXML_NONET)) {
                return null;
            }

            $root = $document->documentElement;

            if (! $root instanceof DOMElement || strtolower($root->localName) !== 'svg') {
                return null;
            }

            static::cleanNode($root);

            // Size to the icon box rather than to whatever the source declared.
            if (! $root->hasAttribute('viewBox')
                && is_numeric($root->getAttribute('width'))
                && is_numeric($root->getAttribute('height'))) {
                $root->setAttribute('viewBox', '0 0 '.$root->getAttribute('width').' '.$root->getAttribute('height'));
            }

            $root->setAttribute('width', '100%');
            $root->setAttribute('height', '100%');
            $root->setAttribute('aria-hidden', 'true');

            if (! $root->hasAttribute('xmlns')) {
                $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            }

            $svg = $document->saveXML($root);

            return is_string($svg) && $svg !== '' ? $svg : null;
        } catch (Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    protected static function cleanNode(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $local = strtolower($attribute->localName);
            $value = trim((string) $attribute->nodeValue);

            // Only same-document references - url(#gradient), href="#shape" -
            // may point anywhere.
            $unsafe = str_starts_with($local, 'on')
                || ($local === 'href' && ! str_starts_with($value, '#'))
                || preg_match('/url\s*\(\s*[\'"]?\s*[^\'"#\s)]/i', $value)
                || preg_match('/^\s*(javascript|data|vbscript):/i', $value)
                || ($local === 'style' && preg_match('/expression|javascript:|@import/i', $value));

            if ($unsafe || $name === 'xml:base') {
                $element->removeAttributeNode($attribute);
            }
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            /** @var DOMNode $child */
            if ($child instanceof DOMElement) {
                if (! in_array(strtolower($child->localName), self::SVG_ELEMENTS, true)) {
                    $element->removeChild($child);

                    continue;
                }

                static::cleanNode($child);

                continue;
            }

            // Text is fine; comments, CDATA and processing instructions are not needed.
            if ($child->nodeType !== XML_TEXT_NODE) {
                $element->removeChild($child);
            }
        }
    }

    protected static function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
