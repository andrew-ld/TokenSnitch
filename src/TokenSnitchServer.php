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
use Amp\ByteStream\ResourceOutputStream;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\Server;
use Amp\Http\Status;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\SocketException;
use Monolog\Logger;
use PhpOption\None;

use function Amp\call;
use function Amp\Socket\listen;

class TokenSnitchServer {

    /**
     * @var TelegramApi
     */
    private $api = null;

    /**
     * @var Config
     */
    private $config = null;

    /**
     * @var TokenProcessHub
     */
    private $hub = null;

    /**
     * TokenSnitchServer constructor.
     */
    public function __construct()
    {
        $this->config = new Config();
        $this->api = new TelegramApi($this->config);
        $this->hub = new TokenProcessHub($this->config);
    }

    /**
     * @param string $file_id
     * @return Promise<None>
     */
    private function checkFile(string $file_id): Promise {
        return call(function () use ($file_id) {
            $info = yield $this->api->request('getFile', ['file_id' => $file_id]);

            if (!$info['ok']) {
                return;
            }

            $buffer = '';
            $matches = [];

            /** @var $file Payload */
            $file = yield $this->api->getFileStream($info['result']['file_path']);

            while (null !== $chunk = yield $file->read()) {
                $buffer .= $chunk;
                $bytes = strlen($buffer);

                /** @var $partial_matches array<string> */
                $partial_matches = yield $this->hub->processBuffer($buffer);

                $matches = array_merge($partial_matches, $matches);
                $buffer = substr($buffer, $bytes - ($bytes % $this->config->getRegexMaxSize()) - 1);
            }

            if (empty($matches)) {
                return;
            }

            yield $this->api->request('sendMessage', [
                'text' => var_export($matches, true),
                'chat_id' => $this->config->getChannel()
            ]);
        });
    }

    /**
     * @throws SocketException
     * @return Promise<None>
     */
    public function start(): Promise {
        $servers = [
            listen($this->config->getListenAddr())
        ];

        $logHandler = new StreamHandler(new ResourceOutputStream(STDOUT));
        $logHandler->setFormatter(new ConsoleFormatter);
        $logger = new Logger('server');
        $logger->pushHandler($logHandler);

        $router = new Router();

        $router->addRoute('GET', '/{file_id}', new CallableRequestHandler(function (Request $request) {
            return call(function () use ($request) {
                yield $request->getClient()->stop(0);
                $args = $request->getAttribute(Router::class);
                yield $this->checkFile($args['file_id']);
                return new Response(Status::OK);
            });
        }));

        $server = new Server($servers, $router, $logger);

        return call(function () use ($server) {
            yield $this->hub->start($this->config->getHubProcesses());
            yield $server->start();

            Loop::onSignal(SIGINT, function (string $watcherId) use ($server) {
                Loop::cancel($watcherId);
                yield $server->stop();
            });
        });
    }
}
