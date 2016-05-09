<?php
namespace DreamFactory\Core\Salesforce;

use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Salesforce\Models\SalesforceConfig;
use DreamFactory\Core\Salesforce\Services\SalesforceDb;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df){
            $df->addType(
                new ServiceType([
                    'name'           => 'salesforce_db',
                    'label'          => 'SalesforceDB',
                    'description'    => 'Database service for Salesforce connections.',
                    'group'          => ServiceTypeGroups::DATABASE,
                    'config_handler' => SalesforceConfig::class,
                    'factory'        => function ($config){
                        return new SalesforceDb($config);
                    },
                ])
            );
        });
    }
}
