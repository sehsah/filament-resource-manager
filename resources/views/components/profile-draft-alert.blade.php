<div
    x-data="{ visible: true }"
    x-show="visible"
    x-transition.opacity.duration.150ms
    wire:key="frm-profile-alert-{{ $profile->getKey() }}"
    class="frm-profile-alert"
    role="status"
>
    <span class="frm-profile-alert-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 8v4m0 4h.01" />
            <circle cx="12" cy="12" r="9" />
        </svg>
    </span>

    <div class="frm-profile-alert-content">
        <strong>{{ __('filament-resource-manager::manager.notifications.profile_governs') }}</strong>
        <p>{{ __('filament-resource-manager::manager.notifications.profile_governs_body', ['profile' => $profile->name]) }}</p>
    </div>

    <button
        type="button"
        class="frm-profile-alert-close"
        x-on:click="visible = false"
        aria-label="{{ __('filament-resource-manager::manager.notifications.dismiss') }}"
    >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M6 6l12 12M18 6 6 18" />
        </svg>
    </button>
</div>

<style>
    .frm-profile-alert {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        width: 100%;
        border: 1px solid rgba(245, 158, 11, .28);
        border-radius: .75rem;
        background: rgb(255, 251, 235);
        padding: .8rem .9rem;
        color: rgb(120, 53, 15);
    }

    .dark .frm-profile-alert {
        border-color: rgba(245, 158, 11, .22);
        background: rgba(69, 26, 3, .46);
        color: rgb(253, 230, 138);
    }

    .frm-profile-alert-icon {
        display: grid;
        flex: 0 0 auto;
        place-items: center;
        width: 1.8rem;
        height: 1.8rem;
        border-radius: .5rem;
        background: rgba(245, 158, 11, .14);
        color: rgb(180, 83, 9);
    }

    .dark .frm-profile-alert-icon { color: rgb(251, 191, 36); }
    .frm-profile-alert-icon svg { width: 1rem; height: 1rem; }
    .frm-profile-alert-content { flex: 1; min-width: 0; }
    .frm-profile-alert-content strong { display: block; font-size: .82rem; line-height: 1.25rem; }
    .frm-profile-alert-content p { margin-top: .15rem; color: rgb(146, 64, 14); font-size: .78rem; line-height: 1.25rem; }
    .dark .frm-profile-alert-content p { color: rgb(252, 211, 77); }

    .frm-profile-alert-close {
        display: grid;
        flex: 0 0 auto;
        place-items: center;
        width: 1.8rem;
        height: 1.8rem;
        border: 0;
        border-radius: .45rem;
        background: transparent;
        color: currentColor;
        cursor: pointer;
        opacity: .68;
        transition: background-color .15s ease, opacity .15s ease;
    }

    .frm-profile-alert-close:hover { background: rgba(245, 158, 11, .14); opacity: 1; }
    .frm-profile-alert-close:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
    .frm-profile-alert-close svg { width: .95rem; height: .95rem; }
</style>
