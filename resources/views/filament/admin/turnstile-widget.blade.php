@if (config('turnstile.enabled') && filled(config('turnstile.site_key')))
    <div wire:ignore class="bnc-admin-turnstile">
        <div
            id="bnc-admin-turnstile"
            class="cf-turnstile"
            data-sitekey="{{ config('turnstile.site_key') }}"
            data-theme="light"
        ></div>
    </div>

    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    <script>
        (function () {
            const mountTurnstile = () => {
                const container = document.getElementById('bnc-admin-turnstile');

                if (!container || container.dataset.rendered === '1' || typeof turnstile === 'undefined') {
                    return;
                }

                container.dataset.rendered = '1';

                turnstile.render(container, {
                    sitekey: @js(config('turnstile.site_key')),
                    theme: 'light',
                    callback: (token) => {
                        @this.set('data.turnstile_token', token);
                    },
                    'expired-callback': () => {
                        @this.set('data.turnstile_token', null);
                    },
                    'error-callback': () => {
                        @this.set('data.turnstile_token', null);
                    },
                });
            };

            document.addEventListener('livewire:navigated', mountTurnstile);
            document.addEventListener('DOMContentLoaded', mountTurnstile);
            window.addEventListener('load', mountTurnstile);
        })();
    </script>
@endif
