<?php

/*
 * psr2
 */

namespace Scout\Xunsearch\Engines;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as Engine;
use Scout\Xunsearch\XunsearchClient as Xunsearch;

class XunsearchEngine extends Engine
{
    /**
     * The Xunsearch client.
     *
     * @var Scout\Xunsearch\XunSearchClient
     */
    protected $xunsearch;

    /**
     * Create a new engine instance.
     *
     * @param Xunsearch $xunsearch
     *
     * @return void
     */
    public function __construct(Xunsearch $xunsearch)
    {
        $this->xunsearch = $xunsearch;
    }

    /**
     * Update the given model in the index.
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     *
     * @throws \XSException
     *
     * @return void
     */
    public function update($models)
    {
        $index = $this->xunsearch->initIndex($models->first()->searchableAs());

        $models->map(function ($model) use ($index) {
            $array = $model->toSearchableArray();

            if (empty($array)) {
                return;
            }

            $doc = new \XSDocument();
            $doc->setFields(array_merge([$model->getKeyName() => $model->getKey()], $array));
            $index->update($doc);
        });

        $index->flushIndex();
    }

    /**
     * Remove the given model from the index.
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     *
     * @throws \XSException
     *
     * @return void
     */
    public function delete($models)
    {
        $index = $this->xunsearch->initIndex($models->first()->searchableAs());

        $models->map(function ($model) use ($index) {
            $index->del($model->getKey());
        });

        $index->flushIndex();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param \Laravel\Scout\Builder $builder
     *
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'hitsPerPage'    => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param \Laravel\Scout\Builder $builder
     * @param int                    $perPage
     * @param int                    $page
     *
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'hitsPerPage'    => $perPage,
            'page'           => $page - 1,
        ]);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param \Laravel\Scout\Builder $builder
     * @param array                  $options
     *
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [], $or = false)
    {
        $search = $this->xunsearch->initSearch(
            $builder->index ?: $builder->model->searchableAs()
        );
        
        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $search,
                $builder->query,
                $options
            );
        }
        
        $query = $this->getScws($builder->query, $or);
        
        $search->setQuery($query);
        
        collect($builder->wheres)->map(function ($value, $key) use ($search) {
            if ($value instanceof \Scout\Xunsearch\Operators\RangeOperator) {
                $search->addRange($key, $value->getFrom(), $value->getTo());
            } elseif ($value instanceof \Scout\Xunsearch\Operators\WeightOperator) {
                $search->addWeight($key, $value);
            } elseif ($value instanceof \Scout\Xunsearch\Operators\CollapseOperator) {
                $search->setCollapse($key, (int) sprintf('%s', $value));
            } elseif ($value instanceof \Scout\Xunsearch\Operators\FuzzyOperator) {
                $search->setFuzzy($value);
            } elseif ($value instanceof \Scout\Xunsearch\Operators\FacetsOperator) {
                $search->setFacets($value->getFields(), $value->getExact());
            } else {
                $search->addRange($key, $value, $value);
            }
        });

        /*
        collect($builder->orders)->map(function ($value, $key) use ($search) {
            $search->setSort($key, $value == 'desc');
        });
        */
        if (!empty($builder->orders)) {
            while (list($key, $val) = each($builder->orders)) {
                $search->setSort($val['column'], $val['direction'] == 'desc' ? false : true);
            }
        }

        $offset = 0;
        $perPage = $options['hitsPerPage'];
        if (!empty($options['page'])) {
            $offset = $perPage * $options['page'];
        }
        $hits = $search->setLimit($perPage, $offset)->search();
        
        if(count($hits) < 3 && $or == false){
            $this->performSearch($builder, $options, true);
        }
        
        $facets = collect($builder->wheres)->map(function ($value, $key) use ($search) {
            if ($value instanceof \Scout\Xunsearch\Operators\FacetsOperator) {
                return collect($value->getFields())->mapWithKeys(function ($field) use ($search) {
                    return [$field =>$search->getFacets($field)];
                });
            }
        })->collapse();

        return ['hits'=>$hits, 'nbHits'=>$search->lastCount, 'facets'=>$facets];
    }

    /**
     * Get the filter array for the query.
     *
     * @param \Laravel\Scout\Builder $builder
     *
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            return $key.'='.$value;
        })->values()->all();
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param mixed $results
     *
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits'])->pluck('id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param mixed                               $results
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if (count($results['hits']) === 0) {
            return Collection::make();
        }
        
        $keys = collect($results['hits'])
                        ->pluck($model->getKeyName())->values()->all();

        $models = $model->whereIn(
            $model->getQualifiedKeyName(),
            $keys
        )->get()->keyBy($model->getKeyName());

        return Collection::make($results['hits'])->map(function ($hit) use ($model, $models) {
            $key = $hit[$model->getKeyName()];

            if (isset($models[$key])) {
                return $models[$key];
            }
        })->filter();
        
//         return collect($results['hits'])->map(function ($hit, $key) use ($model) {
//             return $hit->getFields();
//         })->filter();
    }

    private function getScws($srting, $or){
        if(empty($srting)){
            return $srting;
        }
        
        $tokenizer = new \XSTokenizerScws;
        
        $top_words                      = $tokenizer->getTops($srting, 5, 'n,nr,ns,nz,v,vn');
        
        $top_words = array_column($top_words, 'word');
        if(count($top_words) < 2 || $or){
            return implode(' OR ', $top_words);
        }
        
        $keyword_ = $top_words[0] . ' AND (';
        unset($top_words[0]);
        $keyword = implode(' OR ', $top_words);
        $keyword_ .= $keyword . ')';
        
        return $keyword_;
    }
    
    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param mixed $results
     *
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['nbHits'];
    }
    
    public function flush($model){
        $index = $this->xunsearch->initIndex($model->searchableAs());
        $index->clean();
        $index->flushIndex();
    }
}
