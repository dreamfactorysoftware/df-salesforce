<?php
namespace DreamFactory\Core\Salesforce\SoapClient;

use DreamFactory\Core\Salesforce\SoapClient\Soap\SoapClient;
use DreamFactory\Core\Salesforce\SoapClient\Result;

/**
 * A client for the Salesforce SOAP API
 *
 * @author David de Boer <david@ddeboer.nl>
 */
class Client
{
    /*
     * SOAP namespace
     *
     * @var string
     */
    const SOAP_NAMESPACE = 'urn:enterprise.soap.sforce.com';

    /**
     * SOAP session header
     *
     * @var \SoapHeader
     */
    protected $sessionHeader;

    /**
     * PHP SOAP client for interacting with the Salesforce API
     *
     * @var SoapClient
     */
    protected $soapClient;

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
    protected $token;

    /**
     * Login result
     *
     * @var Result\LoginResult
     */
    protected $loginResult;

    /**
     * Construct Salesforce SOAP client
     *
     * @param SoapClient $soapClient SOAP client
     * @param string     $username   Salesforce username
     * @param string     $password   Salesforce password
     * @param string     $token      Salesforce security token
     */
    public function __construct(SoapClient $soapClient, $username, $password, $token)
    {
        $this->soapClient = $soapClient;
        $this->username = $username;
        $this->password = $password;
        $this->token = $token;
    }

    /**
     * Get user info
     *
     * @return Result\GetUserInfoResult
     * @link http://www.salesforce.com/us/developer/docs/api/Content/sforce_api_calls_getuserinfo.htm
     */
    public function getUserInfo()
    {
        return $this->call('getUserInfo');
    }

    /**
     * Logs in to the login server and starts a client session
     *
     * @param string $username Salesforce username
     * @param string $password Salesforce password
     * @param string $token    Salesforce security token
     *
     * @return Result\LoginResult
     * @link http://www.salesforce.com/us/developer/docs/api/Content/sforce_api_calls_login.htm
     */
    public function login($username, $password, $token)
    {
        $result = $this->soapClient->login(
            array(
                'username'  => $username,
                'password'  => $password.$token
            )
        );
        $this->setLoginResult($result->result);

        return $result->result;
    }

    /**
     * Get login result
     *
     * @return Result\LoginResult
     */
    public function getLoginResult()
    {
        if (null === $this->loginResult) {
            $this->login($this->username, $this->password, $this->token);
        }

        return $this->loginResult;
    }

    /**
     * Ends the session of the logged-in user
     *
     * @link http://www.salesforce.com/us/developer/docs/api/Content/sforce_api_calls_logout.htm
     */
    public function logout()
    {
        $this->call('logout');
        $this->sessionHeader = null;
        $this->setSessionId(null);
    }

    /**
     * Issue a call to Salesforce API
     *
     * @param string $method        SOAP operation name
     * @param array  $params        SOAP parameters
     * @return array|\Traversable An empty array or a result object, such
     *                              as QueryResult, SaveResult, DeleteResult.
     * @throws \SoapFault
     */
    protected function call($method, array $params = array())
    {
        // If there’s no session header yet, this means we haven’t yet logged in
        if (!$this->getSessionHeader()) {
            $this->login($this->username, $this->password, $this->token);
        }

        // Prepare headers
        $this->soapClient->__setSoapHeaders($this->getSessionHeader());

        try {
            $result = $this->soapClient->$method($params);
        } catch (\SoapFault $soapFault) {
            throw $soapFault;
        }

        // No result e.g. for logout, delete with empty array
        if (!isset($result->result)) {
            return array();
        }

        return $result->result;
    }

    /**
     * Get session header
     *
     * @return \SoapHeader
     */
    protected function getSessionHeader()
    {
        return $this->sessionHeader;
    }

    /**
     * Save session id to SOAP headers to be used on subsequent requests
     *
     * @param string $sessionId
     */
    protected function setSessionId($sessionId)
    {
        $this->sessionHeader = new \SoapHeader(
            self::SOAP_NAMESPACE,
            'SessionHeader',
            array(
                'sessionId' => $sessionId
            )
        );
    }

    protected function setLoginResult(Result\LoginResult $loginResult)
    {
        $this->loginResult = $loginResult;
        $this->setEndpointLocation($loginResult->getServerUrl());
        $this->setSessionId($loginResult->getSessionId());
    }

    /**
     * After successful log in, Salesforce wants us to change the endpoint
     * location
     *
     * @param string $location
     */
    protected function setEndpointLocation($location)
    {
        $this->soapClient->__setLocation($location);
    }
}

