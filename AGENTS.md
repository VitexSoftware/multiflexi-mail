# AGENTS.md

## Project Overview

This project provides SMTP/Mail credential support for MultiFlexi via Symfony Mailer as a separate Debian-packaged addon. It produces two binary packages from one source:

- **multiflexi-mail** — credential prototype for `php-vitexsoftware-multiflexi-core` (`MultiFlexi\CredentialProtoType\Mail`)
- **multiflexi-mail-ui** — UI form helper for `multiflexi-web` (`MultiFlexi\Ui\CredentialType\Mail`)

## Directory Structure

- `src/MultiFlexi/CredentialProtoType/Mail.php` — core credential prototype class
- `src/MultiFlexi/Ui/CredentialType/Mail.php` — web UI credential form helper
- `src/images/Mail.svg` — logo asset
- `debian/` — Debian packaging
- `tests/` — PHPUnit tests

## Build & Test

```bash
make vendor    # install composer dependencies
make phpunit   # run tests
make cs        # fix coding standards
make deb       # build Debian packages
```

## Coding Standards

- PHP 8.1+ with strict types
- PSR-12 via ergebnis/php-cs-fixer-config
- Run `make cs` before committing

## Debian Packaging

The `debian/control` defines two binary packages with proper dependency chains:
- `multiflexi-mail` depends on `php-vitexsoftware-multiflexi-core`, `multiflexi-cli (>= 2.2.0)` and `php-symfony-mailer`
- `multiflexi-mail-ui` depends on `multiflexi-mail` and `multiflexi-web`

The `postinst` for `multiflexi-mail` runs `multiflexi-cli crprototype sync` to register the credential prototype.

## Key Classes

### MultiFlexi\CredentialProtoType\Mail
Extends `\MultiFlexi\CredentialProtoType` and implements `\MultiFlexi\credentialTypeInterface`.
Defines fields: MAIL_DSN (Symfony Mailer DSN string), MAIL_FROM (default sender address).

### MultiFlexi\Ui\CredentialType\Mail
Extends `\MultiFlexi\Ui\CredentialFormHelperPrototype`.
Parses the DSN, tests SMTP connectivity (socket + EHLO handshake), displays server capabilities, and sends a test message via Symfony Mailer.

## Credential Fields Provided

The Mail credential prototype (UUID `6ba7b810-9dad-11d1-80b4-00c04fd430c8`) provides two environment variables to consuming applications:

- **`MAIL_DSN`** — Symfony Mailer DSN string. Format: `smtp://user:pass@host:port`. Special characters in user/pass must be URL-encoded. Examples:
  - Plain SMTP: `smtp://user:pass@smtp.example.com:25`
  - SMTPS (implicit TLS): `smtp://user:pass@smtp.example.com:465`
  - STARTTLS: `smtp://user:pass@smtp.example.com:587`
  - No auth: `smtp://smtp.example.com:25`
  - Sendmail: `sendmail://default`
  - 3rd party (e.g. SendGrid): `sendgrid+api://KEY@default`
  - Full DSN spec: https://symfony.com/doc/current/mailer.html#transport-setup
- **`MAIL_FROM`** — Default sender email address (e.g. `noreply@example.com`)

## Consumer Integration Guide (abraflexi-mailer)

The project `~/Projects/VitexSoftware/abraflexi-mailer` is a consumer of this credential type. It sends AbraFlexi documents (invoices) by email.

### Current State (to be migrated)

Currently abraflexi-mailer uses:
- **`Ease\HtmlMailer`** class (from `vitexsoftware/ease-core`) as mail backend
- **`pear/net_smtp`** composer dependency for SMTP transport
- **`EASE_SMTP`** environment variable — PEAR Mail config string (proprietary format)
- **`MAIL_FROM`** environment variable — already matches the credential field

Key files using the old mail backend:
- `src/AbraFlexi/Mailer/DocumentMailer.php` — extends `Ease\HtmlMailer`, uses `$this->fromEmailAddress` from `Shared::cfg('MAIL_FROM')`
- `src/SendUnsent.php` — requires `MAIL_FROM` in `Shared::init()`
- `src/SendUnsentWithAttachments.php` — requires `MAIL_FROM` in `Shared::init()`
- `src/BulkMail.php` — uses `Shared::cfg('MAIL_FROM')` for `From` header

### Target State (after migration)

Replace `Ease\HtmlMailer` + `pear/net_smtp` with `Symfony\Component\Mailer\Mailer` using `MAIL_DSN`:

1. **composer.json** — remove `pear/net_smtp`, add `symfony/mailer` (`^6.0|^7.0`)
2. **DocumentMailer.php** — refactor from extending `Ease\HtmlMailer` to using `Symfony\Component\Mailer\Mailer` + `Symfony\Component\Mime\Email`
3. **Environment variables** — replace `EASE_SMTP` with `MAIL_DSN` everywhere:
   - `Shared::init()` calls in all entry points
   - `example.env`
4. **MultiFlexi app.json files** — in `multiflexi/*.multiflexi.app.json`, replace `EASE_SMTP` environment entries with `MAIL_DSN`

### Symfony Mailer Usage Pattern

To send mail using the credential-provided `MAIL_DSN`:

```php
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

$transport = Transport::fromDsn(\Ease\Shared::cfg('MAIL_DSN'));
$mailer = new Mailer($transport);

$email = (new Email())
    ->from(\Ease\Shared::cfg('MAIL_FROM'))
    ->to($recipientAddress)
    ->subject($subject)
    ->html($htmlBody)
    ->attachFromPath($pdfPath, 'invoice.pdf', 'application/pdf');

$mailer->send($email);
```

### MultiFlexi App JSON Environment Migration

In each `multiflexi/*.multiflexi.app.json`, replace:
```json
"EASE_SMTP": {
  "type": "string",
  "description": { "en": "configuration string for Pear_Mail" },
  ...
}
```
With:
```json
"MAIL_DSN": {
  "type": "string",
  "description": {
    "en": "Symfony Mailer DSN (e.g. smtp://user:pass@host:port)",
    "cs": "Symfony Mailer DSN (např. smtp://user:pass@host:port)"
  },
  "defval": "",
  "required": true,
  "category": "Mail"
}
```

### Affected MultiFlexi App Definitions

These files in `~/Projects/VitexSoftware/abraflexi-mailer/multiflexi/` currently define `EASE_SMTP` and need updating:
- `email_sender.multiflexi.app.json` (uuid: 97c1c85d-3800-4d12-aabb-b60b15cd8df0)
- `abraflexi_send.multiflexi.app.json` (uuid: 37386766-78e5-46f6-8240-a15fb8d895ba)

The `MAIL_FROM` entries in these files already match the credential field and can remain as-is.

### CLI Entry Points Requiring MAIL_DSN

All `bin/` scripts that send mail will need `MAIL_DSN` in their required env:
- `abraflexi-send-unsent` → `src/SendUnsent.php`
- `abraflexi-send-unsent-with-attachments` → `src/SendUnsentWithAttachments.php`
- `abraflexi-bulkmail` → `src/BulkMail.php`
- `abraflexi-send` → `src/SendDocument.php`
- `abraflexi-potvrzeni-prijeti-faktury` → `src/SendPotvrzeniPrijetiFaktury.php`
- `abraflexi-potvrzeni-prijeti-uhrady` → `src/SendPotvrzeniPrijetiUhrady.php`
- `abraflexi-potvrzeni-odeslani-uhrady` → `src/SendPotvrzeniOdeslaniUhrady.php`
