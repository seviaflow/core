<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;

final class FieldValidationBridge
{
    public function __construct(
        private readonly FormValidationRules $formValidationRules = new FormValidationRules
    ) {}

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<int, string>  $allowedFields
     * @return array{rules: array<string, array<int, mixed>>, attributes: array<string, string>}
     */
    public function build(
        string $resourceClass,
        ResourceContext $context,
        array $allowedFields,
        ?Model $record = null
    ): array {
        $allowedLookup = array_fill_keys($allowedFields, true);
        $form = $resourceClass::form(new Form);
        $bridgeRootKey = 'bridgePayload';
        $fields = array_values(array_filter(
            $form->getAllFields(),
            static fn ($field): bool => isset($allowedLookup[$field->name()])
        ));

        if ($fields === []) {
            return [
                'rules' => [],
                'attributes' => [],
            ];
        }

        $prefixedRules = $this->formValidationRules->build(
            $fields,
            $context,
            $resourceClass::model(),
            $bridgeRootKey,
            [],
            $record
        );
        $prefixedAttributes = $this->formValidationRules->attributes($fields, $context, $bridgeRootKey);

        return [
            'rules' => $this->removeRootKey($prefixedRules, $bridgeRootKey),
            'attributes' => $this->removeRootKey($prefixedAttributes, $bridgeRootKey),
        ];
    }

    /**
     * @template TValue
     *
     * @param  array<string, TValue>  $values
     * @return array<string, TValue>
     */
    private function removeRootKey(array $values, string $rootKey): array
    {
        $trimmed = [];
        $prefix = $rootKey.'.';

        foreach ($values as $key => $value) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            $trimmed[substr($key, strlen($prefix))] = $value;
        }

        return $trimmed;
    }
}
