<?php

declare(strict_types=1);

namespace Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection\Exception;

final class InvalidOptionException extends \Exception implements SsrfException
{
    /**
     * @param string[] $expectedTypes
     *
     * @return static
     */
    public static function invalidType(string $type, array $expectedTypes): self
    {
        $expectedTypesList = '"' . implode('", "', \array_slice($expectedTypes, 0, -1))
            . '" or "' . $expectedTypes[\count($expectedTypes) - 1] . '"';

        return new static(\sprintf('Provided type "%s" must be %s', $type, $expectedTypesList));
    }

    /**
     * @return static
     */
    public static function invalidListName(string $listName): self
    {
        return new static(\sprintf('Provided list "%s" must be "whitelist" or "blacklist"', $listName));
    }

    public static function emptyValues(): self
    {
        return new static('Provided values cannot be empty');
    }
}
