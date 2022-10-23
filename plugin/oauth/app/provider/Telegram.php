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

namespace plugin\oauth\app\provider;

use support\Collection;
use plugin\oauth\app\entity\Profile;
use plugin\oauth\app\adapter\AbstractAdapter;
use plugin\oauth\app\adapter\AdapterInterface;
use plugin\oauth\app\exception\InvalidApplicationCredentialsException;
use plugin\oauth\app\exception\InvalidAuthorizationCodeException;
use plugin\oauth\app\exception\UnexpectedApiResponseException;

/**
 * Telegram provider adapter.
 *
 * To set up Telegram you need to interactively create a bot using the
 * Telegram mobile app, talking to botfather. The minimum conversation
 * will look like:
 *
 * /newbot
 * My Bot Title
 * nameofmynewbot
 * /setdomain
 * @nameofmynewbot
 * mydomain.com
 *
 * Example:
 *
 *   $config = [
 *       'callback' => '',
 *       'keys' => ['id' => 'your_bot_name', 'secret' => 'your_bot_token'],
 *   ];
 *
 *   $adapter = new plugin\oauth\app\provider\Telegram($config);
 *
 *   try {
 *       $adapter->authenticate();
 *
 *       $userProfile = $adapter->getUserProfile();
 *   } catch (\Exception $e) {
 *       print $e->getMessage();
 *   }
 */
class Telegram extends AbstractAdapter implements AdapterInterface
{
    protected $botId = '';

    protected $botSecret = '';

    protected $callbackUrl = '';

    /**
     * IPD API Documentation
     *
     * OPTIONAL.
     *
     * @var string
     */
    protected $apiDocumentation = 'https://core.telegram.org/bots';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->botId = $this->config->filter('keys')->get('id');
        $this->botSecret = $this->config->filter('keys')->get('secret');
        $this->callbackUrl = $this->config->get('callback_uri');

        if (!$this->botId || !$this->botSecret) {
            throw new InvalidApplicationCredentialsException(
                'Your application id is required in order to connect to ' . $this->providerId
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate()
    {
        $this->logger->info(sprintf('%s::authenticate()', get_class($this)));
        if (!request()->input('hash', false)) {
            return $this->authenticateBegin();
        } else {
            $this->authenticateCheckError();
            $this->authenticateFinish();
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        $authData = $this->getStoredData('auth_data');
        return !empty($authData);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserProfile()
    {
        $data = new Collection($this->getStoredData('auth_data'));

        if (!$data->exists('id')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        $userProfile = new Profile();

        $userProfile->identifier = $data->get('id');
        $userProfile->firstName = $data->get('first_name');
        $userProfile->lastName = $data->get('last_name');
        $userProfile->displayName = $data->get('username');
        $userProfile->photoURL = $data->get('photo_url');
        $username = $data->get('username');
        if (!empty($username)) {
            // Only some accounts have usernames.
            $userProfile->profileURL = "https://t.me/{$username}";
        }

        return $userProfile;
    }

    /**
     * See: https://telegram.im/widget-login.php
     * See: https://gist.github.com/anonymous/6516521b1fb3b464534fbc30ea3573c2
     */
    protected function authenticateCheckError()
    {
        $auth_data = $this->parseAuthData();

        $check_hash = $auth_data['hash'];
        unset($auth_data['hash']);
        $data_check_arr = [];

        foreach ($auth_data as $key => $value) {
            if (!empty($value)) {
                $data_check_arr[] = $key . '=' . $value;
            }
        }
        sort($data_check_arr);

        $data_check_string = implode("\n", $data_check_arr);
        $secret_key = hash('sha256', $this->botSecret, true);
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);

        if (strcmp($hash, $check_hash) !== 0) {
            throw new InvalidAuthorizationCodeException(
                sprintf('Provider returned an error: %s', 'Data is NOT from Telegram')
            );
        }

        if ((time() - $auth_data['auth_date']) > 86400) {
            throw new InvalidAuthorizationCodeException(
                sprintf('Provider returned an error: %s', 'Data is outdated')
            );
        }
    }

    /**
     * See: https://telegram.im/widget-login.php
     */
    protected function authenticateBegin()
    {
        $this->logger->debug(sprintf('%s::authenticateBegin(), redirecting user to:', get_class($this)));

        $nonce = $this->config->get('nonce');
        $nonce_code = empty($nonce) ? '' : "nonce=\"{$nonce}\"";

        return view('response/telegram', ['nonce_code' => $nonce_code, 'botId' => $this->botId, 'callbackUrl' => $this->callbackUrl]);
    }

    protected function authenticateFinish()
    {
        $this->logger->debug(
            sprintf('%s::authenticateFinish(), callback url:', get_class($this)),
            [request()->url()]
        );

        $this->storeData('auth_data', $this->parseAuthData());

        $this->initialize();
    }

    protected function parseAuthData()
    {
        return [
            'id' => request()->input('id', false),
            'first_name' => request()->input('first_name', false),
            'last_name' => request()->input('last_name', false),
            'username' => request()->input('username', false),
            'photo_url' => request()->input('photo_url', false),
            'auth_date' => request()->input('auth_date', false),
            'hash' => request()->input('hash', false),
        ];
    }
}
