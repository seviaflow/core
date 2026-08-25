<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SeviaFlow\Core\Forms\FieldValidationBridge;
use SeviaFlow\Core\Resources\ImportResource;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEventDispatcher;

final class CsvImportExecutor
{
    public function __construct(
        private readonly FieldValidationBridge $fieldValidationBridge = new FieldValidationBridge,
        private readonly ResourceEventDispatcher $resourceEventDispatcher = new ResourceEventDispatcher
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
        int $maxRows
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
        $casts = $model->getCasts();
        $validationDefinition = $this->rulesFor(
            resourceClass: $resourceClass,
            import: $import,
            requiredFields: $requiredFields,
            allowedFields: $allowedFields,
            casts: $casts
        );
        $rules = $validationDefinition['rules'];
        $attributes = $validationDefinition['attributes'];

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

            $validator = Validator::make($payload, $rules, [], $attributes);
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

        DB::transaction(function () use ($modelClass, $records, $resourceClass): void {
            foreach ($records as $index => $record) {
                /** @var Model $created */
                $created = new $modelClass;
                foreach ($record as $field => $value) {
                    $created->setAttribute($field, $value);
                }

                $resourceSlug = $this->resourceSlug($resourceClass);
                $this->resourceEventDispatcher->dispatch(
                    resourceClass: $resourceClass,
                    event: ResourceEventContext::OPERATION_CREATING,
                    record: $created,
                    context: new ResourceEventContext(
                        user: null,
                        resourceClass: $resourceClass,
                        resourceSlug: $resourceSlug,
                        operation: ResourceEventContext::OPERATION_CREATING,
                        source: ResourceEventContext::SOURCE_IMPORT,
                        request: null,
                        original: [],
                        changes: $record,
                        metadata: ['row' => $index + 1]
                    )
                );

                $created->save();
                $this->resourceEventDispatcher->dispatch(
                    resourceClass: $resourceClass,
                    event: ResourceEventContext::OPERATION_CREATED,
                    record: $created,
                    context: new ResourceEventContext(
                        user: null,
                        resourceClass: $resourceClass,
                        resourceSlug: $resourceSlug,
                        operation: ResourceEventContext::OPERATION_CREATED,
                        source: ResourceEventContext::SOURCE_IMPORT,
                        request: null,
                        original: [],
                        changes: $record,
                        metadata: ['row' => $index + 1]
                    )
                );
                unset($created);
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
        array $casts
    ): array {
        $requiredLookup = array_fill_keys($requiredFields, true);
        $rules = [];
        $attributes = [];
        $bridgedFieldRules = [];

        if ($import->usesFormFieldRules()) {
            $bridged = $this->fieldValidationBridge->build(
                $resourceClass,
                ResourceContext::Create,
                $allowedFields
            );
            $bridgedFieldRules = $bridged['rules'];
            $attributes = $bridged['attributes'];
        }

        foreach ($allowedFields as $field) {
            $baseFieldRules = isset($requiredLookup[$field]) ? ['required'] : ['nullable'];

            $cast = strtolower((string) ($casts[$field] ?? ''));
            if (in_array($cast, ['int', 'integer', 'real', 'float', 'double', 'decimal'], true)) {
                $baseFieldRules[] = 'numeric';
            } elseif (in_array($cast, ['bool', 'boolean'], true)) {
                $baseFieldRules[] = 'boolean';
            } elseif (in_array($cast, ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'], true)) {
                $baseFieldRules[] = 'date';
            } else {
                $baseFieldRules[] = 'string';
            }

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
