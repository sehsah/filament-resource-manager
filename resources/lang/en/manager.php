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
        'icon_hint' => 'Any icon name, for example heroicon-o-users. Empty keeps the resource icon.',
        'active_icon' => 'Active icon',
        'active_icon_hint' => 'Shown while the item is the current page. Defaults to the icon above.',
        'group' => 'Navigation group',
        'group_hint' => 'Empty keeps the resource group.',
        'parent_item' => 'Parent item',
        'parent_item_hint' => 'Nest under another navigation item by its label.',
        'sort' => 'Order',
        'sort_hint' => 'Lower numbers appear first.',
        'is_visible' => 'Visible in navigation',
        'is_visible_hint' => 'Hiding only removes it from the sidebar. It does not block access to the URL.',
        'badge' => 'Badge text',
        'badge_color' => 'Badge colour',
        'badge_tooltip' => 'Badge tooltip',
    ],

    'actions' => [
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
    ],

    'empty' => [
        'heading' => 'No resources found',
        'description' => 'Use "Sync resources" to pull in the resources registered on this panel.',
    ],

];
