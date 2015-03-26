<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Salesforce\Services;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use DreamFactory\Rave\Exceptions\RestException;
use DreamFactory\Rave\Services\BaseNoSqlDbService;
use DreamFactory\Rave\Resources\BaseRestResource;
use DreamFactory\Rave\Salesforce\Resources\Schema;
use DreamFactory\Rave\Salesforce\Resources\Table;
use Guzzle\Http\Client as GuzzleClient;
use Phpforce\SoapClient as SoapClient;

/**
 * SalesforceDb
 *
 * A service to handle SalesforceDB NoSQL (schema-less) database
 * services accessed through the REST API.
 */
class SalesforceDb extends BaseNoSqlDbService
{
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
    protected $_username;
    /**
     * @var array
     */
    protected $_password;
    /**
     * @var array
     */
    protected $_securityToken;
    /**
     * @var array
     */
    protected $_version = 'v28.0';
    /**
     * @var array
     */
    protected $_sessionCache;
    /**
     * @var array
     */
    protected $_fieldCache;

    /**
     * @var array
     */
    protected $resources = [
        Schema::RESOURCE_NAME          => [
            'name'       => Schema::RESOURCE_NAME,
            'class_name' => 'DreamFactory\\Rave\\Salesforce\\Resources\\Schema',
            'label'      => 'Schema',
        ],
        Table::RESOURCE_NAME           => [
            'name'       => Table::RESOURCE_NAME,
            'class_name' => 'DreamFactory\\Rave\\Salesforce\\Resources\\Table',
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
    public function __construct( $settings = array() )
    {
        parent::__construct( $settings );

        $config = ArrayUtils::clean( ArrayUtils::get( $settings, 'config' ) );
//        Session::replaceLookups( $config, true );

        $this->_username = ArrayUtils::get( $config, 'username' );
        $this->_password = ArrayUtils::get( $config, 'password' );
        $this->_securityToken = ArrayUtils::get( $config, 'security_token' );
        if ( empty( $this->_securityToken ) )
        {
            $this->_securityToken = ''; // gets appended to password
        }

        if ( empty( $this->_username ) || empty( $this->_password ) )
        {
            throw new \InvalidArgumentException( 'A Salesforce username and password are required for this service.' );
        }

        $_version = ArrayUtils::get( $config, 'version' );
        if ( !empty( $_version ) )
        {
            $this->_version = $_version;
        }

//        $this->_sessionCache = Pii::getState( 'service.' . $this->getApiName() . '.cache', array() );
//
//        $this->_fieldCache = array();
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
    public function getSObjects( $list_only = false )
    {
        $_result = $this->callGuzzle( 'GET', 'sobjects/' );

        $_tables = ArrayUtils::clean( ArrayUtils::get( $_result, 'sobjects' ) );
        if ( $list_only )
        {
            $_out = array();
            foreach ( $_tables as $_table )
            {
                $_out[] = ArrayUtils::get( $_table, 'name' );
            }

            return $_out;
        }

        return $_tables;
    }
    
    /**
     * @param string $name
     *
     * @return string
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function correctTableName( &$name )
    {
        static $_existing = null;

        if ( !$_existing )
        {
            $_existing = $this->getSObjects( true );
        }

        if ( empty( $name ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        if ( false === array_search( $name, $_existing ) )
        {
            throw new NotFoundException( "Table '$name' not found." );
        }

        return $name;
    }

    /**
     * @param string $main   Main resource or empty for service
     * @param string $sub    Subtending resources if applicable
     * @param string $action Action to validate permission
     */
    protected function validateResourceAccess( $main, $sub, $action )
    {
        if ( !empty( $main ) )
        {
            $_resource = rtrim( $main, '/' ) . '/';
            switch ( $main )
            {
                case Schema::RESOURCE_NAME:
                case Table::RESOURCE_NAME:
                    if ( !empty( $sub ) )
                    {
                        $_resource .= $sub;
                    }
                    break;
            }

            $this->checkPermission( $action, $_resource );

            return;
        }

        parent::validateResourceAccess( $main, $sub, $action );
    }

    /**
     * @param BaseRestResource $class
     * @param array            $info
     *
     * @return mixed
     */
    protected function instantiateResource( $class, $info = [ ] )
    {
        return new $class( $this, $info );
    }

    /**
     * {@InheritDoc}
     */
    protected function handleResource()
    {
        try
        {
            return parent::handleResource();
        }
        catch ( NotFoundException $_ex )
        {
            // If version 1.x, the resource could be a table
//            if ($this->request->getApiVersion())
//            {
//                $resource = $this->instantiateResource( 'DreamFactory\\Rave\\SalesforceDb\\Resources\\Table', [ 'name' => $this->resource ] );
//                $newPath = $this->resourceArray;
//                array_shift( $newPath );
//                $newPath = implode( '/', $newPath );
//
//                return $resource->handleRequest( $this->request, $newPath, $this->outputFormat );
//            }

            throw $_ex;
        }
    }

    /**
     * @return array
     */
    protected function getResources()
    {
        return $this->resources;
    }

    // REST service implementation

    /**
     * {@inheritdoc}
     */
    public function listResources( $include_properties = null )
    {
        if ( !$this->request->queryBool( 'as_access_components' ) )
        {
            return parent::listResources( $include_properties );
        }

        $_resources = [ ];

//        $refresh = $this->request->queryBool( 'refresh' );

        $_name = Schema::RESOURCE_NAME . '/';
        $_access = $this->getPermissions( $_name );
        if ( !empty( $_access ) )
        {
            $_resources[] = $_name;
            $_resources[] = $_name . '*';
        }

        $_result = $this->getSObjects( true );
        foreach ( $_result as $_name )
        {
            $_name = Schema::RESOURCE_NAME . '/' . $_name;
            $_access = $this->getPermissions( $_name );
            if ( !empty( $_access ) )
            {
                $_resources[] = $_name;
            }
        }

        $_name = Table::RESOURCE_NAME . '/';
        $_access = $this->getPermissions( $_name );
        if ( !empty( $_access ) )
        {
            $_resources[] = $_name;
            $_resources[] = $_name . '*';
        }

        foreach ( $_result as $_name )
        {
            $_name = Table::RESOURCE_NAME . '/' . $_name;
            $_access = $this->getPermissions( $_name );
            if ( !empty( $_access ) )
            {
                $_resources[] = $_name;
            }
        }

        return array( 'resource' => $_resources );
    }

    /**
     * @return ServiceResponseInterface
     */
//    protected function respond()
//    {
//        if ( Verbs::POST === $this->getRequestedAction() )
//        {
//            switch ( $this->resource )
//            {
//                case Table::RESOURCE_NAME:
//                case Schema::RESOURCE_NAME:
//                    if ( !( $this->response instanceof ServiceResponseInterface ) )
//                    {
//                        $this->response = ResponseFactory::create( $this->response, $this->outputFormat, ServiceResponseInterface::HTTP_CREATED );
//                    }
//                    break;
//            }
//        }
//
//        parent::respond();
//    }


    protected function _getSoapLoginResult()
    {
        //@todo use client provided Salesforce wsdl for the different versions
        $_wsdl = __DIR__.'/../config/enterprise.wsdl.xml';

        $_builder = new SoapClient\ClientBuilder( $_wsdl, $this->_username, $this->_password, $this->_securityToken );
        $_soapClient = $_builder->build();
        if ( !isset( $_soapClient ) )
        {
            throw new InternalServerErrorException( 'Failed to build session with Salesforce.' );
        }

        $_result = $_soapClient->getLoginResult();
        $this->_sessionCache['server_instance'] = $_result->getServerInstance();
        $this->_sessionCache['session_id'] = $_result->getSessionId();
//        Pii::setState( 'service.' . $this->getApiName() . '.cache', $this->_sessionCache );
    }

    protected function _getSessionId()
    {
        $_id = ArrayUtils::get( $this->_sessionCache, 'session_id' );
        if ( empty( $_id ) )
        {
            $this->_getSoapLoginResult();

            $_id = ArrayUtils::get( $this->_sessionCache, 'session_id' );
            if ( empty( $_id ) )
            {
                throw new InternalServerErrorException( 'Failed to get session id from Salesforce.' );
            }
        }

        return $_id;
    }

    protected function _getServerInstance()
    {
        $_instance = ArrayUtils::get( $this->_sessionCache, 'server_instance' );
        if ( empty( $_instance ) )
        {
            $this->_getSoapLoginResult();

            $_instance = ArrayUtils::get( $this->_sessionCache, 'server_instance' );
            if ( empty( $_instance ) )
            {
                throw new InternalServerErrorException( 'Failed to get server instance from Salesforce.' );
            }
        }

        return $_instance;
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
    public function callGuzzle( $method = 'GET', $uri = null, $parameters = array(), $body = null, $client = null )
    {
        $_options = array();
        try
        {
            if ( !isset( $client ) )
            {
                $client = $this->getGuzzleClient();
            }
            $request = $client->createRequest( $method, $uri, null, $body, $_options );
            $request->setHeader( 'Authorization', 'Bearer ' . $this->_getSessionId() );
            if ( !empty( $body ) )
            {
                $request->setHeader( 'Content-Type', 'application/json' );
            }
            if ( !empty( $parameters ) )
            {
                $request->getQuery()->merge( $parameters );
            }

            $response = $request->send();

            return $response->json();
        }
        catch ( \Guzzle\Http\Exception\BadResponseException $ex )
        {
            $_response = $ex->getResponse();
            $_status = $_response->getStatusCode();
            if ( 401 == $_status )
            {
                // attempt the clear cache and rebuild session
                $this->_sessionCache = array();
                // resend request
                try
                {
                    $client = $client->setBaseUrl( $this->getBaseUrl() );
                    $request = $client->createRequest( $method, $uri, null, $body, $_options );
                    $request->setHeader( 'Authorization', 'Bearer ' . $this->_getSessionId() );
                    if ( !empty( $body ) )
                    {
                        $request->setHeader( 'Content-Type', 'application/json' );
                    }
                    if ( !empty( $parameters ) )
                    {
                        $request->getQuery()->merge( $parameters );
                    }

                    $response = $request->send();

                    return $response->json();
                }
                catch ( \Guzzle\Http\Exception\BadResponseException $ex )
                {
                    $_response = $ex->getResponse();
                    $_status = $_response->getStatusCode();
                    $_error = $_response->json();
                    $_error = ArrayUtils::get( $_error, 0, array() );
                    $_message = ArrayUtils::get( $_error, 'message', $_response->getMessage() );
                    $_code = ArrayUtils::get( $_error, 'errorCode', 'ERROR' );
                    throw new RestException( $_status, $_code . ' ' . $_message );
                }
                catch ( \Exception $ex )
                {
                    throw new InternalServerErrorException( $ex->getMessage(), $ex->getCode() ? : null );
                }

            }

            $_error = $_response->json();
            $_error = ArrayUtils::get( $_error, 0, array() );
            $_message = ArrayUtils::get( $_error, 'message', $_response->getMessage() );
            $_code = ArrayUtils::get( $_error, 'errorCode', 'ERROR' );
            throw new RestException( $_status, $_code . ' ' . $_message );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( $ex->getMessage(), $ex->getCode() ? : null );
        }
    }

    protected function getBaseUrl()
    {
        return sprintf(
            'https://%s.salesforce.com/services/data/%s/',
            $this->_getServerInstance(),
            $this->_version
        );
    }

    /**
     * Get Guzzle client
     *
     * @return \Guzzle\Http\Client
     */
    public function getGuzzleClient()
    {
        return new GuzzleClient( $this->getBaseUrl() );
    }
}