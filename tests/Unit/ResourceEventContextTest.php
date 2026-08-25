<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use SeviaFlow\Core\Resources\ResourceEventContext;

it('stores user in context', function (): void {
    $user = new class extends User {};
    $context = new ResourceEventContext(
        user: $user,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: ResourceEventContext::OPERATION_CREATED,
        source: ResourceEventContext::SOURCE_FORM,
    );

    expect($context->user())->toBe($user);
});

it('stores resource class and slug', function (): void {
    $context = new ResourceEventContext(
        user: null,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: ResourceEventContext::OPERATION_CREATED,
        source: ResourceEventContext::SOURCE_FORM,
    );

    expect($context->resourceClass())->toBe(ResourceEventContextDemoResource::class)
        ->and($context->resourceSlug())->toBe('customers');
});

it('stores operation', function (): void {
    $context = new ResourceEventContext(
        user: null,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: ResourceEventContext::OPERATION_UPDATING,
        source: ResourceEventContext::SOURCE_API,
    );

    expect($context->operation())->toBe('updating');
});

it('stores source', function (): void {
    $context = new ResourceEventContext(
        user: null,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: ResourceEventContext::OPERATION_UPDATED,
        source: ResourceEventContext::SOURCE_WORKFLOW,
    );

    expect($context->source())->toBe('workflow');
});

it('stores nullable request', function (): void {
    $request = Request::create('/admin/resources/customers', 'POST');

    $contextWithRequest = new ResourceEventContext(
        user: null,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: ResourceEventContext::OPERATION_CREATING,
        source: ResourceEventContext::SOURCE_FORM,
        request: $request,
    );

    $contextWithoutRequest = new ResourceEventContext(
        user: null,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: ResourceEventContext::OPERATION_CREATING,
        source: ResourceEventContext::SOURCE_FORM,
    );

    expect($contextWithRequest->request())->toBe($request)
        ->and($contextWithoutRequest->request())->toBeNull();
});

it('stores changes and original attributes', function (): void {
    $context = new ResourceEventContext(
        user: null,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: ResourceEventContext::OPERATION_UPDATED,
        source: ResourceEventContext::SOURCE_API,
        original: ['status' => 'pending', 'email' => 'a@example.com'],
        changes: ['status' => 'active'],
    );

    expect($context->original())->toBe([
        'status' => 'pending',
        'email' => 'a@example.com',
    ])->and($context->changes())->toBe([
        'status' => 'active',
    ]);
});

it('stores metadata', function (): void {
    $context = new ResourceEventContext(
        user: null,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: ResourceEventContext::OPERATION_DELETED,
        source: ResourceEventContext::SOURCE_ACTION,
        metadata: ['trace_id' => 'abc-123', 'batch' => 4],
    );

    expect($context->metadata())->toBe([
        'trace_id' => 'abc-123',
        'batch' => 4,
    ]);
});

it('can be created without user and request', function (): void {
    $context = new ResourceEventContext(
        user: null,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: ResourceEventContext::OPERATION_RESTORED,
        source: ResourceEventContext::SOURCE_SYSTEM,
    );

    expect($context->user())->toBeNull()
        ->and($context->request())->toBeNull();
});

it('fails clearly for unsupported operation', function (): void {
    expect(fn () => new ResourceEventContext(
        user: null,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: 'unknown',
        source: ResourceEventContext::SOURCE_SYSTEM,
    ))->toThrow(InvalidArgumentException::class, 'ResourceEventContext operation [unknown] is not supported');
});

it('fails clearly for unsupported source', function (): void {
    expect(fn () => new ResourceEventContext(
        user: null,
        resourceClass: ResourceEventContextDemoResource::class,
        resourceSlug: 'customers',
        operation: ResourceEventContext::OPERATION_CREATED,
        source: 'unknown',
    ))->toThrow(InvalidArgumentException::class, 'ResourceEventContext source [unknown] is not supported');
});

final class ResourceEventContextDemoResource {}
