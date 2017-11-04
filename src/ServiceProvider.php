<?php

namespace DreamFactory\Core\Salesforce;

use DreamFactory\Core\Enums\LicenseLevel;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\OAuth\Models\OAuthConfig;
use DreamFactory\Core\Salesforce\Models\SalesforceConfig;
use DreamFactory\Core\Salesforce\Services\Salesforce;
use DreamFactory\Core\Salesforce\Services\SalesforceOAuth;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType([
                    'name'                  => 'salesforce_db',
                    'label'                 => 'Salesforce',
                    'description'           => 'Database service with SOAP and/or OAuth authentication support for Salesforce connections.',
                    'group'                 => ServiceTypeGroups::DATABASE,
                    'subscription_required' => LicenseLevel::SILVER,
                    'config_handler'        => SalesforceConfig::class,
                    'factory'               => function ($config) {
                        return new Salesforce($config);
                    },
                ])
            );
        });

        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType([
                    'name'                  => 'oauth_salesforce',
                    'label'                 => 'Salesforce OAuth',
                    'description'           => 'OAuth service for supporting Salesforce authentication and API access.',
                    'group'                 => ServiceTypeGroups::OAUTH,
                    'subscription_required' => LicenseLevel::SILVER,
                    'config_handler'        => OAuthConfig::class,
                    'factory'               => function ($config) {
                        return new SalesforceOAuth($config);
                    },
                ])
            );
        });
    }

    public function boot()
    {
        // add migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
