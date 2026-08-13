<?php

use App\Models\PartnerApiClient;
use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_api_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 16)->default('basic');
            $table->boolean('enabled')->default(true);
            $table->string('api_key_hash')->nullable();
            $table->string('api_key_hint', 8)->nullable();
            $table->timestamp('api_key_created_at')->nullable();
            $table->boolean('require_ip_allowlist')->default(false);
            $table->json('allowed_ips')->nullable();
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(60);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();
        });

        $this->migrateLegacyPartnerExportSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_api_clients');
    }

    private function migrateLegacyPartnerExportSettings(): void
    {
        $stored = SystemSetting::query()->where('key', 'partner_export')->value('value');

        if (! is_array($stored) || blank($stored['api_key_hash'] ?? null)) {
            return;
        }

        $partnerName = trim((string) ($stored['partner_name'] ?? ''));
        $code = $this->resolveLegacyCode($partnerName);

        PartnerApiClient::query()->create([
            'name' => $partnerName !== '' ? $partnerName : 'Legacy partner',
            'code' => $code,
            'type' => PartnerApiClient::TYPE_BASIC,
            'enabled' => (bool) ($stored['enabled'] ?? false),
            'api_key_hash' => $stored['api_key_hash'],
            'api_key_hint' => $stored['api_key_hint'] ?? null,
            'api_key_created_at' => filled($stored['api_key_created_at'] ?? null)
                ? $stored['api_key_created_at']
                : null,
            'require_ip_allowlist' => (bool) ($stored['require_ip_allowlist'] ?? false),
            'allowed_ips' => is_array($stored['allowed_ips'] ?? null) ? $stored['allowed_ips'] : [],
            'rate_limit_per_minute' => max(1, min(300, (int) ($stored['rate_limit_per_minute'] ?? 60))),
            'last_used_at' => filled($stored['last_used_at'] ?? null) ? $stored['last_used_at'] : null,
            'last_used_ip' => filled($stored['last_used_ip'] ?? null) ? (string) $stored['last_used_ip'] : null,
        ]);

        unset(
            $stored['api_key_hash'],
            $stored['api_key_hint'],
            $stored['api_key_created_at'],
            $stored['partner_name'],
            $stored['allowed_ips'],
            $stored['rate_limit_per_minute'],
            $stored['last_used_at'],
            $stored['last_used_ip'],
        );

        SystemSetting::query()->updateOrCreate(
            ['key' => 'partner_export'],
            [
                'value' => $stored,
                'group' => 'integrations',
            ],
        );
    }

    private function resolveLegacyCode(string $partnerName): string
    {
        if ($partnerName === '') {
            return 'legacy';
        }

        $slug = Str::slug($partnerName);

        if ($slug === '') {
            return 'legacy';
        }

        if (PartnerApiClient::query()->where('code', $slug)->exists()) {
            return $slug.'-legacy';
        }

        return $slug;
    }
};
