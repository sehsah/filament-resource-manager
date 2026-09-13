<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use MahmoudSehsah\FilamentResourceManager\FilamentResourceManagerPlugin;
use MahmoudSehsah\FilamentResourceManager\Models\ResourceSetting;
use MahmoudSehsah\FilamentResourceManager\Support\Compat;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceDiscovery;

/**
 * The administration screen for resource navigation settings.
 *
 * Everything here uses APIs that are identical in Filament v3, v4 and v5. The
 * members whose signatures changed between v3 and v4 - getNavigationIcon(),
 * getNavigationGroup(), getSlug() and form() - are declared by the thin
 * version-specific subclasses in Filament\V3 and Filament\V4 instead. The form's
 * fields live here in formComponents(), so both subclasses share one definition.
 */
abstract class BaseResourceSettingResource extends Resource
{
    public static function getModel(): string
    {
        return config('filament-resource-manager.model', ResourceSetting::class);
    }

    public static function getModelLabel(): string
    {
        return __('filament-resource-manager::manager.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resource-manager::manager.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-resource-manager::manager.navigation_label');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-resource-manager.navigation.sort');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-resource-manager.navigation.register', true)
            && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return FilamentResourceManagerPlugin::isAuthorized();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Only the settings belonging to the panel currently being viewed, and only
     * rows whose resource class still exists.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('panel_id', ResourceDiscovery::panelId())
            ->where('is_orphaned', false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->recordUrl(fn (Model $record): string => static::getUrl('edit', ['record' => $record]))
            ->columns([
                IconColumn::make('effective_icon')
                    ->label(__('filament-resource-manager::manager.columns.icon'))
                    ->icon(fn ($record): ?string => $record->effective_icon),

                TextColumn::make('effective_label')
                    ->label(__('filament-resource-manager::manager.columns.label'))
                    ->description(fn ($record): string => class_basename($record->resource_class))
                    ->searchable(['label', 'default_label'])
                    ->sortable(['label']),

                TextColumn::make('navigation_group')
                    ->label(__('filament-resource-manager::manager.columns.group'))
                    ->placeholder('—')
                    ->badge()
                    ->sortable(),

                TextColumn::make('sort')
                    ->label(__('filament-resource-manager::manager.columns.sort'))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('badge')
                    ->label(__('filament-resource-manager::manager.columns.badge'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_visible')
                    ->label(__('filament-resource-manager::manager.columns.visible')),

                TextColumn::make('resource_class')
                    ->label(__('filament-resource-manager::manager.columns.class'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size('xs'),
            ])
            ->actions([
                Compat::editAction(),
            ])
            ->emptyStateHeading(__('filament-resource-manager::manager.empty.heading'))
            ->emptyStateDescription(__('filament-resource-manager::manager.empty.description'));
    }

    /**
     * Every attribute the package can apply to a resource's navigation item.
     *
     * Shared by the v3 and v4/v5 subclasses, which differ only in whether their
     * form() receives a Form or a Schema - both accept this array via schema().
     *
     * Only components that behave identically in v3, v4 and v5 are used here.
     * Section is the one class that moved namespace, and Compat resolves it.
     *
     * @return array<int, mixed>
     */
    public static function formComponents(): array
    {
        $section = Compat::sectionClass();

        return [
            // Spans the form so the resource class is not truncated, and so a
            // two-field panel does not sit beside a tall one leaving a gap.
            $section::make(__('filament-resource-manager::manager.sections.resource'))
                ->description(__('filament-resource-manager::manager.sections.resource_hint'))
                ->icon(static::safeIcon('heroicon-o-cube'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('resource_class')
                        ->label(__('filament-resource-manager::manager.columns.class'))
                        ->prefixIcon(static::safeIcon('heroicon-o-code-bracket'))
                        ->columnSpan(2)
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('default_label')
                        ->label(__('filament-resource-manager::manager.fields.default_label'))
                        ->prefixIcon(static::safeIcon('heroicon-o-identification'))
                        ->disabled()
                        ->dehydrated(false),
                ]),

            $section::make(__('filament-resource-manager::manager.sections.navigation'))
                ->icon(static::safeIcon('heroicon-o-bars-3'))
                ->schema([
                    TextInput::make('label')
                        ->label(__('filament-resource-manager::manager.fields.label'))
                        ->helperText(__('filament-resource-manager::manager.fields.label_hint'))
                        ->placeholder(fn ($record): ?string => $record?->default_label)
                        ->prefixIcon(static::safeIcon('heroicon-o-pencil-square'))
                        ->maxLength(255),

                    // The prefix renders whatever icon name is in the field, so
                    // an administrator sees the icon itself rather than a string
                    // they have to guess at. onBlur keeps it to one round trip
                    // per edit instead of one per keystroke.
                    TextInput::make('icon')
                        ->label(__('filament-resource-manager::manager.fields.icon'))
                        ->helperText(__('filament-resource-manager::manager.fields.icon_hint'))
                        ->placeholder(fn ($record): ?string => $record?->default_icon)
                        ->live(onBlur: true)
                        ->prefixIcon(fn ($state): ?string => static::safeIcon($state)
                            ?? static::safeIcon('heroicon-o-sparkles'))
                        ->maxLength(255),

                    TextInput::make('active_icon')
                        ->label(__('filament-resource-manager::manager.fields.active_icon'))
                        ->helperText(__('filament-resource-manager::manager.fields.active_icon_hint'))
                        ->placeholder(fn ($record): ?string => $record?->icon ?: $record?->default_icon)
                        ->live(onBlur: true)
                        ->prefixIcon(fn ($state): ?string => static::safeIcon($state)
                            ?? static::safeIcon('heroicon-o-sparkles'))
                        ->maxLength(255),

                    Toggle::make('is_visible')
                        ->label(__('filament-resource-manager::manager.fields.is_visible'))
                        ->helperText(__('filament-resource-manager::manager.fields.is_visible_hint'))
                        ->columnSpanFull(),
                ])
                ->columns(2),

            $section::make(__('filament-resource-manager::manager.sections.placement'))
                ->icon(static::safeIcon('heroicon-o-rectangle-group'))
                ->schema([
                    TextInput::make('navigation_group')
                        ->label(__('filament-resource-manager::manager.fields.group'))
                        ->helperText(__('filament-resource-manager::manager.fields.group_hint'))
                        ->datalist(fn (): array => static::knownGroups())
                        ->prefixIcon(static::safeIcon('heroicon-o-folder'))
                        ->maxLength(255),

                    TextInput::make('navigation_parent_item')
                        ->label(__('filament-resource-manager::manager.fields.parent_item'))
                        ->helperText(__('filament-resource-manager::manager.fields.parent_item_hint'))
                        ->prefixIcon(static::safeIcon('heroicon-o-bars-3-bottom-left'))
                        ->maxLength(255),

                    TextInput::make('sort')
                        ->label(__('filament-resource-manager::manager.fields.sort'))
                        ->helperText(__('filament-resource-manager::manager.fields.sort_hint'))
                        ->prefixIcon(static::safeIcon('heroicon-o-hashtag'))
                        ->numeric()
                        ->minValue(0)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            $section::make(__('filament-resource-manager::manager.sections.badge'))
                ->description(__('filament-resource-manager::manager.sections.badge_hint'))
                ->icon(static::safeIcon('heroicon-o-tag'))
                ->columnSpanFull()
                ->schema([
                    TextInput::make('badge')
                        ->label(__('filament-resource-manager::manager.fields.badge'))
                        ->prefixIcon(static::safeIcon('heroicon-o-tag'))
                        ->maxLength(255),

                    Select::make('badge_color')
                        ->label(__('filament-resource-manager::manager.fields.badge_color'))
                        ->options(static::badgeColorOptions())
                        ->allowHtml()
                        ->native(false),

                    TextInput::make('badge_tooltip')
                        ->label(__('filament-resource-manager::manager.fields.badge_tooltip'))
                        ->prefixIcon(static::safeIcon('heroicon-o-chat-bubble-left-ellipsis'))
                        ->maxLength(255),
                ])
                ->columns(3),
        ];
    }

    /**
     * The badge colours, each labelled with a swatch of the colour itself.
     *
     * The swatch reads the panel's own palette through the CSS custom properties
     * Filament emits (`--success-500` and friends, as an "r, g, b" triplet), so a
     * panel with a customised palette shows its real colours rather than colours
     * hardcoded here. `secondary` is not a default colour in every major, so it
     * falls back to gray rather than rendering as transparent.
     *
     * @return array<string, string>
     */
    public static function badgeColorOptions(): array
    {
        $colors = [
            'primary' => 'Primary',
            'secondary' => 'Secondary',
            'success' => 'Success',
            'warning' => 'Warning',
            'danger' => 'Danger',
            'info' => 'Info',
            'gray' => 'Gray',
        ];

        $options = [];

        foreach ($colors as $key => $label) {
            $swatch = 'display:inline-block;width:0.75rem;height:0.75rem;border-radius:9999px;'
                .'margin-inline-end:0.5rem;vertical-align:middle;'
                ."background-color:rgb(var(--{$key}-500, var(--gray-500)));";

            $options[$key] = '<span style="'.$swatch.'"></span><span style="vertical-align:middle;">'
                .e($label).'</span>';
        }

        return $options;
    }

    /**
     * An icon name, but only if it actually resolves.
     *
     * Blade Icons throws when asked for an icon that does not exist, and the
     * icon fields are rendered live from whatever has been typed so far - so an
     * unguarded preview would turn a half-typed name into a 500. Anything that
     * does not resolve degrades to no icon.
     *
     * @var array<string, string|null>
     */
    protected static array $resolvedIcons = [];

    public static function safeIcon(mixed $icon): ?string
    {
        if (! is_string($icon) || blank($icon)) {
            return null;
        }

        if (array_key_exists($icon, static::$resolvedIcons)) {
            return static::$resolvedIcons[$icon];
        }

        if (! class_exists(\BladeUI\Icons\Factory::class)) {
            return static::$resolvedIcons[$icon] = null;
        }

        try {
            app(\BladeUI\Icons\Factory::class)->svg($icon);

            return static::$resolvedIcons[$icon] = $icon;
        } catch (\Throwable) {
            return static::$resolvedIcons[$icon] = null;
        }
    }

    /**
     * Navigation groups already in use, offered as suggestions on the group
     * field so groups stay consistent instead of drifting on a typo.
     *
     * @return array<int, string>
     */
    public static function knownGroups(): array
    {
        try {
            return static::getEloquentQuery()
                ->whereNotNull('navigation_group')
                ->distinct()
                ->pluck('navigation_group')
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected static function configuredSlug(): string
    {
        return (string) config('filament-resource-manager.navigation.slug', 'resource-manager');
    }

    protected static function configuredIcon(): ?string
    {
        $icon = config('filament-resource-manager.navigation.icon', 'heroicon-o-squares-2x2');

        return is_string($icon) && $icon !== '' ? $icon : null;
    }

    protected static function configuredGroup(): ?string
    {
        $group = config('filament-resource-manager.navigation.group');

        return is_string($group) && $group !== '' ? $group : null;
    }
}
