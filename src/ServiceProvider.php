<?php
namespace DreamFactory\Core\Salesforce;

use DreamFactory\Core\Components\ServiceDocBuilder;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Salesforce\Models\SalesforceConfig;
use DreamFactory\Core\Salesforce\Services\SalesforceDb;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    use ServiceDocBuilder;

    public function register()
    {
        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType([
                    'name'            => 'salesforce_db',
                    'label'           => 'Salesforce',
                    'description'     => 'Database service for Salesforce connections.',
                    'group'           => ServiceTypeGroups::DATABASE,
                    'config_handler'  => SalesforceConfig::class,
                    'default_api_doc' => function ($service) {
                        return $this->buildServiceDoc($service->id, SalesforceDb::getApiDocInfo($service));
                    },
                    'factory'         => function ($config) {
                        return new SalesforceDb($config);
                    },
                ])
            );
        });
    }
}
