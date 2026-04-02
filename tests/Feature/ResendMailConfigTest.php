<?php

namespace Tests\Feature;

use Tests\TestCase;

class ResendMailConfigTest extends TestCase
{
    public function test_resend_mailer_is_configured()
    {
        $this->assertSame('resend', config('mail.mailers.resend.transport'));
    }

    public function test_mail_from_address_and_name_are_configured()
    {
        $this->assertNotEmpty(config('mail.from.address'));
        $this->assertNotEmpty(config('mail.from.name'));
        $this->assertNotSame('hello@example.com', config('mail.from.address'));
        $this->assertNotSame('Example', config('mail.from.name'));
    }
}
