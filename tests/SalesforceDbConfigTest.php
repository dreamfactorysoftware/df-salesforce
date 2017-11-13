<?php

class SalesforceDbConfigTest extends \DreamFactory\Core\Database\Testing\DbServiceConfigTestCase
{
    protected $types = ['salesforce_db'];

    public function getDbServiceConfig($name, $type, $maxRecords = null)
    {
        $config = [
            'name'      => $name,
            'label'     => 'test db service',
            'type'      => $type,
            'is_active' => true,
            'config'    => [
                'wsdl'     => 'wsdl',
                'username' => 'user',
                'password' => 'secret'
            ]
        ];

        if (!empty($maxRecords)) {
            $config['config']['max_records'] = $maxRecords;
        }

        return $config;
    }
}