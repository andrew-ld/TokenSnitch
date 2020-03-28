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
use Amp\Ipc\IpcServer;
use Amp\Ipc\Sync\ChannelledSocket;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Process\Process;
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
    private $api;

    /**
     * @var Config
     */
    private $config;

    /**
     * TokenSnitchServer constructor.
     */
    public function __construct()
    {
        $this->config = new Config();
        $this->api = new TelegramApi($this->config);
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

            $ipc_path = sys_get_temp_dir() . '/' . uniqid('ipc_', true);
            $ipc_server = new IpcServer($ipc_path);

            /** @var $process Process */
            $process = new Process([PHP_BINARY, __DIR__ . '/token-process.php', $ipc_path]);
            yield $process->start();

            /** @var $ipc_socket ChannelledSocket */
            $ipc_socket = yield $ipc_server->accept();
            yield $ipc_socket->send($this->config);

            /** @var $file Payload */
            $file = yield $this->api->getFileStream($info['result']['file_path']);

            while (null !== $chunk = yield $file->read()) {
                $buffer .= $chunk;
                $bytes = strlen($buffer);

                yield $ipc_socket->send($buffer);
                $partial_matches = yield $ipc_socket->receive();

                $matches = array_merge($partial_matches[0], $matches);
                $buffer = substr($buffer, $bytes - ($bytes % $this->config->getRegexMaxSize()) - 1);
            }

            yield $ipc_socket->disconnect();
            yield $process->join();
            $ipc_server->close();

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
     * @return Server
     */
    public function start(): Server {
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

        return new Server($servers, $router, $logger);
    }
}
