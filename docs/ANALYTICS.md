# Semantic Analytics

SeviaFlow analytics are defined as a governed semantic catalog. Application code declares datasets, measures, dimensions, time dimensions, and allowed filters. Consumers such as dashboard widgets and Copilot then execute queries by semantic keys only.

The analytics layer does not expose SQL, table names, raw columns, PHP execution, or mutation operations to external consumers. Every query starts from a SeviaFlow `Resource`, so existing resource policies and query scopes remain the authorization boundary.

## Define A Dataset

Create application-level analytics definitions and register them in `AnalyticsRegistry`.

```php
<?php

namespace App\SeviaFlow\Analytics;

use App\SeviaFlow\Resources\OrderItemResource\OrderItemResource;
use SeviaFlow\Core\Analytics\AnalyticsDataset;
use SeviaFlow\Core\Analytics\AnalyticsFilter;
use SeviaFlow\Core\Analytics\Dimension;
use SeviaFlow\Core\Analytics\Measure;
use SeviaFlow\Core\Analytics\TimeDimension;

final class StoreAnalytics
{
    public const string ORDER_ITEMS = 'store.order-items';

    public static function orderItems(): AnalyticsDataset
    {
        return AnalyticsDataset::make(self::ORDER_ITEMS)
            ->label('Order Items')
            ->resource(OrderItemResource::class)
            ->measures([
                Measure::make('line_items')
                    ->label('Line Items')
                    ->count(),
                Measure::make('line_revenue')
                    ->label('Line Revenue')
                    ->sum('line_total')
                    ->currency('USD')
                    ->filters([
                        AnalyticsFilter::in('order_status', ['confirmed', 'completed']),
                    ]),
            ])
            ->dimensions([
                Dimension::make('product')
                    ->label('Product')
                    ->relationship('product', 'name'),
                Dimension::make('product_sku')
                    ->label('SKU')
                    ->relationshipField('product', 'sku'),
                Dimension::make('order_status')
                    ->label('Order Status')
                    ->relationshipField('order', 'status'),
            ])
            ->timeDimensions([
                TimeDimension::make('created_at')
                    ->label('Line Created At')
                    ->field('created_at'),
            ]);
    }

    /**
     * @return array<int, AnalyticsDataset>
     */
    public static function datasets(): array
    {
        return [
            self::orderItems(),
        ];
    }
}
```

Register the datasets during application boot:

```php
use App\SeviaFlow\Analytics\StoreAnalytics;
use SeviaFlow\Core\Analytics\AnalyticsRegistry;

public function boot(): void
{
    $registry = app(AnalyticsRegistry::class);

    foreach (StoreAnalytics::datasets() as $dataset) {
        $registry->register($dataset);
    }
}
```

## Measures

A measure is a numeric value that can be aggregated.

Supported aggregations:

- `count()`
- `sum($columnOrExpression)`
- `avg($columnOrExpression)`
- `min($columnOrExpression)`
- `max($columnOrExpression)`

Measures can define presentation metadata and authorization:

```php
Measure::make('revenue')
    ->label('Revenue')
    ->sum('total')
    ->currency('USD')
    ->decimals(2)
    ->filters([
        AnalyticsFilter::in('status', ['confirmed', 'processing', 'shipped', 'completed']),
    ])
    ->authorize(static fn (mixed $user): bool => $user?->can('viewRevenue') === true);
```

Use `AnalyticsExpression` for simple computed expressions:

```php
use SeviaFlow\Core\Analytics\AnalyticsExpression;

Measure::make('outstanding')
    ->label('Outstanding')
    ->sum(
        AnalyticsExpression::clampMinimum(
            AnalyticsExpression::subtract('total', 'paid_total'),
            0
        )
    )
    ->currency('USD');
```

Measure filters must reference registered dimensions or time dimensions on the same dataset.

## Dimensions

A dimension is a categorical field used for grouping, filtering, and ordering.

Use `field()` for a direct column on the dataset resource model:

```php
Dimension::make('status')
    ->field('status')
    ->labels([
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
    ]);
```

Use `relationship()` for a direct `BelongsTo` relation when the grouped value should be the related model key and the label should come from a related title attribute:

```php
Dimension::make('customer')
    ->label('Customer')
    ->relationship('customer', 'name');
```

Use `relationshipField()` for a direct `BelongsTo` relation when the grouped value and label should be a specific related field:

```php
Dimension::make('product_sku')
    ->label('SKU')
    ->relationshipField('product', 'sku');
```

Current limitation: relationship dimensions support only one direct `BelongsTo` relation from the dataset resource model. Nested relations such as `product.category` are not supported by the compiler. If a query needs product category from `OrderItem`, expose a direct semantic field or relation first, then add a dimension such as `product_category`.

## Time Dimensions

A time dimension is a date or datetime field used for time grouping and range filtering.

```php
TimeDimension::make('ordered_at')
    ->label('Ordered At')
    ->field('ordered_at')
    ->grains(['day', 'week', 'month']);
```

Supported grains are `day`, `week`, `month`, `quarter`, and `year`. If `grains()` is omitted, all grains are allowed.

Time ranges are inclusive: `from` maps to `>=` and `to` maps to `<=`.

## Execute Queries

Use `AnalyticsQuery` to reference only semantic keys from the registered catalog.

```php
use SeviaFlow\Core\Analytics\AnalyticsExecutor;
use SeviaFlow\Core\Analytics\AnalyticsQuery;

$query = AnalyticsQuery::make(StoreAnalytics::ORDER_ITEMS)
    ->measure('line_revenue')
    ->dimension('product')
    ->time('created_at', 'month', '2026-03-01', '2026-05-31')
    ->orderBy('line_revenue', 'desc')
    ->limit(10);

$result = app(AnalyticsExecutor::class)->execute($query, auth()->user());
```

The result is normalized for tables, charts, exports, and Copilot presentations:

```php
$result->toArray();
```

## Query Limits

The executor validates every dataset, measure, dimension, time dimension, filter, and order key before compiling the query.

Current query limitations:

- A query must select at least one measure.
- A query supports at most one regular dimension.
- Time grouping can be combined with a measure and optional regular dimension.
- Filters can reference only registered dimensions or time dimensions.
- Ordering can reference only selected measures, selected dimensions, or the selected time dimension.
- Grouped queries are bounded by `seviaflow.analytics.default_limit` and `seviaflow.analytics.max_limit`.

For use cases such as "top 3 products in each category", the catalog must expose category semantics and the execution layer must support either multiple dimensions, ranking per group, or multiple category-scoped queries.

## Copilot Visibility

Copilot can answer only from datasets, measures, dimensions, and time dimensions visible in `AnalyticsRegistry::catalog($user)`. If a business concept is present in Eloquent models or Resources but missing from the analytics catalog, Copilot should say that the current analytics catalog does not support it.
