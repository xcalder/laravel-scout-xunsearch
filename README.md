Lumen XunSearch
==============

Xunsearch Engine for Laravel Scout.

## Installation

You can install the package via composer:

```bash
composer require xcalder/lumen-xunsearch
```

## Configuration 

Publish the config file into your project by edit `config/scout.php` line 62:

```bash
    'xunsearch' => [
        'index'  => env('XUNSEARCH_INDEX_HOST', '127.0.0.1:8383'),
        'search' => env('XUNSEARCH_SEARCH_HOST', '127.0.0.1:8384'),
        'schema' => [
            'article'=>app()->basePath()  .'/'. env('XUNSEARCH_SCHEMA_ARTICLE'),
            'special'=>app()->basePath()  .'/'. env('XUNSEARCH_SCHEMA_SPECIAL'),
            'forum'=>app()->basePath()  .'/'. env('XUNSEARCH_SCHEMA_FORUM')
        ]
    ],
```

Add Xunsearch settings into `.env` file:

```
SCOUT_DRIVER=xunsearch
SCOUT_PREFIX=opx
XUNSEARCH_INDEX_HOST=127.0.0.1:8383
XUNSEARCH_SEARCH_HOST=127.0.0.1:8384
XUNSEARCH_SCHEMA_ARTICLE=config/xunsearch/article.ini
XUNSEARCH_SCHEMA_SPECIAL=config/xunsearch/special.ini
XUNSEARCH_SCHEMA_FORUM=config/xunsearch/forum.ini
```

## Usage

Now you can use Laravel Scout as described in the [official documentation](https://laravel.com/docs/5.3/scout).

### Where Clauses

This enginge allows you to add more advanced "where" clauses.

* addRange 

```
   $users = App\User::search('Star Trek')
            ->where('age', new \Scout\Xunsearch\Operators\RangeOperator(30,50))->get();
```

* setCollapse

```
   $users = App\User::search('Star Trek')
            ->where('city', new \Scout\Xunsearch\Operators\CollapseOperator($num = 10))->get();
```

* setFuzzy

```
   $users = App\Users::search('Star Trek')
           ->where('**', new \Scout\Xunsearch\Operators\FuzzyOperator($fuzzy = false))->get();
```

* setFacets

```
   $users = App\Users::search('Star Trek')
            ->where('***', new \Scout\Xunsearch\Operators\FacetsOperator(array('age','city')))->get();
```

* addWeight

```
   $users = App\User::search('Star Trek')
   ->where('country', new \Scout\Xunsearch\Operators\WeightOperator('US'))->get();
```

### Configuring Searchable Data

By default, the entire toArray form of a given model will be persisted to its search index. If you would like to customize the data that is synchronized to the search index, you may override the  toSearchableArray method on the model:


```
<?php

namespace App;

use Scout\Xunsearch\Searchable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Searchable;

    /**
     * 获取模型的索引名称.
     * 加上前缀，为了多个项目索引共存
     *
     * @return string
     */
    public function searchableAs()
    {
        return config('scout.prefix').'_article';
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $array = $this->toArray();

        // Customize array...

        return $array;
    }
}

```


##Links

- [Xunsearch](http://www.xunsearch.com/)


## Credits

- [Scout](https://github.com/Scout)
- [All Contributors](../../contributors)

## License

The MIT License (MIT).
