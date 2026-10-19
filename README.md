<p align="center">
  <img src="https://seviaflow.io/assets/img/seviaflow-logo.png" alt="SeviaFlow" width="240">
</p>

<h1 align="center">SeviaFlow Core</h1>

<p align="center"><strong>Define your data once. Operate it everywhere.</strong></p>

<p align="center">The package-neutral Laravel data engine: define Resources, forms, tables, workflows and permissions in PHP, then add only the runtime surfaces your application needs.</p>

<p align="center">
  <a href="https://seviaflow.io/">Website</a> ·
  <a href="https://seviaflow.io/docs/0.1/why-seviaflow">What is SeviaFlow?</a> ·
  <a href="https://seviaflow.io/docs/0.1/">Documentation</a> ·
  <a href="https://github.com/seviaflow/core">Source</a> ·
  <a href="https://packagist.org/packages/seviaflow/core">Packagist</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/status-beta-orange?style=flat-square" alt="Beta release">
  <img src="https://img.shields.io/badge/license-MIT-2563eb?style=flat-square" alt="MIT License">
</p>

<p align="center"><strong>Composer package:</strong> <code>seviaflow/core</code></p>

## Beta compatibility

The free beta supports Laravel 12 on PHP 8.2+ and Laravel 13 on PHP 8.3+. Laravel 11 is outside this beta's support scope; its security support ended on March 12, 2026. CI covers these framework targets. Package unit tests use SQLite; the Northstar integration and browser suites use MariaDB 10.11. PostgreSQL is not yet a verified beta target.

Core owns the package-neutral SeviaFlow data/resource engine: resources, forms, tables, workflows, permissions, events, import/export primitives and extension contracts.

Core intentionally has no Livewire dependency and does not register UI or API routes.

## Admin Access Hook

Core does not infer panel access from the optional role system. Hosts must configure
`seviaflow.permissions.admin_gate` with a callable accepting the current
`Authenticatable` user and returning a boolean. A missing or failing hook denies
panel access. The UI evaluates this hook on every request and Livewire update, so
role revocation takes effect without relying on a stale mounted component.

```php
'permissions' => [
    'admin_gate' => [App\Support\NorthstarAccess::class, 'canAccessPanel'],
],
```

## Installation

This package is in beta and is licensed under the [MIT License](LICENSE). You may use, modify and redistribute it with the license notice. Beta APIs and compatibility may change before a stable release.

Core is the headless profile and can be used on its own. UI, API, DevTools and Framework are optional packages in the free SeviaFlow edition; Copilot is separate.

Install the Core beta from Packagist:

```bash
composer require seviaflow/core:^0.1@beta
```

## Dependencies

Core depends on PHP and Illuminate components only. It does not require `seviaflow/ui`, `seviaflow/api` or `seviaflow/devtools`.

## Documentation

- [Semantic Analytics](docs/ANALYTICS.md): define datasets, measures, dimensions, time dimensions, and semantic queries for dashboards and Copilot.

## Local Checks

```bash
composer validate --strict
composer update
composer check
```

## Free edition scope

Core, UI, API, DevTools and Framework are part of the free edition. Copilot is an optional paid module and is excluded. These five packages use MIT; Copilot has a separate license.
