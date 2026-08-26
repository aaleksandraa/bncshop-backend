<?php

namespace Tests\Unit;

use App\Support\UploadedMediaPath;
use Tests\TestCase;

class UploadedMediaPathTest extends TestCase
{
    public function test_normalize_unwraps_filament_file_upload_arrays(): void
    {
        $this->assertNull(UploadedMediaPath::normalize(null));
        $this->assertNull(UploadedMediaPath::normalize([]));
        $this->assertNull(UploadedMediaPath::normalize(['uuid' => '']));
        $this->assertSame(
            'campaigns/heroes/a.webp',
            UploadedMediaPath::normalize(['uuid' => 'campaigns/heroes/a.webp']),
        );
        $this->assertSame(
            'campaigns/heroes/b.webp',
            UploadedMediaPath::normalize('storage/campaigns/heroes/b.webp'),
        );
        $this->assertSame(
            'campaigns/badges/back-to-school.webp',
            UploadedMediaPath::normalize(
                'https://images.bnc.ba/campaigns/badges/back-to-school.webp',
            ),
        );
        $this->assertNull(UploadedMediaPath::normalize('../secrets/.env'));
    }
}
