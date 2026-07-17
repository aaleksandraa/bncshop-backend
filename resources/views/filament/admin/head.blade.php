<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --bnc-primary: #e30613;
        --bnc-primary-hover: #c90511;
        --bnc-ink: #111827;
        --bnc-muted: #6b7280;
    }

    .fi-body,
    .fi-simple-layout {
        font-family: Inter, ui-sans-serif, system-ui, sans-serif !important;
    }

    .fi-simple-layout {
        background:
            radial-gradient(circle at top right, rgba(227, 6, 19, 0.08), transparent 42%),
            linear-gradient(180deg, #fafafa 0%, #f3f4f6 100%) !important;
    }

    .fi-simple-main-ctn {
        padding-top: 2rem;
        padding-bottom: 2rem;
    }

    .fi-simple-main {
        border: 1px solid rgba(17, 24, 39, 0.08) !important;
        border-radius: 1.25rem !important;
        box-shadow:
            0 20px 45px rgba(17, 24, 39, 0.08),
            0 2px 8px rgba(17, 24, 39, 0.04) !important;
        background: #ffffff !important;
        padding: 2rem !important;
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

    .fi-input-wrp {
        border-radius: 0.85rem !important;
    }

    .fi-btn-color-primary {
        background: linear-gradient(135deg, var(--bnc-primary) 0%, #a8040f 100%) !important;
        border: none !important;
        border-radius: 9999px !important;
        font-weight: 700 !important;
        min-height: 2.75rem;
        box-shadow: 0 10px 24px rgba(227, 6, 19, 0.22);
    }

    .fi-btn-color-primary:hover {
        background: linear-gradient(135deg, var(--bnc-primary-hover) 0%, #8f030d 100%) !important;
    }

    .fi-simple-page .fi-form-actions {
        margin-top: 0.5rem;
    }
</style>
