<?php

declare(strict_types=1);

namespace Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection\Exception;

class InvalidURLException extends \Exception implements SsrfException
{
}
