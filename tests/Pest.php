<?php

declare(strict_types=1);

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Pest\Support\HigherOrderTapProxy;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

if (! function_exists('pest_test_case')) {
    function pest_test_case(): PestTestCaseProxy
    {
        /** @var HigherOrderTapProxy $testCase */
        $testCase = test();

        return new PestTestCaseProxy($testCase);
    }
}

if (! function_exists('flushArrayCache')) {
    function flushArrayCache(): void
    {
        /** @var Repository $cache */
        $cache = cache()->store('array');
        $cache->flush();
    }
}

/**
 * @mixin TestCase
 */
final class PestTestCaseProxy
{
    public mixed $context = null;

    public mixed $receivedRecord = null;

    public mixed $receivedContext = null;

    public mixed $app = null;

    public bool $commandsRegistered = false;

    /**
     * @var array<int, array{command:string, parameters:array<string, mixed>}>
     */
    public array $calls = [];

    private mixed $originalExceptionHandler = null;

    public function __construct(
        private readonly HigherOrderTapProxy $testCase
    ) {}

    public function withoutExceptionHandling(array $except = []): self
    {
        if ($this->originalExceptionHandler === null) {
            $currentExceptionHandler = app(ExceptionHandler::class);

            $this->originalExceptionHandler = $currentExceptionHandler;
        }

        $exceptionHandler = new class($this->originalExceptionHandler, $except) implements ExceptionHandler
        {
            /**
             * @param  list<class-string<Throwable>>  $except
             */
            public function __construct(
                private readonly ExceptionHandler $originalHandler,
                private readonly array $except = []
            ) {}

            public function report(Throwable $e): void
            {
                //
            }

            public function shouldReport(Throwable $e): bool
            {
                return false;
            }

            public function render($request, Throwable $e): Response
            {
                foreach ($this->except as $class) {
                    if ($e instanceof $class) {
                        return $this->originalHandler->render($request, $e);
                    }
                }

                throw $e;
            }

            public function renderForConsole($output, Throwable $e): void
            {
                (new Application)->renderThrowable($e, $output);
            }
        };

        app()->instance(ExceptionHandler::class, $exceptionHandler);

        return $this;
    }

    public function writeFileSafely(string $path, string $contents, bool $force = false): bool
    {
        if (is_file($path) && ! $force) {
            return false;
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $temporaryPath = $path.'.tmp.'.bin2hex(random_bytes(6));

        file_put_contents($temporaryPath, $contents);
        rename($temporaryPath, $path);

        return true;
    }

    public function registerConsoleCommands(): void
    {
        if (is_object($this->app) && method_exists($this->app, 'runningInConsole') && $this->app->runningInConsole()) {
            $this->commandsRegistered = true;
        }
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->testCase->__call($method, $arguments);
    }

    public function __get(string $property): mixed
    {
        return $this->testCase->__get($property);
    }

    public function __set(string $property, mixed $value): void
    {
        $this->testCase->__set($property, $value);
    }
}
