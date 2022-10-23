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

use Exception;
use plugin\oauth\app\adapter\OAuth2;
use plugin\oauth\app\exception\UnexpectedApiResponseException;
use support\Collection;
use plugin\oauth\app\entity\Profile;

use plugin\oauth\app\entity;

/**
 * Vkontakte OAuth2 provider adapter.
 *
 * Example:
 *
 *   $config = [
 *       'callback' => '',
 *       'keys' => [
 *           'id' => '', // App ID
 *           'secret' => '' // Secure key
 *       ],
 *   ];
 *
 *   $adapter = new plugin\oauth\app\provider\Vkontakte($config);
 *
 *   try {
 *       if (!$adapter->isConnected()) {
 *           $adapter->authenticate();
 *       }
 *
 *       $userProfile = $adapter->getUserProfile();
 *   } catch (\Exception $e) {
 *       print $e->getMessage() ;
 *   }
 */
class Vkontakte extends OAuth2
{
    const API_VERSION = '5.95';

    const URL = 'https://vk.com/';

    /**
     * {@inheritdoc}
     */
    protected $apiBaseUrl = 'https://api.vk.com/method/';

    /**
     * {@inheritdoc}
     */
    protected $authorizeUrl = 'https://api.vk.com/oauth/authorize';

    /**
     * {@inheritdoc}
     */
    protected $accessTokenUrl = 'https://api.vk.com/oauth/token';

    /**
     * {@inheritdoc}
     */
    protected $scope = 'email,offline';

    /**
     * {@inheritdoc}
     */
    protected $apiDocumentation = 'https://dev.vk.com';

    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
        parent::initialize();

        // The VK API requires version and access_token from authenticated users
        // for each endpoint.
        $accessToken = $this->getStoredData($this->accessTokenName);
        $this->apiRequestParameters[$this->accessTokenName] = $accessToken;
        $this->apiRequestParameters['v'] = static::API_VERSION;
    }

    /**
     * {@inheritdoc}
     */
    protected function validateAccessTokenExchange($response)
    {
        $data = parent::validateAccessTokenExchange($response);

        // Need to store email for later use.
        $this->storeData('email', $data->get('email'));
    }

    /**
     * {@inheritdoc}
     */
    public function hasAccessTokenExpired($time = null)
    {
        if ($time === null) {
            $time = time();
        }

        // If we are using offline scope, $expired will be false.
        $expired = $this->getStoredData('expires_in')
            ? $this->getStoredData('expires_at') <= $time
            : false;

        return $expired;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserProfile()
    {
        $photoField = 'photo_' . ($this->config->get('photo_size') ?: 'max_orig');

        $response = $this->apiRequest('users.get', 'GET', [
            'fields' => 'screen_name,sex,education,bdate,has_photo,' . $photoField,
        ]);


        if (property_exists($response, 'error')) {
            throw new UnexpectedApiResponseException($response->error->error_msg);
        }

        $data = new Collection($response->response[0]);

        if (!$data->exists('id')) {
            throw new UnexpectedApiResponseException('API Provider вернул неожиданный ответ');
        }

        $userProfile = new Profile();

        $userProfile->identifier = $data->get('id');
        $userProfile->email = $this->getStoredData('email');
        $userProfile->firstName = $data->get('first_name');
        $userProfile->lastName = $data->get('last_name');
        $userProfile->displayName = $data->get('screen_name');
        $userProfile->photoURL = $data->get('has_photo') === 1 ? $data->get($photoField) : '';

        // Handle b-date.
        if ($data->get('bdate')) {
            $bday = explode('.', $data->get('bdate'));
            $userProfile->birthDay = (int)$bday[0];
            $userProfile->birthMonth = (int)$bday[1];
            $userProfile->birthYear = (int)$bday[2];
        }

        $userProfile->data = [
            'education' => $data->get('education'),
        ];

        $screen_name = static::URL . ($data->get('screen_name') ?: 'id' . $data->get('id'));
        $userProfile->profileURL = $screen_name;

        switch ($data->get('sex')) {
            case 1:
                $userProfile->gender = 'female';
                break;

            case 2:
                $userProfile->gender = 'male';
                break;
        }

        return $userProfile;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserContacts()
    {
        $response = $this->apiRequest('friends.get', 'GET', [
            'fields' => 'uid,name,photo_200_orig',
        ]);

        $data = new Collection($response);
        if (!$data->exists('response')) {
            throw new UnexpectedApiResponseException('API Provider вернул неожиданный ответ');
        }

        $contacts = [];
        if (!$data->filter('response')->filter('items')->isEmpty()) {
            foreach ($data->filter('response')->filter('items')->toArray() as $item) {
                $contacts[] = $this->fetchUserContact($item);
            }
        }

        return $contacts;
    }

    /**
     * Parse the user contact.
     *
     * @param array $item
     *
     * @return \plugin\oauth\app\entity\Contact
     */
    protected function fetchUserContact($item)
    {
        $userContact = new entity\Contact();
        $data = new Collection($item);

        $userContact->identifier = $data->get('id');
        $userContact->displayName = sprintf('%s %s', $data->get('first_name'), $data->get('last_name'));
        $userContact->profileURL = static::URL . ($data->get('screen_name') ?: 'id' . $data->get('id'));
        $userContact->photoURL = $data->get('photo_200_orig');

        return $userContact;
    }
}
