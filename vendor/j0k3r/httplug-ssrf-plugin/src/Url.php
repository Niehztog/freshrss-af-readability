<?php

declare(strict_types=1);

namespace Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection;

use Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection\Exception\InvalidURLException;
use Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection\Exception\InvalidURLException\InvalidDomainException;
use Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection\Exception\InvalidURLException\InvalidIPException;
use Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection\Exception\InvalidURLException\InvalidPortException;
use Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection\Exception\InvalidURLException\InvalidSchemeException;

class Url
{
    /**
     * Validates the whole URL.
     *
     * @throws InvalidURLException
     *
     * @return array{url: string, host: string, ips: string[]}
     */
    public static function validateUrl(string $url, Options $options): array
    {
        if ('' === trim($url)) {
            throw new InvalidURLException('Provided URL "' . $url . '" cannot be empty');
        }

        // Split URL into parts first
        $parts = parse_url($url);

        if (empty($parts)) {
            throw new InvalidURLException('Error parsing URL "' . $url . '"');
        }

        if (!isset($parts['host'])) {
            throw new InvalidURLException('Provided URL "' . $url . '" doesn\'t contain a hostname');
        }

        // If credentials are passed in, but we don't want them, raise an exception
        if (!$options->getSendCredentials() && (!empty($parts['user']) || !empty($parts['pass']))) {
            throw new InvalidURLException('Credentials passed in but "sendCredentials" is set to false');
        }

        if (!isset($parts['scheme'])) {
            $parts['scheme'] = 'http';
        }

        $parts['scheme'] = self::validateScheme($parts['scheme'], $options);

        // Validate the port
        if (isset($parts['port'])) {
            $parts['port'] = self::validatePort($parts['port'], $options);
        }

        // Validate the host
        $host = self::validateHost($parts['host'], $options);
        $parts['host'] = $host['host'];
        if ($options->getPinDns()) {
            // Since we're pinning DNS, we replace the host in the URL
            // with an IP, then get cURL to send the Host header
            $parts['host'] = $host['ips'][0];
        }

        // Rebuild the URL
        $url = self::buildUrl($parts);

        return [
            'url' => $url,
            'host' => $host['host'],
            'ips' => $host['ips'],
        ];
    }

    /**
     * Validates a URL scheme.
     *
     * @throws InvalidSchemeException
     */
    public static function validateScheme(string $scheme, Options $options): string
    {
        $scheme = strtolower($scheme);

        // Whitelist always takes precedence over a blacklist
        if (!$options->isInList(Options::LIST_WHITELIST, Options::TYPE_SCHEME, $scheme)) {
            throw new InvalidSchemeException('Provided scheme "' . $scheme . '" doesn\'t match whitelisted values: ' . implode(', ', $options->getList(Options::LIST_WHITELIST, Options::TYPE_SCHEME)));
        }

        if ($options->isInList(Options::LIST_BLACKLIST, Options::TYPE_SCHEME, $scheme)) {
            throw new InvalidSchemeException('Provided scheme "' . $scheme . '" matches a blacklisted value');
        }

        // Existing value is fine
        return $scheme;
    }

    /**
     * Validates a port.
     *
     * @param string|int $port
     *
     * @throws InvalidPortException
     */
    public static function validatePort($port, Options $options): int
    {
        $port = (string) $port;
        if (!$options->isInList(Options::LIST_WHITELIST, Options::TYPE_PORT, $port)) {
            throw new InvalidPortException('Provided port "' . $port . '" doesn\'t match whitelisted values: ' . implode(', ', $options->getList(Options::LIST_WHITELIST, Options::TYPE_PORT)));
        }

        if ($options->isInList(Options::LIST_BLACKLIST, Options::TYPE_PORT, $port)) {
            throw new InvalidPortException('Provided port "' . $port . '" matches a blacklisted value');
        }

        // Existing value is fine
        return (int) $port;
    }

    /**
     * Validates a URL host.
     *
     * @throws InvalidDomainException
     * @throws InvalidIPException
     *
     * @return array{host: string, ips: string[]}
     */
    public static function validateHost(string $host, Options $options): array
    {
        $host = strtolower($host);

        // Check the host against the domain lists
        if (!$options->isInList(Options::LIST_WHITELIST, Options::TYPE_DOMAIN, $host)) {
            throw new InvalidDomainException('Provided host "' . $host . '" doesn\'t match whitelisted values: ' . implode(', ', $options->getList(Options::LIST_WHITELIST, Options::TYPE_DOMAIN)));
        }

        if ($options->isInList(Options::LIST_BLACKLIST, Options::TYPE_DOMAIN, $host)) {
            throw new InvalidDomainException('Provided host "' . $host . '" matches a blacklisted value');
        }

        // Now resolve to an IP and check against the IP lists
        $ips = @gethostbynamel($host);
        if (empty($ips)) {
            throw new InvalidDomainException('Provided host "' . $host . '" doesn\'t resolve to an IP address');
        }

        $whitelistedIps = $options->getList(Options::LIST_WHITELIST, Options::TYPE_IP);

        if (!empty($whitelistedIps)) {
            $valid = false;

            foreach ($whitelistedIps as $whitelistedIp) {
                foreach ($ips as $ip) {
                    if (self::cidrMatch($ip, $whitelistedIp)) {
                        $valid = true;
                        break 2;
                    }
                }
            }

            if (!$valid) {
                throw new InvalidIPException('Provided host "' . $host . '" resolves to "' . implode(', ', $ips) . '", which doesn\'t match whitelisted values: ' . implode(', ', $whitelistedIps));
            }
        }

        $blacklistedIps = $options->getList(Options::LIST_BLACKLIST, Options::TYPE_IP);

        if (!empty($blacklistedIps)) {
            foreach ($blacklistedIps as $blacklistedIp) {
                foreach ($ips as $ip) {
                    if (self::cidrMatch($ip, $blacklistedIp)) {
                        throw new InvalidIPException('Provided host "' . $host . '" resolves to "' . implode(', ', $ips) . '", which matches a blacklisted value: ' . $blacklistedIp);
                    }
                }
            }
        }

        return [
            'host' => $host,
            'ips' => $ips,
        ];
    }

    /**
     * Re-build a URL based on an array of parts.
     *
     * @param array{scheme?: string, user?: string, pass?: string, host?: string, port?: int, path?: string, query?: string, fragment?: string} $parts
     */
    public static function buildUrl(array $parts): string
    {
        $url = '';

        $url .= !empty($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $url .= !empty($parts['user']) ? $parts['user'] : '';
        $url .= !empty($parts['pass']) ? ':' . $parts['pass'] : '';
        // If we have a user or pass, make sure to add an "@"
        $url .= !empty($parts['user']) || !empty($parts['pass']) ? '@' : '';
        $url .= !empty($parts['host']) ? $parts['host'] : '';
        $url .= !empty($parts['port']) ? ':' . $parts['port'] : '';
        $url .= !empty($parts['path']) ? $parts['path'] : '';
        $url .= !empty($parts['query']) ? '?' . $parts['query'] : '';
        $url .= !empty($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $url;
    }

    /**
     * Checks a passed in IP against a CIDR.
     * See http://stackoverflow.com/questions/594112/matching-an-ip-to-a-cidr-mask-in-php5.
     */
    public static function cidrMatch(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            // It doesn't have a prefix, just a straight IP match
            return $ip === $cidr;
        }

        list($subnet, $mask) = explode('/', $cidr);

        return (ip2long($ip) & ~((1 << (32 - (int) $mask)) - 1)) === ip2long($subnet);
    }
}
