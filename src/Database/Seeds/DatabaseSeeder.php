<?php
namespace DreamFactory\Core\Salesforce\Database\Seeds;

use DreamFactory\Core\Database\Seeds\BaseModelSeeder;

class DatabaseSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Core\\Models\\ServiceType';

    protected $records = [
        [
            'name'           => 'salesforce_db',
            'class_name'     => 'DreamFactory\\Core\\Salesforce\\Services\\SalesforceDb',
            'config_handler' => 'DreamFactory\\Core\\Salesforce\\Models\\SalesforceConfig',
            'label'          => 'SalesforceDB',
            'description'    => 'Database service for Salesforce connections.',
            'group'          => 'Databases',
            'singleton'      => false,
        ]
    ];
}
