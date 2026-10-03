# Filament Resource Manager

Let administrators rename, re-icon, regroup, reorder and hide the resources in a
Filament panel's navigation — without touching a single resource class.

Every user then sees the navigation the administrator configured.

Supports **Filament v3, v4 and v5** from one install.

---

## Installation

```bash
composer require sehsah/filament-resource-manager
php artisan vendor:publish --tag=filament-resource-manager-migrations
php artisan migrate
```

### Upgrading

New columns arrive as their own migrations, so an existing install re-publishes
and migrates:

```bash
php artisan vendor:publish --tag=filament-resource-manager-migrations
php artisan migrate
```

Publishing stamps each file with the time it was published, so this leaves you
with a second copy of migrations you already ran, under new names. Every
migration here is written to tolerate that: the create migration returns early
when its table exists, and each upgrade migration only adds the columns that are
missing. You can delete the duplicates afterwards, or leave them — `migrate` will
record them as run without touching the database either way.

The one thing not to do is roll them back: a duplicate of the create migration
still drops the table on `down()`.

Optionally publish the config and translations:

```bash
php artisan vendor:publish --tag=filament-resource-manager-config
php artisan vendor:publish --tag=filament-resource-manager-translations
```

## Register the plugin

In your panel provider:

```php
use MahmoudSehsah\FilamentResourceManager\FilamentResourceManagerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(
            FilamentResourceManagerPlugin::make()
                ->authorize(fn (): bool => auth()->user()?->is_admin ?? false)
        );
}
```

Without `authorize()`, the package falls back to the `gate` ability in the config
file, and if that is null, anyone who can reach the panel can open the manager.

## Usage

Open **Resource Manager** in the sidebar. The table lists every resource
registered on the current panel; **click any row** (or its edit button) to open
that resource's settings page.

The table itself gives you the overview and the two quickest actions: drag a row
to reorder it, and flip the **Visible** toggle to show or hide it.

The edit page exposes every attribute the package can apply:

| Field | Effect |
| --- | --- |
| **Name** | Replaces the sidebar label. Blank keeps the resource's own label. |
| **Icon** | Choose how the icon is given — see [Icon types](#icon-types). Blank keeps the resource's icon. |
| **Active icon** | Shown while that item is the current page, with the same four types. Defaults to the icon above. |
| **Visible in navigation** | Off hides the item from the sidebar. |
| **Navigation group** | Moves the item into a group. Existing groups are suggested. |
| **Override the group** | On with a group named above puts the item there; on with the field empty takes it out of every group. Off keeps whatever group the resource declares. |
| **Parent item** | Nests the item under another navigation item, by its label. |
| **Order** | Lower numbers first. Also set by dragging rows in the table. |
| **Badge type** | Choose static text or a live count from an Eloquent model. |
| **Badge model and conditions** | Count every record or add multiple column/operator/value conditions. All conditions are combined with AND. |
| **Badge colour / tooltip** | Style the static or dynamic badge and add optional explanatory text. |

### Icon types

Both icon fields have a type switch:

| Type | What you enter | How it renders |
| --- | --- | --- |
| **Icon** | Pick from the installed Blade Icons sets (searchable). | Named icon |
| **Code** | Type any icon name by hand, e.g. `heroicon-o-users` or `tabler-home`. | Named icon |
| **SVG** | Paste raw `<svg>` markup. Use `currentColor` so it follows the sidebar colour. | Inline SVG (an `<img>` on Filament releases whose `NavigationItem::icon()` only accepts strings) |
| **Image** | Upload a PNG, JPG, GIF, WebP or SVG file. | `<img>` from the configured disk |

Pasted SVG is sanitised before it is saved or rendered: scripts, event handlers,
`foreignObject`, external links and external `url()` references are removed,
and anything that is not a single well-formed `<svg>` element is rejected.
Uploaded images go to the `public` disk under `navigation-icons/` by default, so
run `php artisan storage:link` if you have not already. Both are configurable
under `icons` in the config file, as is the list of types offered.

**Reset to defaults** on the edit page clears that one resource; **Reset all** on
the table clears every resource. Both keep the ordering.

**Sync resources** picks up resources added since the last visit; it also runs
automatically when the page opens (`auto_sync` in the config).

### Navigation Studio

**Navigation Studio**, from the button above the table, is the same settings
seen as the sidebar: drag resources between groups, drop one onto another to
nest it, toggle visibility, and watch a live preview beside it. Every drop saves
a draft.

A studio layout belongs to a **profile**. A profile keeps a mutable draft and a
history of published versions:

- **Publish** freezes the current draft as a new version. An unrestricted
  profile becomes the panel default; a role-targeted profile is used only by
  users with one of its selected roles. Publish a default profile first.
- **Rollback** on any earlier version restores it into the draft and publishes
  it again as a new version.
- **Delete history** removes one old version or all old versions after
  confirmation. The current published version is always protected.
- **Clone** starts a second profile from the current one — an alternative layout
  you can build up and publish when it is ready. Select its roles in Studio and
  publish to make it live for those roles, while everyone else keeps the default.

The table and edit page, and the studio, are two views of the same navigation:

- Saving the edit page writes into the governing profile's draft as well. With
  `profiles.auto_publish` enabled (the default), the draft is immediately
  published as a new version. Disable it to publish changes manually.
- Saving a layout in the studio writes the placement — group, nesting, order,
  visibility — back to the settings table, so both screens keep showing the
  same thing.
- Other profiles are left alone; they are alternative layouts, not copies.

### Import and export profiles

In Navigation Studio, **Export draft as JSON** downloads the selected profile's
saved draft, including its audience, navigation overrides, icons, badges and
visibility rules. The file has a versioned format and uses resource class names
instead of database IDs, so it can move between environments.

Open **Import a profile**, choose a JSON file (up to 1 MB) or paste its contents,
then **Validate and preview**. Review the settings, choose a new profile name,
and select **Create imported draft**. Import always creates a new, unpublished
profile in the current panel; it does not replace or publish an existing profile,
even when automatic publishing is enabled. Publish separately when ready.

Resources, roles, permissions, and dynamic badge models/columns must exist in the
destination. Unknown entries, duplicate resources, invalid field values and
circular parent relationships are rejected. SVG markup is sanitized before the
preview. Resource defaults are resolved in the destination; resources omitted
from the file retain the destination panel's current settings in the new draft.
Uploaded image files are **not** bundled: copy referenced images to the configured
storage disk separately.

### Role preview

Enable **Preview as roles** beside the live preview and choose one or more roles.
The preview applies the selected saved draft's audience, visibility, and any/all
role and permission rules. **Hidden items and reasons** explains excluded items,
including those whose parent is hidden. This previews the selected draft, not the
published profile resolver's choice. An empty role selection represents an
authenticated user with no roles.

Permissions are loaded from Spatie roles for the panel's authentication guard.
For a custom role system, supply a simulation mapping:

```php
'access_control' => [
    'role_permissions' => [
        'manager' => ['view_orders', 'edit_orders'],
    ],
],
```

You can also select **Additional permissions** in the preview. This is a navigation
rule simulation: it does not impersonate a user, execute application policies, or
invoke custom user-specific role/permission resolvers. Dynamic badge counts still
use the current administrator's context. Previewing never changes saved settings.

### Version comparison

**Version comparison** shows changes between two published versions, or between a
published version and the saved draft. It starts with the current published
version versus the draft when one exists. Choose **Before** and **After** to
change the comparison. Added and removed resources, per-field before/after values,
and profile audience changes are included. Versions are scoped to the selected
profile, and comparison never modifies drafts or history.

Set `profiles.enabled` to `false` in the config to switch profiles off
entirely. Nothing has been published until you open the studio and publish, so
an install that never opens it behaves exactly as it did before.

From the command line:

```bash
php artisan filament-resource-manager:sync
php artisan filament-resource-manager:sync --panel=admin
```

Settings are stored per panel, so an `admin` panel and an `app` panel keep
separate navigation configurations.

Models used by resources on the current panel appear automatically in the
dynamic badge picker. To expose a model that has no Filament resource, add it to
the published config:

```php
'dynamic_badges' => [
    'models' => [
        App\Models\Ticket::class,
        App\Models\Invoice::class => 'Invoices',
    ],
],
```

Dynamic badge changes are copied into profile drafts and follow
`profiles.auto_publish`. When it is disabled, publish from Navigation Studio
when the new count is ready to become visible to users.

> **Hiding is a navigation setting, not an access control.** A hidden resource's
> URLs still work for anyone allowed to visit them. Use policies or
> `canAccess()` to actually restrict access.

### What is not covered

Only navigation is overridden. A resource's model label — the wording on its own
pages, breadcrumbs and buttons — is left alone, because the only way to change it
is to write `Resource::$modelLabel`, a `protected static` on the base Filament
class that resources share. Writing it would leak the value into every other
resource, so the package does not offer it.

## How it works

Filament resolves `Filament\Navigation\NavigationManager` from the container when
a panel builds its navigation. This package re-registers that binding with a
subclass, which mounts the navigation as usual — letting every resource register
its own item — and then rewrites those items before they are grouped and sorted.

Items are matched back to their resource by navigation item key where the
installed Filament exposes one, and otherwise by the URL the item points at.
Both are set from the resource itself, so the match holds on every version.

The obvious alternative, assigning `Resource::$navigationLabel` and friends, is
unsafe. Those are `protected static` properties on the base
`Filament\Resources\Resource` class, and a resource that does not redeclare one
shares the base class's storage, so writing it would leak that value into every
other resource. Navigation items are individual objects, so rewriting them has no
such crosstalk.

If anything fails — the migration has not run, the database is unreachable, a
resource throws while reporting its URL — navigation renders untouched rather
than breaking.

## Version compatibility

The package targets one codebase across three Filament majors. These APIs are
identical in v3, v4 and v5, and the package is built on them:

- `Filament\Contracts\Plugin` (byte-for-byte identical)
- `NavigationManager`, its container binding, and `NavigationItem`'s mutators
- `Table`, `TextColumn`, `IconColumn`, `ToggleColumn`, `reorderable()`
- `TextInput`, `Select` with `searchable()`/`allowHtml()`, `Toggle` (the `Section`
  layout component only moved namespace)
- `Filament\Actions\Action`, `ListRecords`/`EditRecord::getHeaderActions()`, `Page::route()`
- `Resource::canAccess()`, `canViewAny()`, `getNavigationSort()`

Four signatures did change in v4, and are handled by thin per-version subclasses
in `src/Filament/V3` and `src/Filament/V4` (v5 shares the v4 variant):

| | v3 | v4 / v5 |
| --- | --- | --- |
| `form()` | `Form $form): Form` | `Schema $schema): Schema` |
| `getNavigationIcon()` | `string\|Htmlable\|null` | `string\|BackedEnum\|Htmlable\|null` |
| `getNavigationGroup()` | `?string` | `string\|UnitEnum\|null` |
| `getSlug()` | no arguments | `?Panel $panel = null` |
| `Section` | `Filament\Forms\Components` | `Filament\Schemas\Components` |
| `EditAction` | `Filament\Tables\Actions` | `Filament\Actions` |

`Resource::form()` also changed — it receives a `Form` in v3 and a `Schema` in
v4/v5 — so each subclass declares its own `form()`, and both hand it the one
field definition in `BaseResourceSettingResource::formComponents()`. The two
classes that moved namespaces in v4, `Section` and the table's `EditAction`, are
resolved in `Support\Compat`. `Support\FilamentVersion` picks the right subclass
at runtime.

## Configuration

See `config/filament-resource-manager.php`:

- `table_name`, `model` — swap the table or extend the model
- `profiles` — turn navigation profiles on or off, and swap their tables or
  models
- `profiles.auto_publish` — on by default: a change saved in the Resource Manager republishes the
  governing profile at once. Turn it off to collect changes in the draft and publish from Navigation Studio
- `auto_sync` — sync on page open
- `cache` — overrides are cached and flushed on every write
- `icons` — which icon sets the picker offers, how many results a search returns,
  the catalogue's cache, which icon types are offered, the SVG size limit and
  where uploaded icon images are stored. The list is built by scanning the sets Blade Icons
  has registered, so call `IconCatalog::flush()` after installing a new one
- `excluded_resources` — resource classes the manager should ignore
- `gate` — an ability checked when no `authorize()` closure is set
- `navigation` — the manager's own icon, group, sort, slug and registration

## Testing locally

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
```

### Signature compatibility

PHP enforces override compatibility when a class loads, so a signature mismatch
is a fatal error the moment a panel boots — and only on one Filament major,
which is easy to miss. `tests/Compatibility` loads the package's classes against
stand-ins whose signatures are copied from each major's source, so PHP does the
checking. It needs nothing installed but PHP:

```bash
for v in 3 4 5; do FIL_MAJOR=$v php tests/Compatibility/run.php; done
```

Re-run it whenever you add a method that overrides something Filament declares.

Note that it only checks that classes load and that signatures line up — it
never calls a page's `mount()`, so a call to a method Filament does not declare
still needs the PHPUnit suite to catch it.

## License

MIT. See [LICENSE.md](LICENSE.md).
