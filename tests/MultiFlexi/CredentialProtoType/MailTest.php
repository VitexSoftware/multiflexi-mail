<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\MultiFlexi\CredentialProtoType;

use MultiFlexi\CredentialProtoType\Mail;
use PHPUnit\Framework\TestCase;

class MailTest extends TestCase
{
    public function testName(): void
    {
        $this->assertNotEmpty(Mail::name());
    }

    public function testDescription(): void
    {
        $this->assertNotEmpty(Mail::description());
    }

    public function testUuid(): void
    {
        $uuid = Mail::uuid();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $uuid,
        );
    }

    public function testLogo(): void
    {
        $this->assertSame('Mail.svg', Mail::logo());
    }
}
