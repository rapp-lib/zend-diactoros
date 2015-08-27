<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Diactoros;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Server-side HTTP request
 *
 * Extends the Request definition to add methods for accessing incoming data,
 * specifically server parameters, cookies, matched path parameters, query
 * string arguments, body parameters, and upload file information.
 *
 * "Attributes" are discovered via decomposing the request (and usually
 * specifically the URI path), and typically will be injected by the application.
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class ServerRequest implements ServerRequestInterface
{
    /**
     * @var ConcreteRequest
     */
    private $request;

    /**
     * @var array
     */
    private $attributes = array();

    /**
     * @var array
     */
    private $cookieParams = array();

    /**
     * @var null|array|object
     */
    private $parsedBody;

    /**
     * @var array
     */
    private $queryParams = array();

    /**
     * @var array
     */
    private $serverParams;

    /**
     * @var array
     */
    private $uploadedFiles;

    /**
     * @param array $serverParams Server parameters, typically from $_SERVER
     * @param array $uploadedFiles Upload file information, a tree of UploadedFiles
     * @param null|string $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Message body, if any.
     * @param array $headers Headers for the message, if any.
     * @throws InvalidArgumentException for any invalid value.
     */
    public function __construct(
        array $serverParams = array(),
        array $uploadedFiles = array(),
        $uri = null,
        $method = null,
        $body = 'php://input',
        array $headers = array()
    ) {
        $this->validateUploadedFiles($uploadedFiles);

        $body = $this->getStream($body);
        $this->request = new ConcreteRequest($uri, $method, $body, $headers);
        $this->serverParams  = $serverParams;
        $this->uploadedFiles = $uploadedFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerParams()
    {
        return $this->serverParams;
    }

    /**
     * {@inheritdoc}
     */
    public function getUploadedFiles()
    {
        return $this->uploadedFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $this->validateUploadedFiles($uploadedFiles);
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieParams()
    {
        return $this->cookieParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryParams()
    {
        return $this->queryParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * {@inheritdoc}
     */
    public function withParsedBody($data)
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($attribute, $default = null)
    {
        if (! array_key_exists($attribute, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$attribute];
    }

    /**
     * {@inheritdoc}
     */
    public function withAttribute($attribute, $value)
    {
        $new = clone $this;
        $new->attributes[$attribute] = $value;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutAttribute($attribute)
    {
        if (! isset($this->attributes[$attribute])) {
            return clone $this;
        }

        $new = clone $this;
        unset($new->attributes[$attribute]);
        return $new;
    }

    /**
     * Proxy to receive the request method.
     *
     * This overrides the parent functionality to ensure the method is never
     * empty; if no method is present, it returns 'GET'.
     *
     * @return string
     */
    public function getMethod()
    {
        if (!$this->request->getMethod()) {
            return 'GET';
        }
        return $this->request->getMethod();
    }

    /**
     * Set the request method.
     *
     * Unlike the regular Request implementation, the server-side
     * normalizes the method to uppercase to ensure consistency
     * and make checking the method simpler.
     *
     * This methods returns a new instance.
     *
     * @param string $method
     * @return self
     */
    public function withMethod($method)
    {
        $method = strtoupper($method);
        $new = clone $this;
        $new->request = $this->request->withMethod($method);
        return $new;
    }

    /**
     * Set the body stream
     *
     * @param string|resource|StreamInterface $stream
     * @return StreamInterface
     */
    private function getStream($stream)
    {
        if ($stream === 'php://input') {
            return new PhpInputStream();
        }

        if (! is_string($stream) && ! is_resource($stream) && ! $stream instanceof StreamInterface) {
            throw new InvalidArgumentException(
                'Stream must be a string stream resource identifier, '
                . 'an actual stream resource, '
                . 'or a Psr\Http\Message\StreamInterface implementation'
            );
        }

        if (! $stream instanceof StreamInterface) {
            return new Stream($stream, 'r');
        }

        return $stream;
    }

    /**
     * Recursively validate the structure in an uploaded files array.
     *
     * @param array $uploadedFiles
     * @throws InvalidArgumentException if any leaf is not an UploadedFileInterface instance.
     */
    private function validateUploadedFiles(array $uploadedFiles)
    {
        foreach ($uploadedFiles as $file) {
            if (is_array($file)) {
                $this->validateUploadedFiles($file);
                continue;
            }

            if (! $file instanceof UploadedFileInterface) {
                throw new InvalidArgumentException('Invalid leaf in uploaded files structure');
            }
        }
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
        return $this->request->hasHeader($name);
    }


    public function getHeaderLine($name)
    {
        return $this->request->getHeaderLine($name);
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


    public function getHeaders()
    {
        return $this->request->getHeaders();
    }


    public function getHeader($name)
    {
        return $this->request->getHeader($name);
    }
}
