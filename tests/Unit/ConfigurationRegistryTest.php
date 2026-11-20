<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use SeviaFlow\Core\Configuration\ConfigurationRegistry;
use SeviaFlow\Core\Configuration\SettingDefinition;
use SeviaFlow\Core\Configuration\SettingType;
use SeviaFlow\Core\Resources\Resource;
use Tests\TestCase;

final class ConfigurationRegistryTest extends TestCase
{
    private function registry(): ConfigurationRegistry
    {
        return app(ConfigurationRegistry::class);
    }

    public function test_module_defaults_groups_and_config_overrides(): void
    {
        $this->registry()->registerApplication('sample', [
            new SettingDefinition('limit', SettingType::Integer, 10, group: 'limits', rules: ['min:1', 'max:50'], readAbility: 'settings.read', configKey: 'sample.limit'),
        ]);
        Gate::define('settings.read', fn ($user, $module): bool => $module === 'sample');
        $user = new User;
        $this->assertSame(['sample'], $this->registry()->modules());
        $this->assertSame(['limit' => 10], $this->registry()->runtime('sample'));
        config(['sample.limit' => 20]);
        $this->assertSame(['limit' => 20], $this->registry()->values('sample', $user));
        $this->assertSame('limits', $this->registry()->describe('sample', $user)['limit']['group']);
    }

    public function test_secrets_are_redacted_and_read_write_are_separate(): void
    {
        $this->registry()->registerApplication('sample', [
            new SettingDefinition('key', SettingType::String, 'private-default', secret: true, readAbility: 'read', writeAbility: 'write'),
            new SettingDefinition('hidden', SettingType::Boolean, false),
        ]);
        Gate::define('read', fn ($user): bool => true);
        Gate::define('write', fn ($user): bool => false);
        $user = new User;
        $schema = $this->registry()->describe('sample', $user);
        $this->assertArrayNotHasKey('default', $schema['key']);
        $this->assertArrayNotHasKey('hidden', $schema);
        $this->assertFalse($schema['key']['writable']);
        $this->assertSame(['key' => '[REDACTED]'], $this->registry()->values('sample', $user));
        $this->assertSame([], $this->registry()->values('sample', null));
        $this->expectException(AuthorizationException::class);
        $this->registry()->validateChanges('sample', ['key' => 'new'], $user);
    }

    public function test_resource_extensions_are_collected_without_leaking_between_resources(): void
    {
        $first = new class extends Resource {};
        $second = new class extends Resource {};
        $this->registry()->registerResource('sample', [new SettingDefinition('enabled', SettingType::Boolean, false)],
            fn (string $resource): array => ['enabled' => $resource === $first::class]);
        $this->assertTrue($this->registry()->runtime('sample', $first::class)['enabled']);
        $this->assertFalse($this->registry()->runtime('sample', $second::class)['enabled']);
        $this->assertSame(['sample'], $this->registry()->modules('resource'));
    }

    public function test_invalid_type_is_rejected_without_coercion(): void
    {
        $this->registry()->registerApplication('sample', [new SettingDefinition('enabled', SettingType::Boolean, false, configKey: 'sample.enabled')]);
        config(['sample.enabled' => 'false']);
        $this->expectException(ValidationException::class);
        $this->registry()->runtime('sample');
    }

    public function test_authorized_patch_is_validated_without_defaults_or_persistence(): void
    {
        $this->registry()->registerApplication('sample', [
            new SettingDefinition('limit', SettingType::Integer, 10, rules: ['min:1', 'max:50'], writeAbility: 'write'),
            new SettingDefinition('enabled', SettingType::Boolean, false),
        ]);
        Gate::define('write', fn ($user): bool => true);
        $this->assertSame(['limit' => 5], $this->registry()->validateChanges('sample', ['limit' => 5], new User));
        $this->assertSame(10, $this->registry()->runtime('sample')['limit']);
        $this->expectException(ValidationException::class);
        $this->registry()->validateChanges('sample', ['limit' => 100], new User);
    }

    public function test_duplicate_modules_fail_instead_of_overriding_permissions(): void
    {
        $this->registry()->registerApplication('sample', []);
        $this->expectException(\LogicException::class);
        $this->registry()->registerApplication('sample', []);
    }

    public function test_unknown_patch_keys_are_denied(): void
    {
        $this->registry()->registerApplication('sample', []);
        $this->expectException(AuthorizationException::class);
        $this->registry()->validateChanges('sample', ['unknown' => 'value'], new User);
    }
}
