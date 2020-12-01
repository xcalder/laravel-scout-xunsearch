<?php

namespace Scout\Xunsearch\Facades;


use Illuminate\Support\Facades\Facade;
use Scout\Xunsearch\XunsearchClient;

/**
 * 
 * @author xcalder
 *
 */

class Xunsearch extends Facade
{
    protected static function getFacadeAccessor()
    {
        return XunsearchClient::class;
    }
}
