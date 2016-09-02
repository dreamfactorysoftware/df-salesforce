<?php
namespace DreamFactory\Core\Salesforce;

use DreamFactory\Core\Components\ServiceDocBuilder;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\OAuth\Models\OAuthConfig;
use DreamFactory\Core\Salesforce\Models\SalesforceConfig;
use DreamFactory\Core\Salesforce\Services\Salesforce;
use DreamFactory\Core\Salesforce\Services\SalesforceOAuth;
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
                    'description'     => 'Database service with SOAP and/or OAuth authentication support for Salesforce connections.',
                    'group'           => ServiceTypeGroups::DATABASE,
                    'config_handler'  => SalesforceConfig::class,
                    'default_api_doc' => function ($service) {
                        return $this->buildServiceDoc($service->id, Salesforce::getApiDocInfo($service));
                    },
                    'factory'         => function ($config) {
                        return new Salesforce($config);
                    },
                ])
            );
        });

        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType([
                    'name'            => 'oauth_salesforce',
                    'label'           => 'Salesforce OAuth',
                    'description'     => 'OAuth service for supporting Salesforce authentication and API access.',
                    'group'           => ServiceTypeGroups::OAUTH,
                    'config_handler'  => OAuthConfig::class,
                    'default_api_doc' => function ($service) {
                        return $this->buildServiceDoc($service->id, SalesforceOAuth::getApiDocInfo($service));
                    },
                    'factory'         => function ($config) {
                        return new SalesforceOAuth($config);
                    },
                ])
            );
        });
    }
}
