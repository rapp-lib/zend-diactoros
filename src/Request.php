<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Diactoros;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP Request encapsulation
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class Request implements RequestInterface
{

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param null|string $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Message body, if any.
     * @param array $headers Headers for the message, if any.
     * @throws \InvalidArgumentException for any invalid value.
     */
    public function __construct($uri = null, $method = null, $body = 'php://temp', array $headers = array())
    {
        $this->request = new ConcreteRequest($uri, $method, $body, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        $request = $this->request;
        $headers = $request->getHeaders();

        if (!$request->hasHeader('host')
            && ($request->getUri() && $request->getUri()->getHost())
        ) {
            $headers['Host'] = array($this->getHostFromUri());
        }

        return $headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($header)
    {
        if (! $this->request->hasHeader($header)) {
            if (strtolower($header) === 'host'
                && ($this->request->getUri() && $this->request->getUri()->getHost())
            ) {
                return array($this->getHostFromUri());
            }

            return array();
        }
        return $this->request->getHeader($header);
    }


    /**
     * Retrieve the host from the URI instance
     *
     * @return string
     */
    private function getHostFromUri()
    {
        $uri = $this->request->getUri();
        $host  = $uri->getHost();
        $host .= $uri->getPort() ? ':' . $uri->getPort() : '';
        return $host;
    }

    public function getRequestTarget()
    {
        return $this->request->getRequestTarget();
    }

    public function withRequestTarget($requestTarget)
    {
        $new = clone $this;
        $new->request = $this->request->withRequestTarget($requestTarget);
        return $new;
    }

    public function getMethod()
    {
        return $this->request->getMethod();
    }

    public function withMethod($method)
    {
        $new = clone $this;
        $new->request = $this->request->withMethod($method);
        return $new;
    }

    public function getUri()
    {
        return $this->request->getUri();
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $new = clone $this;
        $new->request = $this->request->withUri($uri, $preserveHost);
        return $new;
    }


    public function getProtocolVersion()
    {
        return $this->request->getProtocolVersion();
    }

    public function withProtocolVersion($version)
    {
        $new = clone $this;
        $new->request = $this->request->withProtocolVersion($version);
        return $new;
    }

    public function hasHeader($name)
    {
        return (bool) $this->getHeader($name);
    }


    public function getHeaderLine($name)
    {

        $value = $this->getHeader($name);
        if (empty($value)) {
            return '';
        }

        return implode(',', $value);
    }

    public function withHeader($name, $value)
    {
        $new = clone $this;
        $new->request = $this->request->withHeader($name, $value);
        return $new;
    }

    public function withAddedHeader($name, $value)
    {
        $new = clone $this;
        $new->request = $this->request->withAddedHeader($name, $value);
        return $new;
    }

    public function withoutHeader($name)
    {
        $new = clone $this;
        $new->request = $this->request->withoutHeader($name);
        return $new;
    }

    public function getBody()
    {
        return $this->request->getBody();
    }

    public function withBody(StreamInterface $body)
    {
        $new = clone $this;
        $new->request = $this->request->withBody($body);
        return $new;
    }
}
