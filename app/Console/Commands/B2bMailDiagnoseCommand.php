<?php

namespace App\Console\Commands;

use App\Mail\B2b\B2bAccessApprovedMail;
use App\Mail\B2b\B2bAccessRequestNotification;
use App\Mail\B2b\B2bOrderConfirmationCustomer;
use App\Mail\B2b\B2bOrderNotificationAdmin;
use App\Models\B2bAccessRequest;
use App\Models\B2bOrder;
use App\Models\B2bSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class B2bMailDiagnoseCommand extends Command
{
    protected $signature = 'b2b:mail-diagnose {to? : Test primatelj (default: admin notification email)} {--send : Pošalji stvarne test poruke}';

    protected $description = 'Provjeri B2B mail konfiguraciju i opcionalno pošalji test emailove';

    public function handle(): int
    {
        $this->info('B2B mail dijagnostika');
        $this->newLine();

        $mailer = (string) config('mail.default');
        $this->line('MAIL_MAILER: '.$mailer);

        if ($mailer === 'log') {
            $this->error('[FAIL] MAIL_MAILER=log — emailovi se NE šalju, samo upisuju u laravel.log');
        } elseif ($mailer === 'sendmail') {
            $this->info('[OK] sendmail transport');
        } else {
            $this->warn('[WARN] mailer='.$mailer.' — provjeri da je ispravno podešen za produkciju');
        }

        $this->line('Global From: '.config('mail.from.name').' <'.config('mail.from.address').'>');
        $this->line('B2B Reply-To: '.config('b2b.mail.from_name').' <'.config('b2b.mail.from_address').'>');
        $this->line('B2B use global From: '.(config('b2b.mail.use_global_from') ? 'yes' : 'no'));
        $this->line('Admin notifications To: '.B2bSetting::adminNotificationEmail());
        $this->newLine();

        if (! $this->option('send')) {
            $this->comment('Dodaj --send da pošalješ test poruke (pristup + narudžba).');

            return $mailer === 'log' ? self::FAILURE : self::SUCCESS;
        }

        $to = $this->argument('to') ?: B2bSetting::adminNotificationEmail();

        if (! filled($to)) {
            $this->error('Nema test primatelja. Proslijedi email ili postavi B2B_ADMIN_NOTIFICATION_EMAIL.');

            return self::FAILURE;
        }

        $request = B2bAccessRequest::query()->latest('id')->first() ?? new B2bAccessRequest([
            'first_name' => 'Test',
            'last_name' => 'Korisnik',
            'email' => 'test@example.com',
            'phone' => '061000000',
            'company_name' => 'Test firma d.o.o.',
            'company_address' => 'Test adresa 1',
            'jib' => '1234567890123',
            'status' => 'pending',
        ]);

        try {
            Mail::to($to)->send(new B2bAccessRequestNotification($request));
            $this->info('[OK] Poslan test: novi zahtjev za pristup → '.$to);
        } catch (\Throwable $e) {
            $this->error('[FAIL] Admin test mail: '.$e->getMessage());

            return self::FAILURE;
        }

        $user = User::query()->where('is_b2b_customer', true)->first() ?? new User([
            'name' => 'Test B2B kupac',
            'email' => $to,
        ]);

        try {
            Mail::to($to)->send(new B2bAccessApprovedMail(
                $user,
                rtrim((string) config('bnc.frontend_url'), '/').'/b2b/postavi-lozinku?token=test-token',
            ));
            $this->info('[OK] Poslan test: odobren pristup → '.$to);
        } catch (\Throwable $e) {
            $this->error('[FAIL] Customer approval test mail: '.$e->getMessage());

            return self::FAILURE;
        }

        $order = B2bOrder::query()->latest('id')->with('items')->first() ?? new B2bOrder([
            'order_number' => 'B2B-TEST-001',
            'status' => 'nova',
            'payment_method' => 'invoice',
            'company_name' => 'Test firma d.o.o.',
            'company_address' => 'Test adresa 1',
            'jib' => '1234567890123',
            'contact_name' => 'Test kupac',
            'contact_email' => $to,
            'contact_phone' => '061000000',
            'shipping_address' => 'Dostava 1',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_fee' => 10,
            'total' => 110,
        ]);

        try {
            Mail::to($to)->send(new B2bOrderConfirmationCustomer($order));
            $this->info('[OK] Poslan test: potvrda B2B narudžbe → '.$to);
        } catch (\Throwable $e) {
            $this->error('[FAIL] Order confirmation test mail: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            Mail::to($to)->send(new B2bOrderNotificationAdmin($order));
            $this->info('[OK] Poslan test: nova B2B narudžba (admin) → '.$to);
        } catch (\Throwable $e) {
            $this->error('[FAIL] Order admin test mail: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Test emailovi poslani. Provjeri inbox i spam folder.');

        return self::SUCCESS;
    }
}
