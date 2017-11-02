<?php

namespace DreamFactory\Core\Salesforce\Models;

use DreamFactory\Core\Database\Components\SupportsUpsertAndCache;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\BaseServiceConfigModel;
use DreamFactory\Core\Models\Service;

/**
 * SalesforceConfig
 *
 * @property integer $service_id
 * @property string  $dsn
 * @property string  $options
 * @property string  $driver_options
 *
 */
class SalesforceConfig extends BaseServiceConfigModel
{
    use SupportsUpsertAndCache;

    protected $table = 'salesforce_db_config';

    protected $fillable = [
        'service_id',
        'username',
        'password',
        'security_token',
        'wsdl',
        'version',
        'oauth_service_id'
    ];

    protected $casts = [
        'service_id'       => 'integer',
        'oauth_service_id' => 'integer'
    ];

    protected $encrypted = ['password', 'security_token'];

    protected $protected = ['password', 'security_token'];

    public function validate($data, $throwException = true)
    {
        // if not using OAuth, need some creds for SOAP Authentication
        if (empty(array_get($data, 'wsdl')) || empty(array_get($data, 'username')) ||
            empty(array_get($data, 'password'))
        ) {
            if (empty(array_get($data, 'oauth_service_id'))) {
                throw new BadRequestException('If not using an OAuth service, a Salesforce WSDL file, username, and password are required to access this service.');
            }
        }

        return parent::validate($data, $throwException);
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'username':
                $schema['label'] = 'Username';
                $schema['description'] = 'For non-OAuth authentication, provide a username to a Salesforce account.';
                break;
            case 'password':
                $schema['label'] = 'Password';
                $schema['description'] = 'For non-OAuth authentication, provide the password for the given username.';
                break;
            case 'security_token':
                $schema['label'] = 'Security Token';
                $schema['description'] = 'For non-OAuth authentication, provide a security token for the given username, ' .
                    'may be required in some account setups.';
                break;
            case 'wsdl':
                $schema['label'] = 'Organization WSDL';
                $schema['description'] = 'For non-OAuth authentication, provide an Enterprise WSDL file specifically for your Salesforce organization. ' .
                    'By default, files are looked for in the storage/wsdl directory of your DreamFactory install.';
                break;
            case 'version':
                $schema['label'] = 'Salesforce API Version';
                $schema['description'] = 'Select a specific version of the API to make calls against. ' .
                    'By default, the latest version authenticated against is used.';
                break;
            case 'oauth_service_id':
                $serviceList = [['label' => 'None', 'name' => null]];
                $services = Service::whereType('oauth_salesforce')->get();
                foreach ($services as $service) {
                    $serviceList[] = [
                        'label' => $service->name,
                        'name'  => $service->id
                    ];
                }

                $schema['type'] = 'picklist';
                $schema['values'] = $serviceList;
                $schema['label'] = 'OAuth Service';
                $schema['description'] = 'OAuth service to use for authenticating to your Salesforce organization.';
                break;
        }
    }
}