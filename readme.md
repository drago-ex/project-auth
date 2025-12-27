## Drago Project auth
Authentication and user access package for the Drago project.

This package provides a complete authentication layer including user login,
registration, password recovery and access control. It is designed as a modular
extension for projects built on top of the Drago ecosystem and Nette framework.

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
composer config --no-plugins allow-plugins.drago-ex/project-auth true
composer require drago-ex/project-auth
```
