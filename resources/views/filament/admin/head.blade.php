<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --bnc-primary: #e30613;
        --bnc-primary-hover: #c90511;
        --bnc-ink: #111827;
        --bnc-muted: #6b7280;
        --bnc-input-bg: #ffffff;
        --bnc-input-border: rgba(17, 24, 39, 0.14);
        --bnc-auth-bg: #fafafa;
        --bnc-auth-bg-end: #f3f4f6;
        --bnc-card-bg: #ffffff;
        --bnc-card-border: rgba(17, 24, 39, 0.08);
    }

    .dark {
        --bnc-ink: #f3f4f6;
        --bnc-muted: #9ca3af;
        --bnc-input-bg: #111827;
        --bnc-input-border: rgba(255, 255, 255, 0.12);
        --bnc-auth-bg: #030712;
        --bnc-auth-bg-end: #111827;
        --bnc-card-bg: #111827;
        --bnc-card-border: rgba(255, 255, 255, 0.1);
    }

    .fi-body,
    .fi-simple-layout {
        font-family: Inter, ui-sans-serif, system-ui, sans-serif !important;
    }

    .fi-simple-layout {
        background:
            radial-gradient(circle at top right, rgba(227, 6, 19, 0.08), transparent 42%),
            linear-gradient(180deg, var(--bnc-auth-bg) 0%, var(--bnc-auth-bg-end) 100%) !important;
        color: var(--bnc-ink) !important;
    }

    .dark .fi-simple-layout {
        background:
            radial-gradient(circle at top right, rgba(227, 6, 19, 0.18), transparent 42%),
            linear-gradient(180deg, var(--bnc-auth-bg) 0%, var(--bnc-auth-bg-end) 100%) !important;
    }

    .fi-simple-main-ctn {
        padding-top: 2rem;
        padding-bottom: 2rem;
    }

    .fi-simple-main {
        border: 1px solid var(--bnc-card-border) !important;
        border-radius: 1.25rem !important;
        box-shadow:
            0 20px 45px rgba(17, 24, 39, 0.08),
            0 2px 8px rgba(17, 24, 39, 0.04) !important;
        background: var(--bnc-card-bg) !important;
        padding: 2rem !important;
        color: var(--bnc-ink) !important;
    }

    .dark .fi-simple-main {
        box-shadow:
            0 20px 45px rgba(0, 0, 0, 0.35),
            0 2px 8px rgba(0, 0, 0, 0.2) !important;
    }

    .fi-logo {
        margin-bottom: 0.25rem;
    }

    .fi-simple-header-heading {
        color: var(--bnc-ink) !important;
        font-weight: 800 !important;
        letter-spacing: -0.02em;
    }

    .fi-simple-header-subheading {
        color: var(--bnc-muted) !important;
    }

    .fi-simple-main .fi-fo-field-wrp-label,
    .fi-simple-main .fi-fo-field-wrp-label span,
    .fi-simple-main .fi-fo-checkbox-label,
    .fi-simple-main .fi-fo-field-wrp-helper-text {
        color: var(--bnc-ink) !important;
    }

    .fi-simple-main .fi-fo-field-wrp-helper-text {
        color: var(--bnc-muted) !important;
    }

    .fi-simple-main .fi-input-wrp {
        border-radius: 0.85rem !important;
        background-color: var(--bnc-input-bg) !important;
        border: 1px solid var(--bnc-input-border) !important;
        box-shadow: inset 0 1px 2px rgba(17, 24, 39, 0.04) !important;
        --tw-ring-color: rgba(227, 6, 19, 0.25) !important;
    }

    .dark .fi-simple-main .fi-input-wrp {
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.25) !important;
    }

    .fi-simple-main .fi-input-wrp:focus-within {
        border-color: rgba(227, 6, 19, 0.55) !important;
        box-shadow:
            0 0 0 3px rgba(227, 6, 19, 0.12),
            inset 0 1px 2px rgba(17, 24, 39, 0.04) !important;
    }

    .fi-simple-main .fi-input,
    .fi-simple-main input.fi-input,
    .fi-simple-main textarea.fi-input {
        color: var(--bnc-ink) !important;
        background-color: transparent !important;
        caret-color: var(--bnc-primary) !important;
    }

    .fi-simple-main .fi-input::placeholder,
    .fi-simple-main input::placeholder {
        color: #9ca3af !important;
        opacity: 1 !important;
    }

    .dark .fi-simple-main .fi-input::placeholder,
    .dark .fi-simple-main input::placeholder {
        color: #6b7280 !important;
    }

    .fi-simple-main .fi-icon-btn,
    .fi-simple-main .fi-input-wrp-suffix .fi-icon-btn {
        color: var(--bnc-muted) !important;
    }

    .fi-simple-main .fi-link {
        color: var(--bnc-primary) !important;
    }

    .fi-btn-color-primary {
        background: linear-gradient(135deg, var(--bnc-primary) 0%, #a8040f 100%) !important;
        border: none !important;
        border-radius: 9999px !important;
        font-weight: 700 !important;
        min-height: 2.75rem;
        box-shadow: 0 10px 24px rgba(227, 6, 19, 0.22);
        color: #ffffff !important;
    }

    .fi-btn-color-primary:hover {
        background: linear-gradient(135deg, var(--bnc-primary-hover) 0%, #8f030d 100%) !important;
    }

    .fi-simple-page .fi-form-actions {
        margin-top: 0.5rem;
    }

    .bnc-admin-honeypot-wrap {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        overflow: hidden !important;
        clip: rect(0 0 0 0) !important;
        clip-path: inset(50%) !important;
        white-space: nowrap !important;
        border: 0 !important;
        padding: 0 !important;
        margin: -1px !important;
    }

    .bnc-admin-honeypot {
        opacity: 0 !important;
        pointer-events: none !important;
    }

    .bnc-admin-turnstile {
        display: flex;
        justify-content: center;
        margin-top: 0.25rem;
        margin-bottom: 0.25rem;
    }

    .fi-simple-main .fi-fo-field-wrp-error-message {
        color: #b91c1c !important;
    }

    .dark .fi-simple-main .fi-fo-field-wrp-error-message {
        color: #fca5a5 !important;
    }
</style>
