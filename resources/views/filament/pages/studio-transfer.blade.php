<x-filament::section>
    <x-slot name="heading">{{ __('filament-resource-manager::manager.transfer.title') }}</x-slot>
    <x-slot name="description">{{ __('filament-resource-manager::manager.transfer.hint') }}</x-slot>
    <button type="button" wire:click="exportProfile" wire:loading.attr="disabled" class="frm-button frm-button-secondary">{{ __('filament-resource-manager::manager.transfer.export') }}</button>
    <details class="frm-review-block">
        <summary>{{ __('filament-resource-manager::manager.transfer.import') }}</summary>
        <p class="frm-review-block">{{ __('filament-resource-manager::manager.transfer.files_hint') }}</p>
        <div x-data="{ error: '' }">
            <label class="frm-field">
                <span>{{ __('filament-resource-manager::manager.transfer.file') }}</span>
                <input type="file" accept=".json,application/json" @change="async () => {
                    error = '';
                    const file = $event.target.files[0];
                    if (!file) return;
                    if (file.size > 1048576) { error = @js(__('filament-resource-manager::manager.transfer.too_large')); return; }
                    try { $wire.set('importJson', await file.text()); }
                    catch { error = @js(__('filament-resource-manager::manager.transfer.invalid_json')); }
                }">
            </label>
            <p x-show="error" x-text="error" role="alert"></p>
        </div>
        <label class="frm-field frm-review-block">
            <span>{{ __('filament-resource-manager::manager.transfer.json') }}</span>
            <textarea wire:model="importJson" class="frm-input frm-import-json" dir="ltr" spellcheck="false"></textarea>
        </label>
        <label class="frm-field frm-review-block">
            <span>{{ __('filament-resource-manager::manager.transfer.name') }}</span>
            <input wire:model="importName" maxlength="150" class="frm-input">
        </label>
        <button type="button" wire:click="reviewImport" wire:loading.attr="disabled" class="frm-button frm-button-secondary">{{ __('filament-resource-manager::manager.transfer.review') }}</button>
        @if ($importReview)
            <div class="frm-review-block" role="status">
                <p>{{ __('filament-resource-manager::manager.transfer.summary', ['count' => count($importReview['items'])]) }}</p>
                <p>{{ __('filament-resource-manager::manager.fields.roles') }}: {{ implode(', ', $importReview['roles']) ?: __('filament-resource-manager::manager.transfer.everyone') }}</p>
                @foreach ($importReview['items'] as $item)
                    <details class="frm-review-block">
                        <summary>{{ ($item['label'] ?? null) ?: class_basename($item['resource_class']) }} — {{ $item['resource_class'] }}</summary>
                        <pre>{{ \MahmoudSehsah\FilamentResourceManager\Support\ProfileComparison::display($item) }}</pre>
                    </details>
                @endforeach
            </div>
            <button type="button" wire:click="importProfile" wire:loading.attr="disabled" class="frm-button frm-button-primary">{{ __('filament-resource-manager::manager.transfer.confirm') }}</button>
        @endif
    </details>
</x-filament::section>
