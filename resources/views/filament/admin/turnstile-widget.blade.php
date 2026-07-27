@php
    $turnstileEnabled = app(\App\Services\Security\TurnstileVerifier::class)->isEnabled();
@endphp
@if ($turnstileEnabled)
    <div wire:ignore class="bnc-admin-turnstile space-y-2">
        <div
            id="bnc-admin-turnstile"
            class="cf-turnstile"
            data-sitekey="{{ config('turnstile.site_key') }}"
            data-theme="auto"
        ></div>
        <p id="bnc-admin-turnstile-fallback" class="hidden text-sm text-danger-600 dark:text-danger-400">
            Cloudflare zaštita se nije učitala. Dozvolite challenges.cloudflare.com ili osvežite stranicu.
        </p>
    </div>

    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    <script>
        (function () {
            const showFallback = () => {
                const fallback = document.getElementById('bnc-admin-turnstile-fallback');
                if (fallback) {
                    fallback.classList.remove('hidden');
                }
            };

            const mountTurnstile = ({ allowFallback = false } = {}) => {
                const container = document.getElementById('bnc-admin-turnstile');

                if (!container || container.dataset.rendered === '1') {
                    return;
                }

                if (typeof turnstile === 'undefined') {
                    if (allowFallback) {
                        showFallback();
                    }

                    return;
                }

                container.dataset.rendered = '1';

                turnstile.render(container, {
                    sitekey: @js(config('turnstile.site_key')),
                    theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                    callback: (token) => {
                        @this.set('data.turnstile_token', token);
                    },
                    'expired-callback': () => {
                        @this.set('data.turnstile_token', null);
                    },
                    'error-callback': () => {
                        @this.set('data.turnstile_token', null);
                        showFallback();
                    },
                });
            };

            document.addEventListener('livewire:navigated', () => mountTurnstile());
            document.addEventListener('DOMContentLoaded', () => mountTurnstile());
            window.addEventListener('load', () => mountTurnstile());
            setTimeout(() => mountTurnstile({ allowFallback: true }), 2500);
        })();
    </script>
@endif
