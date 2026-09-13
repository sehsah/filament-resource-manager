# Signature compatibility check

Filament v3, v4 and v5 declare a few `Resource` members with different
signatures. PHP enforces override compatibility at class-load time, so a
mismatch is a fatal error the moment the panel boots — on one Filament major
only, which is easy to miss.

This check loads the package's classes against minimal stand-ins whose
signatures are copied verbatim from each Filament major's source, so PHP itself
does the verifying. It needs nothing installed but PHP.

```bash
for v in 3 4 5; do FIL_MAJOR=$v php tests/Compatibility/run.php; done
```

Known divergences covered here:

| Member | v3 | v4 / v5 |
| --- | --- | --- |
| `getNavigationIcon()` | `string\|Htmlable\|null` | `string\|BackedEnum\|Htmlable\|null` |
| `getNavigationGroup()` | `?string` | `string\|UnitEnum\|null` |
| `getSlug()` | no arguments | `?Panel $panel = null` |
| `form()` | `Form $form): Form` | `Schema $schema): Schema` |

The first three are handled by the per-major subclasses in `src/Filament/V3` and
`src/Filament/V4`. The fourth is sidestepped: the manager screen is edited
inline in the table and declares no form at all.

If you add a method to `BaseResourceSettingResource` that overrides something
Filament declares, re-run this check before releasing.
