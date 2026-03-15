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

namespace MultiFlexi\Ui\CredentialType;

/**
 * Description of Mail.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class Mail extends \MultiFlexi\Ui\CredentialFormHelperPrototype
{
    public function finalize(): void
    {
        $dsnField = $this->credential->getFields()->getFieldByCode('MAIL_DSN');
        $fromField = $this->credential->getFields()->getFieldByCode('MAIL_FROM');
        $dsn = $dsnField ? $dsnField->getValue() : null;
        $from = $fromField ? $fromField->getValue() : '';

        // Always show the DSN builder wizard
        $this->addItem(self::buildDsnWizard($dsn));

        if (empty($dsn)) {
            $this->addItem(new \Ease\TWB4\Alert('danger', _('Mailer DSN is not set')));
            parent::finalize();

            return;
        }

        // Parse DSN
        $parsed = self::parseDsn($dsn);

        if ($parsed === null) {
            $this->addItem(new \Ease\TWB4\Alert('danger', _('Cannot parse Mailer DSN. Expected format: smtp://user:pass@host:port')));
            parent::finalize();

            return;
        }

        // Display parsed DSN info
        $infoPanel = new \Ease\TWB4\Panel(_('Mail Configuration'), 'default');
        $infoList = new \Ease\Html\DlTag(null, ['class' => 'row']);

        $infoList->addItem(new \Ease\Html\DtTag(_('Transport'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($parsed['scheme'], ['class' => 'col-sm-8']));

        $infoList->addItem(new \Ease\Html\DtTag(_('Host'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($parsed['host'], ['class' => 'col-sm-8']));

        $infoList->addItem(new \Ease\Html\DtTag(_('Port'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag((string) $parsed['port'], ['class' => 'col-sm-8']));

        if (!empty($parsed['user'])) {
            $infoList->addItem(new \Ease\Html\DtTag(_('User'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag($parsed['user'], ['class' => 'col-sm-8']));
        }

        if (!empty($from)) {
            $infoList->addItem(new \Ease\Html\DtTag(_('Sender'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag($from, ['class' => 'col-sm-8']));
        }

        $infoPanel->addItem($infoList);
        $this->addItem($infoPanel);

        // Test SMTP connection
        $connectionResult = self::testSmtpConnection($parsed['host'], $parsed['port']);

        if ($connectionResult['success']) {
            $this->addItem(new \Ease\TWB4\Alert('success', sprintf(
                _('SMTP connection to %s:%d successful'),
                $parsed['host'],
                $parsed['port'],
            )));

            if (!empty($connectionResult['banner'])) {
                $this->addItem(new \Ease\Html\DivTag([
                    new \Ease\Html\SmallTag(_('Server banner: '), ['class' => 'text-muted']),
                    new \Ease\Html\SmallTag($connectionResult['banner'], ['class' => 'font-monospace']),
                ], ['class' => 'mb-2']));
            }

            if (!empty($connectionResult['ehlo'])) {
                $ehloPanel = new \Ease\TWB4\Panel(_('Server Capabilities (EHLO)'), 'info');
                $ehloList = new \Ease\Html\UlTag();

                foreach ($connectionResult['ehlo'] as $capability) {
                    $ehloList->addItem(new \Ease\Html\LiTag($capability));
                }

                $ehloPanel->addItem($ehloList);
                $this->addItem($ehloPanel);
            }

            // Attempt to send a test message
            if (!empty($from)) {
                $testResult = self::sendTestMessage($dsn, $from);

                if ($testResult['success']) {
                    $this->addItem(new \Ease\TWB4\Alert('success', sprintf(
                        _('Test message sent successfully to %s'),
                        $from,
                    )));
                } else {
                    $this->addItem(new \Ease\TWB4\Alert('warning', sprintf(
                        _('Test message failed: %s'),
                        $testResult['message'],
                    )));
                }
            } else {
                $this->addItem(new \Ease\TWB4\Alert('info', _('Set MAIL_FROM to enable test message sending')));
            }
        } else {
            $this->addItem(new \Ease\TWB4\Alert('danger', sprintf(
                _('SMTP connection to %s:%d failed: %s'),
                $parsed['host'],
                $parsed['port'],
                $connectionResult['message'],
            )));
        }

        parent::finalize();
    }

    /**
     * Build the interactive DSN wizard panel.
     */
    private static function buildDsnWizard(?string $currentDsn): \Ease\Html\DivTag
    {
        $wizardId = 'dsn-wizard-'.bin2hex(random_bytes(4));

        // Pre-parse current DSN to prefill the wizard
        $prefill = ['scheme' => 'smtp', 'host' => '', 'port' => '', 'user' => '', 'pass' => ''];

        if (!empty($currentDsn)) {
            $parsed = self::parseDsn($currentDsn);

            if ($parsed !== null) {
                $prefill = $parsed;
                $prefill['port'] = (string) $prefill['port'];
            }
        }

        $wizard = new \Ease\Html\DivTag(null, ['id' => $wizardId, 'class' => 'card mb-3']);

        $header = new \Ease\Html\DivTag(
            new \Ease\Html\H5Tag('✉ '._('MAIL_DSN Builder')),
            ['class' => 'card-header bg-light'],
        );
        $wizard->addItem($header);

        $body = new \Ease\Html\DivTag(null, ['class' => 'card-body']);

        // Transport selector
        $body->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dsnTransport_'.$wizardId, _('Transport'), ['class' => 'form-label fw-bold']),
            new \Ease\Html\SelectTag('dsnTransport_'.$wizardId, [
                'smtp' => 'SMTP (smtp://)',
                'sendmail' => 'Sendmail (sendmail://default)',
                'native' => 'Native PHP (native://default)',
            ], $prefill['scheme'], ['class' => 'form-select', 'id' => 'dsnTransport_'.$wizardId]),
        ], ['class' => 'mb-3']));

        // SMTP fields container
        $smtpFields = new \Ease\Html\DivTag(null, ['id' => 'dsnSmtpFields_'.$wizardId]);

        // Host
        $smtpFields->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dsnHost_'.$wizardId, _('SMTP Host'), ['class' => 'form-label']),
            new \Ease\Html\InputTextTag('dsnHost_'.$wizardId, $prefill['host'], [
                'class' => 'form-control',
                'id' => 'dsnHost_'.$wizardId,
                'placeholder' => 'smtp.example.com',
            ]),
        ], ['class' => 'mb-3']));

        // Port
        $smtpFields->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dsnPort_'.$wizardId, _('Port'), ['class' => 'form-label']),
            new \Ease\Html\SelectTag('dsnPort_'.$wizardId, [
                '25' => '25 (SMTP)',
                '465' => '465 (SMTPS / Implicit TLS)',
                '587' => '587 (Submission / STARTTLS)',
                '2525' => '2525 (Alternative)',
            ], $prefill['port'] ?: '587', ['class' => 'form-select', 'id' => 'dsnPort_'.$wizardId]),
        ], ['class' => 'mb-3']));

        // Username
        $smtpFields->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dsnUser_'.$wizardId, _('Username (optional)'), ['class' => 'form-label']),
            new \Ease\Html\InputTextTag('dsnUser_'.$wizardId, $prefill['user'], [
                'class' => 'form-control',
                'id' => 'dsnUser_'.$wizardId,
                'placeholder' => 'user@example.com',
            ]),
        ], ['class' => 'mb-3']));

        // Password
        $smtpFields->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dsnPass_'.$wizardId, _('Password (optional)'), ['class' => 'form-label']),
            new \Ease\Html\InputTag('dsnPass_'.$wizardId, $prefill['pass'], [
                'type' => 'password',
                'class' => 'form-control',
                'id' => 'dsnPass_'.$wizardId,
                'placeholder' => '••••••••',
            ]),
        ], ['class' => 'mb-3']));

        $body->addItem($smtpFields);

        // Preview
        $body->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dsnPreview_'.$wizardId, _('Composed DSN'), ['class' => 'form-label fw-bold']),
            new \Ease\Html\InputTextTag('dsnPreview_'.$wizardId, $currentDsn ?? '', [
                'class' => 'form-control font-monospace bg-light',
                'id' => 'dsnPreview_'.$wizardId,
                'readonly' => 'readonly',
            ]),
        ], ['class' => 'mb-3']));

        // Apply button
        $body->addItem(new \Ease\Html\DivTag(
            new \Ease\Html\ATag('#', '📋 '._('Apply to MAIL_DSN'), [
                'class' => 'btn btn-primary',
                'id' => 'dsnApply_'.$wizardId,
            ]),
            ['class' => 'mb-2'],
        ));

        $wizard->addItem($body);

        // JavaScript
        $js = <<<JS
(function(){
    var wid = '{$wizardId}';
    var transport = document.getElementById('dsnTransport_' + wid);
    var smtpFields = document.getElementById('dsnSmtpFields_' + wid);
    var host = document.getElementById('dsnHost_' + wid);
    var port = document.getElementById('dsnPort_' + wid);
    var user = document.getElementById('dsnUser_' + wid);
    var pass = document.getElementById('dsnPass_' + wid);
    var preview = document.getElementById('dsnPreview_' + wid);
    var applyBtn = document.getElementById('dsnApply_' + wid);

    function encodePart(s) {
        return encodeURIComponent(s).replace(/%40/g, '%40');
    }

    function updatePreview() {
        var scheme = transport.value;
        if (scheme === 'sendmail' || scheme === 'native') {
            preview.value = scheme + '://default';
            smtpFields.style.display = 'none';
            return;
        }
        smtpFields.style.display = '';
        var dsn = scheme + '://';
        var u = user.value.trim();
        var p = pass.value;
        if (u) {
            dsn += encodePart(u);
            if (p) dsn += ':' + encodePart(p);
            dsn += '@';
        }
        dsn += host.value.trim() || 'localhost';
        if (port.value) dsn += ':' + port.value;
        preview.value = dsn;
    }

    transport.addEventListener('change', updatePreview);
    host.addEventListener('input', updatePreview);
    port.addEventListener('change', updatePreview);
    user.addEventListener('input', updatePreview);
    pass.addEventListener('input', updatePreview);

    applyBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var target = document.querySelector('input[name="MAIL_DSN"], input[id*="MAIL_DSN"]');
        if (!target) {
            var inputs = document.querySelectorAll('input[type="text"]');
            for (var i = 0; i < inputs.length; i++) {
                if (inputs[i].name && inputs[i].name.indexOf('MAIL_DSN') !== -1) {
                    target = inputs[i];
                    break;
                }
            }
        }
        if (target) {
            target.value = preview.value;
            target.dispatchEvent(new Event('change', {bubbles: true}));
            applyBtn.textContent = '\\u2705 ' + applyBtn.textContent.replace(/^[\\S]+ /, '');
            setTimeout(function() { applyBtn.textContent = '\\uD83D\\uDCCB ' + applyBtn.textContent.replace(/^[\\S]+ /, ''); }, 2000);
        } else {
            alert('MAIL_DSN input field not found on this page.');
        }
    });

    if (transport.value !== 'smtp') smtpFields.style.display = 'none';
    if (!preview.value) updatePreview();
})();
JS;

        $wizard->addItem(new \Ease\Html\ScriptTag($js));

        return $wizard;
    }

    /**
     * Parse a Symfony Mailer DSN string.
     *
     * @return null|array{scheme: string, user: string, pass: string, host: string, port: int}
     */
    private static function parseDsn(string $dsn): ?array
    {
        $parsed = parse_url($dsn);

        if ($parsed === false || !isset($parsed['host'])) {
            return null;
        }

        $scheme = $parsed['scheme'] ?? 'smtp';
        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($scheme === 'smtps' ? 465 : 25);
        $user = isset($parsed['user']) ? urldecode($parsed['user']) : '';
        $pass = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';

        return [
            'scheme' => $scheme,
            'user' => $user,
            'pass' => $pass,
            'host' => $host,
            'port' => (int) $port,
        ];
    }

    /**
     * Test raw SMTP connection and EHLO handshake.
     *
     * @return array{success: bool, message: string, banner: string, ehlo: array<string>}
     */
    private static function testSmtpConnection(string $host, int $port): array
    {
        $banner = '';
        $ehlo = [];
        $timeout = 10;

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            return [
                'success' => false,
                'message' => sprintf('%s (errno: %d)', $errstr, $errno),
                'banner' => '',
                'ehlo' => [],
            ];
        }

        stream_set_timeout($socket, $timeout);

        // Read server banner
        $banner = trim((string) fgets($socket, 512));

        if (!str_starts_with($banner, '220')) {
            fclose($socket);

            return [
                'success' => false,
                'message' => sprintf(_('Unexpected server banner: %s'), $banner),
                'banner' => $banner,
                'ehlo' => [],
            ];
        }

        // Send EHLO
        $hostname = gethostname() ?: 'localhost';
        fwrite($socket, "EHLO {$hostname}\r\n");

        $response = '';
        $ehlo = [];

        while ($line = fgets($socket, 512)) {
            $line = trim($line);

            if (\strlen($line) > 4) {
                $ehlo[] = substr($line, 4);
            }

            // Last line of multi-line response has space after code
            if (isset($line[3]) && $line[3] === ' ') {
                $response = $line;

                break;
            }
        }

        // Send QUIT
        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        if (!str_starts_with($response, '250')) {
            return [
                'success' => false,
                'message' => sprintf(_('EHLO failed: %s'), $response),
                'banner' => $banner,
                'ehlo' => [],
            ];
        }

        return [
            'success' => true,
            'message' => '',
            'banner' => $banner,
            'ehlo' => $ehlo,
        ];
    }

    /**
     * Send a test email using Symfony Mailer.
     *
     * @return array{success: bool, message: string}
     */
    private static function sendTestMessage(string $dsn, string $from): array
    {
        try {
            $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $email = (new \Symfony\Component\Mime\Email())
                ->from($from)
                ->to($from)
                ->subject('MultiFlexi Mail Credential Test')
                ->text(sprintf(
                    "This is a test message from MultiFlexi.\n\nSent at: %s\nDSN host: %s",
                    (new \DateTime())->format('Y-m-d H:i:s'),
                    parse_url($dsn, \PHP_URL_HOST) ?? 'unknown',
                ));

            $mailer->send($email);

            return ['success' => true, 'message' => ''];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
