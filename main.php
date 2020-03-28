<?php

/**
 * @author andrew-ld <andrew-ld@protonmail.com>
 * @copyright 2020 andrew-ld <andrew-ld@protonmail.com>
 * @license https://opensource.org/licenses/AGPL-3.0 AGPLv3
 *
 * @link https://github.com/andrew-ld/TokenSnitch
 */

declare(strict_types = 1);

use Amp\Loop;
use TokenSnitch\TokenSnitchServer;

require __DIR__ . '/vendor/autoload.php';

Loop::run(function () {
   $server = new TokenSnitchServer();
   yield $server->start();
});
