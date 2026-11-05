# Agent Guide

`drago-ex/project-auth` (type drago-tools-resource, PHP >=8.3 <9, `migration-priority` 10): users, sign-in,
sign-up, password recovery, email verification, user roles and `bin/create-user`. The workspace rules are in
`../AGENTS.md`; dependencies are in `composer.json`.

## Layout
- `migrations/`: `001_users.sql` (complete `users` incl. `verified_at`), `002_user_tokens.sql`, `003_user_roles.sql`.
- `resources/app/Model/User`: `UserAuthenticator`, `UserRepository`, `UserTokenRepository`, entities.
- `resources/app/Presentation/Sign`: `SignPresenter`, factories, `PasswordPolicy`, `RateLimiter`, `SignInLimiter`,
  `Recovery/`, `Verification/`, `Email/`, `conf.neon`, `acl.neon`, `Translate/cs.neon`.
- `resources/app/Presentation/Accessory`: `RequireLogged`, `Widget/@brand.latte`, `Widget/@theme-switch.latte`
  (copied, so project-settings can replace the brand).
- `resources/assets/sign-in.js` + `.scss`: an own Vite entry; `package.json` (with `name`) brings its npm
  dependencies through `npm-loader.js` of drago-ex/project.
- `bin/create-user`, `tests/`.

## How it works
- `tests/bootstrap.php` autoloads `resources/app`. Files of project-front and project-backend come into this
  repository only by `drago-install --dev` and must not be committed.
- Rate limits: `Sign\RateLimiter` (cache), limits defined in `SignInLimiter` and `Recovery\RecoveryLimiter`.
- Password recovery: `Recovery\SessionService` keeps the hashed code and the attempts;
  `RecoveryFactory::changePassword()` must keep the check that the code was verified. A password change
  generates a new user token, which ends the other sessions.
- After sign-in the user goes to `SignPresenter::SignedInDestination` (`:Backend:Admin:`) when the ACL allows it
  (`Drago\Permission\Privilege::isAllowed()`, unknown resource denied; allowed without an authorizator), otherwise
  to `FallbackDestination` (`:Front:Home:`), so a role without the dashboard gets no 403.
- No `class_exists()` checks for optional packages such as project-account; the package stays independent.
- Every account email goes through `Sign\Email\EmailSender` with `Email/@email.latte`; a failed send is logged and
  the action continues, so the answer never tells whether an address is registered.
- Sign-up with a registered address and verification on: `VerificationService::alreadyRegistered()` emails the
  owner (at most 3 per address and hour) and the form answers like a success; without verification the form says
  the address is used. The resend-confirmation link is shown only after the right password of an unconfirmed
  account (`SignTemplate::$notConfirmed`); `Sign:verification` stays reachable directly.
- `RequireLogged` redirects to the sign-in with a `backlink` stored only for a plain GET page without a signal, so
  a link with a signal (e.g. delete) sent to a signed-out admin is never replayed after sign-in. drago-ex/permission's
  `Authorization` trait does the same.
- Roles: `user_roles(user_id, role VARCHAR(50))` stores role NAMES, because the base roles live in the ACL neon of
  drago-ex/permission and custom ones in project-permission; there is no `roles` table here (a `parent` column
  would leak project-permission into the base). `UserRepository::getRolesByUser()` reads them, `addRole()`
  inserts with IGNORE, `UserRoleEntity` has `ColumnRoleSize`. `UserAuthenticator::getRoles()` (sign-in and
  `wakeupIdentity()`) drops roles the authorizator does not know and falls back to `Role::User`, because an empty
  list would deny even `Account`; sign-up writes no row.
- `bin/create-user` gives `admin` with `--admin` (anywhere among the arguments), otherwise no role.
  `bin/project-setup` of drago-ex/project calls it after asking for the administrator.

## Commands
```bash
composer phpstan
composer tester
```
