<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Configuration;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use SeviaFlow\Core\Resources\Resource as SeviaResource;

/**
 * Definitions only: no database storage and no HTTP endpoints.
 * runtime() is for trusted server code; use describe(), values() and
 * validateChanges() at user-facing boundaries.
 */
final class ConfigurationRegistry
{
    /** @var array<string, array<string, array<string, SettingDefinition>>> */
    private array $definitions = ['application' => [], 'resource' => []];

    /** @var array<string, Closure(class-string<SeviaResource>):array<string, mixed>> */
    private array $resolvers = [];

    public function __construct(
        private readonly Repository $config,
        private readonly Factory $validator,
        private readonly Gate $gate,
    ) {}

    /** @param array<int, SettingDefinition> $definitions */
    public function registerApplication(string $module, array $definitions): void
    {
        $this->register('application', $module, $definitions);
    }

    /**
     * @param  array<int, SettingDefinition>  $definitions
     * @param  Closure(class-string<SeviaResource>):array<string, mixed>  $resolver
     */
    public function registerResource(string $module, array $definitions, Closure $resolver): void
    {
        $this->register('resource', $module, $definitions);
        $this->resolvers[$module] = $resolver;
    }

    /** @return array<int, string> */
    public function modules(string $scope = 'application'): array
    {
        $this->assertScope($scope);
        $modules = array_keys($this->definitions[$scope]);
        sort($modules);

        return $modules;
    }

    /**
     * Privileged runtime read: validates values and returns secrets unmasked.
     * Never expose this method directly through an API, MCP or UI controller.
     *
     * @param  class-string<SeviaResource>|null  $resource
     * @return array<string, mixed>
     */
    public function runtime(string $module, ?string $resource = null): array
    {
        $definitions = $this->getDefinitions($module, $resource);
        $values = [];
        if ($resource !== null) {
            $values = ($this->resolvers[$module])($resource);
        } else {
            foreach ($definitions as $key => $definition) {
                if ($definition->configKey !== null && $this->config->has($definition->configKey)) {
                    $values[$key] = $this->config->get($definition->configKey);
                }
            }
        }

        return $this->validate($definitions, $values, true);
    }

    /**
     * Safe metadata for form generators. Secrets never include a default.
     *
     * @param  class-string<SeviaResource>|null  $resource
     * @return array<string, array<string, mixed>>
     */
    public function describe(string $module, ?Authenticatable $actor, ?string $resource = null): array
    {
        $result = [];
        foreach ($this->getDefinitions($module, $resource) as $key => $definition) {
            if (! $this->allows($definition->readAbility, $actor, $module, $resource)) {
                continue;
            }
            $result[$key] = [
                'type' => $definition->type->value,
                'nullable' => $definition->nullable,
                'group' => $definition->group,
                'label' => $definition->label ?? $key,
                'secret' => $definition->secret,
                'writable' => $this->allows($definition->writeAbility, $actor, $module, $resource),
            ];
            if (! $definition->secret) {
                $result[$key]['default'] = $definition->default;
            }
        }

        return $result;
    }

    /**
     * @param  class-string<SeviaResource>|null  $resource
     * @return array<string, mixed>
     */
    public function values(string $module, ?Authenticatable $actor, ?string $resource = null): array
    {
        $schema = $this->describe($module, $actor, $resource);
        if ($schema === []) {
            return [];
        }
        $values = $this->runtime($module, $resource);
        $result = [];
        foreach ($schema as $key => $metadata) {
            $result[$key] = $metadata['secret'] ? '[REDACTED]' : $values[$key];
        }

        return $result;
    }

    /**
     * Validate an authorized patch; returns only supplied keys. Does not persist.
     *
     * @param  array<string, mixed>  $changes
     * @param  class-string<SeviaResource>|null  $resource
     * @return array<string, mixed>
     */
    public function validateChanges(string $module, array $changes, ?Authenticatable $actor, ?string $resource = null): array
    {
        $definitions = $this->getDefinitions($module, $resource);
        foreach ($changes as $key => $value) {
            if (! isset($definitions[$key]) || ! $this->allows($definitions[$key]->writeAbility, $actor, $module, $resource)) {
                throw new AuthorizationException('Setting change is not permitted.');
            }
        }

        return $this->validate($definitions, $changes, false);
    }

    /**
     * @param  array<int, SettingDefinition>  $definitions
     */
    private function register(string $scope, string $module, array $definitions): void
    {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/D', $module) || isset($this->definitions[$scope][$module])) {
            throw new LogicException('Invalid or duplicate configuration module.');
        }
        $indexed = [];
        foreach ($definitions as $definition) {
            if (isset($indexed[$definition->key])) {
                throw new LogicException('Duplicate configuration setting.');
            }
            $indexed[$definition->key] = $definition;
        }
        $this->definitions[$scope][$module] = $indexed;
    }

    /**
     * @param  class-string<SeviaResource>|null  $resource
     * @return array<string, SettingDefinition>
     */
    private function getDefinitions(string $module, ?string $resource): array
    {
        if ($resource !== null && ! is_subclass_of($resource, SeviaResource::class)) {
            throw new InvalidArgumentException('Resource configuration requires a SeviaFlow resource class.');
        }
        $scope = $resource === null ? 'application' : 'resource';
        if (! isset($this->definitions[$scope][$module])) {
            throw new LogicException('Configuration module is not registered in this scope.');
        }

        return $this->definitions[$scope][$module];
    }

    /**
     * @param  array<string, SettingDefinition>  $definitions
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function validate(array $definitions, array $values, bool $defaults): array
    {
        if (array_diff_key($values, $definitions) !== []) {
            throw new InvalidArgumentException('Unknown configuration settings.');
        }
        if ($defaults) {
            $values = array_replace(array_map(fn (SettingDefinition $definition): mixed => $definition->default, $definitions), $values);
        }
        foreach ($values as $key => $value) {
            $definition = $definitions[$key];
            if (! $definition->accepts($value)) {
                throw ValidationException::withMessages([$key => 'Invalid setting type.']);
            }
            try {
                $typeRule = match ($definition->type) {
                    SettingType::Boolean => 'boolean',
                    SettingType::Integer => 'integer',
                    SettingType::Number => 'numeric',
                    SettingType::String => 'string',
                    SettingType::List, SettingType::Map => 'array',
                };
                $rules = ['value' => array_merge($definition->nullable ? ['nullable', $typeRule] : [$typeRule], $definition->rules)];
                if ($definition->itemRules !== []) {
                    $rules['value.*'] = $definition->itemRules;
                }
                $this->validator->make(['value' => $value], $rules)->validate();
            } catch (ValidationException) {
                // Never echo submitted values or rules containing sensitive data.
                throw ValidationException::withMessages([$key => 'Invalid setting value.']);
            }
        }

        return $values;
    }

    /** @param class-string<SeviaResource>|null $resource */
    private function allows(?string $ability, ?Authenticatable $actor, string $module, ?string $resource): bool
    {
        return $actor !== null && $ability !== null
            && $this->gate->forUser($actor)->allows($ability, $resource === null ? [$module] : [$resource, $module]);
    }

    private function assertScope(string $scope): void
    {
        if (! in_array($scope, ['application', 'resource'], true)) {
            throw new InvalidArgumentException('Unknown configuration scope.');
        }
    }
}
