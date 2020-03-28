<?php

/**
 * @author andrew-ld <andrew-ld@protonmail.com>
 * @copyright 2020 andrew-ld <andrew-ld@protonmail.com>
 * @license https://opensource.org/licenses/AGPL-3.0 AGPLv3
 *
 * @link https://github.com/andrew-ld/TokenSnitch
 */

namespace TokenSnitch;

use Amp\ByteStream\Payload;
use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;

use function Amp\call;

class TelegramApi {

    /**
     * @var string
     */
    private $api = null;

    /**
     * @var HttpClient
     */
    private $client = null;

    /**
     * @var string
     */
    private $file_api = null;

    /**
     * TelegramApi constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->api = sprintf($config->getTelegramApi(), $config->getToken()) . '/%s';
        $this->file_api = sprintf($config->getTelegramFileApi(), $config->getToken()) . '/%s';
        $this->client = HttpClientBuilder::buildDefault();
    }

    /**
     * @param string $method
     * @param array $args
     * @return Promise<array>
     */
    public function request(string $method, array $args = []): Promise {
        return call(function () use ($method, $args) {
            $body = new FormBody();
            $body->addFields($args);

            $request = new Request(sprintf($this->api, $method), 'POST');
            $request->setBody($body);

            /** @var $response Response */
            $response = yield $this->client->request($request);

            return json_decode(yield $response->getBody()->buffer(), true);
        });
    }

    /**
     * @param string $file_id
     * @return Promise<Payload>
     */
    public function getFileStream(string $file_id): Promise {
        return call(function () use ($file_id) {
            $request = new Request(sprintf($this->file_api, $file_id), 'GET');
            $request->setBodySizeLimit(1500 * 1024 * 1024);
            $request->setTransferTimeout(240 * 1000);

            /** @var $response Response */
            $response = yield $this->client->request($request);

            return $response->getBody();
        });
    }
}
