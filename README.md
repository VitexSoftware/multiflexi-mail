# multiflexi-mail

SMTP/Mail credential support for [MultiFlexi](https://multiflexi.eu) via [Symfony Mailer](https://symfony.com/doc/current/mailer.html).

## Description

This package provides mail/SMTP credential management for MultiFlexi, split into two Debian packages:

- `multiflexi-mail` — Credential prototype with Mailer DSN and sender address fields (enhances `php-vitexsoftware-multiflexi-core`)
- `multiflexi-mail-ui` — Connection test panel, EHLO capabilities display, test message sending (enhances `multiflexi-web`)

## Credential Fields

- **MAIL_DSN** — Symfony Mailer DSN string (e.g. `smtp://user:pass@smtp.example.com:465`)
- **MAIL_FROM** — Default sender email address

## UI Features

The web interface component displays:
- Parsed DSN configuration (transport, host, port, user)
- SMTP connection test result
- Server banner and EHLO capabilities
- Test message sending (sends to MAIL_FROM address)

## Installation

### From Debian packages

```bash
apt install multiflexi-mail multiflexi-mail-ui
```

### From source (development)

```bash
composer install
make phpunit
make cs
```

## Building Debian Packages

```bash
make deb
```

This produces `multiflexi-mail_*.deb` and `multiflexi-mail-ui_*.deb` in the parent directory.

## License

MIT — see [debian/copyright](debian/copyright) for details.

## MultiFlexi

[![MultiFlexi](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/)
