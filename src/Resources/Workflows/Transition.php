<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Workflows;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use Throwable;

final class Transition
{
    private ?string $label = null;

    /**
     * @var array<int, string>
     */
    private array $fromStates = [];

    private ?string $toState = null;

    private bool $danger = false;

    private bool $requiresConfirmation = false;

    private bool $requiresComment = false;

    private ?string $successMessage = null;

    private ?string $errorMessage = null;

    /**
     * @var callable|class-string|null
     */
    private $handler = null;

    private ?Closure $visibleIf = null;

    private ?Closure $canRunIf = null;

    private ?Closure $authorize = null;

    private function __construct(
        private readonly string $name
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Workflow transition name cannot be empty.');
        }
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(?string $label = null): static
    {
        if (func_num_args() === 0) {
            return $this;
        }

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

    /**
     * @param  string|array<int, string>  $states
     */
    public function from(string|array $states): static
    {
        $values = is_array($states) ? $states : [$states];
        $normalized = [];

        foreach ($values as $state) {
            $name = trim($state);

            if ($name === '') {
                throw new InvalidArgumentException('Workflow transition from-state cannot be empty.');
            }

            $normalized[] = $name;
        }

        $this->fromStates = array_values(array_unique($normalized));

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getFromStates(): array
    {
        return $this->fromStates;
    }

    public function to(string $state): static
    {
        $state = trim($state);

        if ($state === '') {
            throw new InvalidArgumentException('Workflow transition target state cannot be empty.');
        }

        $this->toState = $state;

        return $this;
    }

    public function getToState(): string
    {
        if ($this->toState === null) {
            throw new LogicException('Workflow transition target state is required. Call to() first.');
        }

        return $this->toState;
    }

    public function danger(bool $condition = true): static
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

    public function requiresConfirmation(bool ...$condition): static
    {
        if ($condition === []) {
            $this->requiresConfirmation = true;

            return $this;
        }

        $this->requiresConfirmation = $condition[0];

        return $this;
    }

    public function shouldRequireConfirmation(): bool
    {
        return $this->requiresConfirmation;
    }

    public function requiresComment(bool ...$condition): static
    {
        if ($condition === []) {
            $this->requiresComment = true;

            return $this;
        }

        $this->requiresComment = $condition[0];

        return $this;
    }

    public function shouldRequireComment(): bool
    {
        return $this->requiresComment;
    }

    public function successMessage(?string $message = null): static
    {
        $this->successMessage = $message;

        return $this;
    }

    public function getSuccessMessage(): ?string
    {
        return $this->successMessage;
    }

    public function errorMessage(?string $message = null): static
    {
        $this->errorMessage = $message;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function visibleIf(callable $callback): static
    {
        $this->visibleIf = Closure::fromCallable($callback);

        return $this;
    }

    public function canRunIf(callable $callback): static
    {
        $this->canRunIf = Closure::fromCallable($callback);

        return $this;
    }

    public function authorize(callable $callback): static
    {
        $this->authorize = Closure::fromCallable($callback);

        return $this;
    }

    public function isVisibleFor(mixed $record, mixed $user = null, ?TransitionContext $context = null): bool
    {
        if ($this->visibleIf === null) {
            return true;
        }

        return $this->evaluateDecision($this->visibleIf, $record, $user, $context);
    }

    public function canRunFor(mixed $record, mixed $user = null, ?TransitionContext $context = null): bool
    {
        if ($this->canRunIf === null) {
            return true;
        }

        return $this->evaluateDecision($this->canRunIf, $record, $user, $context);
    }

    public function isAuthorizedFor(mixed $user, mixed $record, ?TransitionContext $context = null): bool
    {
        if ($this->authorize === null) {
            return true;
        }

        return $this->evaluateDecision($this->authorize, $record, $user, $context);
    }

    public function handle(callable|string $handler): static
    {
        if (is_string($handler)) {
            $handler = trim($handler);

            if ($handler === '') {
                throw new InvalidArgumentException('Workflow transition handler class cannot be empty.');
            }

            $this->handler = $handler;

            return $this;
        }

        $this->handler = $handler;

        return $this;
    }

    /**
     * @return callable|class-string|null
     */
    public function getHandler(): callable|string|null
    {
        return $this->handler;
    }

    public function execute(mixed $record, TransitionContext $context): mixed
    {
        if ($this->handler === null) {
            return null;
        }

        if (is_string($this->handler)) {
            $resolved = app($this->handler);

            if (! is_callable($resolved)) {
                throw new LogicException(sprintf(
                    'Workflow transition handler class [%s] must be invokable.',
                    $this->handler
                ));
            }

            return $resolved($record, $context);
        }

        return ($this->handler)($record, $context);
    }

    private function evaluateDecision(Closure $callback, mixed $record, mixed $user, ?TransitionContext $context): bool
    {
        try {
            $reflection = new ReflectionFunction($callback);
            $parameterCount = $reflection->getNumberOfParameters();

            return match ($parameterCount) {
                0 => (bool) $callback(),
                1 => (bool) $callback($record),
                2 => (bool) $callback($record, $user),
                default => (bool) $callback($record, $user, $context),
            };
        } catch (Throwable) {
            return false;
        }
    }
}
