<?php

namespace App\Core\Connectors;

use App\Application;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;

class DB extends Capsule
{
    /**
     * Constructor.
     */
    public function __construct ()
    {
        $connectionInfo = [
            'driver' => Application::get('env:DB_DRIVER'),
            'host' => Application::get('env:DB_HOST'),
            'port' => Application::get('env:DB_PORT'),
            'username' => Application::get('env:DB_USERNAME'),
            'password' => Application::get('env:DB_PASSWORD'),
            'database' => Application::get('env:DB_DATABASE'),
        ];

        parent::__construct();
        $this->addConnection($connectionInfo);

        $this->setAsGlobal();
        $this->setEventDispatcher(new Dispatcher(new Container()));
        $this->bootEloquent();
    }
}