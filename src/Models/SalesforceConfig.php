<?php
namespace DreamFactory\Core\Salesforce\Models;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\BaseServiceConfigModel;
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

    protected $fillable = ['service_id', 'dsn', 'options', 'driver_options'];

    protected $casts = ['options' => 'array', 'driver_options' => 'array'];

    public static function validateConfig($config, $create=true)
    {
        if ((null === ArrayUtils::get($config, 'dsn', null, true))) {
            if ((null === ArrayUtils::getDeep($config, 'options', 'db', null, true))) {
                throw new BadRequestException('Database name must be included in the \'dsn\' or as an \'option\' attribute.');
            }
        }

        return true;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'dsn':
                $schema['label'] = 'DSN';
                $schema['description'] =
                    'The Data Source Name, or DSN, contains the information required to connect to the database.';
                break;
            case 'options':
                $schema['description'] = 'An array of options for the connection.';
                break;
            case 'driver_options':
                $schema['description'] = 'An array of options for the driver.';
                break;
        }
    }
}