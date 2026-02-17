# Laravel Dynamic Query 

A rich set of features designed to make querying a database more dynamic, flexible, and secure.

## Table of Content
- [Features](#sparkles-features)
- [Installation](#-installation)
- [Usage](#technologist-usage)
    - [Add trait to model, use controller (index, show) and test with url](#%EF%B8%8F-supported-data-formats)
    - [Dynamic Select](#dynamic-select)
        - [Dynamic Columns](#dynamic-columns)
        - [Dynamic Relations](#dynamic-relations)
        - [Dynamic Appends](#dynamic-appends)
        - [Dynamic Aggregates](#dynamic-aggregates)
        - [use dynamicSelect + dynamicAppends](#--)
        - [deep relations](#--)
    - [Dynamic Filter](#dynamic-filter)
        - [DynamicFilters](#dynamic-where)
            - [not](#not-usage)
            - [like Left & Right](#like-usage)
        - [operators list](#operators-list)
        - [clauses](#clauses)
        - [logic](#logic)
        - [Scopes](#scopes)
        - [computes](#computes)
    - [Groups](#groups)
    - [Sorts](#sort)
- [:gear: Configuration](#gear-configuration)



## :sparkles: Features

- **Flexibility & Customization**: Dynamically customize queries and select only necessary fields.
- **Modular Use**: Use specific traits to reduce redundant code.
- **Easy Integration**: Integrates smoothly with Laravel’s core features.
- **Data Efficiency**: Fetch only needed data with automatic eager loading.
- **Secure Queries**: Control allowed fields to prevent query manipulation.
- **Advanced Queries**: Simplify complex queries with deep relations and aggregates.
- **Clean Results**: Return optimized and clean API responses.
- **Ease of Use**: Extend functionality easily with query parameters.

## :small_red_triangle_down: Installation:

    composer require yassinedabbous/laravel-dynamic-query

## :technologist: Quick Setup

This packages is a set of 4 traits, you can add one of them or just use the general **HasDynamicQuery** trait:

```PHP
    use HasDynamicFields; // for columns, relations, appends and aggregates
    use HasDynamicFilter; // for filters where, having, joins
    use HasDynamicSorts;  // for sorting results 
    use HasDynamicGroup;  // for grouping results

    # Or just
    use HasDynamicQuery;
```

In your controller, call the needed scopes or just use the **dynamicAPI()** method:

```php
class UserController
{
    public function index()
    {
        $result = User::dynamicSelect()       # = SELECT id, name, age, (...) as posts_count ...
                        ->dynamicFilter()       # = WHERE id=x and name like x% ... 
                        ->dynamicOrderBy()      # = ORDER BY id DESC
                        ->dynamicGroupBy()      # = GROUP BY PRICE
                        ->dynamicPaginate();    # = limit 10 offset 0

        $result->dynamicAppend();               # = setAppends for each model
        return $result;

        # Or just
        return User::dynamicAPI();            # call all dynamic features at once
    }
}
```

For more details on usage, see the sections below.



## :technologist: Usage:


## Select:

Since the API client (FrontEnd developer) doesn't need to know which fields are columns, appends, or model relationships, DynamicFields simplifies the process by automatically handling:
- selecting columns
- appending attributes
- eager loading relationships (with deep fields)
- applying aggregates
All through a single URL parameter: **_fields**.

*Note: to support deep fields, all requested relations should use DynamicFields.*

• API call: 

    GET /users?_fields=id,name,avatar,followers_count,posts:id|title|time_ago

• Resulting Database Queries:

    SELECT `id`, `name`, `avatar`, (SELECT COUNT("id") FROM "followers" where ...) AS `followers_count`  FROM `users`;

    SELECT `id`, `title`, `created_at` FROM `posts` WHERE `users_id` = ?;

• Response: ("time_ago", "followers_count" .. are automatically appended)

    [
        {
            "id": 1,
            "name": "Someone",
            "avatar": "...",
            "followers_count": 1048,
            "posts": [
                {"id":1, "title":"post 1", "time_ago": "2 weeks ago"},
                ...
            ]
        },
        {
            "id": 1,
            ...
        },
        ...
    ]

To utilize the dynamic selection feature, you need to define a list of selectable `columns, relations, aggregates, and appends` within your model.


#### Dynamic Columns:

```php
class User extends Model
{
    use HasDynamicQuery;

    // Define the list of selectable columns.
    // Only the returned columns can be requested via the API.
    public function dynamicColumns(): array
    {
        return ['id', 'name', 'avatar', 'birthday', 'created_at'];
    }
    
    ...
}
```
#### Dynamic Relations:

Model relations can be inferred automatically from methods if they are defined with a typed return:



```diff
-    public function posts()
+    public function posts(): Relation
    {
        $this->hasMany(Post::class);
    }
```

Alternatively, you can manually define the list of allowed relations using the `dynamicRelations()` method:


```php
class Post extends Model
{
    ...
    // Define the list of allowed relations.
    // Only these relations can be requested via the API.
    // Specify each relation along with its dependent columns.
    public function dynamicRelations(): array
    {
          return [
            'user' => 'user_id',                                        // "user" relation depends on 'user_id' column
            'commentable' => ['commentable_type', 'commentable_id'],    // "morphable" relation depends on both 'morphable_type' and 'morphable_id' columns
            'replies' => null,                                          // "replies" relation has no dependencies
          ];
    }
    
    ...
}
```


#### Dynamic Appends:

To function properly, appends must be defined along with their dependent columns or relations:

To work correctly, appends must be defined with their dependent columns or relations:



```php
class User extends Model
{
    ...
    // Define the list of allowed appends.
    // Only these appended fields can be requested via the API.
    // Specify dependencies for each append (columns or relations).
    public function dynamicAppends(): array
    {
        return [
            'status_name'     => 'status',                            // "status_name" depends on 'status' relation
            'full_name'       => ['first_name', 'last_name'],         // "full_name" depends on 'first_name' and 'last_name' columns
            'custom_key',                                             // "custom_key" has no dependencies
        ];
    }
    
    ...
}
```

#### Dynamic Aggregates:

**Aggregates** can be defined as named scopes or closures:


```php
class User extends Model
{
    ...
    // Define the list of allowed aggregates.
    // Only the returned aggregates can be requested via the API.
    // Each aggregate field can be a named scope or a closure.
    public function dynamicAggregates(): array
    {
        return [
            'custom_aggr'                => null,                       // Equivalent to ->customAggr() scope
            'another_custom'        => 'second_named_scope',            // Equivalent to ->secondNamedScope() scope
            'employees_count'       => fn($q) => $q->withCount('employees'),
            'employees_sum_salary'  => fn($q) => $q->withSum('employees', 'salary'),
        ];
    }

    // Named scope
    public function scopeCustomAggr($q){
        return $q->withCount('relation', fn($b) => $b->where('c', 'v'));
    }
    
    ...
}
```















### Filter:
**DynamicFilters** Allows API consumers to filter queries based on URL parameters, it support multiple clause types and logics:

API Example:

    api/endpoint?status_id=20&members_count=3
                &_operators[members_count]=>
                &_clauses[members_count]=having

#### Dynamic Filters:
To implement dynamic filters, you need to define a list of acceptable filters, optionally with their allowed operators:



```PHP
    // List of accepted filters (all operators are applicable).
    public function dynamicFilters(): array {
        return ['name', 'id', 'price'];
    }


    // OR, define specific operators for each filter.
    public function dynamicFilters(): array {
        return [
            'name'    => null,                             // "name" accepts all operators.
            'id'      => '=',                              // "id" accepts only the equality operator "="
            'price'   => ['=', '!=', '<', '<=', '>', '>='],// "price" accepts only 6 comparison operators
        ];
    }
```




#### Operators:

**DynamicFilters** uses the equality `=` operator by default, but this can be changed for each filter using the *`_operators`* parameter.

- Example:

> api/products?price=1000&**_operators[price]=>**

Results in the following SQL query:

    SELECT * FROM products WHERE price > 1000

- Example

> api/products?name=Iphone&**_operators[name]=LIKE%**

Resulted DB query: `SELECT * FROM products WHERE name LIKE "Iphone%"`

##### • Using NOT Logic
To apply the NOT logic, use the exclamation mark (!) before operators:

| Operator | Query |
|--|--|
| != | `SELECT * FROM table WHERE column != value` |
| !LIKE | `SELECT * FROM table WHERE !(column LIKE value)` |
| !NULL | `SELECT * FROM table WHERE column NOT NULL` |
| !IN | `SELECT * FROM table WHERE column NOT IN (value1, value2 ...)` |
| !BETWEEN | `SELECT * FROM table WHERE column NOT BETWEEN value1 AND value2` |
| ... | ... |
| ... | ... |

##### • Left and Right LIKE
Use `%` to specify left or right LIKE conditions:

| Operator | Query |
|--|--|
| LIKE | `SELECT * FROM table WHERE column LIKE "value"` |
| LIKE% | `SELECT * FROM table WHERE column LIKE "value%"` |
| %LIKE | `SELECT * FROM table WHERE column LIKE "%value"` |


Supported operators:

        [
            '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
            '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
            'like', '!like', '%like', 'like%',
            'like binary', 'not like', 'ilike',
            'rlike', 'not rlike', 'regexp', 'not regexp',
        ];

#### Clauses:

**DynamicFilters** uses `WHERE` as the default clause, but you can change it with the *_clause* parameter:

By default, DynamicFilters uses the `WHERE` clause, but this can be changed using the **_clause** parameter:

use WHERE clause for all filters:
>    api/endpoint?**_clause**=where      

use HAVING clause for all filters:
>    api/endpoint?**_clause**=having

To apply a clause to a specific filter, use *`_clauses`*.

Example:

>    api/endpoint?price=1000&reviews_count=3&**_clauses**[reviews_count]=having

Resulting SQL query:

    SELECT * FROM products WHERE price = 1000 HAVING reviews_count = 3

#### Logic operator:

**DynamicFilters** uses `AND` by default, but this can be changed using the _logic parameter:

    api/endpoint?_logic=and      # use AND clause for all filters
    api/endpoint?_logic=or       # use OR clause for all filters

Supported logic clauses are `AND` and `OR`.

#### Scopes:
**DynamicFilters** support named scopes and closures as filter:

```php
{
    ...
    public function dynamicFilters(): array
    {
        return [
            'with_trashed'          => null,                                 // Equivalent to ->withTrashed() scope
            'custom_filter'         => ['=', '!=', '<', '<=', '>', '>='],    // Equivalent to ->customFilter() scope
        ];
    }

    // named scope
    public function scopeCustomFilter($q){
        return $q->whereHas('relation', fn($b) => $b->where('key', 'value'));
    }
    
    // you can also benefits from params provided by this package
    public function scopeCustomFilter($q, $value, $operator, $logic, $not, $clause){
        return $q->whereHas('relation', fn($b) => $b->where('key', $operator, $value));
    }

    ...
}
```

















### Sorting:
The `_sort` query parameter specifies the property by which the results will be ordered. By default, sorting is in ascending order, but it can be reversed by prefixing the property name with a hyphen (`-`).


|  Operator  |                 Query                   |
|------------|-----------------------------------------|
| _sort= `id`  | *SELECT * FROM table ORDER BY id* `ASC` |
| _sort= `-id` | *SELECT * FROM table ORDER BY id* `DESC`|

You can also sort by multiple properties:

>    /endpoint?_sort=**id**,**-price**

or

>    /endpoint?_sort[]=**id**&_sort[]=**-price**


To enable sorting, define a list of allowed sortable fields in your model:


```php
    public function dynamicSorts(): array {
        return ['id', 'price', ];
    }
```

### Groups:
The `_group` query parameter specifies the properties by which the results will be grouped. Grouping is often used with computed values or subqueries (see [Scopes](#scopes) in [DynamicFields](#DynamicFields) section).


You can group by one or more properties:

> /endpoint?_group=**month**

> /endpoint?_group=**month**,**status**

> /endpoint?_group[]=**month**&_group[]=**status**

To enable grouping, define a list of allowed grouping fields in your model:

```php
    public function dynamicGroups(): array {
        return ['status', 'month', ''];
    }
```



### Paginate:

Dynamic pagination works similarly to Laravel's paginator but offers more flexibility.


```php
class UserController
{
    public function index()
    {
        $result = User::dynamicPaginate(maxPerPage: 30, allowGet: true);
    }
}
```

API call example:

> /endpoint?**page**=3&**per_page**=40

To fetch all records at once, use the `_get_all` query parameter, optionally limiting the number of results with `_limit`.

> /endpoint?**_get_all**=true&**_limit**=50




## :gear: Configuration:

You can optionally publish the [config file](src/config.php) with:

    php artisan vendor:publish --tag=dynamic-query-config


These are the contents of the default config file that will be published:

```php
<?php

return [
    /**configuration would be here */
];
```


# Final result

```diff
- api/data?price=1000&title=product&with_items=true&has_reviews=true
+ api/data?price=1000&title=product&reviews=1&sort=-id&per_page=7&_fields=id,name,discount_amount,active_items:id|name

- $query = Model::query();
- if($request->filled('price')){
-     $query->where('price', $query->price);
- }
- if($request->filled('price')){
-     $query->where('price', 'like', "{$query->price}%");
- }
- if($request->has('with_active_items') && $request->with_active_items){
-     $query->with('items', function($q){
-         $q->where('active', 1)->select('id', 'name');
-     });
- }
- if($request->filled('has_reviews')){
-     $query->withCount('reviews', function($q){
-         $q->where('rate', '>=', 1);
-     });
-     $query->having('reviews_count', '>=', 1);
- }
- 
- $query->select('id', 'title', 'price', 'discount');
- 
- $query->orderBy('id', 'desc');
- 
- $results = $query->paginate(7);
- 
- foreach($results as $value){
-     $value->setAppends(['discount_amount']);
-     $value->setVisible(['id', 'name', 'discount_amount']);
- }
- 
- return $results;
+ return Model::dynamicAPI();
```