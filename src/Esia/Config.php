<?php

namespace Esia;

use Esia\Exceptions\InvalidConfigurationException;

class Config
{
    private $clientId;
    private $redirectUrl;
    private $privateKeyPath;
    private $certPath;

    private $useCli = false;

    private $portalUrl = 'http://esia-portal1.test.gosuslugi.ru/';
    private $tokenUrlPath = 'aas/oauth2/te';
    private $codeUrlPath = 'aas/oauth2/ac';
    private $personUrlPath = 'rs/prns';
    private $privateKeyPassword = '';

    /**
     * @var string[]
     */
    private $scope = [
        'fullname',
        'birthdate',
        'gender',
        'email',
        'mobile',
        'id_doc',
        'snils',
        'inn',
    ];

    private $tmpPath = '/var/tmp';

    private $responseType = 'code';
    private $accessType = 'offline';

    private $token = '';
    private $tokenExpiresIn = '';
    private $oid = '';
    private $refreshToken = '';

    /**
     * Config constructor.
     *
     * @param array $config
     * @throws InvalidConfigurationException
     */
    public function __construct($config = [])
    {
        // Required params
        $this->clientId = isset($config['clientId']) ? $config['clientId'] : $this->clientId;
        if (!$this->clientId) {
            throw new InvalidConfigurationException('Please provide clientId');
        }

        $this->redirectUrl = isset($config['redirectUrl']) ? $config['redirectUrl'] : $this->redirectUrl;
        if (!$this->redirectUrl) {
            throw new InvalidConfigurationException('Please provide redirectUrl');
        }

        $this->privateKeyPath = isset($config['privateKeyPath']) ? $config['privateKeyPath'] : $this->privateKeyPath;
        if (!$this->privateKeyPath) {
            throw new InvalidConfigurationException('Please provide privateKeyPath');
        }
        $this->certPath = isset($config['certPath']) ? $config['certPath'] : $this->certPath;
        if (!$this->certPath) {
            throw new InvalidConfigurationException('Please provide certPath');
        }

        $this->useCli = isset($config['useCli']) ? $config['useCli'] : $this->useCli;

        $this->portalUrl = isset($config['portalUrl']) ? $config['portalUrl'] : $this->portalUrl;
        $this->tokenUrlPath = isset($config['tokenUrlPath']) ? $config['tokenUrlPath'] : $this->tokenUrlPath;
        $this->codeUrlPath = isset($config['codeUrlPath']) ? $config['codeUrlPath'] : $this->codeUrlPath;
        $this->personUrlPath = isset($config['personUrlPath']) ? $config['personUrlPath'] : $this->personUrlPath;
        $this->privateKeyPassword = isset($config['privateKeyPassword']) ? $config['privateKeyPassword'] : $this->privateKeyPassword;
        $this->oid = isset($config['oid']) ? $config['oid'] : $this->oid;
        $this->scope = isset($config['scope']) ? $config['scope'] : $this->scope;
        if (!is_array($this->scope)) {
            throw new InvalidConfigurationException('scope must be array of strings');
        }

        $this->responseType = isset($config['responseType']) ? $config['responseType'] : $this->responseType;
        $this->accessType = isset($config['accessType']) ? $config['accessType'] : $this->accessType;
        $this->tmpPath = isset($config['tmpPath']) ? $config['tmpPath'] : $this->tmpPath;
        $this->token = isset($config['token']) ? $config['token'] : $this->token;
        $this->refreshToken = isset($config['refreshToken']) ? $config['refreshToken'] : $this->refreshToken;
        $this->tokenExpiresIn = isset($config['tokenExpiresIn']) ? $config['tokenExpiresIn'] : $this->tokenExpiresIn;
    }

    public function getPortalUrl()
    {
        return $this->portalUrl;
    }

    public function getPrivateKeyPath()
    {
        return $this->privateKeyPath;
    }

    public function getPrivateKeyPassword()
    {
        return $this->privateKeyPassword;
    }

    public function getCertPath()
    {
        return $this->certPath;
    }

    public function getOid()
    {
        return $this->oid;
    }

    public function setOid($oid)
    {
        $this->oid = $oid;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function getScopeString()
    {
        return implode(' ', $this->scope);
    }

    public function getResponseType()
    {
        return $this->responseType;
    }

    public function getAccessType()
    {
        return $this->accessType;
    }

    public function getTmpPath()
    {
        return $this->tmpPath;
    }

    public function getToken()
    {
        return $this->token;
    }

    /*
     * Returns expiration time in seconds
     */
    public function getTokenExpiresIn() {
        return $this->tokenExpiresIn;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function setTokenExpiresIn($seconds) {
        $this->tokenExpiresIn = $seconds;
    }


    public function getRefreshToken() {
        return $this->refreshToken;
    }

    public function setRefreshToken($refreshToken) {
        $this->refreshToken = $refreshToken;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * Return an url for request to get an access token
     */
    public function getTokenUrl()
    {
        return $this->portalUrl . $this->tokenUrlPath;
    }

    /**
     * Return an url for request to get an authorization code
     */
    public function getCodeUrl()
    {
        return $this->portalUrl . $this->codeUrlPath;
    }

    /**
     * @return string
     * @throws InvalidConfigurationException
     */
    public function getPersonUrl()
    {
        if (!$this->oid) {
            throw new InvalidConfigurationException('Please provide oid');
        }
        return $this->portalUrl . $this->personUrlPath . '/' . $this->oid;
    }

    /**
     * Return a param telling us whether we should use CliSignerPKCS7 for signing requests.
     * @return bool
     */
    public function getUseCli() {
        return $this->useCli;
    }
}
