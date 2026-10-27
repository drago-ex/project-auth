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

## Files copied into the project

The files are copied into the project by [drago-ex/project-tools](https://github.com/drago-ex/project-tools),
as set in `extra.drago-tools` of this package's `composer.json`.

## Database migration
```bash
php vendor/bin/migration db:migrate --packages
```
The package has `migration-priority` 10, so the `users` table exists before the packages that use it.

## Features
- User authentication (sign in / sign out)
- User registration (sign up)
- Password recovery and reset
- Email address verification (after registration and when the address changes)
- Rate limiting of sign-in, registration and password recovery
- User identity handling
- Integration with Nette Security and DI
- Ready-to-use authentication UI components

## Configuration
Parameters in `app/Presentation/Sign/conf.neon`:
- `authMailFrom` - sender of the account emails, use an address of your domain
- `authRequireEmailVerification` - a new account confirms its email address by a link (default `true`)
- `mail` section - SMTP connection

After signing in, the user goes to the administration (`:Backend:Admin:`), or to the front page (`:Front:Home:`)
when the ACL does not allow the administration to them. To change it, edit the `SignedInDestination` and
`FallbackDestination` constants in `app/Presentation/Sign/SignPresenter.php`.

Behind a reverse proxy, set `http: proxy:`, otherwise the per-IP limits apply to the proxy address.

## Rate limiting
Attempts are counted in the application cache; clearing `temp/cache` resets them.

| Action | Limit |
|---|---|
| Sign-in | 10 failed attempts per email and 30 per IP address in 15 minutes |
| Registration | 10 attempts per IP address in an hour |
| Password recovery | 5 requests per email and 20 per IP address in an hour, 10 wrong codes in 15 minutes |
| New confirmation link | 3 requests per email and 10 per IP address in an hour |

## Security
- Passwords are hashed by `Nette\Security\Passwords`; rules in `Sign/PasswordPolicy` (at least 12 characters).
- Sign-in and password recovery do not reveal whether an email has an account.
- Changing or recovering the password signs the user out of all other sessions.
- Recovery codes and confirmation links are stored hashed, expire and work only once.
- With email verification on, registration answers the same for a registered address and only emails its owner;
  without verification it says that the address is already used (the new account would be usable at once).
- The link for a new confirmation email is shown only after the right password of an unconfirmed account.
- Two-factor authentication is not included.

## Emails
The email templates are in `Sign/Email/` (shared layout `@email.latte`, also used by `Sign/Recovery/email.latte`).

To catch emails locally, add [Mailpit](https://mailpit.axllent.org) to `docker-compose.override.yaml`:
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

## Adds a new user to the database
Validates the email and the password (`PasswordPolicy`), hashes the password, generates a token, and ensures the email is unique.
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
A visitor who is not signed in goes to the sign-in page and, after signing in, back to the requested page.

---

## Optional add-ons

The package works without them. Set them up by hand as described below when the project needs them.

### Permissions
The package works without permissions and without [drago-ex/permission](https://github.com/drago-ex/permission).
For basic permissions, install drago-ex/permission and generate the access rules from [`bin/create-auth-permission`](bin/create-auth-permission):
```bash
php vendor/bin/drago-commands
```

Users have no roles of their own until `getRolesByUser()` in `app/Model/User/UserRepository.php` returns them.
To load them from the database of [drago-ex/project-permission](https://github.com/drago-ex/project-permission),
implement it:
```php
use App\Presentation\Backend\Permission\Component\Roles\RolesEntity;
use App\Presentation\Backend\Permission\Component\Users\UsersRolesEntity;
use Drago\Permission\Role;

public function getRolesByUser(int $userId): array
{
	$roles = $this->getConnection()
		->select('r.*')->from(RolesEntity::Table)->as('r')
		->innerJoin(UsersRolesEntity::Table)->as('ur')->on('ur.role_id = r.id')
		->where('ur.%n = ?', UsersRolesEntity::ColumnUserId, $userId)
		->fetchPairs(value: RolesEntity::ColumnName);

	$roles = array_values($roles);
	return $roles ?: [Role::RoleUser];
}
```
