<?php
namespace DreamFactory\Core\Salesforce\Models;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\BaseServiceConfigModel;
use DreamFactory\Core\OAuth\Models\OAuthConfig;
use Illuminate\Database\Query\Builder;

/**
 * SalesforceConfig
 *
 * @property integer $service_id
 * @property string  $dsn
 * @property string  $options
 * @property string  $driver_options
 *
 * @method static Builder|SalesforceConfig whereServiceId($value)
 */
class SalesforceConfig extends BaseServiceConfigModel
{
    protected $table = 'salesforce_db_config';

    protected $fillable = ['service_id', 'username', 'password', 'security_token', 'wsdl', 'version'];

    protected $encrypted = ['password', 'security_token'];

    protected static $oauthFields = [
        'default_role',
        'client_id',
        'client_secret',
        'redirect_url',
        'icon_class',
        'custom_provider',
    ];

    public static function validateConfig($config, $create = true)
    {
        // if not using OAuth, need some creds for SOAP Authentication
        if (empty(array_get($config, 'wsdl')) || empty(array_get($config, 'username')) ||
            empty(array_get($config, 'password'))
        ) {
            try {
                OAuthConfig::validateConfig($config, $create);
            } catch (\Exception $ex) {
                throw new BadRequestException('If not using an OAuth service, a Salesforce WSDL file, username, and password are required to access this service.');
            }
        }

        return true;
    }

    public static function getConfig($id)
    {
        $config = parent::getConfig($id);

        $oauthConfig = OAuthConfig::find($id);
        if (!empty($oauthConfig)) {
            $config = array_merge((array)$config, $oauthConfig->toArray());
        }

        return $config;
    }

    public static function setConfig($id, $config)
    {
        $configOAuth = [];
        foreach (static::$oauthFields as $field) {
            if (!empty($temp = array_get($config, $field))) {
                $configOAuth[$field] = $temp;
            }
            unset($config[$field]);
        }

        if (!empty($model = OAuthConfig::find($id))) {
            if (!empty($configOAuth)) {
                $model->update($configOAuth);
            } else {
                $model->delete($id);
            }
        } else {
            //Making sure service_id is the first item in the config.
            //This way service_id will be set first and is available
            //for use right away. This helps setting an auto-generated
            //field that may depend on parent data. See OAuthConfig->setAttribute.
            $configOAuth = array_reverse($configOAuth, true);
            $configOAuth['service_id'] = $id;
            $configOAuth = array_reverse($configOAuth, true);
            OAuthConfig::create($configOAuth);
        }

        parent::setConfig($id, $config);
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $out = parent::getConfigSchema();

        $oauthConfig = new OAuthConfig();
        if (!empty($oauthSchema = $oauthConfig->getConfigSchema())) {
            foreach ($oauthSchema as &$item) {
                // WSDL interface may be provisioned so don't require anything
                $item['required'] = false;
            }
            $out = array_merge($out, $oauthSchema);
        }

        return $out;
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
        }
    }
}