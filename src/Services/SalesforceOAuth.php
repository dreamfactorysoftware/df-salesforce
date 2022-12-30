<?php
namespace DreamFactory\Core\Salesforce\Services;

use DreamFactory\Core\OAuth\Services\BaseOAuthService;
use DreamFactory\Core\Salesforce\Components\SalesforceProvider;
use Arr;

class SalesforceOAuth extends BaseOAuthService
{
    /**
     * OAuth service provider name.
     */
    const PROVIDER_NAME = 'salesforce';

    /** @inheritdoc */
    protected function setProvider($config)
    {
        $clientId = Arr::get($config, 'client_id');
        $clientSecret = Arr::get($config, 'client_secret');
        $redirectUrl = Arr::get($config, 'redirect_url');

        $this->provider = new SalesforceProvider($clientId, $clientSecret, $redirectUrl);
    }

    /** @inheritdoc */
    public function getProviderName()
    {
        return self::PROVIDER_NAME;
    }
}