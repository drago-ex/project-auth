# Agent Guide

Use this file as package-specific guidance when working in this repository. Keep changes aligned with the existing public API, project conventions, and supported PHP range.

## Package Overview
- **Package**: `drago-ex/project-auth`
- **Type**: drago-tools-resource
- **Purpose**: Authentication and user access package for the Drago project.
- **PHP**: >=8.3 <9
- **Runtime dependencies**: drago-ex/database (^4.0), drago-ex/application (^4.0), drago-ex/form (^3.0), drago-ex/migration (^1.0), drago-ex/utils (^2.0), drago-ex/translator (^4.0.1), drago-ex/project-front (^3.0), drago-ex/project-backend (^1.0), nette/caching (^3.3), nette/http (^3.0), nette/mail (^4.0), nette/security (^3.2)
- **License**: MIT

## Repository Layout
- `bin/`: command-line entry points.
- `bin/create-auth-permission`: generates the basic access rules (permission providers) with `create-permission` of `drago-ex/permission`; listed in `extra.drago-tools.commands` and run by `drago-commands` of drago-ex/project-tools. Existing providers are kept; without drago-ex/permission it does nothing.
- `migrations/`: database migrations.
- `resources/`: application resources contributed to the consuming Drago project.
- `tests/`: automated tests.
- `.github/`: CI workflows; keep their PHP versions and commands consistent with Composer and the package scripts.
- `composer.json`: package metadata, dependency constraints, autoloading, and available Composer scripts.

## Key Parts
- Resources in `resources/app` are copied into the project by drago-tools; `tests/bootstrap.php` autoloads them from there. Files of `project-front` and `project-backend` are copied into this repository only by `drago-install --dev` and must not be committed.
- Sign-in, registration and password recovery are rate limited through `Sign\RateLimiter` (cache-based); `SignInLimiter` and `Recovery\RecoveryLimiter` define the limits.
- Password recovery: `Recovery\SessionService` keeps the hashed code and attempts; `RecoveryFactory::changePassword()` must keep the check that the code was verified, and a password change generates a new user token to end other sessions.
- The package stays independent of optional packages such as `project-account`: no `class_exists()` checks for their classes. A signed-in user is redirected to `SignPresenter::SignedInDestination` (`:Backend:Admin:`, from the required `project-backend`) when the ACL allows its `{action}-read` (or there is no authorizator, checked like the Authorization trait; an unknown resource is denied), otherwise to `FallbackDestination` (`:Front:Home:`); projects change those constants. So a role without the dashboard (e.g. a comment moderator before the dashboard is granted) gets no 403 after signing in.
- Every account email (confirmation, email change, recovery code) goes through `Sign\Email\EmailSender` with the `Email/@email.latte` layout; a failed send is logged and the action continues, so the answer never tells whether an address is registered.
- Sign-up with a registered address and verification on: `VerificationService::alreadyRegistered()` emails the owner (at most 3 per address and hour, then silently nothing, so the form cannot flood an inbox) and the form answers like a success; without verification the form says the address is used. The sign-in page shows the resend-confirmation link only after the right password of an unconfirmed account (`SignTemplate::$notConfirmed`); `Sign:verification` stays reachable directly.
- `Accessory\RequireLogged` redirects to the sign-in with a `backlink`, so the user gets back to the requested page. Only a plain GET page without a signal is stored (2026-10-25, code review): a stored signal or POST was replayed after signing in as a same-origin request, so a link sent to a signed-out admin (e.g. a delete signal) ran after the sign-in. drago-ex/permission's Authorization trait does the same.
- Migrations: `001_users.sql` (complete `users` table incl. `verified_at`), `002_user_tokens.sql`; see the workspace rule (one complete `CREATE TABLE` per table while no real project runs).

## Dependencies

Composer constraints describe the package contract. Before changing integration behavior, inspect the source and the actual call sites in this package; do not infer runtime usage solely from dependency declarations.
Development dependencies include nette/tester (^2.5), phpstan/phpstan-nette (^2.0), drago-ex/project-tools (^1.0).

## Essential Commands

```bash
composer tester                 # tester tests -s
composer phpstan                # phpstan analyse
```

## Installation Hooks

Composer install and update hooks invoke Drago project setup/cleanup commands. Inspect those scripts and their effects before invoking Composer operations that trigger them; they may modify generated project files.

## Conventions & Rules

- Preserve backward compatibility unless the task explicitly permits a breaking change. Check callers and public signatures before changing behavior.
- Follow the existing PHP style: strict types, explicit parameter and return types, tabs for indentation, and single-quoted strings where practical. Match nearby code when a convention is unclear.
- Keep package-specific configuration, resources, and migrations in their established directories. Do not edit generated or consuming-project files without confirming they are part of this repository’s intended output.
- Add or update focused tests for behavioral changes when this package has a runnable test setup. Do not invent test commands; use only the scripts and configuration present here.
- Update README examples and Composer metadata when a public API or supported requirement changes.
