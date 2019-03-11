<?php

namespace Esia;

use Esia\Exceptions\AbstractEsiaException;
use Esia\Exceptions\ForbiddenException;
use Esia\Exceptions\RequestFailException;
use Esia\Signer\Exceptions\CannotGenerateRandomIntException;
use Esia\Signer\Exceptions\SignFailException;
use Esia\Http\GuzzleHttpClient;
use Esia\Signer\SignerInterface;
use Esia\Signer\SignerPKCS7;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Class OpenId
 */
class OpenId
{
    use LoggerAwareTrait;

    /**
     * @var SignerInterface
     */
    private $signer;

    /**
     * Http Client
     *
     * @var ClientInterface
     */
    private $client;

    /**
     * Config
     *
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->client = new GuzzleHttpClient(new Client());
        $this->logger = new NullLogger();
        $this->signer = new SignerPKCS7(
            $config->getCertPath(),
            $config->getPrivateKeyPath(),
            $config->getPrivateKeyPassword(),
            $config->getTmpPath()
        );
    }

    /**
     * @param SignerInterface $signer
     */
    public function setSigner(SignerInterface $signer)
    {
        $this->signer = $signer;
    }

    /**
     * Get config
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Return an url for authentication
     *
     * ```php
     *     <a href="<?=$esia->buildUrl()?>">Login</a>
     * ```
     *
     * @return string|false
     * @throws SignFailException
     */
    public function buildUrl()
    {
        $timestamp = $this->getTimeStamp();
        $state = $this->buildState();
        $message = $this->config->getScopeString()
            . $timestamp
            . $this->config->getClientId()
            . $state;

        $clientSecret = $this->signer->sign($message);

        $url = $this->config->getCodeUrl() . '?%s';

        $params = [
            'client_id' => $this->config->getClientId(),
            'client_secret' => $clientSecret,
            'redirect_uri' => $this->config->getRedirectUrl(),
            'scope' => $this->config->getScopeString(),
            'response_type' => $this->config->getResponseType(),
            'state' => $state,
            'access_type' => $this->config->getAccessType(),
            'timestamp' => $timestamp,
        ];

        $request = http_build_query($params);

        return sprintf($url, $request);
    }

    /**
     * Method collect a token with given code
     *
     * @param $code
     * @return string
     * @throws SignFailException
     * @throws AbstractEsiaException
     */
    public function getToken($code)
    {
        $timestamp = $this->getTimeStamp();
        $state = $this->buildState();

        $clientSecret = $this->signer->sign(
            $this->config->getScopeString()
            . $timestamp
            . $this->config->getClientId()
            . $state
        );

        $body = [
            'client_id' => $this->config->getClientId(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_secret' => $clientSecret,
            'state' => $state,
            'redirect_uri' => $this->config->getRedirectUrl(),
            'scope' => $this->config->getScopeString(),
            'timestamp' => $timestamp,
            'token_type' => 'Bearer',
            'refresh_token' => $state,
        ];

        $payload = $this->sendRequest(
            new Request(
                'POST',
                $this->config->getTokenUrl(),
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                http_build_query($body)
            )
        );

        $this->logger->debug('Payload: ', $payload);

        $token = $payload['access_token'];
        $refreshToken = $payload['refresh_token'];
        $expiresIn = $payload['expires_in'];

        $this->config->setToken($token);
        $this->config->setRefreshToken($refreshToken);
        $this->config->setTokenExpiresIn($expiresIn);

        # get object id from token
        $chunks = explode('.', $token);
        $payload = json_decode($this->base64UrlSafeDecode($chunks[1]), true);
        $this->config->setOid($payload['urn:esia:sbj_id']);

        return $token;
    }

    /**
     * Get a new token using the refresh token
     * The method is quite similar to the getToken(), except 2 new parameters:
     * refresh_token = $refreshToken
     * grant_type = 'refresh_token'
     * http://minsvyaz.ru/uploaded/presentations/mr-esia-237.pdf
     *
     * @return string
     *
     * @throws SignFailException
     * @throws AbstractEsiaException
     */
    public function refreshToken() {
        $timestamp = $this->getTimeStamp();
        $state = $this->buildState();

        $clientSecret = $this->signer->sign(
            $this->config->getScopeString()
            . $timestamp
            . $this->config->getClientId()
            . $state
        );

        $body = [
            'client_id' => $this->config->getClientId(),
            'refresh_token' => $this->config->getRefreshToken(),
            'grant_type' => 'refresh_token',
            'client_secret' => $clientSecret,
            'state' => $state,
            'redirect_uri' => $this->config->getRedirectUrl(),
            'scope' => $this->config->getScopeString(),
            'timestamp' => $timestamp,
            'token_type' => 'Bearer',
        ];

        $payload = $this->sendRequest(
            new Request(
                'POST',
                $this->config->getTokenUrl(),
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                http_build_query($body)
            )
        );

        $this->logger->debug('Payload: ', $payload);

        $token = $payload['access_token'];
        $this->config->setToken($token);

        // previous refresh token is consumed upon refreshing, storing the new one
        $refreshToken = $payload['refresh_token'];
        $this->config->setRefreshToken($refreshToken);

        $expiresIn = $payload['expires_in'];
        $this->config->setTokenExpiresIn($expiresIn);

        # get object id from token
        $chunks = explode('.', $token);
        $payload = json_decode($this->base64UrlSafeDecode($chunks[1]), true);
        $this->config->setOid($payload['urn:esia:sbj_id']);

        return $token;
    }

    /**
     * Fetch person info from current person
     *
     * You must collect token person before
     * calling this method
     *
     * @return null|array
     * @throws AbstractEsiaException
     */
    public function getPersonInfo()
    {
        $url = $this->config->getPersonUrl();

        return $this->sendRequest(new Request('GET', $url));
    }

    /**
     * Fetch contact info about current person
     *
     * You must collect token person before
     * calling this method
     *
     * @return array
     * @throws Exceptions\InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function getContactInfo()
    {
        $url = $this->config->getPersonUrl() . '/ctts';
        $payload = $this->sendRequest(new Request('GET', $url));

        if ($payload && $payload['size'] > 0) {
            return $this->collectArrayElements($payload['elements']);
        }

        return $payload;
    }


    /**
     * Fetch address from current person
     *
     * You must collect token person before
     * calling this method
     *
     * @return array
     * @throws Exceptions\InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function getAddressInfo()
    {
        $url = $this->config->getPersonUrl() . '/addrs';
        $payload = $this->sendRequest(new Request('GET', $url));

        if ($payload['size'] > 0) {
            return $this->collectArrayElements($payload['elements']);
        }

        return $payload;
    }

    /**
     * Fetch documents info about current person
     *
     * You must collect token person before
     * calling this method
     *
     * @return array
     * @throws Exceptions\InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function getDocInfo()
    {
        $url = $this->config->getPersonUrl() . '/docs';

        $payload = $this->sendRequest(new Request('GET', $url));

        if ($payload && $payload['size'] > 0) {
            return $this->collectArrayElements($payload['elements']);
        }

        return $payload;
    }

    /**
     * This method can iterate on each element
     * and fetch entities from esia by url
     *
     *
     * @param $elements array of urls
     * @return array
     * @throws AbstractEsiaException
     */
    private function collectArrayElements($elements)
    {
        $result = [];
        foreach ($elements as $elementUrl) {
            $elementPayload = $this->sendRequest(new Request('GET', $elementUrl));

            if ($elementPayload) {
                $result[] = $elementPayload;
            }

        }

        return $result;
    }

    /**
     * @param RequestInterface $request
     * @return array
     * @throws AbstractEsiaException
     */
    private function sendRequest(RequestInterface $request)
    {
        try {
            if ($this->config->getToken()) {
                $request = $request->withHeader('Authorization', 'Bearer ' . $this->config->getToken());
            }
            $response = $this->client->sendRequest($request);
            $responseBody = json_decode($response->getBody()->getContents(), true);

            if (!is_array($responseBody)) {
                throw new \RuntimeException(
                    sprintf(
                        'Cannot decode response body. JSON error (%d): %s',
                        json_last_error(),
                        json_last_error_msg()
                    )
                );
            }

            return $responseBody;
        } catch (ClientException $e) {
            $this->logger->error('Request was failed', ['exception' => $e]);
            $prev = $e->getPrevious();

            // Only for Guzzle
            if ($prev instanceof BadResponseException
                && $prev->getResponse() !== null
                && $prev->getResponse()->getStatusCode() === 403
            ) {
                throw new ForbiddenException('Request is forbidden', 0, $e);
            }

            throw new RequestFailException('Request is failed', 0, $e);
        } catch (\RuntimeException $e) {
            $this->logger->error('Cannot read body', ['exception' => $e]);
            throw new RequestFailException('Cannot read body', 0, $e);
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Wrong header', ['exception' => $e]);
            throw new RequestFailException('Wrong header', 0, $e);
        }
    }

    /**
     * @return string
     */
    private function getTimeStamp()
    {
        return date('Y.m.d H:i:s O');
    }


    /**
     * Generate state with uuid
     *
     * @return string
     * @throws SignFailException
     */
    private function buildState()
    {
        try {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff)
            );
        } catch (\Exception $e) {
            throw new CannotGenerateRandomIntException('Cannot generate random integer', $e);
        }
    }

    /**
     * Url safe for base64
     *
     * @param $string
     * @return string
     */
    private function base64UrlSafeDecode($string)
    {
        $base64 = strtr($string, '-_', '+/');

        return base64_decode($base64);
    }
}
