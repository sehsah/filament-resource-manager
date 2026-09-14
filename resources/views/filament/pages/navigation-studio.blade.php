<x-filament-panels::page>
    @if (! $profile)
        <x-filament::section>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                {{ __('filament-resource-manager::manager.studio.migration_required') }}
            </div>
        </x-filament::section>
    @else
        <div class="frm-theme">
            <div class="frm-commandbar">
                <div class="frm-profile-picker">
                    <div class="frm-command-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="3" y="3" width="7" height="7" rx="2" />
                            <rect x="14" y="3" width="7" height="7" rx="2" />
                            <rect x="3" y="14" width="7" height="7" rx="2" />
                            <rect x="14" y="14" width="7" height="7" rx="2" />
                        </svg>
                    </div>
                    <label class="frm-field">
                        <span>{{ __('filament-resource-manager::manager.studio.profile') }}</span>
                        <select wire:model.live="profileId" class="frm-input frm-profile-select">
                            @foreach ($profiles as $availableProfile)
                                <option value="{{ $availableProfile->getKey() }}">
                                    {{ $availableProfile->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <span class="frm-status frm-status-{{ $profile->status }}">
                        <span class="frm-status-dot" aria-hidden="true"></span>
                        {{ ucfirst($profile->status) }}
                    </span>
                </div>

                <form wire:submit="createProfile" class="frm-create-profile">
                    <label class="frm-field frm-grow">
                        <span>{{ __('filament-resource-manager::manager.studio.new_profile') }}</span>
                        <input wire:model="newProfileName" class="frm-input" maxlength="255"
                            placeholder="{{ __('filament-resource-manager::manager.studio.profile_example') }}">
                    </label>
                    <button type="submit" class="frm-button frm-button-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="8" y="8" width="11" height="11" rx="2" />
                            <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" />
                        </svg>
                        {{ __('filament-resource-manager::manager.studio.clone_profile') }}
                    </button>
                </form>

                <div class="frm-publish">
                    <button wire:click="publishProfile" wire:loading.attr="disabled"
                        class="frm-button frm-button-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5" />
                            <path d="M5 13v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" />
                        </svg>
                        {{ __('filament-resource-manager::manager.studio.publish') }}
                    </button>
                </div>
            </div>

            <script>
                window.frmNavigationStudio = (initialItems, initialGroups) => ({
                    items: initialItems,
                    groups: initialGroups,
                    dragged: null,
                    newGroup: '',
                    saving: false,
                    hideLabel: @js(__('filament-resource-manager::manager.studio.hide')),
                    showLabel: @js(__('filament-resource-manager::manager.studio.show')),

                    groupKey(value) {
                        return value || '';
                    },

                    topItems(group) {
                        return this.items
                            .filter(item => this.groupKey(item.navigation_group) === this.groupKey(group) && !item.parent_resource_class)
                            .sort((a, b) => (a.sort || 0) - (b.sort || 0));
                    },

                    childItems(resourceClass) {
                        return this.items
                            .filter(item => item.parent_resource_class === resourceClass)
                            .sort((a, b) => (a.sort || 0) - (b.sort || 0));
                    },

                    find(resourceClass) {
                        return this.items.find(item => item.resource_class === resourceClass);
                    },

                    start(resourceClass) {
                        this.dragged = resourceClass;
                    },

                    finish() {
                        this.dragged = null;
                    },

                    dropIntoGroup(group) {
                        const moving = this.find(this.dragged);
                        if (!moving) return;
                        moving.navigation_group = group || null;
                        moving.parent_resource_class = null;
                        moving.sort = this.items.length + 1;
                        this.persist();
                    },

                    dropBefore(targetClass) {
                        const moving = this.find(this.dragged);
                        const target = this.find(targetClass);
                        if (!moving || !target || moving === target) return;
                        moving.navigation_group = target.navigation_group;
                        moving.parent_resource_class = target.parent_resource_class;
                        const targetSort = target.sort || 1;
                        this.items.forEach(item => {
                            if (item !== moving && (item.sort || 0) >= targetSort) item.sort = (item.sort || 0) + 1;
                        });
                        moving.sort = targetSort;
                        this.persist();
                    },

                    nestUnder(parentClass) {
                        const moving = this.find(this.dragged);
                        const parent = this.find(parentClass);
                        if (!moving || !parent || moving === parent) return;
                        moving.navigation_group = parent.navigation_group;
                        moving.parent_resource_class = parent.resource_class;
                        moving.sort = this.items.length + 1;
                        this.persist();
                    },

                    addGroup() {
                        const label = this.newGroup.trim();
                        if (!label || this.groups.some(group => group.key === label)) return;
                        this.groups.push({ key: label, label });
                        this.newGroup = '';
                    },

                    toggle(item) {
                        item.is_visible = !item.is_visible;
                        this.persist();
                    },

                    resequence() {
                        const ordered = [];
                        this.groups.forEach(group => {
                            this.topItems(group.key).forEach(parent => {
                                ordered.push(parent);
                                this.childItems(parent.resource_class).forEach(child => ordered.push(child));
                            });
                        });
                        this.items.filter(item => !ordered.includes(item)).forEach(item => ordered.push(item));
                        ordered.forEach((item, index) => item.sort = index + 1);
                        this.items = ordered;
                    },

                    persist() {
                        this.resequence();
                        this.finish();
                        this.saving = true;
                        this.$wire.saveLayout(this.items).finally(() => this.saving = false);
                    },
                });
            </script>

            <div
                wire:key="navigation-studio-{{ $profile->getKey() }}-{{ $profile->updated_at?->timestamp }}"
                x-data="frmNavigationStudio(@js($studioItems), @js($groups))"
                class="frm-studio-grid"
            >
                <x-filament::section class="frm-builder-section">
                    <x-slot name="heading">{{ __('filament-resource-manager::manager.studio.builder') }}</x-slot>
                    <x-slot name="description">{{ __('filament-resource-manager::manager.studio.builder_hint') }}</x-slot>

                    <div class="frm-builder-tools">
                        <div class="frm-new-group">
                            <input x-model="newGroup" @keydown.enter.prevent="addGroup" class="frm-input"
                                placeholder="{{ __('filament-resource-manager::manager.studio.group_name') }}">
                            <button type="button" @click="addGroup" class="frm-button frm-button-secondary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                                {{ __('filament-resource-manager::manager.studio.add_group') }}
                            </button>
                        </div>
                        <span x-cloak x-show="saving" class="frm-saving">
                            <span class="frm-saving-spinner" aria-hidden="true"></span>
                            {{ __('filament-resource-manager::manager.studio.saving') }}
                        </span>
                    </div>

                    <div class="frm-groups">
                        <template x-for="group in groups" :key="group.key">
                            <section class="frm-group" @dragover.prevent @drop.prevent="dropIntoGroup(group.key)">
                                <header class="frm-group-heading">
                                    <span class="frm-group-title">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M3.5 7.5h6l2-2h9a1.5 1.5 0 0 1 1.5 1.5v11a1.5 1.5 0 0 1-1.5 1.5h-17A1.5 1.5 0 0 1 2 18V9a1.5 1.5 0 0 1 1.5-1.5Z" />
                                        </svg>
                                        <span x-text="group.label"></span>
                                    </span>
                                    <span class="frm-count" x-text="topItems(group.key).length"></span>
                                </header>

                                <div class="frm-group-items">
                                    <template x-for="item in topItems(group.key)" :key="item.resource_class">
                                        <article class="frm-tree-item"
                                            :class="{ 'frm-hidden-item': !item.is_visible, 'frm-dragging': dragged === item.resource_class }"
                                            draggable="true" @dragstart="start(item.resource_class)"
                                            @dragend="finish"
                                            @dragover.prevent @drop.stop.prevent="dropBefore(item.resource_class)">
                                            <div class="frm-tree-row">
                                                <span class="frm-drag" aria-hidden="true">
                                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                                        <circle cx="6" cy="5" r="1.25" /><circle cx="14" cy="5" r="1.25" />
                                                        <circle cx="6" cy="10" r="1.25" /><circle cx="14" cy="10" r="1.25" />
                                                        <circle cx="6" cy="15" r="1.25" /><circle cx="14" cy="15" r="1.25" />
                                                    </svg>
                                                </span>
                                                <span class="frm-item-icon" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <rect x="4" y="4" width="6" height="6" rx="1.5" />
                                                        <rect x="14" y="4" width="6" height="6" rx="1.5" />
                                                        <rect x="4" y="14" width="6" height="6" rx="1.5" />
                                                        <rect x="14" y="14" width="6" height="6" rx="1.5" />
                                                    </svg>
                                                </span>
                                                <span class="frm-item-label" x-text="item.label"></span>
                                                <span x-show="item.badge" class="frm-badge" x-text="item.badge"></span>
                                                <button type="button" class="frm-eye" @click.stop="toggle(item)"
                                                    :title="item.is_visible ? hideLabel : showLabel"
                                                    :aria-pressed="item.is_visible">
                                                    <svg x-show="item.is_visible" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                                                        <circle cx="12" cy="12" r="2.5" />
                                                    </svg>
                                                    <svg x-cloak x-show="!item.is_visible" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                        <path d="m4 4 16 16M10.7 6.1A9 9 0 0 1 12 6c6 0 9.5 6 9.5 6a15 15 0 0 1-2.1 2.7M6.5 7.4C3.9 9.1 2.5 12 2.5 12s3.5 6 9.5 6a9.4 9.4 0 0 0 3-.5M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                                                    </svg>
                                                </button>
                                            </div>

                                            <div class="frm-child-zone" @dragover.prevent
                                                @drop.stop.prevent="nestUnder(item.resource_class)">
                                                <span class="frm-child-hint">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                        <path d="M8 5v8a3 3 0 0 0 3 3h9M16 12l4 4-4 4" />
                                                    </svg>
                                                    {{ __('filament-resource-manager::manager.studio.drop_to_nest') }}
                                                </span>
                                                <template x-for="child in childItems(item.resource_class)" :key="child.resource_class">
                                                    <div class="frm-tree-row frm-child" :class="{ 'frm-hidden-item': !child.is_visible }"
                                                        draggable="true" @dragstart.stop="start(child.resource_class)"
                                                        @dragend="finish"
                                                        @dragover.prevent @drop.stop.prevent="dropBefore(child.resource_class)">
                                                        <span class="frm-drag" aria-hidden="true">
                                                            <svg viewBox="0 0 20 20" fill="currentColor">
                                                                <circle cx="6" cy="5" r="1.25" /><circle cx="14" cy="5" r="1.25" />
                                                                <circle cx="6" cy="10" r="1.25" /><circle cx="14" cy="10" r="1.25" />
                                                                <circle cx="6" cy="15" r="1.25" /><circle cx="14" cy="15" r="1.25" />
                                                            </svg>
                                                        </span>
                                                        <svg class="frm-child-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                            <path d="M7 5v8a3 3 0 0 0 3 3h8M15 13l3 3-3 3" />
                                                        </svg>
                                                        <span class="frm-item-label" x-text="child.label"></span>
                                                        <span x-show="child.badge" class="frm-badge" x-text="child.badge"></span>
                                                        <button type="button" class="frm-eye" @click.stop="toggle(child)"
                                                            :title="child.is_visible ? hideLabel : showLabel"
                                                            :aria-pressed="child.is_visible">
                                                            <svg x-show="child.is_visible" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                                                                <circle cx="12" cy="12" r="2.5" />
                                                            </svg>
                                                            <svg x-cloak x-show="!child.is_visible" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                                <path d="m4 4 16 16M10.7 6.1A9 9 0 0 1 12 6c6 0 9.5 6 9.5 6a15 15 0 0 1-2.1 2.7M6.5 7.4C3.9 9.1 2.5 12 2.5 12s3.5 6 9.5 6a9.4 9.4 0 0 0 3-.5M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </article>
                                    </template>

                                    <div x-show="topItems(group.key).length === 0" class="frm-empty-group">
                                        {{ __('filament-resource-manager::manager.studio.drop_here') }}
                                    </div>
                                </div>
                            </section>
                        </template>
                    </div>
                </x-filament::section>

                <x-filament::section class="frm-preview-section">
                    <x-slot name="heading">{{ __('filament-resource-manager::manager.studio.live_preview') }}</x-slot>
                    <x-slot name="description">{{ __('filament-resource-manager::manager.studio.preview_hint') }}</x-slot>

                    <aside class="frm-preview">
                        <div class="frm-preview-header">
                            <div class="frm-preview-mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <rect x="4" y="4" width="6" height="6" rx="1.5" />
                                    <rect x="14" y="4" width="6" height="6" rx="1.5" />
                                    <rect x="4" y="14" width="6" height="6" rx="1.5" />
                                    <rect x="14" y="14" width="6" height="6" rx="1.5" />
                                </svg>
                            </div>
                            <div>
                                <div class="frm-preview-brand">{{ $profile->name }}</div>
                                <div class="frm-preview-caption">{{ __('filament-resource-manager::manager.studio.preview') }}</div>
                            </div>
                            <span class="frm-live-dot" aria-hidden="true"></span>
                        </div>
                        <template x-for="group in groups" :key="'preview-' + group.key">
                            <div x-show="topItems(group.key).some(item => item.is_visible)" class="frm-preview-group">
                                <div x-show="group.key" class="frm-preview-group-label" x-text="group.label"></div>
                                <template x-for="item in topItems(group.key).filter(item => item.is_visible)" :key="'preview-item-' + item.resource_class">
                                    <div>
                                        <div class="frm-preview-item">
                                            <span class="frm-preview-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                    <rect x="4" y="4" width="6" height="6" rx="1.5" />
                                                    <rect x="14" y="4" width="6" height="6" rx="1.5" />
                                                    <rect x="4" y="14" width="6" height="6" rx="1.5" />
                                                    <rect x="14" y="14" width="6" height="6" rx="1.5" />
                                                </svg>
                                            </span>
                                            <span class="frm-preview-label" x-text="item.label"></span>
                                            <span x-show="item.badge" class="frm-badge" x-text="item.badge"></span>
                                        </div>
                                        <template x-for="child in childItems(item.resource_class).filter(child => child.is_visible)" :key="'preview-child-' + child.resource_class">
                                            <div class="frm-preview-item frm-preview-child">
                                                <svg class="frm-child-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path d="M7 5v8a3 3 0 0 0 3 3h8M15 13l3 3-3 3" />
                                                </svg>
                                                <span class="frm-preview-label" x-text="child.label"></span>
                                                <span x-show="child.badge" class="frm-badge" x-text="child.badge"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </aside>
                </x-filament::section>
            </div>

            <div class="frm-lower-grid">
                <x-filament::section>
                    <x-slot name="heading">{{ __('filament-resource-manager::manager.studio.history') }}</x-slot>
                    <x-slot name="description">{{ __('filament-resource-manager::manager.studio.history_hint') }}</x-slot>

                    <div class="frm-list">
                        @forelse ($versions as $version)
                            <div class="frm-list-row">
                                <div class="frm-version-info">
                                    <span class="frm-version-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M12 8v4l2.5 2.5" />
                                            <circle cx="12" cy="12" r="8.5" />
                                        </svg>
                                    </span>
                                    <div>
                                        <strong>v{{ $version->version }}</strong>
                                        <small>{{ $version->published_at?->format('Y-m-d H:i') }}</small>
                                    </div>
                                </div>
                                @if ($profile->published_version_id === $version->getKey())
                                    <span class="frm-status frm-status-published">
                                        <span class="frm-status-dot" aria-hidden="true"></span>
                                        {{ __('filament-resource-manager::manager.studio.current') }}
                                    </span>
                                @else
                                    <button wire:click="rollbackTo({{ $version->getKey() }})"
                                        wire:confirm="{{ __('filament-resource-manager::manager.studio.rollback_confirm') }}"
                                        class="frm-button frm-button-quiet">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M4 8v5h5M5.2 12.8A7.5 7.5 0 1 0 7 7.2L4 10" />
                                        </svg>
                                        {{ __('filament-resource-manager::manager.studio.rollback') }}
                                    </button>
                                @endif
                            </div>
                        @empty
                            <div class="frm-empty-group">{{ __('filament-resource-manager::manager.studio.no_versions') }}</div>
                        @endforelse
                    </div>
                </x-filament::section>
            </div>
        </div>
    @endif

    <style>
        [x-cloak] { display: none !important; }

        .frm-theme {
            --frm-surface: 255, 255, 255;
            --frm-surface-muted: 248, 250, 252;
            --frm-surface-strong: 241, 245, 249;
            --frm-surface-hover: 241, 245, 249;
            --frm-border: 226, 232, 240;
            --frm-border-strong: 203, 213, 225;
            --frm-text: 15, 23, 42;
            --frm-text-soft: 51, 65, 85;
            --frm-muted: 100, 116, 139;
            --frm-faint: 148, 163, 184;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            color: rgb(var(--frm-text));
            color-scheme: light;
        }

        .dark .frm-theme {
            --frm-surface: 24, 24, 27;
            --frm-surface-muted: 31, 31, 35;
            --frm-surface-strong: 39, 39, 42;
            --frm-surface-hover: 45, 45, 50;
            --frm-border: 52, 52, 58;
            --frm-border-strong: 70, 70, 78;
            --frm-text: 250, 250, 250;
            --frm-text-soft: 212, 212, 216;
            --frm-muted: 161, 161, 170;
            --frm-faint: 113, 113, 122;
            color-scheme: dark;
        }

        .frm-commandbar {
            display: grid;
            grid-template-columns: auto minmax(20rem, 1fr) auto;
            align-items: end;
            gap: 1.25rem;
            border: 1px solid rgb(var(--frm-border));
            border-radius: 1rem;
            background:
                linear-gradient(135deg, rgba(var(--primary-500, 59, 130, 246), .07), transparent 38%),
                rgb(var(--frm-surface));
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px rgba(15, 23, 42, .035);
        }

        .dark .frm-commandbar {
            background:
                linear-gradient(135deg, rgba(var(--primary-400, 96, 165, 250), .08), transparent 42%),
                rgb(var(--frm-surface));
            box-shadow: 0 10px 30px rgba(0, 0, 0, .16);
        }

        .frm-profile-picker,
        .frm-create-profile,
        .frm-publish,
        .frm-new-group,
        .frm-tree-row,
        .frm-list-row,
        .frm-version-info,
        .frm-group-title,
        .frm-preview-header,
        .frm-saving {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .frm-grow { flex: 1; }
        .frm-create-profile { min-width: 0; align-items: end; }
        .frm-publish { justify-self: end; }
        .frm-profile-picker { align-items: end; min-width: 15rem; }

        .frm-command-icon,
        .frm-preview-mark,
        .frm-version-icon {
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            width: 2.6rem;
            height: 2.6rem;
            border: 1px solid rgba(var(--primary-500, 59, 130, 246), .22);
            border-radius: .75rem;
            background: rgba(var(--primary-500, 59, 130, 246), .1);
            color: rgb(var(--primary-600, 37, 99, 235));
        }

        .dark .frm-command-icon,
        .dark .frm-preview-mark,
        .dark .frm-version-icon { color: rgb(var(--primary-400, 96, 165, 250)); }

        .frm-command-icon svg,
        .frm-preview-mark svg { width: 1.25rem; height: 1.25rem; }

        .frm-field {
            display: flex;
            flex-direction: column;
            gap: .4rem;
            min-width: 0;
            color: rgb(var(--frm-muted));
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .025em;
        }

        .frm-input {
            width: 100%;
            min-width: 0;
            min-height: 2.6rem;
            border: 1px solid rgb(var(--frm-border-strong));
            border-radius: .65rem;
            background: rgb(var(--frm-surface-muted));
            padding: .55rem .75rem;
            color: rgb(var(--frm-text));
            font-size: .84rem;
            line-height: 1.25rem;
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
        }

        .frm-profile-select { min-width: 9rem; }
        .frm-input::placeholder { color: rgb(var(--frm-faint)); }
        .frm-input:hover { border-color: rgb(var(--frm-muted)); background: rgb(var(--frm-surface)); }
        .frm-input:focus {
            border-color: rgb(var(--primary-600, 37, 99, 235));
            outline: none;
            background: rgb(var(--frm-surface));
            box-shadow: 0 0 0 3px rgba(var(--primary-500, 59, 130, 246), .16);
        }

        .frm-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-height: 2.6rem;
            border: 1px solid transparent;
            border-radius: .65rem;
            padding: .58rem .9rem;
            font-size: .82rem;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease, border-color .15s ease;
        }

        .frm-button svg { width: 1rem; height: 1rem; }
        .frm-button:hover { transform: translateY(-1px); }
        .frm-button:focus-visible { outline: 2px solid rgb(var(--primary-500, 59, 130, 246)); outline-offset: 2px; }
        .frm-button:disabled { cursor: wait; opacity: .55; transform: none; }
        .frm-button-primary {
            background: rgb(var(--primary-600, 37, 99, 235));
            color: white;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .12), 0 5px 14px rgba(var(--primary-600, 37, 99, 235), .2);
        }

        .frm-button-primary:hover { background: rgb(var(--primary-500, 59, 130, 246)); }
        .frm-button-secondary {
            border-color: rgb(var(--frm-border));
            background: rgb(var(--frm-surface-muted));
            color: rgb(var(--frm-text));
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .frm-button-secondary:hover { border-color: rgb(var(--frm-border-strong)); background: rgb(var(--frm-surface-hover)); }
        .frm-button-quiet { min-height: 2.25rem; padding: .45rem .7rem; color: rgb(var(--frm-text-soft)); }
        .frm-button-quiet:hover { background: rgb(var(--frm-surface-hover)); color: rgb(var(--frm-text)); }

        .frm-status {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border-radius: 999px;
            padding: .28rem .62rem;
            font-size: .7rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .frm-status-dot { width: .4rem; height: .4rem; border-radius: 999px; background: currentColor; }
        .frm-status-draft { background: rgb(254, 243, 199); color: rgb(146, 64, 14); }
        .frm-status-published { background: rgb(220, 252, 231); color: rgb(22, 101, 52); }
        .dark .frm-status-draft { background: rgb(69, 26, 3); color: rgb(253, 230, 138); }
        .dark .frm-status-published { background: rgb(5, 46, 22); color: rgb(187, 247, 208); }

        .frm-studio-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(19rem, .85fr);
            gap: 1.25rem;
            align-items: start;
        }

        .frm-builder-section,
        .frm-preview-section { min-width: 0; }

        .frm-builder-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgb(var(--frm-border));
            padding-bottom: 1rem;
        }

        .frm-new-group { align-items: center; width: min(100%, 25rem); }
        .frm-new-group .frm-input { flex: 1; }
        .frm-saving { gap: .4rem; padding: 0; color: rgb(var(--frm-muted)); font-size: .75rem; white-space: nowrap; }
        .frm-saving-spinner {
            width: .75rem;
            height: .75rem;
            border: 2px solid rgb(var(--frm-border-strong));
            border-top-color: rgb(var(--primary-500, 59, 130, 246));
            border-radius: 999px;
            animation: frm-spin .7s linear infinite;
        }

        @keyframes frm-spin { to { transform: rotate(360deg); } }

        .frm-groups { display: flex; flex-direction: column; gap: .9rem; }

        .frm-group {
            overflow: hidden;
            border: 1px solid rgb(var(--frm-border));
            border-radius: .85rem;
            background: rgb(var(--frm-surface-muted));
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .frm-group-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgb(var(--frm-border));
            padding: .7rem .85rem;
            color: rgb(var(--frm-text-soft));
            font-size: .72rem;
            font-weight: 750;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .frm-group-title { gap: .45rem; }
        .frm-group-title svg { width: 1rem; height: 1rem; color: rgb(var(--frm-muted)); }
        .frm-count {
            min-width: 1.35rem;
            border-radius: 999px;
            background: rgb(var(--frm-surface-strong));
            padding: .18rem .4rem;
            color: rgb(var(--frm-muted));
            font-size: .67rem;
            line-height: 1;
            text-align: center;
        }

        .frm-group-items { min-height: 3.5rem; padding: .65rem; }
        .frm-tree-item {
            margin-bottom: .55rem;
            border: 1px solid rgb(var(--frm-border));
            border-radius: .7rem;
            background: rgb(var(--frm-surface));
            box-shadow: 0 1px 2px rgba(15, 23, 42, .045);
            transition: border-color .15s ease, box-shadow .15s ease, opacity .15s ease, transform .15s ease;
        }

        .frm-tree-item:last-child { margin-bottom: 0; }
        .frm-tree-item:hover {
            border-color: rgba(var(--primary-500, 59, 130, 246), .5);
            box-shadow: 0 4px 14px rgba(15, 23, 42, .07);
        }

        .frm-tree-item.frm-dragging { opacity: .55; transform: scale(.99); }
        .frm-tree-row { align-items: center; gap: .6rem; min-height: 3rem; padding: .55rem .65rem; color: rgb(var(--frm-text)); }
        .frm-drag { display: grid; place-items: center; width: 1.1rem; cursor: grab; color: rgb(var(--frm-faint)); }
        .frm-drag:active { cursor: grabbing; }
        .frm-drag svg { width: 1rem; height: 1rem; }
        .frm-item-icon,
        .frm-preview-icon {
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            width: 1.85rem;
            height: 1.85rem;
            border-radius: .5rem;
            background: rgba(var(--primary-500, 59, 130, 246), .1);
            color: rgb(var(--primary-600, 37, 99, 235));
        }

        .dark .frm-item-icon,
        .dark .frm-preview-icon { color: rgb(var(--primary-400, 96, 165, 250)); }
        .frm-item-icon svg,
        .frm-preview-icon svg { width: .95rem; height: .95rem; }
        .frm-item-label { flex: 1; min-width: 0; overflow: hidden; font-size: .87rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
        .frm-hidden-item { opacity: .5; }

        .frm-badge {
            margin-inline-start: auto;
            border: 1px solid rgb(var(--frm-border));
            border-radius: 999px;
            background: rgb(var(--frm-surface-strong));
            padding: .18rem .45rem;
            color: rgb(var(--frm-text-soft));
            font-size: .66rem;
            font-weight: 700;
            line-height: 1;
        }

        .frm-eye {
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            width: 1.9rem;
            height: 1.9rem;
            border-radius: .5rem;
            color: rgb(var(--frm-muted));
            cursor: pointer;
            transition: background-color .15s ease, color .15s ease;
        }

        .frm-eye:hover { background: rgb(var(--frm-surface-hover)); color: rgb(var(--primary-600, 37, 99, 235)); }
        .frm-eye:focus-visible { outline: 2px solid rgb(var(--primary-500, 59, 130, 246)); outline-offset: 1px; }
        .frm-eye svg { width: 1.05rem; height: 1.05rem; }

        .frm-child-zone {
            min-height: 2rem;
            margin: 0 .55rem .55rem 2.65rem;
            border: 1px dashed rgb(var(--frm-border-strong));
            border-radius: .55rem;
            background: rgb(var(--frm-surface-muted));
            padding: .3rem;
            transition: border-color .15s ease, background-color .15s ease;
        }

        .frm-child-zone:hover {
            border-color: rgb(var(--primary-400, 96, 165, 250));
            background: rgba(var(--primary-500, 59, 130, 246), .05);
        }

        .frm-child-hint {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            min-height: 1.35rem;
            color: rgb(var(--frm-faint));
            font-size: .66rem;
            text-align: center;
        }

        .frm-child-hint svg,
        .frm-child-arrow { width: .9rem; height: .9rem; flex: 0 0 auto; }
        .frm-child {
            margin-top: .3rem;
            border: 1px solid rgb(var(--frm-border));
            border-radius: .45rem;
            background: rgb(var(--frm-surface));
            font-size: .82rem;
        }

        .frm-child-arrow { color: rgb(var(--frm-faint)); }
        .frm-empty-group {
            display: grid;
            min-height: 4rem;
            place-items: center;
            border: 1px dashed rgb(var(--frm-border-strong));
            border-radius: .6rem;
            color: rgb(var(--frm-muted));
            font-size: .78rem;
            text-align: center;
        }

        .frm-preview {
            position: sticky;
            top: 1rem;
            min-height: 28rem;
            border: 1px solid rgb(var(--frm-border));
            border-radius: .9rem;
            background:
                radial-gradient(circle at top right, rgba(var(--primary-500, 59, 130, 246), .1), transparent 38%),
                rgb(var(--frm-surface-muted));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .06), 0 12px 28px rgba(15, 23, 42, .08);
            padding: .75rem;
            color: rgb(var(--frm-text));
        }

        .dark .frm-preview { box-shadow: inset 0 1px 0 rgba(255, 255, 255, .035), 0 14px 34px rgba(0, 0, 0, .22); }
        .frm-preview-header {
            margin-bottom: .75rem;
            border-bottom: 1px solid rgb(var(--frm-border));
            padding: .35rem .35rem 1rem;
        }

        .frm-preview-mark { width: 2.25rem; height: 2.25rem; border-radius: .65rem; }
        .frm-preview-brand { color: rgb(var(--frm-text)); font-size: .9rem; font-weight: 750; line-height: 1.2; }
        .frm-preview-caption { margin-top: .15rem; color: rgb(var(--frm-muted)); font-size: .65rem; }
        .frm-live-dot {
            margin-inline-start: auto;
            width: .5rem;
            height: .5rem;
            border-radius: 999px;
            background: rgb(34, 197, 94);
            box-shadow: 0 0 0 4px rgba(34, 197, 94, .12);
        }

        .frm-preview-group { margin-bottom: .85rem; }
        .frm-preview-group-label {
            padding: .5rem .65rem .35rem;
            color: rgb(var(--frm-muted));
            font-size: .64rem;
            font-weight: 800;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .frm-preview-item {
            display: flex;
            align-items: center;
            gap: .65rem;
            min-height: 2.6rem;
            border: 1px solid transparent;
            border-radius: .65rem;
            padding: .42rem .55rem;
            color: rgb(var(--frm-text-soft));
            font-size: .82rem;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }

        .frm-preview-item:hover {
            border-color: rgb(var(--frm-border));
            background: rgb(var(--frm-surface));
            color: rgb(var(--frm-text));
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .frm-preview-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .frm-preview-child { min-height: 2.25rem; padding-inline-start: 2.8rem; color: rgb(var(--frm-muted)); }

        .frm-lower-grid { min-width: 0; }
        .frm-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
            gap: .65rem;
        }

        .frm-list-row {
            align-items: center;
            justify-content: space-between;
            min-height: 4.25rem;
            border: 1px solid rgb(var(--frm-border));
            border-radius: .75rem;
            background: rgb(var(--frm-surface-muted));
            padding: .65rem .75rem;
            color: rgb(var(--frm-text-soft));
            font-size: .82rem;
            transition: border-color .15s ease, background-color .15s ease;
        }

        .frm-list-row:hover { border-color: rgb(var(--frm-border-strong)); background: rgb(var(--frm-surface)); }
        .frm-version-info { min-width: 0; }
        .frm-version-icon { width: 2.2rem; height: 2.2rem; border-color: rgb(var(--frm-border)); background: rgb(var(--frm-surface)); color: rgb(var(--frm-muted)); }
        .frm-version-icon svg { width: 1rem; height: 1rem; }
        .frm-list-row strong { color: rgb(var(--frm-text)); }
        .frm-list-row small { display: block; margin-top: .15rem; color: rgb(var(--frm-muted)); }

        @media (max-width: 1100px) {
            .frm-commandbar { grid-template-columns: 1fr auto; }
            .frm-create-profile { grid-column: 1 / -1; grid-row: 2; }
            .frm-publish { grid-column: 2; grid-row: 1; }
            .frm-studio-grid { grid-template-columns: minmax(0, 1.35fr) minmax(17rem, .8fr); }
        }

        @media (max-width: 800px) {
            .frm-studio-grid { grid-template-columns: 1fr; }
            .frm-preview { position: static; }
        }

        @media (max-width: 600px) {
            .frm-commandbar { grid-template-columns: 1fr; padding: .85rem; }
            .frm-profile-picker { flex-wrap: wrap; min-width: 0; }
            .frm-profile-picker .frm-field { flex: 1; }
            .frm-profile-picker .frm-status { align-self: center; }
            .frm-create-profile { grid-column: 1; grid-row: auto; flex-direction: column; align-items: stretch; }
            .frm-publish { grid-column: 1; grid-row: auto; justify-self: stretch; }
            .frm-publish .frm-button { width: 100%; }
            .frm-builder-tools { align-items: stretch; flex-direction: column; }
            .frm-new-group { width: 100%; }
            .frm-new-group .frm-button { padding-inline: .7rem; }
            .frm-child-zone { margin-inline-start: 1rem; }
            .frm-list { grid-template-columns: 1fr; }
        }
    </style>
</x-filament-panels::page>
