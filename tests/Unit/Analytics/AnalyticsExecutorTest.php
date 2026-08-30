<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Analytics\AnalyticsDataset;
use SeviaFlow\Core\Analytics\AnalyticsExecutor;
use SeviaFlow\Core\Analytics\AnalyticsExpression;
use SeviaFlow\Core\Analytics\AnalyticsFilter;
use SeviaFlow\Core\Analytics\AnalyticsQuery;
use SeviaFlow\Core\Analytics\AnalyticsRegistry;
use SeviaFlow\Core\Analytics\Dimension;
use SeviaFlow\Core\Analytics\Exceptions\AnalyticsAuthorizationException;
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

    config()->set('seviaflow.resources', [
        'analytics-orders' => AnalyticsOrderResource::class,
    ]);
    config()->set('seviaflow.analytics.default_limit', 2);
    config()->set('seviaflow.analytics.max_limit', 3);

    $retail = AnalyticsCustomerRecord::query()->create(['name' => 'Retail Co', 'segment' => 'retail']);
    $enterprise = AnalyticsCustomerRecord::query()->create(['name' => 'Enterprise Co', 'segment' => 'enterprise']);
    $scoped = AnalyticsUser::query()->create(['name' => 'Scoped']);
    $global = AnalyticsUser::query()->create(['name' => 'Global']);

    AnalyticsOrderRecord::query()->create([
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
    AnalyticsOrderRecord::query()->create([
        'owner_id' => $scoped->getKey(),
        'customer_id' => $enterprise->getKey(),
        'status' => 'shipped',
        'total' => 50,
        'paid_total' => 75,
        'ordered_at' => '2026-02-02 10:00:00',
    ]);
    AnalyticsOrderRecord::query()->create([
        'owner_id' => $global->getKey(),
        'customer_id' => $enterprise->getKey(),
        'status' => 'completed',
        'total' => 300,
        'paid_total' => 0,
        'ordered_at' => '2026-03-05 10:00:00',
    ]);
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

final class AnalyticsOrderRecord extends Model
{
    protected $table = 'analytics_orders';

    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(AnalyticsCustomerRecord::class, 'customer_id');
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
