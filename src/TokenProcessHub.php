<?php

/**
 * @author andrew-ld <andrew-ld@protonmail.com>
 * @copyright 2020 andrew-ld <andrew-ld@protonmail.com>
 * @license https://opensource.org/licenses/AGPL-3.0 AGPLv3
 *
 * @link https://github.com/andrew-ld/TokenSnitch
 */

namespace TokenSnitch;

use Amp\Ipc\IpcServer;
use Amp\Ipc\Sync\ChannelledSocket;
use Amp\Process\Process;
use Amp\Promise;
use Amp\Sync\LocalSemaphore;
use Amp\Sync\Lock;
use Error;
use RuntimeException;
use PhpOption\None;

use function Amp\call;

class TokenProcessHub {

    /**
     * @var IpcServer
     */
    private $ipc_server = null;

    /**
     * @var string
     */
    private $ipc_path = null;

    /**
     * @var array<array<Process, ChannelledSocket, boolean>>
     */
    private $processes = null;

    /**
     * @var Config
     */
    private $config = null;

    /**
     * @var LocalSemaphore
     */
    private $semaphore = null;

    /**
     * @var boolean
     */
    private $initialized = null;

    /**
     * TokenProcessHub constructor.
     * @param Config $config
     */
    public function __construct(Config $config) {
        $this->ipc_path = sys_get_temp_dir() . '/' . uniqid('ipc-', true);
        $this->ipc_server = new IpcServer($this->ipc_path);
        $this->processes = [];
        $this->config = $config;
        $this->initialized = false;
    }

    /**
     * @param string $buffer
     * @throws Error
     * @return Promise<array<string>>
     */
    public function processBuffer(string $buffer): Promise {
        if (!$this->initialized) {
            throw new Error('should initialize process hub');
        }

        return call(function () use ($buffer) {
            /** @var $lock Lock */
            $lock = yield $this->semaphore->acquire();
            $result = null;

            for ($i = 0; $i < sizeof($this->processes); $i++) {
                /** @var $process Process */
                /** @var $ipc ChannelledSocket */
                /** @var $in_use boolean */
                list($process, $ipc, $in_use) = $this->processes[$i];

                if (!$process->isRunning() || !$ipc) {
                    continue;
                }

                if (!$in_use) {
                    $this->processes[$i][2] = true;

                    yield $ipc->send($buffer);
                    $result = yield $ipc->receive();

                    $this->processes[$i][2] = false;
                    break;
                }
            }

            $lock->release();

            if (is_null($result)) {
                throw new RuntimeException('all processes die');
            }

            return $result[0];
        });
    }

    /**
     * @param int $n
     * @return Promise<None>
     */
    public function start(int $n): Promise {
        if ($n < 1) {
            throw new Error('number of processes must be greater than 0');
        }

        if ($this->initialized) {
            throw new Error('process hub already initialized');
        }

        return call(function () use ($n) {
            for ($i = 0; $i < $n; $i++) {
                $process = new Process([PHP_BINARY, __DIR__ . '/token-process.php', $this->ipc_path]);
                yield $process->start();

                /** @var $ipc_socket ChannelledSocket */
                $ipc_socket = yield $this->ipc_server->accept();
                yield $ipc_socket->send($this->config);

                $this->processes[] = [$process, $ipc_socket, false];
            }

            $this->semaphore = new LocalSemaphore(sizeof($this->processes));
            $this->initialized = true;
        });
    }
}
