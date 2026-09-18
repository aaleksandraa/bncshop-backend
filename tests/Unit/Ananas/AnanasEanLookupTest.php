<?php

namespace Tests\Unit\Ananas;

use App\Services\Ananas\AnanasEanLookup;
use Tests\TestCase;

class AnanasEanLookupTest extends TestCase
{
    public function test_candidate_values_include_leading_zero_variant(): void
    {
        $candidates = AnanasEanLookup::candidateQueryValues('0736373267145');

        $this->assertContains('0736373267145', $candidates);
        $this->assertContains('736373267145', $candidates);
    }
}
