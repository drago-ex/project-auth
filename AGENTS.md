# Agent Guide

Use this file as package-specific guidance when working in this repository. Keep changes aligned with the existing public API, project conventions, and supported PHP range.

## Package Overview
- **Package**: `drago-ex/project-auth`
- **Type**: drago-tools-resource
- **Purpose**: Authentication and user access package for the Drago project.
- **PHP**: >=8.3 <9
- **Runtime dependencies**: drago-ex/database (^3.0), drago-ex/application (^3.0), drago-ex/form (^2.0), drago-ex/migration (^2.0), drago-ex/utils (^2.0), drago-ex/translator (^4.0), drago-ex/project-front (^2.0), drago-ex/project-backend (^2.0), nette/caching (^3.3), nette/http (^3.0), nette/mail (^4.0), nette/security (^3.2)
- **License**: MIT

## Repository Layout
- `bin/`: command-line entry points.
- `migrations/`: database migrations.
- `resources/`: application resources contributed to the consuming Drago project.
- `tests/`: automated tests.
- `.github/`: CI workflows; keep their PHP versions and commands consistent with Composer and the package scripts.
- `composer.json`: package metadata, dependency constraints, autoloading, and available Composer scripts.

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
