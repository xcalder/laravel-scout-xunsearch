<?php

namespace Scout\Xunsearch\Facades;


use Illuminate\Support\Facades\Facade;

/**
 * 
 * @author xcalder
 *
 */

class Xunsearch extends Facade
{
    /**
     * 
     * @return string
     */
    
    protected static function getFacadeAccessor()
    {
        return static::$app[\Scout\Xunsearch\XunsearchClient::class];
    }
}
