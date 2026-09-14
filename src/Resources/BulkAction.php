<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final class BulkAction
{
    private ?string $label = null;

    private ?string $description = null;

    private bool $danger = false;

    private bool $readOnly = false;

    private bool $mutating = true;

    private bool $requiresConfirmation = false;

    private ?string $confirmButton = null;

    private ?string $cancelButton = null;

    private ?string $successMessage = null;

    private ?string $errorMessage = null;

    private ?int $maxRecords = null;

    /**
     * @var callable|null
     */
    private $handler = null;

    /**
     * @var callable|null
     */
    private $authorization = null;

    private function __construct(
        private readonly string $name
    ) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(?string $label = null): self
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        return Str::of($this->name)
            ->replace(['_', '-'], ' ')
            ->lower()
            ->ucfirst()
            ->toString();
    }

    public function description(?string $description = null): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function danger(bool $condition = true): self
    {
        $this->danger = $condition;

        if ($condition) {
            $this->requiresConfirmation = true;
        }

        return $this;
    }

    public function isDanger(): bool
    {
        return $this->danger;
    }

    public function readOnly(bool $condition = true): self
    {
        $this->readOnly = $condition;
        if ($condition) {
            $this->mutating = false;
        }

        return $this;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function mutating(bool $condition = true): self
    {
        $this->mutating = $condition;
        if ($condition) {
            $this->readOnly = false;
        }

        return $this;
    }

    public function isMutating(): bool
    {
        return $this->mutating;
    }

    public function requiresConfirmation(bool $condition = true): self
    {
        $this->requiresConfirmation = $condition;

        return $this;
    }

    public function shouldRequireConfirmation(): bool
    {
        return $this->requiresConfirmation;
    }

    public function confirmButton(?string $label = null): self
    {
        $this->confirmButton = $label;

        return $this;
    }

    public function getConfirmButton(): ?string
    {
        return $this->confirmButton;
    }

    public function cancelButton(?string $label = null): self
    {
        $this->cancelButton = $label;

        return $this;
    }

    public function getCancelButton(): ?string
    {
        return $this->cancelButton;
    }

    public function successMessage(?string $message = null): self
    {
        $this->successMessage = $message;

        return $this;
    }

    public function getSuccessMessage(): ?string
    {
        return $this->successMessage;
    }

    public function errorMessage(?string $message = null): self
    {
        $this->errorMessage = $message;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function maxRecords(?int $maxRecords): self
    {
        if ($maxRecords === null) {
            $this->maxRecords = null;

            return $this;
        }

        $this->maxRecords = max(1, $maxRecords);

        return $this;
    }

    public function getMaxRecords(): ?int
    {
        return $this->maxRecords;
    }

    public function handle(callable $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function authorize(callable $authorization): self
    {
        $this->authorization = $authorization;

        return $this;
    }

    public function canExecute(mixed $user, Collection $records): bool
    {
        if ($this->authorization === null) {
            return true;
        }

        try {
            return (bool) ($this->authorization)($user, $records);
        } catch (Throwable) {
            return false;
        }
    }

    public function execute(Collection $records): mixed
    {
        if ($this->handler === null) {
            throw new LogicException(sprintf(
                'Bulk action [%s] cannot be executed because no handler was configured.',
                $this->name
            ));
        }

        return ($this->handler)($records);
    }
}
