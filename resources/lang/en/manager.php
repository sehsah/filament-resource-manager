<?php

return [

    'model_label' => 'Resource',
    'plural_model_label' => 'Resources',
    'navigation_label' => 'Resource Manager',

    'columns' => [
        'resource' => 'Resource',
        'label' => 'Name',
        'icon' => 'Icon',
        'group' => 'Group',
        'sort' => 'Order',
        'badge' => 'Badge',
        'visible' => 'Visible',
        'class' => 'Resource class',
    ],

    'sections' => [
        'resource' => 'Resource',
        'resource_hint' => 'Read-only. This is the resource these settings apply to.',
        'navigation' => 'Navigation',
        'placement' => 'Placement',
        'badge' => 'Badge',
        'badge_hint' => 'A static badge shown next to the item in the sidebar. Leave the text empty for no badge.',
    ],

    'fields' => [
        'default_label' => 'Default name',
        'label' => 'Name',
        'label_hint' => 'Leave empty to keep the name the resource defines itself.',
        'icon' => 'Icon',
        'icon_hint' => 'Search your installed icon sets. Empty keeps the resource icon.',
        'active_icon' => 'Active icon',
        'active_icon_hint' => 'Shown while the item is the current page. Empty uses the icon above.',
        'group' => 'Navigation group',
        'group_hint' => 'Empty keeps the resource group.',
        'parent_item' => 'Parent item',
        'parent_item_hint' => 'Nest under another resource. The relationship follows its class even when labels change.',
        'sort' => 'Order',
        'sort_hint' => 'Lower numbers appear first.',
        'is_visible' => 'Visible in navigation',
        'is_visible_hint' => 'Hiding only removes it from the sidebar. It does not block access to the URL.',
        'badge' => 'Badge text',
        'badge_color' => 'Badge colour',
        'badge_tooltip' => 'Badge tooltip',
    ],

    'actions' => [
        'studio' => 'Navigation Studio',
        'sync' => 'Sync resources',
        'reset' => 'Reset all',
        'reset_confirm' => 'This clears every custom name, icon, group, badge and visibility setting. Order is kept.',
        'reset_one' => 'Reset to defaults',
        'reset_one_confirm' => 'This clears every customisation for this resource. Order is kept.',
    ],

    'notifications' => [
        'synced' => 'Resources synced',
        'synced_body' => ':created added, :updated updated, :orphaned removed.',
        'reset' => 'Settings reset',
        'profile_created' => 'Profile created',
        'draft_saved' => 'Draft layout saved',
        'profile_published' => 'Profile published as version :version',
        'profile_rolled_back' => 'Rollback published as version :version',
    ],

    'studio' => [
        'profile' => 'Profile',
        'new_profile' => 'New profile',
        'profile_example' => 'Support, Accountant, Tenant…',
        'clone_profile' => 'Clone current',
        'publish' => 'Publish draft',
        'builder' => 'Navigation tree',
        'builder_hint' => 'Drag items between groups, onto another item to nest, or before an item to reorder.',
        'new_group' => 'New group',
        'group_name' => 'New group name',
        'add_group' => 'Add group',
        'saving' => 'Saving…',
        'hide' => 'Hide resource',
        'show' => 'Show resource',
        'ungrouped' => 'Ungrouped',
        'drop_here' => 'Drop a resource here',
        'drop_to_nest' => 'Drop here to nest under this resource',
        'live_preview' => 'Live draft preview',
        'preview' => 'Sidebar preview',
        'preview_hint' => 'This preview changes immediately. Users continue seeing the published version.',
        'history' => 'Version history',
        'history_hint' => 'Publishing and rollback always create an immutable version.',
        'current' => 'Current',
        'rollback' => 'Rollback',
        'rollback_confirm' => 'Restore this version and publish it as a new version?',
        'no_versions' => 'This profile has not been published yet.',
        'migration_required' => 'Navigation profiles are unavailable. Publish and run the package migrations first.',
        'invalid_profile_name' => 'Enter a profile name up to 255 characters.',
    ],

    'empty' => [
        'heading' => 'No resources found',
        'description' => 'Use "Sync resources" to pull in the resources registered on this panel.',
    ],

];
