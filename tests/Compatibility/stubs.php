<?php
// Minimal stand-ins whose signatures are copied verbatim from the Filament
// source for the requested major. Loading the package's classes against these
// makes PHP itself enforce override compatibility.

namespace Filament {
    class Panel {
        protected array $resources = [];
        public function __construct(protected string $id = 'admin') {}
        public function getId(): string { return $this->id; }
        public function getResources(): array { return $this->resources; }
        public function resources(array $resources): static { $this->resources = array_merge($this->resources, $resources); return $this; }
    }
}

namespace Filament\Support\Components { class ViewComponent { public static function make(...$a): static { return new static; } public function __call($m, $a) { return $this; } public static function __callStatic($m, $a) { return new static; } } }

namespace Filament\Tables {
    class Table { public function __call($m, $a) { return $this; } }
}
namespace Filament\Tables\Columns {
    class Column extends \Filament\Support\Components\ViewComponent {}
    class TextColumn extends Column {}
    class TextInputColumn extends Column {}
    class ToggleColumn extends Column {}
    class IconColumn extends Column {}
}
namespace Filament\Tables\Actions { class EditAction extends \Filament\Support\Components\ViewComponent {} }

namespace Filament\Forms\Components {
    class Component extends \Filament\Support\Components\ViewComponent {}
    class Field extends Component {}
    class TextInput extends Field {}
    class Toggle extends Field {}
    class Select extends Field {}
    class Repeater extends Field {}
    class Section extends Component {}
}
namespace Filament\Forms { class Form { public function schema(array $s): static { return $this; } } }

namespace Filament\Schemas { class Schema { public function components(array $c): static { return $this; } public function schema(array $c): static { return $this; } } }
/* FILAMENT_SCHEMA_SECTION_START */
namespace Filament\Schemas\Components { class Section extends \Filament\Support\Components\ViewComponent {} }
/* FILAMENT_SCHEMA_SECTION_END */

namespace Filament\Navigation {
    class NavigationItem { public function getUrl(): ?string { return null; } public function __call($m, $a) { return $this; } }
    class NavigationManager {
        protected \Filament\Panel $panel;
        protected bool $isNavigationMounted = false;
        protected array $navigationItems = [];
        public function get(): array { return []; }
        public function mountNavigation(): void { $this->isNavigationMounted = true; }
        public function getNavigationItems(): array { return $this->navigationItems; }
    }
}
namespace Filament\Contracts { interface Plugin { public function getId(): string; public function register(\Filament\Panel $p): void; public function boot(\Filament\Panel $p): void; } }
namespace Filament\Facades { class Filament { public static function getFacadeRoot() { return new \stdClass; } } }
namespace Filament\Actions { class Action extends \Filament\Support\Components\ViewComponent {} class EditAction extends \Filament\Support\Components\ViewComponent {} }
namespace Filament\Notifications { class Notification extends \Filament\Support\Components\ViewComponent {} }

namespace Filament\Resources\Pages {
    class PageRegistration {}
    class Page {
        public static function route(string $p): PageRegistration { return new PageRegistration; }
        public static function getResource(): string { return ''; }
        public function mount(): void {}
        public function getView(): string { return ''; }
        public function getTitle(): string { return ''; }
        public function getSubheading(): ?string { return null; }
        protected function getHeaderActions(): array { return []; }
    }
    class ListRecords extends Page {}
    class EditRecord extends Page {
        public function getRecord() { return null; }
        public function fillForm(): void {}
        protected function getRedirectUrl(): ?string { return null; }
    }
}
namespace Filament\Resources {
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Contracts\Support\Htmlable;

    abstract class Resource {
        public static function table(\Filament\Tables\Table $t): \Filament\Tables\Table { return $t; }
        public static function getEloquentQuery(): Builder { return new Builder; }
        public static function getUrl(string $name = 'index', array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string { return ''; }
        public static function getModel(): string { return ''; }
        public static function getModelLabel(): string { return ''; }
        public static function getPluralModelLabel(): string { return ''; }
        public static function getNavigationLabel(): string { return ''; }
        public static function getNavigationSort(): ?int { return null; }
        public static function shouldRegisterNavigation(): bool { return true; }
        public static function canAccess(): bool { return true; }
        public static function canViewAny(): bool { return true; }
        public static function canCreate(): bool { return true; }
        public static function canDelete(Model $record): bool { return true; }
        public static function canDeleteAny(): bool { return true; }
        public static function getPages(): array { return []; }

        // --- members that differ between majors (patched for v3 by run.php) ---
        /* FILAMENT_MAJOR_MEMBERS_START */
        public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema { return $schema; }
        public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null { return null; }
        public static function getNavigationGroup(): string|\UnitEnum|null { return null; }
        public static function getSlug(?\Filament\Panel $panel = null): string { return ''; }
        /* FILAMENT_MAJOR_MEMBERS_END */
    }
}
