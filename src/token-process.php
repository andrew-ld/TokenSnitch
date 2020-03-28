<?php

/**
 * @author andrew-ld <andrew-ld@protonmail.com>
 * @copyright 2020 andrew-ld <andrew-ld@protonmail.com>
 * @license https://opensource.org/licenses/AGPL-3.0 AGPLv3
 *
 * @link https://github.com/andrew-ld/TokenSnitch
 */

declare(strict_types = 1);

namespace TokenSnitch;

use Amp\Ipc\Sync\ChannelledSocket;
use Amp\Loop;

use function Amp\asyncCall;
use function Amp\Ipc\connect;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(static function () use ($argv) {
    $clientHandler = function (ChannelledSocket $socket) {
        /** @var $config Config */
        $config = null;

        while (null !== $payload = yield $socket->receive()) {
            if ($payload instanceof Config) {
                $config = $payload;
                continue;
            }

            if (is_string($payload) && $config instanceof Config) {
                $matches = [];
                preg_match_all($config->getTokenRegex(), $payload, $matches);
                yield $socket->send($matches);
                continue;
            }
        }

        yield $socket->disconnect();
    };

    $channel = yield connect($argv[1]);
    asyncCall($clientHandler, $channel);
});
