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

namespace Test\MultiFlexi\Ui\CredentialType;

use MultiFlexi\Ui\CredentialType\Mail;
use PHPUnit\Framework\TestCase;

class MailTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Mail::class));
    }

    public function testExtendsCredentialFormHelperPrototype(): void
    {
        $reflection = new \ReflectionClass(Mail::class);
        $this->assertTrue($reflection->isSubclassOf(\MultiFlexi\Ui\CredentialFormHelperPrototype::class));
    }

    public function testHasFinalizeMethod(): void
    {
        $this->assertTrue(method_exists(Mail::class, 'finalize'));
    }
}
