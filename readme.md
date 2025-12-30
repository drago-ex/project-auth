## Drago Project auth
Authentication and user access package for the Drago project.

This package provides a complete authentication layer including user login,
registration, password recovery and access control. It is designed as a modular
extension for projects built on top of the Drago ecosystem and Nette framework.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://raw.githubusercontent.com/drago-ex/project-auth/main/license)
[![PHP version](https://badge.fury.io/ph/drago-ex%2Fproject-auth.svg)](https://badge.fury.io/ph/drago-ex%2Fproject-auth)
[![Coding Style](https://github.com/drago-ex/project-auth/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/project-auth/actions/workflows/coding-style.yml)

## Requirements
- PHP >= 8.3
- Nette Framework
- Drago Project core packages

## Features
- User authentication (sign in / sign out)
- User registration (sign up)
- Password recovery and reset
- User identity handling
- Integration with Nette Security and DI
- Ready-to-use backend UI components

## Install
```bash
composer require drago-ex/project-auth
```

## Adds a new user to the database
Hashes the password, generates a token, and ensures the email is unique.
```bash
php vendor/bin/create-user <username> <email> <password>
```

# Database migration
- https://github.com/drago-ex/migration
```bash
php vendor/bin/migration db:migrate vendor/drago-ex/project-auth/migrations 
```
