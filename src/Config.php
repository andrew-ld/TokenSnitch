<?php

/**
 * @author andrew-ld <andrew-ld@protonmail.com>
 * @copyright 2020 andrew-ld <andrew-ld@protonmail.com>
 * @license https://opensource.org/licenses/AGPL-3.0 AGPLv3
 *
 * @link https://github.com/andrew-ld/TokenSnitch
 */

namespace TokenSnitch;

use Dotenv\Dotenv;

class Config {

    /**
     * @var string
     */
    private $TOKEN = null;

    /**
     * @var integer
     */
    private $CHANNEL_ID = null;

    /**
     * @var integer
     */
    private $REGEX_MAX_MATCH_SIZE = 47;

    /**
     * @var string
     */
    private $TELEGRAM_API = 'https://api.telegram.org/bot%s';

    /**
     * @var string
     */
    private $TELEGRAM_FILE_API = 'https://api.telegram.org/file/bot%s';

    /**
     * @var string
     */
    private $TOKEN_SEARCH = '/([0-9]{7,10}):AA([0-9a-zA-Z_-]{32,34})/m';

    /**
     * @var string
     */
    private $LISTEN_ADDR = null;

    /**
     * Config constructor.
     */
    public function __construct()
    {
        $dotenv = Dotenv::createMutable(getcwd());
        $dotenv->load();

        $dotenv
            ->required('channel_id')
            ->notEmpty()
        ;

        $dotenv
            ->required('token')
            ->notEmpty()
        ;

        $dotenv
            ->required('listen_addr')
            ->notEmpty()
        ;

        $this->TOKEN = $_ENV['token'];
        $this->CHANNEL_ID = (int) $_ENV['channel_id'];
        $this->LISTEN_ADDR = $_ENV['listen_addr'];
    }

    /**
     * @return string
     */
    public function getToken(): string {
        return $this->TOKEN;
    }

    /**
     * @return string
     */
    public function getListenAddr(): string {
        return $this->LISTEN_ADDR;
    }

    /**
     * @return integer
     */
    public function getChannel(): int {
        return $this->CHANNEL_ID;
    }

    /**
     * @return string
     */
    public function getTokenRegex(): string {
        return $this->TOKEN_SEARCH;
    }

    /**
     * @return integer
     */
    public function getRegexMaxSize(): int {
        return $this->REGEX_MAX_MATCH_SIZE;
    }

    /**
     * @return string
     */
    public function getTelegramApi(): string {
        return $this->TELEGRAM_API;
    }

    /**
     * @return string
     */
    public function getTelegramFileApi(): string {
        return $this->TELEGRAM_FILE_API;
    }
}
