# Module configuration registry

Installed packages register application settings and resource extensions with
`SeviaFlow\Core\Configuration\ConfigurationRegistry` in their service provider's
`boot()` method. Consume the complete registry after all providers have booted,
for example in a controller or an `app->booted()` callback. Core registers the
registry as a singleton. It never scans vendor directories or loads absent modules.

```php
use SeviaFlow\Core\Configuration\ConfigurationRegistry;
use SeviaFlow\Core\Configuration\SettingDefinition;
use SeviaFlow\Core\Configuration\SettingType;

public function boot(ConfigurationRegistry $registry): void
{
    $registry->registerApplication('delivery', [
        new SettingDefinition(
            key: 'enabled', type: SettingType::Boolean, default: false,
            group: 'general', label: 'Enable delivery',
            readAbility: 'settings.view', writeAbility: 'settings.update',
            configKey: 'delivery.enabled',
        ),
        new SettingDefinition(
            key: 'api_key', type: SettingType::String, default: '',
            group: 'credentials', secret: true,
            readAbility: 'settings.view', writeAbility: 'settings.update',
            configKey: 'delivery.api_key', rules: ['max:512'],
        ),
    ]);

    $registry->registerResource('delivery', [
        new SettingDefinition('enabled', SettingType::Boolean, false),
    ], static fn (string $resource): array => [
        'enabled' => method_exists($resource, 'deliveryEnabled')
            && $resource::deliveryEnabled(),
    ]);
}
```

Types: `Boolean`, `Integer`, `Number`, `String`, `List`, `Map`. Types are strict;
the string `"false"` does not become boolean false. Use Laravel config/env parsing
or explicitly normalize form input before submitting a change. Null must be
explicitly enabled with `nullable: true`. Defaults must match the declared type.
Laravel validation `rules` apply to the complete value; `itemRules` apply to each
element of a list/map (e.g. `['string', 'in:list,get,create,update']`).

Application values come from the declared Laravel config keys; missing values use
defaults, while an explicit null is validated as null. Resource values come from
the module's resolver callback; missing keys use defaults. Unknown resolver keys,
duplicate setting keys and duplicate module registrations within one scope fail
instead of silently overriding settings or permissions.

## Trusted runtime versus user-facing access

`runtime('delivery')` and `runtime('delivery', ProductResource::class)` return
validated effective settings, including raw secrets. These methods are for trusted
server code only. They do not authorize a user, and must not be exposed as an API.
Resource definitions are resolved on every call, without a cross-user/tenant cache.
The current authentication and tenant context must be established by the host app.

For a UI/API/configuration generator, enumerate `modules()` or
`modules('resource')` and call `describe($module, $actor, $resourceClassOrNull)`.
The result includes type, nullable, label, group, secret marker, editable flag, and
non-secret defaults. Only readable settings are included. No raw config values,
config keys, validation closures or secret defaults appear in this schema.

`values($module, $actor, $resourceClassOrNull)` returns readable effective values,
with every secret replaced by `[REDACTED]`. Do not send that marker back as a secret
change; an unchanged secret must be omitted from the patch.

The host defines Laravel Gate abilities. Application checks pass the module name
as an argument; resource checks pass resource class and module name. Read and write
permissions are independent and deny by default when actor/ability is absent.
Resource settings authorization is separate from record CRUD authorization.

`validateChanges($module, $patch, $actor, $resourceClassOrNull)` requires write
permission on every supplied key, rejects unknown keys, validates types and rules,
and returns only supplied values. It does not apply defaults or persist changes.
Validation errors contain setting keys and generic messages, never submitted values.

This registry deliberately has no HTTP endpoints, database persistence or dynamic
config mutation. The host owns storage, encryption, tenant isolation, audit and
cache refresh if it adds an editable settings store. Validating a resource patch
does not edit the resource's PHP definition. Infrastructure settings can be readable
with no write ability, making them visible but not editable in generated forms.

MCP is the first consumer in `seviaflow/mcp`; it registers `mcp` in both scopes
and preserves the existing resource `mcp(McpResource $mcp)` configuration method.
