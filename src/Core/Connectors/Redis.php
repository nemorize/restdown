<?php

namespace App\Core\Connectors;

use App\Application;
use Predis\Client as RedisClient;

class Redis extends RedisClient
{
    /**
     * Constructor.
     */
    public function __construct ()
    {
        parent::__construct(Application::get('env:REDIS_DSN'));
    }
}