<?php

namespace MahmoudSehsah\FilamentResourceManager\Support;

use Illuminate\Database\Eloquent\Model;

class ProfileComparison
{
    /** Null selects the saved draft. Version IDs are always scoped to this profile. */
    public static function compare(Model $profile, ?int $from, ?int $to): array
    {
        [$before, $beforeRoles] = static::state($profile, $from);
        [$after, $afterRoles] = static::state($profile, $to);
        $changes = [];
        if (static::normalize($beforeRoles, 'roles') !== static::normalize($afterRoles, 'roles')) {
            $changes[] = ['resource' => null, 'type' => 'changed', 'field' => 'roles', 'before' => $beforeRoles, 'after' => $afterRoles];
        }
        $before = array_column($before, null, 'resource_class');
        $after = array_column($after, null, 'resource_class');
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $resource) {
            if (! isset($before[$resource]) || ! isset($after[$resource])) {
                $changes[] = ['resource' => $resource, 'type' => isset($after[$resource]) ? 'added' : 'removed', 'field' => 'resource_class', 'before' => $before[$resource] ?? null, 'after' => $after[$resource] ?? null];

                continue;
            }
            foreach (array_diff(ProfileManager::ITEM_ATTRIBUTES, ['resource_class', 'is_orphaned']) as $field) {
                $old = $before[$resource][$field] ?? null;
                $new = $after[$resource][$field] ?? null;
                if (static::normalize($old, $field) !== static::normalize($new, $field)) {
                    $changes[] = ['resource' => $resource, 'type' => 'changed', 'field' => $field, 'before' => $old, 'after' => $new];
                }
            }
        }

        return $changes;
    }

    protected static function state(Model $profile, ?int $versionId): array
    {
        if ($versionId === null) {
            return [ProfileManager::snapshot($profile), (array) $profile->roles];
        }
        $version = $profile->versions()->findOrFail($versionId);

        return [(array) $version->snapshot, (array) $version->roles];
    }

    protected static function normalize(mixed $value, string $field): mixed
    {
        if (in_array($field, ['roles', 'permissions'], true)) {
            $value = array_unique((array) $value);
            sort($value);
        }

        return $value;
    }

    public static function display(mixed $value): string
    {
        return $value === null ? '—' : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
