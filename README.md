# SeviaFlow Core

Composer package: `seviaflow/core`

Repository: `https://github.com/seviaflow/core`

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

## Installation Status

SeviaFlow is pre-release. This package is licensed under the [MIT License](LICENSE), which permits commercial use, modification and redistribution with its copyright and license notice. Release tags, distribution endpoints and support scope are documented separately.

The intended Core headless profile is a no-Hub, no-API installation. The owner approved the free Core/UI/API/DevTools/Framework edition on 2026-10-10. MIT usage rights apply to this package; publication, support scope and the first stable tag remain release decisions.

```bash
composer require seviaflow/core:^0.1@dev
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

Core, UI, API, DevTools and Framework are free. API requires no paid license or runtime Hub activation. Copilot remains an optional paid module and is excluded from the free edition. The five free packages use MIT; Copilot retains its separate license. Repository visibility and published artifacts are separate from the usage license.
