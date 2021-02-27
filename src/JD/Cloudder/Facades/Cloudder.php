<?php 

namespace JD\Cloudder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Cloudder
 *
 * @package JD\Cloudder\Facades
 */
class Cloudder extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cloudder';
    }
}
