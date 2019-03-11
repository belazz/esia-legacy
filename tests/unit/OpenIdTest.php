<?php

namespace tests\unit;

use Codeception\Test\Unit;
use Esia\Config;
use Esia\Http\GuzzleHttpClient;
use Esia\Signer\Exceptions\SignFailException;
use Esia\OpenId;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class OpenIdTest extends Unit
{
    public $config;

    /**
     * @var OpenId
     */
    public $openId;

    /**
     * @throws \Esia\Exceptions\InvalidConfigurationException
     */
    public function setUp()
    {
        $this->config = [
            'clientId' => 'INSP03211',
            'redirectUrl' => 'http://my-site.com/response.php',
            'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
            'privateKeyPath' => codecept_data_dir('server.key'),
            'privateKeyPassword' => 'test',
            'certPath' => codecept_data_dir('server.crt'),
            'tmpPath' => codecept_log_dir(),
        ];

        $config = new Config($this->config);

        $this->openId = new OpenId($config);
    }

    /**
     * @throws SignFailException
     * @throws \Esia\Exceptions\AbstractEsiaException
     * @throws \Esia\Exceptions\InvalidConfigurationException
     */
    public function testGetToken(): void
    {
        $config = new Config($this->config);

        $oid = '123';
        $oidBase64 = base64_encode('{ "urn:esia:sbj_id" : ' . $oid . '}');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{ "access_token": "test.' . $oidBase64 . '.test"}'),
        ]);
        $openId = new OpenId($config, $client);

        $token = $openId->getToken('test');
        $this->assertNotEmpty($token);
        $this->assertSame($oid, $openId->getConfig()->getOid());
    }

    /**
     * @throws \Esia\Exceptions\InvalidConfigurationException
     * @throws \Esia\Exceptions\AbstractEsiaException
     */
    public function testGetPersonInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"username": "test"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getPersonInfo();
        $this->assertNotEmpty($info);
        $this->assertSame(['username' => 'test'], $info);
    }

    /**
     * @throws \Esia\Exceptions\InvalidConfigurationException
     * @throws \Esia\Exceptions\AbstractEsiaException
     */
    public function testGetContactInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 2, "elements": ["phone", "email"]}'),
            new Response(200, [], '{"phone": "555 555 555"}'),
            new Response(200, [], '{"email": "test@gmail.com"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getContactInfo();
        $this->assertNotEmpty($info);
        $this->assertSame([['phone' => '555 555 555'], ['email' => 'test@gmail.com']], $info);
    }

    /**
     * @throws \Esia\Exceptions\InvalidConfigurationException
     * @throws \Esia\Exceptions\AbstractEsiaException
     */
    public function testGetAddressInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 2, "elements": ["phone", "email"]}'),
            new Response(200, [], '{"phone": "555 555 555"}'),
            new Response(200, [], '{"email": "test@gmail.com"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getAddressInfo();
        $this->assertNotEmpty($info);
        $this->assertSame([['phone' => '555 555 555'], ['email' => 'test@gmail.com']], $info);
    }

    /**
     * @throws \Esia\Exceptions\InvalidConfigurationException
     * @throws \Esia\Exceptions\AbstractEsiaException
     */
    public function testGetDocInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 2, "elements": ["phone", "email"]}'),
            new Response(200, [], '{"phone": "555 555 555"}'),
            new Response(200, [], '{"email": "test@gmail.com"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getDocInfo();
        $this->assertNotEmpty($info);
        $this->assertSame([['phone' => '555 555 555'], ['email' => 'test@gmail.com']], $info);
    }

    /**
     * Client with prepared responses
     *
     * @param array $responses
     * @return ClientInterface
     */
    private function buildClientWithResponses(array $responses): ClientInterface
    {
        $mock = new MockHandler($responses);

        $handler = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handler]);

        return new GuzzleHttpClient($guzzleClient);
    }
}
