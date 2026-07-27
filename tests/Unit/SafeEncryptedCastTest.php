<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SafeEncryptedCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_ciphertext_cannot_be_decrypted(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'Test source',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'invalid-ciphertext',
            'password' => Crypt::encryptString('secret'),
            'page_size' => 500,
            'is_active' => true,
        ]);

        $this->assertNull($source->fresh()->username);
        $this->assertSame('secret', $source->fresh()->password);
    }

    public function test_encrypts_and_decrypts_on_save(): void
    {
        $source = ApiSource::query()->create([
            'name' => 'Test source',
            'target_system_code' => 'bnc-shop',
            'base_url' => 'https://example.test',
            'username' => 'bnc-user',
            'password' => 'bnc-pass',
            'page_size' => 500,
            'is_active' => true,
        ]);

        $fresh = $source->fresh();

        $this->assertSame('bnc-user', $fresh->username);
        $this->assertSame('bnc-pass', $fresh->password);
        $this->assertNotSame('bnc-user', $fresh->getRawOriginal('username'));
    }
}
