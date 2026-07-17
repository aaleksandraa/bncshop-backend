<x-filament-panels::page>
    <style>
        .order-page-status {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .order-page-status--neutral {
            background: rgb(243 244 246);
            color: rgb(55 65 81);
        }

        .order-page-status--amber {
            background: rgb(254 243 199);
            color: rgb(146 64 14);
        }

        .order-page-status--success {
            background: rgb(209 250 229);
            color: rgb(6 95 70);
        }

        .order-page-status--danger {
            background: rgb(254 226 226);
            color: rgb(153 27 27);
        }

        .dark .order-page-status--neutral {
            background: rgb(55 65 81);
            color: rgb(229 231 235);
        }

        .dark .order-page-status--amber {
            background: rgb(120 53 15 / 0.35);
            color: rgb(252 211 77);
        }

        .dark .order-page-status--success {
            background: rgb(6 78 59 / 0.35);
            color: rgb(110 231 183);
        }

        .dark .order-page-status--danger {
            background: rgb(127 29 29 / 0.35);
            color: rgb(252 165 165);
        }
    </style>

    @include('filament.resources.orders.partials.order-dashboard', ['order' => $this->record])
</x-filament-panels::page>
