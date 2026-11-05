# Drago Project Auth

Users of Drago Project: sign-in, sign-up, password recovery and email verification, with rate limits, account
emails, user roles and a command to create users.

[![PHP version](https://badge.fury.io/ph/drago-ex%2Fproject-auth.svg)](https://badge.fury.io/ph/drago-ex%2Fproject-auth)
[![Tests](https://github.com/drago-ex/project-auth/actions/workflows/tests.yml/badge.svg)](https://github.com/drago-ex/project-auth/actions/workflows/tests.yml)
[![Coding Style](https://github.com/drago-ex/project-auth/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/project-auth/actions/workflows/coding-style.yml)

## Requirements

- PHP >= 8.3, < 9
- [Drago Project](https://github.com/drago-ex/project)

## Installation

```bash
composer require drago-ex/project-auth
```

[Project Tools](https://github.com/drago-ex/project-tools) copies the files of the package into the project,
where you adapt them. The sign-in pages have their own entry point `assets/sign-in.js`; its npm dependencies
(theme switcher, Font Awesome) are installed by `npm install` of the project.

## Database

The migrations create the tables `users`, `user_tokens` and `user_roles` and run before the packages that use
them. Run them with `bin/project-setup` of Drago Project (it also asks for the administrator) or
`php vendor/bin/migration db:migrate --packages`.

## Configuration

`app/Presentation/Sign/conf.neon`:
- `authMailFrom`: sender of the account emails, an address of your domain
- `authRequireEmailVerification`: a new account confirms its email address by a link (default `true`)
- `mail`: the SMTP connection

After signing in, the user goes to the administration (`:Backend:Admin:`), or to the front page (`:Front:Home:`)
when the ACL does not allow the administration to them; change it by the constants `SignedInDestination` and
`FallbackDestination` of `Sign/SignPresenter.php`.

## Users

Create a user from the console; the email and the password are checked, `--admin` gives the role `admin`:

```bash
php vendor/bin/create-user <username> <email> <password> [--admin]
```

The roles of users are stored by name in `user_roles` (`admin` or a custom role of
[Project Permission](https://github.com/drago-ex/project-permission)); a user may have more roles. A user without
a role is `user`, roles the ACL does not know are left out on sign-in. A role can be given in code:

```php
$userRepository->addRole($userId, Drago\Permission\Role::Admin);
```

Require a signed-in user in a presenter; a visitor goes to the sign-in and back to the requested page:

```php
use App\Presentation\Accessory\RequireLogged;
use Nette\Application\UI\Presenter;

final class SecurePresenter extends Presenter
{
	use RequireLogged;
}
```

## Security

- Passwords are hashed by `Nette\Security\Passwords`; the rules are in `Sign/PasswordPolicy` (at least 12 characters).
- Sign-in, sign-up and password recovery do not reveal whether an email has an account.
- Changing or recovering the password signs the user out of all other sessions.
- Recovery codes and confirmation links are stored hashed, expire and work only once.

Attempts are limited in the application cache (clearing `temp/cache` resets them):

| Action | Limit |
|---|---|
| Sign-in | 10 failed attempts per email and 30 per IP address in 15 minutes |
| Registration | 10 attempts per IP address in an hour |
| Password recovery | 5 requests per email and 20 per IP address in an hour, 10 wrong codes in 15 minutes |
| New confirmation link | 3 requests per email and 10 per IP address in an hour |

Behind a reverse proxy set `http: proxy:`, otherwise the per-IP limits apply to the proxy address.

## Emails

The email templates are in `Sign/Email/` with the shared layout `@email.latte`. To catch emails locally, add
[Mailpit](https://mailpit.axllent.org) to `docker-compose.override.yaml`:

```yaml
  mailpit:
    image: axllent/mailpit:v1.27
    restart: always
    ports:
      - '8025:8025'
```

and point the mailer to it in `Sign/conf.neon`:

```neon
mail:
	smtp: true
	host: mailpit
	port: 1025
```

Emails are shown at http://localhost:8025.

## Permissions

The rules are in `Sign/acl.neon`: the resource `Sign`, for everyone.

The same file describes the privileges (`permissionLabels`) for the permissions page of [Project Permission](https://github.com/drago-ex/project-permission).
