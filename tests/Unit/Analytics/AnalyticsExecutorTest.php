<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Analytics\AnalyticsAggregateFilter;
use SeviaFlow\Core\Analytics\AnalyticsDataset;
use SeviaFlow\Core\Analytics\AnalyticsExecutor;
use SeviaFlow\Core\Analytics\AnalyticsExpression;
use SeviaFlow\Core\Analytics\AnalyticsFilter;
use SeviaFlow\Core\Analytics\AnalyticsQuery;
use SeviaFlow\Core\Analytics\AnalyticsRegistry;
use SeviaFlow\Core\Analytics\Dimension;
use SeviaFlow\Core\Analytics\Exceptions\AnalyticsAuthorizationException;
use SeviaFlow\Core\Analytics\Exceptions\AnalyticsQueryTooComplexException;
use SeviaFlow\Core\Analytics\Measure;
use SeviaFlow\Core\Analytics\TimeDimension;
use SeviaFlow\Core\Resources\Resource;

beforeEach(function (): void {
    Schema::create('analytics_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('analytics_customers', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('segment');
        $table->timestamps();
    });

    Schema::create('analytics_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('analytics_products', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('category_id');
        $table->string('name');
        $table->string('sku');
        $table->timestamps();
    });

    Schema::create('analytics_orders', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('owner_id');
        $table->foreignId('customer_id');
        $table->string('status');
        $table->decimal('total', 12, 2);
        $table->decimal('paid_total', 12, 2)->default(0);
        $table->dateTime('ordered_at');
        $table->timestamps();
    });

    Schema::create('analytics_order_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('order_id');
        $table->foreignId('product_id');
        $table->integer('quantity');
        $table->decimal('line_total', 12, 2);
        $table->timestamps();
    });

    config()->set('seviaflow.resources', [
        'analytics-orders' => AnalyticsOrderResource::class,
        'analytics-order-items' => AnalyticsOrderItemResource::class,
    ]);
    config()->set('seviaflow.analytics.default_limit', 2);
    config()->set('seviaflow.analytics.max_limit', 3);
    config()->set('seviaflow.analytics.max_dimensions', 4);
    config()->set('seviaflow.analytics.max_relationship_depth', 3);
    config()->set('seviaflow.analytics.max_ranking_limit', 25);
    config()->set('seviaflow.analytics.max_complexity', 20);
    config()->set('seviaflow.analytics.complexity_limit_threshold', 20);
    config()->set('seviaflow.analytics.limit_complexity_divisor', 100);

    $retail = AnalyticsCustomerRecord::query()->create(['name' => 'Retail Co', 'segment' => 'retail']);
    $enterprise = AnalyticsCustomerRecord::query()->create(['name' => 'Enterprise Co', 'segment' => 'enterprise']);
    $scoped = AnalyticsUser::query()->create(['name' => 'Scoped']);
    $global = AnalyticsUser::query()->create(['name' => 'Global']);
    $hardware = AnalyticsCategoryRecord::query()->create(['name' => 'Hardware']);
    $software = AnalyticsCategoryRecord::query()->create(['name' => 'Software']);
    $laptop = AnalyticsProductRecord::query()->create(['category_id' => $hardware->getKey(), 'name' => 'Laptop', 'sku' => 'LAP-1']);
    $mouse = AnalyticsProductRecord::query()->create(['category_id' => $hardware->getKey(), 'name' => 'Mouse', 'sku' => 'MOU-1']);
    $keyboard = AnalyticsProductRecord::query()->create(['category_id' => $hardware->getKey(), 'name' => 'Keyboard', 'sku' => 'KEY-1']);
    $monitor = AnalyticsProductRecord::query()->create(['category_id' => $hardware->getKey(), 'name' => 'Monitor', 'sku' => 'MON-1']);
    $crm = AnalyticsProductRecord::query()->create(['category_id' => $software->getKey(), 'name' => 'CRM', 'sku' => 'CRM-1']);
    $backup = AnalyticsProductRecord::query()->create(['category_id' => $software->getKey(), 'name' => 'Backup', 'sku' => 'BAK-1']);
    $antivirus = AnalyticsProductRecord::query()->create(['category_id' => $software->getKey(), 'name' => 'Antivirus', 'sku' => 'AV-1']);

    $firstOrder = AnalyticsOrderRecord::query()->create([
        'owner_id' => $scoped->getKey(),
        'customer_id' => $retail->getKey(),
        'status' => 'completed',
        'total' => 100,
        'paid_total' => 40,
        'ordered_at' => '2026-01-10 10:00:00',
    ]);
    AnalyticsOrderRecord::query()->create([
        'owner_id' => $scoped->getKey(),
        'customer_id' => $retail->getKey(),
        'status' => 'draft',
        'total' => 500,
        'paid_total' => 0,
        'ordered_at' => '2026-01-12 10:00:00',
    ]);
    $secondOrder = AnalyticsOrderRecord::query()->create([
        'owner_id' => $scoped->getKey(),
        'customer_id' => $enterprise->getKey(),
        'status' => 'shipped',
        'total' => 50,
        'paid_total' => 75,
        'ordered_at' => '2026-02-02 10:00:00',
    ]);
    $thirdOrder = AnalyticsOrderRecord::query()->create([
        'owner_id' => $global->getKey(),
        'customer_id' => $enterprise->getKey(),
        'status' => 'completed',
        'total' => 300,
        'paid_total' => 0,
        'ordered_at' => '2026-03-05 10:00:00',
    ]);

    foreach ([
        [$firstOrder, $laptop, 5],
        [$firstOrder, $mouse, 10],
        [$firstOrder, $keyboard, 3],
        [$firstOrder, $monitor, 1],
        [$firstOrder, $crm, 7],
        [$secondOrder, $laptop, 2],
        [$secondOrder, $mouse, 6],
        [$secondOrder, $keyboard, 4],
        [$secondOrder, $monitor, 8],
        [$secondOrder, $backup, 9],
        [$secondOrder, $antivirus, 1],
        [$thirdOrder, $laptop, 20],
        [$thirdOrder, $mouse, 2],
        [$thirdOrder, $keyboard, 1],
        [$thirdOrder, $crm, 15],
        [$thirdOrder, $backup, 2],
    ] as [$order, $product, $quantity]) {
        AnalyticsOrderItemRecord::query()->create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => $quantity,
            'line_total' => $quantity * 10,
        ]);
    }
});

it('registers datasets and exposes a user-visible catalog', function (): void {
    $registry = new AnalyticsRegistry([$dataset = analyticsOrdersDataset()]);

    expect($registry->has('orders'))->toBeTrue()
        ->and($registry->resolve('orders'))->toBe($dataset)
        ->and($registry->catalog()['datasets']['orders']['measures'])->toHaveKey('revenue')
        ->and(fn () => $registry->resolve('unknown'))->toThrow(InvalidArgumentException::class, 'not registered');
});

it('serializes and restores sql-free analytics queries', function (): void {
    $query = AnalyticsQuery::make('orders')
        ->measures(['revenue'])
        ->dimension('status')
        ->time('ordered_at', 'month', '2026-01-01', '2026-12-31')
        ->filter(AnalyticsFilter::in('status', ['completed', 'shipped']))
        ->orderBy('revenue', 'desc')
        ->limit(10);

    $payload = $query->toArray();
    $restored = AnalyticsQuery::fromArray($payload);

    expect($payload)->toBe($restored->toArray())
        ->and(json_encode($payload))->not->toContain('select')
        ->and(json_encode($payload))->not->toContain('from analytics_orders');
});

it('preserves explicit money measure metadata when aggregate methods are chained later', function (): void {
    $measure = Measure::make('revenue')
        ->currency('EUR')
        ->sum('total');

    expect($measure->toArray()['type'])->toBe('money')
        ->and($measure->toArray()['format']['currency'])->toBe('EUR');
});

it('exposes semantic metadata aliases and units and rejects duplicate aliases', function (): void {
    $registry = new AnalyticsRegistry([analyticsOrderItemsDataset()]);
    $catalog = $registry->catalog()['datasets']['order-items'];

    expect($catalog['measures']['units']['description'])->toBe('Sold quantity across order lines.')
        ->and($catalog['measures']['units']['aliases'])->toContain('quantity')
        ->and($catalog['measures']['units']['unit'])->toBe('integer')
        ->and($catalog['dimensions']['category']['description'])->toBe('Product category reached through the product relationship.')
        ->and($catalog['dimensions']['category']['aliases'])->toContain('category name')
        ->and($catalog['time_dimensions']['order_ordered_at']['aliases'])->toContain('order date');

    expect(fn () => AnalyticsDataset::make('duplicate-aliases')
        ->resource(AnalyticsOrderResource::class)
        ->measures([
            Measure::make('orders')->alias('sales'),
            Measure::make('revenue')->alias('Sales'),
        ]))
        ->toThrow(InvalidArgumentException::class, 'alias [Sales]');
});

it('rejects unknown measures dimensions filters grains and unsafe order fields', function (): void {
    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrdersDataset()]));

    expect(fn () => $executor->execute(AnalyticsQuery::make('orders')->measure('missing')))
        ->toThrow(InvalidArgumentException::class, 'measure [missing]');

    expect(fn () => $executor->execute(AnalyticsQuery::make('orders')->measure('orders')->dimension('missing')))
        ->toThrow(InvalidArgumentException::class, 'dimension [missing]');

    expect(fn () => $executor->execute(AnalyticsQuery::make('orders')->measure('orders')->time('ordered_at', 'minute')))
        ->toThrow(InvalidArgumentException::class, 'grain [minute]');

    expect(fn () => $executor->execute(AnalyticsQuery::make('orders')->measure('orders')->filter(AnalyticsFilter::equals('total', 100))))
        ->toThrow(InvalidArgumentException::class, 'filter field [total]');

    expect(fn () => AnalyticsQuery::make('orders')->orderBy('total desc; drop table users', 'desc'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => AnalyticsQuery::make('orders')->measure('orders')->limit(0))
        ->toThrow(InvalidArgumentException::class);
});

it('executes scalar aggregation with measure-level semantics', function (): void {
    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrdersDataset()]));

    $result = $executor->execute(AnalyticsQuery::make('orders')->measures(['orders', 'revenue', 'average_order_value']));

    expect($result->firstMeasureValue('orders'))->toBe(4)
        ->and($result->firstMeasureValue('revenue'))->toBe(450.0)
        ->and($result->firstMeasureValue('average_order_value'))->toBe(150.0)
        ->and($result->toArray()['metadata']['grouped'])->toBeFalse();
});

it('executes computed measures safely', function (): void {
    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrdersDataset()]));

    $result = $executor->execute(AnalyticsQuery::make('orders')->measure('outstanding'));

    expect($result->firstMeasureValue('outstanding'))->toBe(860.0);
});

it('groups by dimensions relationship dimensions and time grains with bounded limits', function (): void {
    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrdersDataset()]));

    $byStatus = $executor->execute(
        AnalyticsQuery::make('orders')
            ->measure('orders')
            ->dimension('status')
            ->orderBy('orders', 'desc')
            ->limit(50)
    );

    expect($byStatus->rows())->toHaveCount(3)
        ->and($byStatus->toArray()['metadata']['limit'])->toBe(3);

    $byCustomer = $executor->execute(
        AnalyticsQuery::make('orders')
            ->measure('revenue')
            ->dimension('customer')
            ->orderBy('revenue', 'desc')
            ->limit(2)
    );

    expect($byCustomer->rows()[0]['dimensions']['customer']['label'])->toBe('Enterprise Co')
        ->and($byCustomer->rows()[0]['measures']['revenue'])->toBe(350.0);

    $byMonth = $executor->execute(
        AnalyticsQuery::make('orders')
            ->measure('revenue')
            ->time('ordered_at', 'month', '2026-01-01', '2026-02-28')
            ->orderBy('ordered_at', 'asc')
    );

    expect($byMonth->rows())->toHaveCount(2)
        ->and($byMonth->rows()[0]['time']['value'])->toBe('2026-01-01')
        ->and($byMonth->rows()[0]['measures']['revenue'])->toBe(100.0)
        ->and($byMonth->rows()[1]['time']['value'])->toBe('2026-02-01')
        ->and($byMonth->rows()[1]['measures']['revenue'])->toBe(50.0);
});

it('executes every supported time grain', function (): void {
    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrdersDataset()]));

    foreach (TimeDimension::GRAINS as $grain) {
        $result = $executor->execute(
            AnalyticsQuery::make('orders')
                ->measure('orders')
                ->time('ordered_at', $grain, '2026-01-01', '2026-12-31')
                ->orderBy('ordered_at')
        );

        expect($result->rows())
            ->not->toBeEmpty()
            ->and($result->metadata()['grouped'])->toBeTrue();
    }
});

it('supports safe filters against registered semantic dimensions and time dimensions', function (): void {
    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrdersDataset()]));

    $result = $executor->execute(
        AnalyticsQuery::make('orders')
            ->measure('revenue')
            ->filter(AnalyticsFilter::equals('customer', 1))
            ->filter(AnalyticsFilter::between('ordered_at', '2026-01-01', '2026-01-31'))
    );

    expect($result->firstMeasureValue('revenue'))->toBe(100.0);
});

it('executes multiple dimensions over governed relationship paths and related time dimensions', function (): void {
    config()->set('seviaflow.analytics.max_limit', 50);

    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrderItemsDataset()]));

    $result = $executor->execute(
        AnalyticsQuery::make('order-items')
            ->measure('units')
            ->dimensions(['category', 'product'])
            ->filter(AnalyticsFilter::gte('order_ordered_at', '2026-01-01'))
            ->filter(AnalyticsFilter::lt('order_ordered_at', '2026-03-01'))
            ->orderBy('category')
            ->orderBy('units', 'desc')
            ->limit(20)
    );

    expect($result->rows())->toHaveCount(7)
        ->and($result->rows()[0]['dimensions']['category']['label'])->toBe('Hardware')
        ->and($result->rows()[0]['dimensions']['product']['label'])->toBe('Mouse')
        ->and($result->rows()[0]['measures']['units'])->toBe(16)
        ->and($result->toArray()['metadata']['dataset']['measures']['units']['unit'])->toBe('integer')
        ->and($result->toArray()['metadata']['complexity']['breakdown']['relationship_hops'])->toBe(3);
});

it('ranks top and bottom rows per selected group with stable semantic partitions', function (): void {
    config()->set('seviaflow.analytics.max_limit', 50);

    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrderItemsDataset()]));

    $top = $executor->execute(
        AnalyticsQuery::make('order-items')
            ->measure('units')
            ->dimensions(['category', 'product'])
            ->time('order_ordered_at', 'month', '2026-01-01', '2026-03-01')
            ->top('units', 2, ['category', 'order_ordered_at'])
            ->orderBy('order_ordered_at')
            ->orderBy('category')
            ->orderBy('units', 'desc')
            ->limit(20)
    );

    $counts = [];
    foreach ($top->rows() as $row) {
        $key = $row['time']['value'].'|'.$row['dimensions']['category']['label'];
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    expect($top->rows())->toHaveCount(7)
        ->and(max($counts))->toBeLessThanOrEqual(2)
        ->and($top->rows()[0]['dimensions']['product']['label'])->toBe('Mouse')
        ->and($top->rows()[0]['measures']['units'])->toBe(10);

    $bottom = $executor->execute(
        AnalyticsQuery::make('order-items')
            ->measure('units')
            ->dimensions(['category', 'product'])
            ->bottom('units', 1, ['category'])
            ->orderBy('category')
            ->orderBy('units')
            ->limit(20)
    );

    expect($bottom->rows())->toHaveCount(2)
        ->and($bottom->rows()[0]['dimensions']['category']['label'])->toBe('Hardware')
        ->and($bottom->rows()[0]['dimensions']['product']['label'])->toBe('Keyboard')
        ->and($bottom->rows()[1]['dimensions']['category']['label'])->toBe('Software')
        ->and($bottom->rows()[1]['dimensions']['product']['label'])->toBe('Antivirus');
});

it('applies aggregate filters after grouping and enforces semantic complexity budgets', function (): void {
    config()->set('seviaflow.analytics.max_limit', 50);

    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrdersDataset(), analyticsOrderItemsDataset()]));

    $customers = $executor->execute(
        AnalyticsQuery::make('orders')
            ->measure('revenue')
            ->dimension('customer')
            ->aggregateFilter(AnalyticsAggregateFilter::gt('revenue', 200))
            ->orderBy('revenue', 'desc')
            ->limit(20)
    );

    expect($customers->rows())->toHaveCount(1)
        ->and($customers->rows()[0]['dimensions']['customer']['label'])->toBe('Enterprise Co')
        ->and($customers->rows()[0]['measures']['revenue'])->toBe(350.0);

    config()->set('seviaflow.analytics.max_complexity', 6);

    expect(fn () => $executor->execute(
        AnalyticsQuery::make('order-items')
            ->measure('units')
            ->dimensions(['category', 'product'])
            ->filter(AnalyticsFilter::gte('order_ordered_at', '2026-01-01'))
            ->time('order_ordered_at', 'month', '2026-01-01', '2026-03-01')
            ->top('units', 3, ['category', 'order_ordered_at'])
            ->limit(20)
    ))
        ->toThrow(AnalyticsQueryTooComplexException::class);
});

it('enforces dimension count and relationship depth limits centrally', function (): void {
    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrderItemsDataset()]));

    config()->set('seviaflow.analytics.max_dimensions', 2);
    expect(fn () => $executor->execute(
        AnalyticsQuery::make('order-items')
            ->measure('units')
            ->dimensions(['category', 'product', 'order_status'])
    ))
        ->toThrow(InvalidArgumentException::class, 'at most 2 dimensions');

    config()->set('seviaflow.analytics.max_dimensions', 4);
    config()->set('seviaflow.analytics.max_relationship_depth', 1);
    expect(fn () => $executor->execute(
        AnalyticsQuery::make('order-items')
            ->measure('units')
            ->dimension('category')
    ))
        ->toThrow(InvalidArgumentException::class, 'relationship depth [2]');
});

it('applies resource query scope to explicit executor users', function (): void {
    $executor = new AnalyticsExecutor(new AnalyticsRegistry([analyticsOrdersDataset()]));
    $scopedUser = AnalyticsUser::query()->where('name', 'Scoped')->firstOrFail();

    $scoped = $executor->execute(AnalyticsQuery::make('orders')->measure('revenue'), $scopedUser);
    $global = $executor->execute(AnalyticsQuery::make('orders')->measure('revenue'));

    expect($scoped->firstMeasureValue('revenue'))->toBe(150.0)
        ->and($global->firstMeasureValue('revenue'))->toBe(450.0);
});

it('enforces dataset and measure authorization before execution', function (): void {
    $deniedDataset = analyticsOrdersDataset()
        ->authorize(static fn (?AnalyticsUser $user): bool => $user?->getAttribute('name') === 'Global');
    $measureDenied = AnalyticsDataset::make('restricted-orders')
        ->resource(AnalyticsOrderResource::class)
        ->measures([
            Measure::make('revenue')
                ->sum('total')
                ->authorize(static fn (?AnalyticsUser $user): bool => $user?->getAttribute('name') === 'Global'),
        ]);
    $executor = new AnalyticsExecutor(new AnalyticsRegistry([$deniedDataset, $measureDenied]));
    $scopedUser = AnalyticsUser::query()->where('name', 'Scoped')->firstOrFail();

    expect(fn () => $executor->execute(AnalyticsQuery::make('orders')->measure('revenue'), $scopedUser))
        ->toThrow(AnalyticsAuthorizationException::class, 'dataset [orders]');

    expect(fn () => $executor->execute(AnalyticsQuery::make('restricted-orders')->measure('revenue'), $scopedUser))
        ->toThrow(AnalyticsAuthorizationException::class, 'measure [revenue]');
});

function analyticsOrdersDataset(): AnalyticsDataset
{
    $revenueStatuses = ['completed', 'shipped'];

    return AnalyticsDataset::make('orders')
        ->resource(AnalyticsOrderResource::class)
        ->measures([
            Measure::make('orders')->count(),
            Measure::make('revenue')
                ->sum('total')
                ->currency('EUR')
                ->filter(AnalyticsFilter::in('status', $revenueStatuses)),
            Measure::make('average_order_value')
                ->avg('total')
                ->currency('EUR')
                ->filter(AnalyticsFilter::in('status', $revenueStatuses)),
            Measure::make('outstanding')
                ->sum(AnalyticsExpression::clampMinimum(AnalyticsExpression::subtract('total', 'paid_total'), 0))
                ->currency('EUR'),
        ])
        ->dimensions([
            Dimension::make('status')
                ->field('status')
                ->labels([
                    'completed' => 'Completed',
                    'draft' => 'Draft',
                    'shipped' => 'Shipped',
                ]),
            Dimension::make('customer')->relationship('customer', 'name'),
            Dimension::make('customer_segment')->relationshipField('customer', 'segment'),
        ])
        ->timeDimensions([
            TimeDimension::make('ordered_at')->field('ordered_at'),
        ]);
}

function analyticsOrderItemsDataset(): AnalyticsDataset
{
    return AnalyticsDataset::make('order-items')
        ->label('Order Items')
        ->resource(AnalyticsOrderItemResource::class)
        ->measures([
            Measure::make('units')
                ->label('Units')
                ->description('Sold quantity across order lines.')
                ->aliases(['quantity', 'items sold'])
                ->sum('quantity')
                ->unit('integer'),
            Measure::make('line_revenue')
                ->label('Line revenue')
                ->description('Line revenue across order items.')
                ->aliases(['item revenue'])
                ->sum('line_total')
                ->currency('EUR'),
        ])
        ->dimensions([
            Dimension::make('product')
                ->label('Product')
                ->description('Product sold on the order line.')
                ->aliases(['sku product'])
                ->relationship('product', 'name'),
            Dimension::make('product_sku')
                ->label('Product SKU')
                ->description('Product stock keeping unit.')
                ->aliases(['sku'])
                ->relationshipField('product', 'sku'),
            Dimension::make('category')
                ->label('Category')
                ->description('Product category reached through the product relationship.')
                ->aliases(['category name'])
                ->relationshipPath(['product', 'category'], 'id', 'name'),
            Dimension::make('order_status')
                ->label('Order status')
                ->description('Status from the related order.')
                ->aliases(['status'])
                ->relationshipField('order', 'status'),
            Dimension::make('customer')
                ->label('Customer')
                ->description('Customer reached through the related order.')
                ->aliases(['buyer'])
                ->relationshipPath(['order', 'customer'], 'id', 'name'),
        ])
        ->timeDimensions([
            TimeDimension::make('created_at')
                ->label('Item created at')
                ->field('created_at'),
            TimeDimension::make('order_ordered_at')
                ->label('Order ordered at')
                ->description('Ordered-at timestamp from the related order.')
                ->aliases(['order date'])
                ->relationshipPath(['order'])
                ->field('ordered_at'),
        ]);
}

final class AnalyticsUser extends AuthenticatableUser
{
    protected $table = 'analytics_users';

    protected $guarded = [];
}

final class AnalyticsCustomerRecord extends Model
{
    protected $table = 'analytics_customers';

    protected $guarded = [];
}

final class AnalyticsCategoryRecord extends Model
{
    protected $table = 'analytics_categories';

    protected $guarded = [];
}

final class AnalyticsProductRecord extends Model
{
    protected $table = 'analytics_products';

    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AnalyticsCategoryRecord::class, 'category_id');
    }
}

final class AnalyticsOrderRecord extends Model
{
    protected $table = 'analytics_orders';

    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(AnalyticsCustomerRecord::class, 'customer_id');
    }
}

final class AnalyticsOrderItemRecord extends Model
{
    protected $table = 'analytics_order_items';

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(AnalyticsOrderRecord::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AnalyticsProductRecord::class, 'product_id');
    }
}

final class AnalyticsOrderResource extends Resource
{
    protected static string $model = AnalyticsOrderRecord::class;

    public static function query(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user instanceof AnalyticsUser && $user->getAttribute('name') === 'Scoped') {
            return $query->where('owner_id', $user->getKey());
        }

        return $query;
    }
}

final class AnalyticsOrderItemResource extends Resource
{
    protected static string $model = AnalyticsOrderItemRecord::class;
}
