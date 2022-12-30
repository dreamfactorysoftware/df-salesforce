<?php

namespace DreamFactory\Core\Salesforce\Services;

use DreamFactory\Core\Database\Services\BaseDbService;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\OAuth\Models\OAuthTokenMap;
use DreamFactory\Core\Salesforce\Database\Schema\Schema as DatabaseSchema;
use DreamFactory\Core\Salesforce\Resources\Schema;
use DreamFactory\Core\Salesforce\Resources\Table;
use DreamFactory\Core\Salesforce\SoapClient\Client;
use DreamFactory\Core\Salesforce\SoapClient\Soap\SoapClientFactory;
use DreamFactory\Core\Utility\Session;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use Arr;

/**
 * SalesforceDb
 *
 * A database service to access Salesforce SObjects via their REST API.
 */
class Salesforce extends BaseDbService
{
    /**
     * Default Salesforce API version if not gleaned from connection.
     */
    const SALESFORCE_API_VERSION = '37.0';

    /**
     * @var GuzzleClient
     */
    protected $guzzleClient;
    /**
     * @var string
     */
    protected $username;
    /**
     * @var string
     */
    protected $password;
    /**
     * @var string
     */
    protected $securityToken;
    /**
     * @var string
     */
    protected $wsdl;
    /**
     * @var string
     */
    protected $version;
    /**
     * @var integer
     */
    protected $oauthServiceId;
    /**
     * @var string
     */
    protected $sessionId;
    /**
     * @var string
     */
    protected $serverUrl;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new Salesforce service
     *
     * @param array $settings
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);

        $this->username = Arr::get($this->config, 'username');
        $this->password = Arr::get($this->config, 'password');
        $this->securityToken = strval(Arr::get($this->config, 'security_token')); // gets appended to password
        $this->wsdl = Arr::get($this->config, 'wsdl');

        if (!empty($this->wsdl)) {
            if (!str_contains($this->wsdl, DIRECTORY_SEPARATOR)) {
                // no directories involved, store it where we want to store it
                if (!empty($storage = storage_path('wsdl'))) {
                    $this->wsdl = rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->wsdl;
                }
            } elseif (false !== $path = realpath($this->wsdl)) {
                $this->wsdl = $path;
            }
        }

        if (!empty($version = Arr::get($this->config, 'version'))) {
            $this->version = $version;
        }

        $this->oauthServiceId = Arr::get($this->config, 'oauth_service_id');

        if (empty($this->oauthServiceId)) {
            if (empty($this->wsdl) || empty($this->username) || empty($this->password)) {
                throw new \InvalidArgumentException('If not using an OAuth service, a Salesforce WSDL file, username, and password are required to access this service.');
            }
        }
        $this->dbConn = $this;
        /** @noinspection PhpParamsInspection */
        $this->schema = new DatabaseSchema($this->dbConn);

        $this->setConfigBasedCachePrefix($this->username . $this->wsdl . ':');
    }

    public function getResourceHandlers()
    {
        $handlers = parent::getResourceHandlers();

        $handlers[Schema::RESOURCE_NAME] = [
            'name'       => Schema::RESOURCE_NAME,
            'class_name' => Schema::class,
            'label'      => 'Schema',
        ];
        $handlers[Table::RESOURCE_NAME] = [
            'name'       => Table::RESOURCE_NAME,
            'class_name' => Table::class,
            'label'      => 'Table',
        ];

        return $handlers;
    }

    /**
     * @param bool $list_only
     *
     * @return array
     */
    public function getSObjects($list_only = false)
    {
        $result = $this->callResource('sobjects');

        $tables = (array)Arr::get($result, 'sobjects');
        if ($list_only) {
            $out = [];
            foreach ($tables as $table) {
                $out[] = Arr::get($table, 'name');
            }

            return $out;
        }

        return $tables;
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
            $name = Schema::RESOURCE_NAME . '/' . $name . '/';
            $access = $this->getPermissions($name);
            if (!empty($access)) {
		$resources[] = $name;
		$resources[] = $name . '*';
            }
        }

        $name = Table::RESOURCE_NAME . '/';
        $access = $this->getPermissions($name);
        if (!empty($access)) {
            $resources[] = $name;
            $resources[] = $name . '*';
        }

        foreach ($result as $name) {
            $name = Table::RESOURCE_NAME . '/' . $name . '/';
            $access = $this->getPermissions($name);
            if (!empty($access)) {
		$resources[] = $name;
		$resources[] = $name . '*';
            }
        }

        return $resources;
    }

    protected function getSoapLoginResult()
    {
        if (empty($this->wsdl) || empty($this->username) || empty($this->password)) {
            throw new UnauthorizedException('Failed to build session with Salesforce with the given configuration.');
        }

        $soapClientFactory = new SoapClientFactory();
        $soapClient = $soapClientFactory->factory($this->wsdl);

        $client = new Client($soapClient, $this->username, $this->password, $this->securityToken);
        if (!isset($client)) {
            throw new UnauthorizedException('Failed to build session with Salesforce with the given configuration.');
        }

        $result = $client->getLoginResult();
        $this->sessionId = $result->getSessionId();
        $this->addToCache('session_id', $this->sessionId, true);
        $serverInstance = $result->getServerInstance();
        $this->serverUrl = sprintf('https://%s.salesforce.com', $serverInstance);
        $this->addToCache('server_url', $this->serverUrl, true);
        $this->version = strstr(substr($result->getServerUrl(), stripos($result->getServerUrl(), '/Soap/c/') + 8), '/',
            true);
        $this->addToCache('server_version', $this->version, true);
    }

    protected function getOAuthLoginResult()
    {
        if (empty($this->oauthServiceId)) {
            throw new UnauthorizedException('Failed to build session with Salesforce with the given configuration.');
        }

        if (empty($result = $this->getOAuthResponse())) {
            throw new UnauthorizedException('Failed to build session with Salesforce with the given configuration.');
        }

        $this->sessionId = Arr::get($result, 'access_token'); // don't cache this
        $this->serverUrl = Arr::get($result, 'instance_url');
        $this->addToCache('server_url', $this->serverUrl, true);

        if (!empty($result = $this->callGuzzle('GET'))) {
            $this->version = Arr::get(Arr::last($result), 'version');
            $this->addToCache('server_version', $this->version, true);
        }
    }

    protected function getSessionId()
    {
        if (empty($this->sessionId)) {
            // oauth takes precedence over cached SOAP generated session
            if (empty($this->sessionId = $this->getOAuthToken())) {
                if (empty($this->sessionId = $this->getFromCache('session_id'))) {
                    $this->getSoapLoginResult();
                    if (empty($this->sessionId)) {
                        throw new UnauthorizedException('Failed to build a session with Salesforce.');
                    }
                }
            }
        }

        return $this->sessionId;
    }

    protected function getServerUrl()
    {
        if (empty($this->serverUrl)) {
            if (empty($this->serverUrl = $this->getFromCache('server_url'))) {
                try {
                    $this->getOAuthLoginResult();
                } catch (\Exception $ex) {
                    $this->getSoapLoginResult();
                }
                if (empty($this->serverUrl)) {
                    throw new InternalServerErrorException('Failed to get server instance from Salesforce.');
                }
            }
        }

        return $this->serverUrl;
    }

    protected function getVersion()
    {
        if (empty($this->version)) {
            if (empty($this->version = $this->getFromCache('server_version'))) {
                try {
                    $this->getOAuthLoginResult();
                } catch (\Exception $ex) {
                    try {
                        $this->getSoapLoginResult();
                    } catch (\Exception $ex) {
                        // do nothing here, see fallback below
                    }
                }
                if (empty($this->version)) {
                    $this->version = static::SALESFORCE_API_VERSION;
                }
            }
        }

        return $this->version;
    }

    protected function getOAuthToken()
    {
        return OAuthTokenMap::getCachedToken($this->oauthServiceId, Session::getCurrentUserId());
    }

    protected function getOAuthResponse()
    {
        return OAuthTokenMap::whereServiceId($this->oauthServiceId)->whereUserId(Session::getCurrentUserId())->value('response');
    }

    /**
     * Perform call to Salesforce REST API
     *
     * @param string $resource
     * @param string $method
     * @param string $uri
     * @param array  $parameters
     * @param mixed  $body
     *
     * @throws InternalServerErrorException
     * @throws RestException
     * @return array The JSON response as an array
     */
    public function callResource($resource, $method = 'GET', $uri = null, $parameters = [], $body = null)
    {
        $uri = 'v' . $this->getVersion() . '/' . $resource . (empty($uri) ? '' : '/' . $uri);

        return $this->callGuzzle($method, $uri, $parameters, $body);
    }

    /**
     * Perform call to Salesforce REST API
     *
     * @param string $method
     * @param string $uri
     * @param array  $parameters
     * @param mixed  $body
     *
     * @throws InternalServerErrorException
     * @throws RestException
     * @return array The JSON response as an array
     */
    public function callGuzzle(
        $method = 'GET',
        $uri = null,
        $parameters = [],
        $body = null
    ) {
        $client = $this->getGuzzleClient();
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
            if (401 == $status) {
                // attempt the clear cache and rebuild session
                $this->flush();
                /* Need to clear out current object sessionId as well */
                $this->sessionId = null;
                // resend request
                try {
                    $options = [
                        'query'   => $parameters,
                        'headers' => ['Authorization' => 'Bearer ' . $this->getSessionId()]
                    ];
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
                    $error = Arr::get($error, 0, []);
                    $message = Arr::get($error, 'message', $response->getReasonPhrase());
                    $code = Arr::get($error, 'errorCode', 'ERROR');
                    throw new RestException($status, $code . ' ' . $message);
                } catch (\Exception $ex) {
                    throw new InternalServerErrorException($ex->getMessage(), $ex->getCode() ?: null);
                }
            }

            $error = json_decode($response->getBody(), true);
            $error = Arr::get($error, 0, []);
            $message = Arr::get($error, 'message', $response->getReasonPhrase());
            $code = Arr::get($error, 'errorCode', 'ERROR');
            throw new RestException($status, $code . ' ' . $message);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException($ex->getMessage(), $ex->getCode() ?: null);
        }
    }

    /**
     * Get Guzzle client
     *
     * @return GuzzleClient
     */
    public function getGuzzleClient()
    {
        if (!$this->guzzleClient) {
            $uri = rtrim($this->getServerUrl(), '/') . '/services/data/';
            $this->guzzleClient = new GuzzleClient(['base_uri' => $uri]);
        }

        return $this->guzzleClient;
    }
}
