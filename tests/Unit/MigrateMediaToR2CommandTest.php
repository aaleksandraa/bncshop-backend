<?php

namespace Tests\Unit;

use App\Console\Commands\MigrateMediaToR2Command;
use Tests\TestCase;

class MigrateMediaToR2CommandTest extends TestCase
{
    public function test_command_is_registered_with_expected_signature(): void
    {
        $this->artisan('bnc:migrate-media-to-r2 --help')
            ->assertExitCode(0);

        $command = $this->app->make(MigrateMediaToR2Command::class);

        $this->assertSame('bnc:migrate-media-to-r2', $command->getName());
    }
}
