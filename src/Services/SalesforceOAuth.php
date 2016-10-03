<?php
namespace DreamFactory\Core\Salesforce\Services;

use DreamFactory\Core\OAuth\Services\BaseOAuthService;
use DreamFactory\Core\Salesforce\Components\SalesforceProvider;

class SalesforceOAuth extends BaseOAuthService
{
    /**
     * OAuth service provider name.
     */
    const PROVIDER_NAME = 'salesforce';

    /** @inheritdoc */
    protected function setProvider($config)
    {
        $clientId = array_get($config, 'client_id');
        $clientSecret = array_get($config, 'client_secret');
        $redirectUrl = array_get($config, 'redirect_url');

        $this->provider = new SalesforceProvider($clientId, $clientSecret, $redirectUrl);
    }

    /** @inheritdoc */
    public function getProviderName()
    {
        return self::PROVIDER_NAME;
    }
}