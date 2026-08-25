# SeviaFlow Core

Composer package: `seviaflow/core`

Repository: `https://github.com/seviaflow/core`

Core owns the package-neutral SeviaFlow data/resource engine: resources, forms, tables, workflows, permissions, events, import/export primitives and extension contracts.

Core intentionally has no Livewire dependency and does not register UI or API routes.

## Installation Status

SeviaFlow is pre-release. The `main` branch is aliased as `0.1.x-dev`; the first stable tag is expected to start at `0.1.0`.

```bash
composer require seviaflow/core:^0.1@dev
```

## Dependencies

Core depends on PHP and Illuminate components only. It does not require `seviaflow/ui`, `seviaflow/api` or `seviaflow/devtools`.

## Local Checks

```bash
composer validate --strict
composer update
composer check
```
