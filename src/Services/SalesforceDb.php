<?php
namespace DreamFactory\Core\Salesforce\Services;

use DreamFactory\Core\Components\DbSchemaExtras;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Services\BaseNoSqlDbService;
use DreamFactory\Core\Salesforce\Resources\Schema;
use DreamFactory\Core\Salesforce\Resources\Table;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use Phpforce\SoapClient as SoapClient;

/**
 * SalesforceDb
 *
 * A service to handle SalesforceDB NoSQL (schema-less) database
 * services accessed through the REST API.
 */
class SalesforceDb extends BaseNoSqlDbService
{
    use DbSchemaExtras;

    //*************************************************************************
    //	Constants
    //*************************************************************************

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var SalesforceDB
     */
    protected $dbConn = null;
    /**
     * @var string
     */
    protected $username;
    /**
     * @var array
     */
    protected $password;
    /**
     * @var array
     */
    protected $securityToken;
    /**
     * @var array
     */
    protected $version = 'v28.0';
    /**
     * @var array
     */
    protected $sessionCache;
    /**
     * @var array
     */
    protected $fieldCache;
    /**
     * @var array
     */
    protected $tableNames = [];
    /**
     * @var array
     */
    protected $tables = [];

    /**
     * @var array
     */
    protected static $resources = [
        Schema::RESOURCE_NAME => [
            'name'       => Schema::RESOURCE_NAME,
            'class_name' => Schema::class,
            'label'      => 'Schema',
        ],
        Table::RESOURCE_NAME  => [
            'name'       => Table::RESOURCE_NAME,
            'class_name' => Table::class,
            'label'      => 'Table',
        ],
    ];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new SalesforceDbSvc
     *
     * @param array $settings
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct($settings = array())
    {
        parent::__construct($settings);

        $config = (array)array_get($settings, 'config');
        Session::replaceLookups($config, true);

        $this->username = array_get($config, 'username');
        $this->password = array_get($config, 'password');
        $this->securityToken = array_get($config, 'security_token');
        if (empty($this->securityToken)) {
            $this->securityToken = ''; // gets appended to password
        }

        if (empty($this->username) || empty($this->password)) {
            throw new \InvalidArgumentException('A Salesforce username and password are required for this service.');
        }

        $version = array_get($config, 'version');
        if (!empty($version)) {
            $this->version = $version;
        }

        $this->sessionCache = [];
//
//        $this->fieldCache = array();
    }

    /**
     * Object destructor
     */
    public function __destruct()
    {
    }

    /**
     * @param bool $list_only
     *
     * @return array
     */
    public function getSObjects($list_only = false)
    {
        $result = $this->callGuzzle('GET', 'sobjects/');

        $tables = (array)array_get($result, 'sobjects');
        if ($list_only) {
            $out = array();
            foreach ($tables as $table) {
                $out[] = array_get($table, 'name');
            }

            return $out;
        }

        return $tables;
    }

    public function getTableNames($schema = null, $refresh = false, $use_alias = false)
    {
        if ($refresh ||
            (empty($this->tableNames) &&
                (null === $this->tableNames = $this->getFromCache('table_names')))
        ) {
            /** @type TableSchema[] $names */
            $names = [];
            $tables = $this->getSObjects(true);
            foreach ($tables as $table) {
                $names[strtolower($table)] = new TableSchema(['name' => $table]);
            }
            // merge db extras
            if (!empty($extrasEntries = $this->getSchemaExtrasForTables($tables, false))) {
                foreach ($extrasEntries as $extras) {
                    if (!empty($extraName = strtolower(strval($extras['table'])))) {
                        if (array_key_exists($extraName, $tables)) {
                            $names[$extraName]->fill($extras);
                        }
                    }
                }
            }
            $this->tableNames = $names;
            $this->addToCache('table_names', $this->tableNames, true);
        }

        return $this->tableNames;
    }

    public function refreshTableCache()
    {
        $this->removeFromCache('table_names');
        $this->tableNames = [];
        $this->tables = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessList()
    {
        $resources = [];

//        $refresh = $this->request->queryBool( 'refresh' );

        $name = Schema::RESOURCE_NAME . '/';
        $access = $this->getPermissions($name);
        if (!empty($access)) {
            $resources[] = $name;
            $resources[] = $name . '*';
        }

        $result = $this->getSObjects(true);
        foreach ($result as $name) {
            $name = Schema::RESOURCE_NAME . '/' . $name;
            $access = $this->getPermissions($name);
            if (!empty($access)) {
                $resources[] = $name;
            }
        }

        $name = Table::RESOURCE_NAME . '/';
        $access = $this->getPermissions($name);
        if (!empty($access)) {
            $resources[] = $name;
            $resources[] = $name . '*';
        }

        foreach ($result as $name) {
            $name = Table::RESOURCE_NAME . '/' . $name;
            $access = $this->getPermissions($name);
            if (!empty($access)) {
                $resources[] = $name;
            }
        }

        return $resources;
    }

    protected function getSoapLoginResult()
    {
        //@todo use client provided Salesforce wsdl for the different versions
        $wsdl = __DIR__ . '/../../config/enterprise.wsdl.xml';

        $builder = new SoapClient\ClientBuilder($wsdl, $this->username, $this->password, $this->securityToken);
        $soapClient = $builder->build();
        if (!isset($soapClient)) {
            throw new InternalServerErrorException('Failed to build session with Salesforce.');
        }

        $result = $soapClient->getLoginResult();
        $this->sessionCache['server_instance'] = $result->getServerInstance();
        $this->sessionCache['session_id'] = $result->getSessionId();
//        Pii::setState( 'service.' . $this->getApiName() . '.cache', $this->sessionCache );
    }

    protected function getSessionId()
    {
        $id = array_get($this->sessionCache, 'session_id');
        if (empty($id)) {
            $this->getSoapLoginResult();

            $id = array_get($this->sessionCache, 'session_id');
            if (empty($id)) {
                throw new InternalServerErrorException('Failed to get session id from Salesforce.');
            }
        }

        return $id;
    }

    protected function getServerInstance()
    {
        $instance = array_get($this->sessionCache, 'server_instance');
        if (empty($instance)) {
            $this->getSoapLoginResult();

            $instance = array_get($this->sessionCache, 'server_instance');
            if (empty($instance)) {
                throw new InternalServerErrorException('Failed to get server instance from Salesforce.');
            }
        }

        return $instance;
    }

    /**
     * Perform call to Salesforce REST API
     *
     * @param string       $method
     * @param string       $uri
     * @param array        $parameters
     * @param mixed        $body
     * @param GuzzleClient $client
     *
     * @throws InternalServerErrorException
     * @throws RestException
     * @return array The JSON response as an array
     */
    public function callGuzzle(
        $method = 'GET',
        $uri = null,
        $parameters = array(),
        $body = null,
        $client = null
    ){
        try {
            if (!isset($client)) {
                $client = $this->getGuzzleClient();
            }
            $options = ['query' => $parameters, 'headers' => ['Authorization' => 'Bearer ' . $this->getSessionId()]];
            if (!empty($body)) {
                $options['headers']['Content-Type'] = 'application/json';
                $options['body'] = $body;
            }
            $response = $client->request($method, $uri, $options);

            return json_decode($response->getBody(), true);
        } catch (BadResponseException $ex) {
            $response = $ex->getResponse();
            $status = $response->getStatusCode();
            if (401 == $status) {
                // attempt the clear cache and rebuild session
                $this->sessionCache = array();
                // resend request
                try {
                    $options = ['query' => $parameters, 'headers' => ['Authorization' => 'Bearer ' . $this->getSessionId()]];
                    if (!empty($body)) {
                        $options['headers']['Content-Type'] = 'application/json';
                        $options['body'] = $body;
                    }
                    $response = $client->request($method, $uri, $options);

                    return json_decode($response->getBody(), true);
                } catch (BadResponseException $ex) {
                    $response = $ex->getResponse();
                    $status = $response->getStatusCode();
                    $error = json_decode($response->getBody(), true);
                    $error = array_get($error, 0, array());
                    $message = array_get($error, 'message', $response->getReasonPhrase());
                    $code = array_get($error, 'errorCode', 'ERROR');
                    throw new RestException($status, $code . ' ' . $message);
                } catch (\Exception $ex) {
                    throw new InternalServerErrorException($ex->getMessage(), $ex->getCode() ?: null);
                }
            }

            $error = json_decode($response->getBody(), true);
            $error = array_get($error, 0, array());
            $message = array_get($error, 'message', $response->getReasonPhrase());
            $code = array_get($error, 'errorCode', 'ERROR');
            throw new RestException($status, $code . ' ' . $message);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException($ex->getMessage(), $ex->getCode() ?: null);
        }
    }

    protected function getBaseUrl()
    {
        return sprintf(
            'https://%s.salesforce.com/services/data/%s/',
            $this->getServerInstance(),
            $this->version
        );
    }

    /**
     * Get Guzzle client
     *
     * @return GuzzleClient
     */
    public function getGuzzleClient()
    {
        return new GuzzleClient(['base_uri' => $this->getBaseUrl()]);
    }
}