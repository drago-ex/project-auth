# Drago Project Auth

Authentication and user access package for the Drago project.
This package provides a complete authentication layer including user login,
registration, password recovery and access control. It is designed as a modular
extension for projects built on top of the Drago ecosystem and Nette Framework.

[![PHP version](https://badge.fury.io/ph/drago-ex%2Fproject-auth.svg)](https://badge.fury.io/ph/drago-ex%2Fproject-auth)
[![Tests](https://github.com/drago-ex/project-auth/actions/workflows/tests.yml/badge.svg)](https://github.com/drago-ex/project-auth/actions/workflows/tests.yml)
[![Coding Style](https://github.com/drago-ex/project-auth/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/project-auth/actions/workflows/coding-style.yml)

## Requirements

- PHP >= 8.3, < 9
- Nette Framework
- Composer
- Bootstrap
- Naja
- Node.js
- Drago Project core packages

## Installation

```bash
composer require drago-ex/project-auth
```

### npm Installation

The authentication UI requires the theme switcher and Font Awesome icons:
```bash
npm install theme-switcher-compostrap @fortawesome/fontawesome-free
```

## Project files

File copying is handled automatically by [drago-ex/project-tools](https://github.com/drago-ex/project-tools),
which must be installed in your project. Without it, copy the files manually according to the `copy` section
in this package's `composer.json`. To skip this package, set `"skip": true` under
`extra.drago-tools.packages.<package-name>` in your root `composer.json`.

## Features

- User authentication (sign in / sign out)
- User registration (sign up)
- Password recovery and reset
- Email address verification (after registration and when the address changes)
- User identity handling
- Integration with Nette Security and DI
- Ready-to-use authentication UI components
- Rate limiting of sign-in, registration and password recovery

## Rate limiting

Attempts are counted in the application cache (`Nette\Caching\Storage`), independently of the session,
so opening a new session does not reset them. Emails are normalized and all subjects are hashed in cache keys.

| Action | Limit |
|---|---|
| Sign-in | 10 failed attempts per email and 30 per IP address in 15 minutes; a successful sign-in clears the email counter |
| Registration | 10 attempts per IP address in an hour |
| Password recovery | 5 requests per email and 20 per IP address in an hour, 10 wrong codes per email in 15 minutes |
| New confirmation link | 3 requests per email and 10 per IP address in an hour |

Clearing the cache (`temp/cache`) also resets these counters.

The IP address comes from `Nette\Http\IRequest::getRemoteAddress()`. Behind a reverse proxy or load balancer,
set `http: proxy:` in the configuration; otherwise all visitors share the proxy address and the per-IP limits
block everyone at once.

## Security

What the package handles for you:

- **Passwords** are hashed by `Nette\Security\Passwords` and rehashed automatically when the algorithm changes.
- **Sign-in** shows one message for a wrong email or password, and an unknown email takes as long to answer as
  a known one, so neither reveals who has an account. Nette regenerates the session ID on sign-in, and `backlink`
  is restored through `restoreRequest()`, so it cannot redirect to another site.
- **Sessions** are restored by a random token of the user. Changing or recovering the password replaces it,
  which signs the user out of all other sessions.
- **Password recovery** answers the same way whether the email exists or not. The 6-digit code is stored hashed
  in the session, is valid for 15 minutes and for one use only, and is limited per session, email and IP address.
- **Password rules** (`Sign/PasswordPolicy`) follow NIST SP 800-63B and OWASP ASVS: at least 12 characters,
  no required character classes, spaces allowed, at most 72 bytes (bcrypt ignores the rest). Registration,
  password recovery, the account (`drago-ex/project-account`) and `bin/create-user` use the same rules.
- **Sign-out** happens at once only from a link on your site. A link from another site, or a browser prefetch,
  shows a sign-out button (POST form) instead, so another site cannot sign the user out.
- **Email verification**: a new account confirms its email address by a link before it can sign in; the message
  about it is shown only after the right password, so it does not reveal accounts. Links are random, stored only
  as a hash, valid for 24 hours (1 hour for an email change) and for one use; only the latest link works.
  Completing a password recovery also confirms the address. See [Email verification](#email-verification).
- **Forms** are protected against CSRF by Nette's same-origin check; signals require AJAX requests.

Deliberate choices you may want to change in your project:

- **Registration reveals an existing email** ("An account with this email address already exists"), which is
  friendlier for users. It is limited to 10 attempts per IP address in an hour; if it matters for your project,
  show a neutral message instead and inform the owner by email.
- **Two-factor authentication** is not included.

Set `authMailFrom` in `Sign/conf.neon` to an address of your domain, otherwise the account emails end up in spam.

## Email verification

Verification is on by default (`authRequireEmailVerification` in `Sign/conf.neon`):

- **Registration** sends a link to confirm the address. Until it is confirmed, signing in shows "Please confirm
  your email address first", and the sign-in page offers to send the link again (`Sign:verification`).
- **Changing the email address** (`drago-ex/project-account`) sends a link to the new address; the account keeps
  the previous address until the link is opened. The previous address then gets a notice with a link to reset
  the password, in case someone else made the change.
- Links lead to `Sign:verify`, which confirms the address and signs nobody in.
- Accounts created by the installer or `bin/create-user` are verified at once. The migration
  `003_users_verified_at.sql` marks existing accounts as verified, so nobody loses access.

With `authRequireEmailVerification: false`, new accounts can sign in at once and get a welcome email instead,
and an email change applies immediately (the previous address is still notified).

## Emails

All emails are translated and can be adapted in your project. Those in `Sign/Email/` share the layout `@email.latte`:

| Template | Sent |
|---|---|
| `Sign/Email/verify.latte` | after registration, with the confirmation link |
| `Sign/Email/sign-up.latte` | after registration when verification is off (welcome) |
| `Sign/Email/email-change.latte` | to a new address, with the confirmation link |
| `Sign/Email/email-changed.latte` | to the previous address after a change |
| `Sign/Recovery/email.latte` | password recovery code |

The SMTP connection is set in the `mail` section of `Sign/conf.neon`.

### Testing emails locally with Docker

[Mailpit](https://mailpit.axllent.org) catches every email the application sends and shows it in a web interface,
so nothing reaches real addresses. Add it to `docker-compose.override.yaml` under `services`:

```yaml
  # catches all outgoing emails; web interface at http://localhost:8025
  mailpit:
    image: axllent/mailpit:v1.27
    restart: always
    ports:
      - '8025:8025'
```

Point the mailer to it in `Sign/conf.neon`:

```neon
mail:
	smtp: true
	host: mailpit
	port: 1025
```

Start it with `docker compose up -d mailpit` and open http://localhost:8025. The application reaches Mailpit by
the service name `mailpit` inside the Docker network, so port 1025 does not need to be published. Use this only
for development; production needs a real SMTP server.

## Adds a new user to the database

Hashes the password, generates a token, and ensures the email is unique.
```bash
php vendor/bin/create-user <username> <email> <password>
```

## Secure access to the section

```php
use App\Presentation\Accessory\RequireLogged;
use Nette\Application\UI\Presenter;

final class SecurePresenter extends Presenter
{
	use RequireLogged;
}
```

## Generate permission provider

If you use project ACL (drago-ex/permission), you can generate a module permission class:
```bash
php vendor/bin/create-auth-permission
```

## Database migration

The migrations are exported into the project's `migrations/` directory and run from there, together with
those of the other packages in one order (`db-migration-order.json`). This package has `commands-priority` 10,
so its tables exist before the packages that use the `users` table.

```bash
php vendor/bin/sql-export
php vendor/bin/migration db:migrate migrations
```

With [drago-ex/project-tools](https://github.com/drago-ex/project-tools), the `db:migrate` operation of
`drago-setup` does both.

## Automated setup

This package exposes setup commands in `composer.json` under `extra.drago-tools.commands`.
If [drago-ex/project-tools](https://github.com/drago-ex/project-tools) is installed, you can run them from the project root:

```bash
php vendor/bin/drago-setup
```
