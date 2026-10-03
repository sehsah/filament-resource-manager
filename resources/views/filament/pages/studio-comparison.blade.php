<x-filament::section>
    <x-slot name="heading">{{ __('filament-resource-manager::manager.comparison.title') }}</x-slot>
    <x-slot name="description">{{ __('filament-resource-manager::manager.comparison.hint') }}</x-slot>
    <div class="frm-review-controls">
        @foreach (['compareFrom' => 'before', 'compareTo' => 'after'] as $property => $label)
            <label class="frm-field">
                <span>{{ __('filament-resource-manager::manager.comparison.'.$label) }}</span>
                <select wire:model.live="{{ $property }}" class="frm-input">
                    <option value="">{{ __('filament-resource-manager::manager.comparison.draft') }}</option>
                    @foreach ($comparisonVersions as $version)
                        <option value="{{ $version->getKey() }}">v{{ $version->version }}{{ $profile->published_version_id === $version->getKey() ? ' — '.__('filament-resource-manager::manager.studio.current') : '' }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach
    </div>
    @if ($comparisonError)
        <p role="alert">{{ $comparisonError }}</p>
    @elseif ($comparison)
        <div class="frm-review-table-wrap">
            <table class="frm-review-table">
                <thead><tr>
                    <th>{{ __('filament-resource-manager::manager.columns.resource') }}</th>
                    <th>{{ __('filament-resource-manager::manager.comparison.field') }}</th>
                    <th>{{ __('filament-resource-manager::manager.comparison.before') }}</th>
                    <th>{{ __('filament-resource-manager::manager.comparison.after') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($comparison as $change)
                        @php($fieldKey = 'filament-resource-manager::manager.fields.'.$change['field'])
                        <tr>
                            <td>{{ $change['resource'] ?? __('filament-resource-manager::manager.studio.audience') }}<br>{{ __('filament-resource-manager::manager.comparison.'.$change['type']) }}</td>
                            <td>{{ \Illuminate\Support\Facades\Lang::has($fieldKey) ? __($fieldKey) : \Illuminate\Support\Str::headline($change['field']) }}</td>
                            <td><pre>{{ \MahmoudSehsah\FilamentResourceManager\Support\ProfileComparison::display($change['before']) }}</pre></td>
                            <td><pre>{{ \MahmoudSehsah\FilamentResourceManager\Support\ProfileComparison::display($change['after']) }}</pre></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p>{{ __('filament-resource-manager::manager.comparison.no_changes') }}</p>
    @endif
</x-filament::section>
