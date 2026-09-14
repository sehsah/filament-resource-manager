<?php

namespace MahmoudSehsah\FilamentResourceManager\Filament;

use BladeUI\Icons\Factory;
use Filament\Forms\Components\Repeater;
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
use MahmoudSehsah\FilamentResourceManager\Support\FilamentVersion;
use MahmoudSehsah\FilamentResourceManager\Support\IconCatalog;
use MahmoudSehsah\FilamentResourceManager\Support\ModelCatalog;
use MahmoudSehsah\FilamentResourceManager\Support\ProfileManager;
use MahmoudSehsah\FilamentResourceManager\Support\ResourceDiscovery;
use MahmoudSehsah\FilamentResourceManager\Support\TableColumns;

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
        return FilamentResourceManagerPlugin::isAuthorized(ResourceDiscovery::panel());
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
            ->header(function () {
                $profile = ProfileManager::governingProfile(ResourceDiscovery::panelId());

                return $profile === null
                    ? null
                    : view('filament-resource-manager::components.profile-draft-alert', [
                        'profile' => $profile,
                    ]);
            })
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

                TextColumn::make('effective_navigation_group')
                    ->label(__('filament-resource-manager::manager.columns.group'))
                    ->placeholder(fn ($record): string => static::hasGroupOverrideColumn()
                        && $record->navigation_group_overridden
                        ? __('filament-resource-manager::manager.studio.ungrouped')
                        : '—')
                    ->badge()
                    ->sortable(['navigation_group']),

                TextColumn::make('sort')
                    ->label(__('filament-resource-manager::manager.columns.sort'))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('badge')
                    ->label(__('filament-resource-manager::manager.columns.badge'))
                    ->getStateUsing(fn ($record): ?string => ($record->badge_type ?? 'static') === 'dynamic'
                        ? __('filament-resource-manager::manager.badge_types.dynamic_short', [
                            'model' => class_basename($record->badge_model ?: ''),
                        ])
                        : $record->badge)
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

                    static::iconField('icon'),

                    static::iconField('active_icon'),

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
                        ->placeholder(fn ($record): ?string => $record?->default_navigation_group)
                        ->datalist(fn (): array => static::knownGroups())
                        ->prefixIcon(static::safeIcon('heroicon-o-folder'))
                        ->live(onBlur: true)
                        // Typing a group is itself an override; the toggle
                        // below is only needed to override it to "no group".
                        ->afterStateUpdated(function ($state, $set): void {
                            if (filled($state) && static::hasGroupOverrideColumn()) {
                                $set('navigation_group_overridden', true);
                            }
                        })
                        ->maxLength(255),

                    Toggle::make('navigation_group_overridden')
                        ->label(__('filament-resource-manager::manager.fields.group_overridden'))
                        ->helperText(__('filament-resource-manager::manager.fields.group_overridden_hint'))
                        // Saving a column the table does not have yet would be
                        // an SQL error, so the field waits for its migration.
                        ->visible(fn (): bool => static::hasGroupOverrideColumn())
                        ->dehydrated(fn (): bool => static::hasGroupOverrideColumn()),

                    Select::make('parent_resource_class')
                        ->label(__('filament-resource-manager::manager.fields.parent_item'))
                        ->helperText(__('filament-resource-manager::manager.fields.parent_item_hint'))
                        ->options(fn ($record): array => static::parentResourceOptions($record))
                        ->searchable()
                        ->preload()
                        ->native(false),

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
                    Select::make('badge_type')
                        ->label(__('filament-resource-manager::manager.fields.badge_type'))
                        ->options([
                            'static' => __('filament-resource-manager::manager.badge_types.static'),
                            'dynamic' => __('filament-resource-manager::manager.badge_types.dynamic'),
                        ])
                        ->default('static')
                        ->live()
                        ->native(false)
                        ->required(),

                    TextInput::make('badge')
                        ->label(__('filament-resource-manager::manager.fields.badge'))
                        ->helperText(__('filament-resource-manager::manager.fields.badge_static_hint'))
                        ->prefixIcon(static::safeIcon('heroicon-o-tag'))
                        ->maxLength(255)
                        ->visible(fn ($get): bool => ($get('badge_type') ?? 'static') === 'static'),

                    Select::make('badge_model')
                        ->label(__('filament-resource-manager::manager.fields.badge_model'))
                        ->helperText(__('filament-resource-manager::manager.fields.badge_model_hint'))
                        ->options(fn (): array => ModelCatalog::options())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->native(false)
                        ->required(fn ($get): bool => $get('badge_type') === 'dynamic')
                        ->visible(fn ($get): bool => $get('badge_type') === 'dynamic'),

                    Select::make('badge_color')
                        ->label(__('filament-resource-manager::manager.fields.badge_color'))
                        ->options(static::badgeColorOptions())
                        ->allowHtml()
                        ->native(false),

                    TextInput::make('badge_tooltip')
                        ->label(__('filament-resource-manager::manager.fields.badge_tooltip'))
                        ->prefixIcon(static::safeIcon('heroicon-o-chat-bubble-left-ellipsis'))
                        ->maxLength(255),

                    Repeater::make('badge_conditions')
                        ->label(__('filament-resource-manager::manager.fields.badge_conditions'))
                        ->helperText(__('filament-resource-manager::manager.fields.badge_conditions_hint'))
                        ->schema([
                            Select::make('column')
                                ->label(__('filament-resource-manager::manager.fields.badge_condition_column'))
                                ->options(fn ($get): array => ModelCatalog::columns($get('../../badge_model')))
                                ->searchable()
                                ->native(false)
                                ->required(),

                            Select::make('operator')
                                ->label(__('filament-resource-manager::manager.fields.badge_condition_operator'))
                                ->options(static::badgeOperatorOptions())
                                ->default('equals')
                                ->live()
                                ->native(false)
                                ->required(),

                            TextInput::make('value')
                                ->label(__('filament-resource-manager::manager.fields.badge_condition_value'))
                                ->visible(fn ($get): bool => ! in_array($get('operator'), [
                                    'is_null',
                                    'is_not_null',
                                    'is_true',
                                    'is_false',
                                ], true)),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->addActionLabel(__('filament-resource-manager::manager.fields.add_badge_condition'))
                        ->collapsible()
                        ->columnSpanFull()
                        ->visible(fn ($get): bool => $get('badge_type') === 'dynamic'),
                ])
                ->columns(3),
        ];
    }

    /**
     * An icon field: a searchable picker when the installed icon sets could be
     * enumerated, and the original free-text input with a live preview when they
     * could not. Falling back keeps a custom or unscannable icon set usable
     * instead of presenting an empty dropdown.
     */
    public static function iconField(string $name): mixed
    {
        $label = __("filament-resource-manager::manager.fields.{$name}");
        $helper = __("filament-resource-manager::manager.fields.{$name}_hint");

        $placeholder = $name === 'active_icon'
            ? fn ($record): ?string => $record?->icon ?: $record?->default_icon
            : fn ($record): ?string => $record?->default_icon;

        if (IconCatalog::names() === []) {
            return TextInput::make($name)
                ->label($label)
                ->helperText($helper)
                ->placeholder($placeholder)
                ->live(onBlur: true)
                ->prefixIcon(fn ($state): ?string => static::safeIcon($state)
                    ?? static::safeIcon('heroicon-o-sparkles'))
                ->maxLength(255);
        }

        return Select::make($name)
            ->label($label)
            ->helperText($helper)
            ->placeholder($placeholder)
            ->options(fn (): array => IconCatalog::options())
            ->searchable()
            ->getSearchResultsUsing(fn (?string $search): array => IconCatalog::search($search))
            ->getOptionLabelUsing(fn ($value): ?string => IconCatalog::label(
                is_string($value) ? $value : null,
            ))
            ->allowHtml()
            ->native(false);
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

        // v3 stores these custom properties as an "r, g, b" triplet, so the
        // value has to be wrapped in rgb(); v4 and v5 store a complete
        // oklch() colour, which must be used as-is. Wrapping an oklch() value
        // in rgb() is invalid CSS and renders nothing at all, which is what
        // this used to do on v4/v5.
        $swatchColor = FilamentVersion::isSchemaBased()
            ? fn (string $key): string => "var(--{$key}-500, var(--gray-500, #6b7280))"
            : fn (string $key): string => "rgb(var(--{$key}-500, var(--gray-500, 107, 114, 128)))";

        foreach ($colors as $key => $label) {
            $swatch = 'display:inline-block;width:0.75rem;height:0.75rem;border-radius:9999px;'
                .'margin-inline-end:0.5rem;vertical-align:middle;'
                .'background-color:'.$swatchColor($key).';';

            $options[$key] = '<span style="'.$swatch.'"></span><span style="vertical-align:middle;">'
                .e($label).'</span>';
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function badgeOperatorOptions(): array
    {
        return [
            'equals' => __('filament-resource-manager::manager.badge_operators.equals'),
            'not_equals' => __('filament-resource-manager::manager.badge_operators.not_equals'),
            'greater_than' => __('filament-resource-manager::manager.badge_operators.greater_than'),
            'greater_than_or_equal' => __('filament-resource-manager::manager.badge_operators.greater_than_or_equal'),
            'less_than' => __('filament-resource-manager::manager.badge_operators.less_than'),
            'less_than_or_equal' => __('filament-resource-manager::manager.badge_operators.less_than_or_equal'),
            'contains' => __('filament-resource-manager::manager.badge_operators.contains'),
            'starts_with' => __('filament-resource-manager::manager.badge_operators.starts_with'),
            'ends_with' => __('filament-resource-manager::manager.badge_operators.ends_with'),
            'is_null' => __('filament-resource-manager::manager.badge_operators.is_null'),
            'is_not_null' => __('filament-resource-manager::manager.badge_operators.is_not_null'),
            'is_true' => __('filament-resource-manager::manager.badge_operators.is_true'),
            'is_false' => __('filament-resource-manager::manager.badge_operators.is_false'),
        ];
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

        if (! class_exists(Factory::class)) {
            return static::$resolvedIcons[$icon] = null;
        }

        try {
            app(Factory::class)->svg($icon);

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
                ->get(['navigation_group', 'default_navigation_group'])
                ->flatMap(fn ($record): array => [
                    $record->navigation_group,
                    $record->default_navigation_group,
                ])
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, string> */
    public static function parentResourceOptions(?Model $record = null): array
    {
        try {
            return static::getEloquentQuery()
                ->when($record?->getKey(), fn ($query, $key) => $query->whereKeyNot($key))
                ->orderBy('sort')
                ->get()
                ->mapWithKeys(fn ($item): array => [
                    $item->resource_class => ($item->effective_label ?: class_basename($item->resource_class))
                        .' · '.class_basename($item->resource_class),
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function hasGroupOverrideColumn(): bool
    {
        return TableColumns::has(
            (new (static::getModel()))->getTable(),
            ['navigation_group_overridden'],
        );
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
