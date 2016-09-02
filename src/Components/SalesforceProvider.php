<?php
namespace DreamFactory\Core\Salesforce\Components;

use DreamFactory\Core\OAuth\Components\DfOAuthTwoProvider;
use SocialiteProviders\SalesForce\Provider;
use Illuminate\Http\Request;

class SalesforceProvider extends Provider
{
    use DfOAuthTwoProvider;

    /**
     * @param Request $clientId
     * @param string  $clientSecret
     * @param string  $redirectUrl
     */
    public function __construct($clientId, $clientSecret, $redirectUrl)
    {
        /** @var Request $request */
        $request = \Request::instance();
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl);
    }
}