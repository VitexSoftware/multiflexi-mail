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

namespace MultiFlexi\CredentialProtoType;

/**
 * Description of Mail.
 *
 * author Vitex <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class Mail extends \MultiFlexi\CredentialProtoType implements \MultiFlexi\credentialTypeInterface, \MultiFlexi\checkableCredentialInterface
{
    public static string $logo = 'Mail.svg';

    public function __construct()
    {
        parent::__construct();

        $dsnField = new \MultiFlexi\ConfigField('MAIL_DSN', 'string', _('Mailer DSN'), _('Symfony Mailer DSN string (e.g. smtp://user:pass@smtp.example.com:465)'));
        $dsnField->setHint('smtp://user:pass@smtp.example.com:465')->setValue('');

        $fromField = new \MultiFlexi\ConfigField('MAIL_FROM', 'string', _('Sender Address'), _('Default sender email address'));
        $fromField->setHint('noreply@example.com')->setValue('');

        $this->configFieldsInternal->addField($dsnField);
        $this->configFieldsInternal->addField($fromField);
    }

    public function load(int $credTypeId)
    {
        $loaded = parent::load($credTypeId);

        foreach ($this->configFieldsInternal->getFields() as $field) {
            $this->configFieldsProvided->addField($field);
        }

        return $loaded;
    }

    #[\Override]
    public function prepareConfigForm(): void
    {
    }

    public function name(): string
    {
        return _('Mail (SMTP)');
    }

    public function description(): string
    {
        return _('SMTP mail delivery via Symfony Mailer');
    }

    public function uuid(): string
    {
        return 'f6c8e33b-9d10-4766-9eff-6e21c468cd6f';
    }

    #[\Override]
    public function logo(): string
    {
        return self::$logo;
    }

    #[\Override]
    public function checkAvailability(): \MultiFlexi\CredentialCheckResult
    {
        $dsn = (string) ($this->configFieldsInternal->getFieldByCode('MAIL_DSN')?->getValue() ?? '');

        if ($dsn === '') {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Misconfigured,
                _('MAIL_DSN is not set'),
                time(),
            );
        }

        $parsed = parse_url($dsn);

        if ($parsed === false || empty($parsed['host'])) {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Misconfigured,
                _('MAIL_DSN cannot be parsed — check the DSN format (e.g. smtp://user:pass@host:465)'),
                time(),
            );
        }

        $host = $parsed['host'];
        $port = (int) ($parsed['port'] ?? 25);

        // Host reachability only — avoid sending a test message as a health probe.
        $socket = @fsockopen($host, $port, $errno, $errstr, 5.0);

        if ($socket === false) {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Unavailable,
                sprintf(_('Cannot reach mail server %s:%d — %s'), $host, $port, $errstr),
                time(),
                60,
            );
        }

        fclose($socket);

        return new \MultiFlexi\CredentialCheckResult(\MultiFlexi\CredentialState::Available, '', time(), 300);
    }
}
