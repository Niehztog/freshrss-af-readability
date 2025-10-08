<?php

declare(strict_types=1);

namespace Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection;

use Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection\Exception\InvalidOptionException;

class Options
{
    public const LIST_WHITELIST = 'whitelist';
    public const LIST_BLACKLIST = 'blacklist';

    public const TYPE_IP = 'ip';
    public const TYPE_PORT = 'port';
    public const TYPE_DOMAIN = 'domain';
    public const TYPE_SCHEME = 'scheme';

    private const AVAILABLE_TYPE = [
        self::TYPE_IP,
        self::TYPE_PORT,
        self::TYPE_DOMAIN,
        self::TYPE_SCHEME,
    ];
    /**
     * Allow credentials in a URL.
     */
    private bool $sendCredentials = false;

    /**
     * Pin DNS records.
     */
    private bool $pinDns = false;

    /**
     * blacklist and whitelist.
     *
     * @var array{
     *     whitelist: array{ip: string[], port: string[], domain: string[], scheme: string[]},
     *     blacklist: array{ip: string[], port: string[], domain: string[], scheme: string[]},
     * }
     */
    private array $lists = [
        'whitelist' => [
            'ip' => [],
            'port' => ['80', '443', '8080'],
            'domain' => [],
            'scheme' => ['http', 'https'],
        ],
        'blacklist' => [
            'ip' => [
                '0.0.0.0/8',
                '10.0.0.0/8',
                '100.64.0.0/10',
                '127.0.0.0/8',
                '169.254.0.0/16',
                '172.16.0.0/12',
                '192.0.0.0/29',
                '192.0.2.0/24',
                '192.88.99.0/24',
                '192.168.0.0/16',
                '198.18.0.0/15',
                '198.51.100.0/24',
                '203.0.113.0/24',
                '224.0.0.0/4',
                '240.0.0.0/4',
            ],
            'port' => [],
            'domain' => [],
            'scheme' => [],
        ],
    ];

    /**
     * Get send credentials option.
     */
    public function getSendCredentials(): bool
    {
        return $this->sendCredentials;
    }

    /**
     * Enable sending of credenitals
     * This is potentially a security risk.
     */
    public function enableSendCredentials(): self
    {
        $this->sendCredentials = true;

        return $this;
    }

    /**
     * Disable sending of credentials.
     */
    public function disableSendCredentials(): self
    {
        $this->sendCredentials = false;

        return $this;
    }

    /**
     * Get pin DNS option.
     */
    public function getPinDns(): bool
    {
        return $this->pinDns;
    }

    /**
     * Enable DNS pinning.
     */
    public function enablePinDns(): self
    {
        $this->pinDns = true;

        return $this;
    }

    /**
     * Disable DNS pinning.
     */
    public function disablePinDns(): self
    {
        $this->pinDns = false;

        return $this;
    }

    /**
     * Checks if a specific value is in a list.
     *
     * @param self::LIST_* $listName Accepts 'whitelist' or 'blacklist
     * @param self::TYPE_* $type
     *
     * @throws InvalidOptionException
     */
    public function isInList(string $listName, string $type, string $value): bool
    {
        $this->checkListByName($listName);
        $value = (string) $value;

        if (!\array_key_exists($type, $this->lists[$listName])) {
            throw InvalidOptionException::invalidType($type, self::AVAILABLE_TYPE);
        }

        if (empty($this->lists[$listName][$type])) {
            return self::LIST_WHITELIST === $listName;
        }

        // For domains, a regex match is needed
        if (self::TYPE_DOMAIN === $type) {
            foreach ($this->lists[$listName][$type] as $domain) {
                if (preg_match('/^' . $domain . '$/i', $value)) {
                    return true;
                }
            }

            return false;
        }

        return \in_array($value, $this->lists[$listName][$type], true);
    }

    /**
     * Returns a specific list.
     *
     * @param self::LIST_*  $listName Accepts 'whitelist' or 'blacklist
     * @param ?self::TYPE_* $type
     *
     * @throws InvalidOptionException
     *
     * @return ($type is null ? array{ip: string[], port: string[], domain: string[], scheme: string[]} : string[])
     */
    public function getList(string $listName, ?string $type = null): array
    {
        $this->checkListByName($listName);

        if (null !== $type) {
            if (!\array_key_exists($type, $this->lists[$listName])) {
                throw InvalidOptionException::invalidType($type, self::AVAILABLE_TYPE);
            }

            return $this->lists[$listName][$type];
        }

        return $this->lists[$listName];
    }

    /**
     * Sets a list, the values must be passed as an array.
     *
     * @template T of ?self::TYPE_*
     *
     * @param self::LIST_*                                                                                                                                         $listName Accepts 'whitelist' or 'blacklist
     * @param (T is null ? array{ip?: string[], port?: (string|int)[], domain?: string[], scheme?: string[]} : (T is self::TYPE_PORT ? (string|int)[] : string[])) $values
     * @param T                                                                                                                                                    $type
     *
     * @throws InvalidOptionException
     */
    public function setList(string $listName, array $values, ?string $type = null): self
    {
        $this->checkListByName($listName);

        if (null !== $type) {
            if (!\array_key_exists($type, $this->lists[$listName])) {
                throw InvalidOptionException::invalidType($type, self::AVAILABLE_TYPE);
            }

            // For PHPStan, the conditional type does not seem to work properly.
            /** @var (string|int)[] */
            $values = $values;

            if (self::TYPE_PORT === $type) {
                $values = self::ensureStringList($values);
            }

            // For PHPStan, the conditional type does not seem to work properly.
            /** @var string[] */
            $values = $values;

            $this->lists[$listName][$type] = $values;

            return $this;
        }

        // For PHPStan, the conditional type does not seem to work properly.
        /** @var array{ip?: string[], port?: (string|int)[], domain?: string[], scheme?: string[]} */
        $values = $values;

        foreach ($values as $type => $value) {
            if (!\in_array($type, self::AVAILABLE_TYPE, true)) {
                throw InvalidOptionException::invalidType($type, self::AVAILABLE_TYPE);
            }

            if (self::TYPE_PORT === $type) {
                $value = self::ensureStringList($value);
            }

            // For PHPStan, the conditional type does not seem to work properly.
            /** @var string[] */
            $value = $value;

            $this->lists[$listName][$type] = $value;
        }

        return $this;
    }

    /**
     * Adds a value/values to a specific list.
     *
     * @template T of self::TYPE_*
     *
     * @param self::LIST_*                                                         $listName Accepts 'whitelist' or 'blacklist
     * @param T                                                                    $type
     * @param (T is self::TYPE_PORT ? (string|int)[]|string|int : string[]|string) $values
     *
     * @throws InvalidOptionException
     */
    public function addToList(string $listName, string $type, $values): self
    {
        $this->checkListByName($listName);

        if (!\array_key_exists($type, $this->lists[$listName])) {
            throw InvalidOptionException::invalidType($type, self::AVAILABLE_TYPE);
        }

        if (empty($values)) {
            throw InvalidOptionException::emptyValues();
        }

        // Cast single values to an array
        $values = (array) $values;

        if (self::TYPE_PORT === $type) {
            $values = self::ensureStringList($values);
        }

        // For PHPStan, the conditional type does not seem to work properly.
        /** @var string[] */
        $values = $values;

        foreach ($values as $value) {
            if (!\in_array($value, $this->lists[$listName][$type], true)) {
                $this->lists[$listName][$type][] = $value;
            }
        }

        return $this;
    }

    /**
     * Removes a value/values from a specific list.
     *
     * @template T of self::TYPE_*
     *
     * @param self::LIST_*                                                         $listName Accepts 'whitelist' or 'blacklist
     * @param T                                                                    $type
     * @param (T is self::TYPE_PORT ? (string|int)[]|string|int : string[]|string) $values
     *
     * @throws InvalidOptionException
     */
    public function removeFromList(string $listName, string $type, $values): self
    {
        $this->checkListByName($listName);

        if (!\array_key_exists($type, $this->lists[$listName])) {
            throw InvalidOptionException::invalidType($type, self::AVAILABLE_TYPE);
        }

        if (empty($values)) {
            throw InvalidOptionException::emptyValues();
        }

        // Cast single values to an array
        $values = (array) $values;

        if (self::TYPE_PORT === $type) {
            $values = self::ensureStringList($values);
        }

        $this->lists[$listName][$type] = array_diff($this->lists[$listName][$type], $values);

        return $this;
    }

    /**
     * @param self::LIST_* $listName Accepts 'whitelist' or 'blacklist
     *
     * @throws InvalidOptionException
     */
    private function checkListByName(string $listName): void
    {
        if (!isset($this->lists[$listName])) {
            throw InvalidOptionException::invalidListName($listName);
        }
    }

    /**
     * @param (string|int)[] $values
     *
     * @return string[]
     */
    private static function ensureStringList(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (\is_int($value)) {
                $value = (string) $value;
            }
            if (!\is_string($value)) {
                throw new \TypeError('Option values can only be strings or ints.');
            }

            $result[] = $value;
        }

        return $result;
    }
}
