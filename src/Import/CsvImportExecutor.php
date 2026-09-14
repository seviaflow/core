<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use SeviaFlow\Core\Forms\FieldValidationBridge;
use SeviaFlow\Core\Operations\ResourceOperationContext;
use SeviaFlow\Core\Operations\ResourceOperationService;
use SeviaFlow\Core\Resources\Fields\DateField;
use SeviaFlow\Core\Resources\Fields\DateTimeField;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\TimeField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\ImportResource;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;

final class CsvImportExecutor
{
    public function __construct(
        private readonly FieldValidationBridge $fieldValidationBridge = new FieldValidationBridge,
        private readonly ResourceOperationService $operationService = new ResourceOperationService,
    ) {}

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array{
     *   imported_count:int,
     *   failed_count:int,
     *   row_errors:array<int, string>,
     *   global_errors:array<int, string>
     * }
     */
    public function execute(
        string $resourceClass,
        ImportResource $import,
        string $path,
        int $maxRows,
        mixed $user = null,
        ?Request $request = null,
    ): array {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            return $this->failure([], ['Could not read CSV file.']);
        }

        $headers = fgetcsv($stream);
        if (! is_array($headers) || $headers === []) {
            fclose($stream);

            return $this->failure([], ['CSV headers are required.']);
        }

        $normalizedHeaders = array_values(array_map(
            static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
            $headers
        ));

        $allowedFields = $import->getAllowedFields();
        $requiredFields = $import->getRequiredFields();

        $globalErrors = [];
        $allowedLookup = array_fill_keys($allowedFields, true);

        $unknownHeaders = array_values(array_filter(
            $normalizedHeaders,
            static fn (string $header): bool => $header !== '' && ! isset($allowedLookup[$header])
        ));
        if ($unknownHeaders !== []) {
            $globalErrors[] = sprintf('Unknown fields are not allowed: %s.', implode(', ', $unknownHeaders));
        }

        $missingRequired = [];
        foreach ($requiredFields as $requiredField) {
            if (! in_array($requiredField, $normalizedHeaders, true)) {
                $missingRequired[] = $requiredField;
            }
        }
        if ($missingRequired !== []) {
            $globalErrors[] = sprintf('Missing required fields: %s.', implode(', ', $missingRequired));
        }

        if ($globalErrors !== []) {
            fclose($stream);

            return $this->failure([], $globalErrors);
        }

        $rows = [];
        while (($line = fgetcsv($stream)) !== false) {
            if (! is_array($line)) {
                continue;
            }

            $rows[] = $line;
            if (count($rows) > $maxRows) {
                fclose($stream);

                return $this->failure([], [
                    sprintf('Import exceeds the maximum allowed rows (%d).', $maxRows),
                ]);
            }
        }
        fclose($stream);

        $modelClass = $resourceClass::model();
        /** @var Model $model */
        $model = new $modelClass;
        $fields = $this->operationService->fieldsForResource($resourceClass, $allowedFields);
        $fileFields = array_values(array_filter(
            $fields,
            static fn (Field $field): bool => $field instanceof FileField
                && in_array($field->name(), $normalizedHeaders, true)
        ));
        if ($fileFields !== []) {
            return $this->failure([], [
                sprintf(
                    'File fields cannot be imported from CSV: %s.',
                    implode(', ', array_map(static fn (Field $field): string => $field->name(), $fileFields))
                ),
            ]);
        }

        $casts = $model->getCasts();
        $rowErrors = [];
        $records = [];

        foreach ($rows as $index => $line) {
            $payload = [];
            foreach ($normalizedHeaders as $columnIndex => $header) {
                if ($header === '' || ! isset($allowedLookup[$header])) {
                    continue;
                }

                $value = $line[$columnIndex] ?? '';
                $payload[$header] = is_scalar($value) ? trim((string) $value) : '';
            }

            $payload = $this->operationService->prepareForImport($fields, $payload);
            $validationDefinition = $this->rulesFor(
                resourceClass: $resourceClass,
                import: $import,
                requiredFields: $requiredFields,
                allowedFields: $allowedFields,
                casts: $casts,
                fields: $fields,
                fieldValues: $payload,
            );

            $validator = Validator::make(
                $payload,
                $validationDefinition['rules'],
                [],
                $validationDefinition['attributes']
            );
            if ($validator->fails()) {
                $rowErrors[] = sprintf(
                    'Row %d: %s',
                    $index + 1,
                    implode('; ', $validator->errors()->all())
                );

                continue;
            }

            /** @var array<string, mixed> $validated */
            $validated = $validator->validated();
            $records[] = $validated;
        }

        if ($rowErrors !== []) {
            return $this->failure($rowErrors, []);
        }

        $model->getConnection()->transaction(function () use ($modelClass, $records, $resourceClass, $allowedFields, $user, $request): void {
            $resourceSlug = $this->resourceSlug($resourceClass);
            $fields = $this->operationService->fieldsForResource($resourceClass, $allowedFields);

            foreach ($records as $index => $record) {
                /** @var Model $created */
                $created = new $modelClass;
                $this->operationService->execute(
                    resourceClass: $resourceClass,
                    resourceSlug: $resourceSlug,
                    model: $created,
                    fields: $fields,
                    values: $record,
                    context: ResourceContext::Create,
                    operationContext: new ResourceOperationContext(
                        actor: $user,
                        source: ResourceEventContext::SOURCE_IMPORT,
                        request: $request,
                        metadata: ['row' => $index + 1],
                    ),
                );
            }
        });

        return [
            'imported_count' => count($records),
            'failed_count' => 0,
            'row_errors' => [],
            'global_errors' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $casts
     * @return array{
     *   rules: array<string, array<int, mixed>>,
     *   attributes: array<string, string>
     * }
     */
    private function rulesFor(
        string $resourceClass,
        ImportResource $import,
        array $requiredFields,
        array $allowedFields,
        array $casts,
        array $fields,
        array $fieldValues = [],
    ): array {
        $requiredLookup = array_fill_keys($requiredFields, true);
        $rules = [];
        $attributes = [];
        $bridgedFieldRules = [];
        $fieldMap = [];

        foreach ($fields as $field) {
            $fieldMap[$field->name()] = $field;
        }

        if ($import->usesFormFieldRules()) {
            $bridged = $this->fieldValidationBridge->build(
                $resourceClass,
                ResourceContext::Create,
                $allowedFields,
                null,
                $fieldValues,
            );
            $bridgedFieldRules = $bridged['rules'];
            $attributes = $bridged['attributes'];
        }

        foreach ($allowedFields as $field) {
            $baseFieldRules = isset($requiredLookup[$field]) ? ['required'] : ['nullable'];

            $baseFieldRules = array_merge($baseFieldRules, $this->typeRules(
                $fieldMap[$field] ?? null,
                strtolower((string) ($casts[$field] ?? '')),
            ));

            $rules[$field] = $this->deduplicateRules(array_values(array_merge(
                $baseFieldRules,
                $bridgedFieldRules[$field] ?? []
            )));
        }

        return [
            'rules' => $rules,
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function typeRules(?Field $field, string $cast): array
    {
        if ($field instanceof MultiSelectField) {
            return ['array'];
        }

        if ($field instanceof ToggleField) {
            return ['boolean'];
        }

        if ($field instanceof NumberField) {
            return ['numeric'];
        }

        if ($field instanceof FileField) {
            return ['file'];
        }

        if ($field instanceof DateField || $field instanceof DateTimeField || $field instanceof TimeField) {
            return ['date'];
        }

        return match (true) {
            in_array($cast, ['int', 'integer', 'real', 'float', 'double', 'decimal'], true) => ['numeric'],
            in_array($cast, ['bool', 'boolean'], true) => ['boolean'],
            in_array($cast, ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'], true) => ['date'],
            default => ['string'],
        };
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<int, mixed>
     */
    private function deduplicateRules(array $rules): array
    {
        $seen = [];
        $deduplicated = [];

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                $deduplicated[] = $rule;

                continue;
            }

            $key = strtolower(trim($rule));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduplicated[] = $rule;
        }

        return $deduplicated;
    }

    /**
     * @param  array<int, string>  $rowErrors
     * @param  array<int, string>  $globalErrors
     * @return array{
     *   imported_count:int,
     *   failed_count:int,
     *   row_errors:array<int, string>,
     *   global_errors:array<int, string>
     * }
     */
    private function failure(array $rowErrors, array $globalErrors): array
    {
        return [
            'imported_count' => 0,
            'failed_count' => count($rowErrors),
            'row_errors' => $rowErrors,
            'global_errors' => $globalErrors,
        ];
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function resourceSlug(string $resourceClass): string
    {
        /** @var array<string,mixed> $resources */
        $resources = (array) config('seviaflow.resources', []);

        foreach ($resources as $slug => $class) {
            if (is_string($slug) && is_string($class) && $class === $resourceClass) {
                return $slug;
            }
        }

        return $resourceClass;
    }
}
