<?php

declare(strict_types=1);

namespace Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection;

use Graby\HttpClient\Plugin\ServerSideRequestForgeryProtection\Exception\InvalidURLException;
use Http\Client\Common\Plugin;
use Http\Client\Exception\RequestException;
use Http\Client\Promise\HttpRejectedPromise;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Validates each part of the URL against a white or black list, to help protect against Server-Side Request Forgery
 * attacks.
 *
 * @see https://github.com/j0k3r/safecurl
 * @see https://whitton.io/articles/safecurl-ssrf-protection-and-a-capture-the-bitcoins/
 */
class ServerSideRequestForgeryProtectionPlugin implements Plugin
{
    private Options $options;
    private UriFactoryInterface $uriFactory;

    public function __construct(?Options $options = null, ?UriFactoryInterface $uriFactory = null)
    {
        $this->options = $options ?: new Options();
        $this->uriFactory = $uriFactory ?: Psr17FactoryDiscovery::findUriFactory();
    }

    /**
     * @param callable(RequestInterface): Promise $next
     * @param callable(RequestInterface): Promise $first
     */
    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        try {
            $urlData = Url::validateUrl((string) $request->getUri(), $this->options);
        } catch (InvalidURLException $e) {
            return new HttpRejectedPromise(new RequestException($e->getMessage(), $request, $e));
        }

        $uri = $this->uriFactory->createUri($urlData['url']);

        if ((string) $uri !== (string) $request->getUri()) {
            $request = $request->withUri($uri->withHost($urlData['host']));
        }

        return $next($request);
    }
}
