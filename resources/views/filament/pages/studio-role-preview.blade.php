<div class="frm-role-preview">
    <label class="frm-role-choice">
        <input type="checkbox" wire:model.live="previewEnabled">
        <span>{{ __('filament-resource-manager::manager.role_preview.enable') }}</span>
    </label>
    @if ($previewEnabled)
        <p class="frm-review-block">{{ __('filament-resource-manager::manager.role_preview.hint') }}</p>
        <div class="frm-review-controls">
            <label class="frm-field">
                <span>{{ __('filament-resource-manager::manager.fields.roles') }}</span>
                <select wire:model.live="previewRoles" multiple class="frm-input">
                    @foreach ($availableRoles as $role => $label)
                        <option value="{{ $role }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="frm-field">
                <span>{{ __('filament-resource-manager::manager.role_preview.extra_permissions') }}</span>
                <select wire:model.live="previewPermissions" multiple class="frm-input">
                    @foreach ($availablePermissions as $permission => $label)
                        <option value="{{ $permission }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <p class="frm-review-block">{{ __('filament-resource-manager::manager.role_preview.effective_permissions') }}: {{ implode(', ', $effectivePreviewPermissions) ?: '—' }}</p>
    @endif
</div>
