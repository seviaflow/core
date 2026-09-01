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
use SeviaFlow\Core\Analytics\AnalyticsAggregateFilter;
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
                    ->description('Revenue from commercial order-item lines.')
                    ->aliases(['product revenue', 'item sales'])
                    ->sum('line_total')
                    ->currency('USD')
                    ->filters([
                        AnalyticsFilter::in('order_status', ['confirmed', 'completed']),
                    ]),
            ])
            ->dimensions([
                Dimension::make('product')
                    ->label('Product')
                    ->description('Product sold on the order item.')
                    ->aliases(['sku product'])
                    ->relationship('product', 'name'),
                Dimension::make('category')
                    ->label('Category')
                    ->description('Product category reached through the governed product relation.')
                    ->aliases(['product category'])
                    ->relationshipPath(['product', 'category'], 'id', 'name'),
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
                TimeDimension::make('order_ordered_at')
                    ->label('Order Ordered At')
                    ->description('Ordered-at timestamp from the related order.')
                    ->aliases(['order date'])
                    ->relationshipPath(['order'])
                    ->field('ordered_at'),
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

Measures can define presentation metadata, aliases, units, and authorization:

```php
Measure::make('revenue')
    ->label('Revenue')
    ->description('Booked commercial order revenue.')
    ->aliases(['sales', 'turnover'])
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

Use `unit()` when a measure is numeric but not money:

```php
Measure::make('units')
    ->label('Units')
    ->description('Total units sold.')
    ->aliases(['quantity', 'items sold'])
    ->sum('quantity')
    ->unit('integer');
```

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

Use `relationshipPath()` for governed multi-hop `BelongsTo` paths:

```php
Dimension::make('category')
    ->label('Category')
    ->description('Category of the product on the order item.')
    ->aliases(['product category'])
    ->relationshipPath(['product', 'category'], 'id', 'name');
```

Only paths declared in the catalog can be used. Consumers still reference the semantic key, for example `category`; they never send `product.category`, raw column names, or SQL.

## Time Dimensions

A time dimension is a date or datetime field used for time grouping and range filtering.

```php
TimeDimension::make('ordered_at')
    ->label('Ordered At')
    ->description('Date and time when the order was placed.')
    ->aliases(['order date'])
    ->field('ordered_at')
    ->grains(['day', 'week', 'month']);
```

Supported grains are `day`, `week`, `month`, `quarter`, and `year`. If `grains()` is omitted, all grains are allowed.

Use `relationshipPath()` on time dimensions when the timestamp lives on a related model:

```php
TimeDimension::make('order_ordered_at')
    ->label('Order Ordered At')
    ->relationshipPath(['order'])
    ->field('ordered_at');
```

Time ranges are half-open: `from` maps to `>=` and `to` maps to `<`. Use `2026-06-01` as the exclusive end for "through May 2026".

## Execute Queries

Use `AnalyticsQuery` to reference only semantic keys from the registered catalog.

```php
use SeviaFlow\Core\Analytics\AnalyticsExecutor;
use SeviaFlow\Core\Analytics\AnalyticsQuery;

$query = AnalyticsQuery::make(StoreAnalytics::ORDER_ITEMS)
    ->measure('units')
    ->dimensions(['category', 'product'])
    ->filter(AnalyticsFilter::gte('order_ordered_at', '2026-01-01'))
    ->filter(AnalyticsFilter::lt('order_ordered_at', '2026-06-01'))
    ->top('units', 3, ['category'])
    ->orderBy('category')
    ->orderBy('units', 'desc')
    ->limit(200);

$result = app(AnalyticsExecutor::class)->execute($query, auth()->user());
```

The result is normalized for tables, charts, exports, and Copilot presentations:

```php
$result->toArray();
```

## Query Limits

The executor validates every dataset, measure, dimension, time dimension, filter, and order key before compiling the query.

Supported query features:

- A query must select at least one measure.
- A query can select multiple categorical dimensions up to `seviaflow.analytics.max_dimensions`.
- Time grouping can be combined with multiple dimensions.
- Relationship dimensions and related time dimensions support governed `BelongsTo` paths up to `seviaflow.analytics.max_relationship_depth`.
- Filters can reference only registered dimensions or time dimensions.
- Aggregate filters use selected measure keys and compile to HAVING-style filters.
- Ranking supports Top-N and Bottom-N globally or per selected dimension/time partition.
- Ordering can reference only selected measures, selected dimensions, or the selected time dimension.
- Grouped queries are bounded by `seviaflow.analytics.default_limit` and `seviaflow.analytics.max_limit`.

Example HAVING-style aggregate filter:

```php
AnalyticsQuery::make('store.orders')
    ->measure('revenue')
    ->dimension('customer')
    ->aggregateFilter(AnalyticsAggregateFilter::gt('revenue', 5000))
    ->orderBy('revenue', 'desc');
```

Example monthly Top-N per category:

```php
AnalyticsQuery::make(StoreAnalytics::ORDER_ITEMS)
    ->measure('units')
    ->dimensions(['category', 'product'])
    ->time('order_ordered_at', 'month', '2026-01-01', '2026-06-01')
    ->top('units', 3, ['category', 'order_ordered_at']);
```

The centralized complexity budget scores dimensions, related joins, time grouping, aggregate filters, ranking, and large limits. If the score exceeds `seviaflow.analytics.max_complexity`, the executor rejects the query with `AnalyticsQueryTooComplexException`; Copilot receives a `query_too_complex` tool result and must simplify, split, or clarify.

## Copilot Visibility

Copilot can answer only from datasets, measures, dimensions, and time dimensions visible in `AnalyticsRegistry::catalog($user)`. If a business concept is present in Eloquent models or Resources but missing from the analytics catalog, Copilot should say that the current analytics catalog does not support it.
