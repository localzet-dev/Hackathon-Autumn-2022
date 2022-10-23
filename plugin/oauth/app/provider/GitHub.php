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

use plugin\oauth\app\adapter\OAuth2;
use plugin\oauth\app\exception\UnexpectedApiResponseException;

use plugin\oauth\app\entity;
use support\Collection;

/**
 * Github OAuth2 provider adapter.
 */
class GitHub extends OAuth2
{
    /**
     * {@inheritdoc}
     */
    protected $scope = 'user:email';

    /**
     * {@inheritdoc}
     */
    protected $apiBaseUrl = 'https://api.github.com/';

    /**
     * {@inheritdoc}
     */
    protected $authorizeUrl = 'https://github.com/login/oauth/authorize';

    /**
     * {@inheritdoc}
     */
    protected $accessTokenUrl = 'https://github.com/login/oauth/access_token';

    /**
     * {@inheritdoc}
     */
    protected $apiDocumentation = 'https://developer.github.com/v3/oauth/';

    /**
     * {@inheritdoc}
     */
    public function getUserProfile()
    {
        $response = $this->apiRequest('user');

        $data = new Collection($response);

        if (!$data->exists('id')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        $userProfile = new entity\Profile();

        $userProfile->identifier = $data->get('id');
        $userProfile->displayName = $data->get('name');
        $userProfile->description = $data->get('bio');
        $userProfile->photoURL = $data->get('avatar_url');
        $userProfile->profileURL = $data->get('html_url');
        $userProfile->email = $data->get('email');
        $userProfile->webSiteURL = $data->get('blog');
        $userProfile->region = $data->get('location');

        $userProfile->displayName = $userProfile->displayName ?: $data->get('login');

        if (empty($userProfile->email) && strpos($this->scope, 'user:email') !== false) {
            try {
                // user email is not mandatory so keep it quite.
                $userProfile = $this->requestUserEmail($userProfile);
            } catch (\Exception $e) {
            }
        }

        return $userProfile;
    }

    /**
     * Request connected user email
     *
     * https://developer.github.com/v3/users/emails/
     * @param entity\Profile $userProfile
     *
     * @return entity\Profile
     *
     * @throws \Exception
     */
    protected function requestUserEmail(entity\Profile $userProfile)
    {
        $response = $this->apiRequest('user/emails');

        foreach ($response as $idx => $item) {
            if (!empty($item->primary) && $item->primary == 1) {
                $userProfile->email = $item->email;

                if (!empty($item->verified) && $item->verified == 1) {
                    $userProfile->emailVerified = $userProfile->email;
                }

                break;
            }
        }

        return $userProfile;
    }
}
