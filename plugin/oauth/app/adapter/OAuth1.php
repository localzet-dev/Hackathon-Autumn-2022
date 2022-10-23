<?php

/**
 * @package     FrameX (FX) OAuth Plugin
 * @link        https://localzet.gitbook.io
 * 
 * @author      localzet <creator@localzet.ru>
 * 
 * @copyright   Copyright (c) 2018-2020 Zorin Projects 
 * @copyright   Copyright (c) 2020-2022 NONA Team
 * 
 * @license     https://www.localzet.ru/license GNU GPLv3 License
 */

namespace plugin\oauth\app\adapter;

use Exception;
use plugin\oauth\app\exception\InvalidApplicationCredentialsException;
use plugin\oauth\app\exception\AuthorizationDeniedException;
use plugin\oauth\app\exception\InvalidOauthTokenException;
use plugin\oauth\app\exception\InvalidAccessTokenException;
use plugin\oauth\lib\OAuthConsumer;
use plugin\oauth\lib\OAuthRequest;
use plugin\oauth\lib\OAuthSignatureMethodHMACSHA1;
use plugin\oauth\lib\OAuthUtil;
use support\Parser;

/**
 * This class  can be used to simplify the authorization flow of OAuth 1 based service providers.
 *
 * Subclasses (i.e., providers adapters) can either use the already provided methods or override
 * them when necessary.
 */
abstract class OAuth1 extends AbstractAdapter implements AdapterInterface
{
    /**
     * Base URL to provider API
     *
     * This var will be used to build urls when sending signed requests
     *
     * @var string
     */
    protected $apiBaseUrl = '';

    /**
     * @var string
     */
    protected $authorizeUrl = '';

    /**
     * @var string
     */
    protected $requestTokenUrl = '';

    /**
     * @var string
     */
    protected $accessTokenUrl = '';

    /**
     * IPD API Documentation
     *
     * OPTIONAL.
     *
     * @var string
     */
    protected $apiDocumentation = '';

    /**
     * OAuth Version
     *
     *  '1.0' OAuth Core 1.0
     * '1.0a' OAuth Core 1.0 Revision A
     *
     * @var string
     */
    protected $oauth1Version = '1.0a';

    /**
     * @var string
     */
    protected $consumerKey = null;

    /**
     * @var string
     */
    protected $consumerSecret = null;

    /**
     * @var object
     */
    protected $OAuthConsumer = null;

    /**
     * @var object
     */
    protected $sha1Method = null;

    /**
     * @var object
     */
    protected $consumerToken = null;

    /**
     * Authorization Url Parameters
     *
     * @var bool
     */
    protected $AuthorizeUrlParameters = [];

    /**
     * @var string
     */
    protected $requestTokenMethod = 'POST';

    /**
     * @var array
     */
    protected $requestTokenParameters = [];

    /**
     * @var array
     */
    protected $requestTokenHeaders = [];

    /**
     * @var string
     */
    protected $tokenExchangeMethod = 'POST';

    /**
     * @var array
     */
    protected $tokenExchangeParameters = [];

    /**
     * @var array
     */
    protected $tokenExchangeHeaders = [];

    /**
     * @var array
     */
    protected $apiRequestParameters = [];

    /**
     * @var array
     */
    protected $apiRequestHeaders = [];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->consumerKey = $this->config->filter('keys')->get('id') ?: $this->config->filter('keys')->get('key');
        $this->consumerSecret = $this->config->filter('keys')->get('secret');

        if (!$this->consumerKey || !$this->consumerSecret) {
            throw new InvalidApplicationCredentialsException(
                'Ваш идентификатор приложения требуется для подключения к ' . $this->providerId
            );
        }

        if ($this->config->exists('tokens')) {
            $this->setAccessToken($this->config->get('tokens'));
        }

        $this->setCallback($this->config->get('callback_uri'));
        $this->setApiEndpoints($this->config->get('endpoints'));
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
        /**
         * Set up OAuth Signature and Consumer
         *
         * OAuth Core: All Token requests and Protected Resources requests MUST be signed
         * by the Consumer and verified by the Service Provider.
         *
         * The protocol defines three signature methods: HMAC-SHA1, RSA-SHA1, and PLAINTEXT..
         *
         * The Consumer declares a signature method in the oauth_signature_method parameter..
         *
         * http://oauth.net/core/1.0a/#signing_process
         */
        $this->sha1Method = new OAuthSignatureMethodHMACSHA1();

        $this->OAuthConsumer = new OAuthConsumer(
            $this->consumerKey,
            $this->consumerSecret
        );

        if ($this->getStoredData('request_token')) {
            $this->consumerToken = new OAuthConsumer(
                $this->getStoredData('request_token'),
                $this->getStoredData('request_token_secret')
            );
        }

        if ($this->getStoredData('access_token')) {
            $this->consumerToken = new OAuthConsumer(
                $this->getStoredData('access_token'),
                $this->getStoredData('access_token_secret')
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate()
    {
        $this->logger->info(sprintf('%s::authenticate()', get_class($this)));

        if ($this->isConnected()) {
            return true;
        }

        try {
            if (!$this->getStoredData('request_token')) {
                // Start a new flow.
                return $this->authenticateBegin();
            } elseif (empty(request()->get('oauth_token') && empty(request()->get('oauth_token')))) {
                // A previous authentication was not finished, and this request is not finishing it.
                return $this->authenticateBegin();
            } else {
                // Finish a flow.
                $this->authenticateFinish();
            }
        } catch (Exception $exception) {
            $this->clearStoredData();

            throw $exception;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        return (bool)$this->getStoredData('access_token');
    }

    /**
     * Initiate the authorization protocol
     *
     * 1. Obtaining an Unauthorized Request Token
     * 2. Build Authorization URL for Authorization Request and redirect the user-agent to the
     *    Authorization Server.
     */
    protected function authenticateBegin()
    {
        $response = $this->requestAuthToken();

        $this->validateAuthTokenRequest($response);

        $authUrl = $this->getAuthorizeUrl();

        $this->logger->debug(sprintf('%s::authenticateBegin(), redirecting user to:', get_class($this)), [$authUrl]);

        return redirect($authUrl);
    }

    /**
     * Finalize the authorization process
     *
     * @throws AuthorizationDeniedException
     * @throws \plugin\oauth\app\exception\HttpClientFailureException
     * @throws \plugin\oauth\app\exception\HttpRequestFailedException
     * @throws InvalidAccessTokenException
     * @throws InvalidOauthTokenException
     */
    protected function authenticateFinish()
    {
        $this->logger->debug(sprintf('%s::authenticateFinish(), callback url:', get_class($this)));

        $denied = isset(request()->get('denied')) && is_string(request()->get('denied')) ? request()->get('denied') : '';
        $oauth_problem = isset(request()->get('oauth_problem')) && is_string(request()->get('oauth_problem')) ? request()->get('oauth_problem') : '';
        $oauth_token = isset(request()->get('oauth_token')) && is_string(request()->get('oauth_token')) ? request()->get('oauth_token') : '';
        $oauth_verifier = isset(request()->get('oauth_verifier')) && is_string(request()->get('oauth_verifier')) ? request()->get('oauth_verifier') : '';

        if ($denied) {
            throw new AuthorizationDeniedException(
                'Пользователь запретил запрос доступа. Провайдер вернул запрещенный токен: ' . htmlentities($denied)
            );
        }

        if ($oauth_problem) {
            throw new InvalidOauthTokenException(
                'Провайдер вернул ошибку. OAUTH_PROBLEM: ' . htmlentities($oauth_problem)
            );
        }

        if (!$oauth_token) {
            throw new InvalidOauthTokenException(
                'Ожидание ненульного OAuth_Token, чтобы продолжить поток авторизации'
            );
        }

        $response = $this->exchangeAuthTokenForAccessToken($oauth_token, $oauth_verifier);

        $this->validateAccessTokenExchange($response);

        $this->initialize();
    }

    /**
     * Build Authorization URL for Authorization Request
     *
     * @param array $parameters
     *
     * @return string
     */
    protected function getAuthorizeUrl($parameters = [])
    {
        $this->AuthorizeUrlParameters = !empty($parameters)
            ? $parameters
            : array_replace(
                (array)$this->AuthorizeUrlParameters,
                (array)$this->config->get('authorize_url_parameters')
            );

        $this->AuthorizeUrlParameters['oauth_token'] = $this->getStoredData('request_token');

        return $this->authorizeUrl . '?' . http_build_query($this->AuthorizeUrlParameters, '', '&');
    }

    /**
     * Unauthorized Request Token
     *
     * OAuth Core: The Consumer obtains an unauthorized Request Token by asking the Service Provider
     * to issue a Token. The Request Token's sole purpose is to receive User approval and can only
     * be used to obtain an Access Token.
     *
     * http://oauth.net/core/1.0/#auth_step1
     * 6.1.1. Consumer Obtains a Request Token
     *
     * @return string Raw Provider API response
     * @throws \plugin\oauth\app\exception\HttpClientFailureException
     * @throws \plugin\oauth\app\exception\HttpRequestFailedException
     */
    protected function requestAuthToken()
    {
        /**
         * OAuth Core 1.0 Revision A: oauth_callback: An absolute URL to which the Service Provider will redirect
         * the User back when the Obtaining User Authorization step is completed.
         *
         * http://oauth.net/core/1.0a/#auth_step1
         */
        if ('1.0a' == $this->oauth1Version) {
            $this->requestTokenParameters['oauth_callback'] = $this->callback;
        }

        $response = $this->oauthRequest(
            $this->requestTokenUrl,
            $this->requestTokenMethod,
            $this->requestTokenParameters,
            $this->requestTokenHeaders
        );

        return $response;
    }

    /**
     * Validate Unauthorized Request Token Response
     *
     * OAuth Core: The Service Provider verifies the signature and Consumer Key. If successful,
     * it generates a Request Token and Token Secret and returns them to the Consumer in the HTTP
     * response body.
     *
     * http://oauth.net/core/1.0/#auth_step1
     * 6.1.2. Service Provider Issues an Unauthorized Request Token
     *
     * @param string $response
     *
     * @return array
     * @throws InvalidOauthTokenException
     */
    protected function validateAuthTokenRequest($response)
    {
        /**
         * The response contains the following parameters:
         *
         *    - oauth_token               The Request Token.
         *    - oauth_token_secret        The Token Secret.
         *    - oauth_callback_confirmed  MUST be present and set to true.
         *
         * http://oauth.net/core/1.0/#auth_step1
         * 6.1.2. Service Provider Issues an Unauthorized Request Token
         *
         * Example of a successful response:
         *
         *  HTTP/1.1 200 OK
         *  Content-Type: text/html; charset=utf-8
         *  Cache-Control: no-store
         *  Pragma: no-cache
         *
         *  oauth_token=80359084-clg1DEtxQF3wstTcyUdHF3wsdHM&oauth_token_secret=OIF07hPmJB:P
         *  6qiHTi1znz6qiH3tTcyUdHnz6qiH3tTcyUdH3xW3wsDvV08e&example_parameter=example_value
         *
         * OAuthUtil::parse_parameters will attempt to decode the raw response into an array.
         */
        $tokens = OAuthUtil::parse_parameters($response);

        if (!isset($tokens['oauth_token'])) {
            throw new InvalidOauthTokenException(
                'Провайдер вернул не OAuth_token: ' . htmlentities($response)
            );
        }

        $this->consumerToken = new OAuthConsumer(
            $tokens['oauth_token'],
            $tokens['oauth_token_secret']
        );

        $this->storeData('request_token', $tokens['oauth_token']);
        $this->storeData('request_token_secret', $tokens['oauth_token_secret']);

        return $tokens;
    }

    /**
     * Requests an Access Token
     *
     * OAuth Core: The Request Token and Token Secret MUST be exchanged for an Access Token and Token Secret.
     *
     * http://oauth.net/core/1.0a/#auth_step3
     * 6.3.1. Consumer Requests an Access Token
     *
     * @param string $oauth_token
     * @param string $oauth_verifier
     *
     * @return string Raw Provider API response
     * @throws \plugin\oauth\app\exception\HttpClientFailureException
     * @throws \plugin\oauth\app\exception\HttpRequestFailedException
     */
    protected function exchangeAuthTokenForAccessToken($oauth_token, $oauth_verifier = '')
    {
        $this->tokenExchangeParameters['oauth_token'] = $oauth_token;

        /**
         * OAuth Core 1.0 Revision A: oauth_verifier: The verification code received from the Service Provider
         * in the "Service Provider Directs the User Back to the Consumer" step.
         *
         * http://oauth.net/core/1.0a/#auth_step3
         */
        if ('1.0a' == $this->oauth1Version) {
            $this->tokenExchangeParameters['oauth_verifier'] = $oauth_verifier;
        }

        $response = $this->oauthRequest(
            $this->accessTokenUrl,
            $this->tokenExchangeMethod,
            $this->tokenExchangeParameters,
            $this->tokenExchangeHeaders
        );

        return $response;
    }

    /**
     * Validate Access Token Response
     *
     * OAuth Core: If successful, the Service Provider generates an Access Token and Token Secret and returns
     * them in the HTTP response body.
     *
     * The Access Token and Token Secret are stored by the Consumer and used when signing Protected Resources requests.
     *
     * http://oauth.net/core/1.0a/#auth_step3
     * 6.3.2. Service Provider Grants an Access Token
     *
     * @param string $response
     *
     * @return array
     * @throws InvalidAccessTokenException
     */
    protected function validateAccessTokenExchange($response)
    {
        /**
         * The response contains the following parameters:
         *
         *    - oauth_token         The Access Token.
         *    - oauth_token_secret  The Token Secret.
         *
         * http://oauth.net/core/1.0/#auth_step3
         * 6.3.2. Service Provider Grants an Access Token
         *
         * Example of a successful response:
         *
         *  HTTP/1.1 200 OK
         *  Content-Type: text/html; charset=utf-8
         *  Cache-Control: no-store
         *  Pragma: no-cache
         *
         *  oauth_token=sHeLU7Far428zj8PzlWR75&oauth_token_secret=fXb30rzoG&oauth_callback_confirmed=true
         *
         * OAuthUtil::parse_parameters will attempt to decode the raw response into an array.
         */
        $tokens = OAuthUtil::parse_parameters($response);

        if (!isset($tokens['oauth_token'])) {
            throw new InvalidAccessTokenException(
                'Провайдер вернул не OAuth_token: ' . htmlentities($response)
            );
        }

        $this->consumerToken = new OAuthConsumer(
            $tokens['oauth_token'],
            $tokens['oauth_token_secret'],
        );

        $this->storeData('access_token', $tokens['oauth_token']);
        $this->storeData('access_token_secret', $tokens['oauth_token_secret']);

        $this->deleteStoredData('request_token');
        $this->deleteStoredData('request_token_secret');

        return $tokens;
    }

    /**
     * Send a signed request to provider API
     *
     * Note: Since the specifics of error responses is beyond the scope of RFC6749 and OAuth specifications,
     * OAuth will consider any HTTP status code that is different than '200 OK' as an ERROR.
     *
     * @param string $url
     * @param string $method
     * @param array $parameters
     * @param array $headers
     * @param bool $multipart
     *
     * @return mixed
     * @throws \plugin\oauth\app\exception\HttpClientFailureException
     * @throws \plugin\oauth\app\exception\HttpRequestFailedException
     */
    public function apiRequest($url, $method = 'GET', $parameters = [], $headers = [], $multipart = false)
    {
        // refresh tokens if needed
        $this->maintainToken();

        if (strrpos($url, 'http://') !== 0 && strrpos($url, 'https://') !== 0) {
            $url = rtrim($this->apiBaseUrl, '/') . '/' . ltrim($url, '/');
        }

        $parameters = array_replace($this->apiRequestParameters, (array)$parameters);

        $headers = array_replace($this->apiRequestHeaders, (array)$headers);

        $response = $this->oauthRequest($url, $method, $parameters, $headers, $multipart);

        $response = (new Parser())->parse($response);

        return $response;
    }

    /**
     * Setup and Send a Signed Oauth Request
     *
     * This method uses OAuth Library.
     *
     * @param string $uri
     * @param string $method
     * @param array $parameters
     * @param array $headers
     * @param bool $multipart
     *
     * @return string Raw Provider API response
     * @throws \plugin\oauth\app\exception\HttpClientFailureException
     * @throws \plugin\oauth\app\exception\HttpRequestFailedException
     */
    protected function oauthRequest($uri, $method = 'GET', $parameters = [], $headers = [], $multipart = false)
    {
        $signing_parameters = $parameters;
        if ($multipart) {
            $signing_parameters = [];
        }

        $request = OAuthRequest::from_consumer_and_token(
            $this->OAuthConsumer,
            $this->consumerToken,
            $method,
            $uri,
            $signing_parameters
        );

        $request->sign_request(
            $this->sha1Method,
            $this->OAuthConsumer,
            $this->consumerToken
        );

        $uri = $request->get_normalized_http_url();
        $headers = array_replace($request->to_header(), (array)$headers);

        $response = $this->httpClient->request(
            $uri,
            $method,
            $parameters,
            $headers,
            $multipart
        );

        $this->validateApiResponse('Signed API request to ' . $uri . ' has returned an error');

        return $response;
    }
}
