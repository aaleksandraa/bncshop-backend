<?php

namespace Tests\Unit;

use App\Support\OrderNotificationMail;
use Tests\TestCase;

class OrderNotificationMailTest extends TestCase
{
    public function test_recipients_include_seller_and_admin_when_both_set(): void
    {
        config([
            'bnc.seller_notification_email' => 'prodaja@bnc.ba',
            'bnc.admin_notification_email' => 'info@bnc.ba',
        ]);

        $this->assertSame(
            ['prodaja@bnc.ba', 'info@bnc.ba'],
            OrderNotificationMail::recipients(),
        );
    }

    public function test_recipients_deduplicate_same_address(): void
    {
        config([
            'bnc.seller_notification_email' => 'info@bnc.ba',
            'bnc.admin_notification_email' => 'info@bnc.ba',
        ]);

        $this->assertSame(['info@bnc.ba'], OrderNotificationMail::recipients());
    }
}
