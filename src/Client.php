<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\httpclient;

use InvalidArgumentException;
use Yii;
use yii\base\Component;
use yii\helpers\StringHelper;

/**
 * Client provide high level interface for HTTP requests execution.
 *
 * @property Transport $transport HTTP message transport instance. Note that the type of this property differs
 * in getter and setter. See [[getTransport()]] and [[setTransport()]] for details.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 *
 * @since 2.0
 */
class Client extends Component
{
    /**
     * @event RequestEvent an event raised right before sending request.
     */
    final public const EVENT_BEFORE_SEND = 'beforeSend';
    /**
     * @event RequestEvent an event raised right after request has been sent.
     */
    final public const EVENT_AFTER_SEND = 'afterSend';
    /**
     * JSON format
     */
    final public const FORMAT_JSON = 'json';
    /**
     * urlencoded by RFC1738 query string, like name1=value1&name2=value2
     *
     * @see https://php.net/manual/en/function.urlencode.php
     */
    final public const FORMAT_URLENCODED = 'urlencoded';
    /**
     * urlencoded by PHP_QUERY_RFC3986 query string, like name1=value1&name2=value2
     *
     * @see https://php.net/manual/en/function.rawurlencode.php
     */
    final public const FORMAT_RAW_URLENCODED = 'raw-urlencoded';
    /**
     * XML format
     */
    final public const FORMAT_XML = 'xml';
    /**
     * CURL format
     */
    final public const FORMAT_CURL = 'curl';

    /**
     * @var string base request URL.
     */
    public $baseUrl = '';
    /**
     * @var array the formatters for converting data into the content of the specified [[format]].
     * The array keys are the format names, and the array values are the corresponding configurations
     * for creating the formatter objects.
     */
    public $formatters = [];
    /**
     * @var array the parsers for converting content of the specified [[format]] into the data.
     * The array keys are the format names, and the array values are the corresponding configurations
     * for creating the parser objects.
     */
    public $parsers = [];
    /**
     * @var array request object configuration.
     */
    public $requestConfig = [];
    /**
     * @var array response config configuration.
     */
    public $responseConfig = [];
    /**
     * @var int maximum symbols count of the request content, which should be taken to compose a
     * log and profile messages. Exceeding content will be truncated.
     *
     * @see createRequestLogToken()
     */
    public $contentLoggingMaxSize = 2000;

    /**
     * @var array|callable|string|Transport HTTP message transport.
     */
    private $_transport = StreamTransport::class;

    /**
     * Sets the HTTP message transport. It can be specified in one of the following forms:
     *
     * - an instance of `Transport`: actual transport object to be used
     * - a string: representing the class name of the object to be created
     * - a configuration array: the array must contain a `class` element which is treated as the object class,
     *   and the rest of the name-value pairs will be used to initialize the corresponding object properties
     * - a PHP callable: either an anonymous function or an array representing a class method (`[$class or $object, $method]`).
     *   The callable should return a new instance of the object being created.
     *
     * @param array|string|Transport $transport HTTP message transport
     */
    public function setTransport($transport): void
    {
        $this->_transport = $transport;
    }

    /**
     * @throws \yii\base\InvalidConfigException
     *
     * @return Transport HTTP message transport instance.
     */
    public function getTransport()
    {
        if (!is_object($this->_transport)) {
            $this->_transport = Yii::createObject($this->_transport);
        }
        return $this->_transport;
    }

    /**
     * Returns HTTP message formatter instance for the specified format.
     *
     * @param string $format format name.
     *
     * @throws InvalidArgumentException on invalid format name.
     * @throws \yii\base\InvalidConfigException
     *
     * @return FormatterInterface formatter instance.
     */
    public function getFormatter($format)
    {
        static $defaultFormatters = [
            self::FORMAT_JSON => 'yii\httpclient\JsonFormatter',
            self::FORMAT_URLENCODED => [
                'class' => 'yii\httpclient\UrlEncodedFormatter',
                'encodingType' => PHP_QUERY_RFC1738,
            ],
            self::FORMAT_RAW_URLENCODED => [
                'class' => 'yii\httpclient\UrlEncodedFormatter',
                'encodingType' => PHP_QUERY_RFC3986,
            ],
            self::FORMAT_XML => 'yii\httpclient\XmlFormatter',
            self::FORMAT_CURL => 'yii\httpclient\CurlFormatter',
        ];

        if (!isset($this->formatters[$format])) {
            if (!isset($defaultFormatters[$format])) {
                throw new InvalidArgumentException("Unrecognized format '{$format}'");
            }
            $this->formatters[$format] = $defaultFormatters[$format];
        }

        if (!is_object($this->formatters[$format])) {
            $this->formatters[$format] = Yii::createObject($this->formatters[$format]);
        }

        return $this->formatters[$format];
    }

    /**
     * Returns HTTP message parser instance for the specified format.
     *
     * @param string $format format name
     *
     * @throws InvalidArgumentException on invalid format name.
     * @throws \yii\base\InvalidConfigException
     *
     * @return ParserInterface parser instance.
     */
    public function getParser($format)
    {
        static $defaultParsers = [
            self::FORMAT_JSON => 'yii\httpclient\JsonParser',
            self::FORMAT_URLENCODED => 'yii\httpclient\UrlEncodedParser',
            self::FORMAT_RAW_URLENCODED => 'yii\httpclient\UrlEncodedParser',
            self::FORMAT_XML => 'yii\httpclient\XmlParser',
        ];

        if (!isset($this->parsers[$format])) {
            if (!isset($defaultParsers[$format])) {
                throw new InvalidArgumentException("Unrecognized format '{$format}'");
            }
            $this->parsers[$format] = $defaultParsers[$format];
        }

        if (!is_object($this->parsers[$format])) {
            $this->parsers[$format] = Yii::createObject($this->parsers[$format]);
        }

        return $this->parsers[$format];
    }

    /**
     * @throws \yii\base\InvalidConfigException
     *
     * @return Request request instance.
     */
    public function createRequest()
    {
        $config = $this->requestConfig;
        if (!isset($config['class'])) {
            $config['class'] = Request::className();
        }
        $config['client'] = $this;
        return Yii::createObject($config);
    }

    /**
     * Creates a response instance.
     *
     * @param string $content raw content
     * @param array $headers headers list.
     *
     * @throws \yii\base\InvalidConfigException
     *
     * @return Response request instance.
     */
    public function createResponse($content = null, array $headers = [])
    {
        $config = $this->responseConfig;
        if (!isset($config['class'])) {
            $config['class'] = Response::className();
        }
        $config['client'] = $this;
        $response = Yii::createObject($config);
        $response->setContent($content);
        $response->setHeaders($headers);
        return $response;
    }

    /**
     * Performs given request.
     *
     * @param Request $request request to be sent.
     *
     * @throws Exception on failure.
     * @throws \yii\base\InvalidConfigException
     *
     * @return Response response instance.
     */
    public function send($request)
    {
        return $this->getTransport()->send($request);
    }

    /**
     * Performs multiple HTTP requests in parallel.
     * This method accepts an array of the [[Request]] objects and returns an array of the  [[Response]] objects.
     * Keys of the response array correspond the ones from request array.
     *
     * ```php
     * $client = new Client();
     * $requests = [
     *     'news' => $client->get('http://domain.com/news'),
     *     'friends' => $client->get('http://domain.com/user/friends', ['userId' => 12]),
     * ];
     * $responses = $client->batchSend($requests);
     * var_dump($responses['news']->isOk);
     * var_dump($responses['friends']->isOk);
     * ```
     *
     * @param Request[] $requests requests to perform.
     *
     * @throws Exception
     * @throws \yii\base\InvalidConfigException
     *
     * @return Response[] responses list.
     */
    public function batchSend(array $requests)
    {
        return $this->getTransport()->batchSend($requests);
    }

    /**
     * Composes the log/profiling message token for the given HTTP request parameters.
     * This method should be used by transports during request sending logging.
     *
     * @param string $method request method name.
     * @param string $url request URL.
     * @param array $headers request headers.
     * @param string $content request content.
     *
     * @return string log token.
     */
    public function createRequestLogToken($method, $url, $headers, $content)
    {
        $token = strtoupper($method) . ' ' . $url;
        if (!empty($headers)) {
            $token .= "\n" . implode("\n", $headers);
        }
        if ($content !== null) {
            $token .= "\n\n" . StringHelper::truncate($content, $this->contentLoggingMaxSize);
        }
        return $token;
    }

    // Create request shortcut methods :

    /**
     * Creates 'GET' request.
     *
     * @param array|string $url target URL.
     * @param array|string $data if array - request data, otherwise - request content.
     * @param array $headers request headers.
     * @param array $options request options.
     *
     * @return Request request instance.
     */
    public function get($url, $data = null, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('GET', $url, $data, $headers, $options);
    }

    /**
     * Creates 'POST' request.
     *
     * @param array|string $url target URL.
     * @param array|string $data if array - request data, otherwise - request content.
     * @param array $headers request headers.
     * @param array $options request options.
     *
     * @return Request request instance.
     */
    public function post($url, $data = null, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('POST', $url, $data, $headers, $options);
    }

    /**
     * Creates 'PUT' request.
     *
     * @param array|string $url target URL.
     * @param array|string $data if array - request data, otherwise - request content.
     * @param array $headers request headers.
     * @param array $options request options.
     *
     * @return Request request instance.
     */
    public function put($url, $data = null, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('PUT', $url, $data, $headers, $options);
    }

    /**
     * Creates 'PATCH' request.
     *
     * @param array|string $url target URL.
     * @param array|string $data if array - request data, otherwise - request content.
     * @param array $headers request headers.
     * @param array $options request options.
     *
     * @return Request request instance.
     */
    public function patch($url, $data = null, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('PATCH', $url, $data, $headers, $options);
    }

    /**
     * Creates 'DELETE' request.
     *
     * @param array|string $url target URL.
     * @param array|string $data if array - request data, otherwise - request content.
     * @param array $headers request headers.
     * @param array $options request options.
     *
     * @return Request request instance.
     */
    public function delete($url, $data = null, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('DELETE', $url, $data, $headers, $options);
    }

    /**
     * Creates 'HEAD' request.
     *
     * @param array|string $url target URL.
     * @param array $headers request headers.
     * @param array $options request options.
     *
     * @return Request request instance.
     */
    public function head($url, $headers = [], $options = [])
    {
        return $this->createRequestShortcut('HEAD', $url, null, $headers, $options);
    }

    /**
     * Creates 'OPTIONS' request.
     *
     * @param array|string $url target URL.
     * @param array $options request options.
     *
     * @return Request request instance.
     */
    public function options($url, $options = [])
    {
        return $this->createRequestShortcut('OPTIONS', $url, null, [], $options);
    }

    /**
     * This method is invoked right before request is sent.
     * The method will trigger the [[EVENT_BEFORE_SEND]] event.
     *
     * @param Request $request request instance.
     */
    public function beforeSend($request): void
    {
        $event = new RequestEvent();
        $event->request = $request;
        $this->trigger(self::EVENT_BEFORE_SEND, $event);
    }

    /**
     * This method is invoked right after request is sent.
     * The method will trigger the [[EVENT_AFTER_SEND]] event.
     *
     * @param Request $request request instance.
     * @param Response $response received response instance.
     */
    public function afterSend($request, $response): void
    {
        $event = new RequestEvent();
        $event->request = $request;
        $event->response = $response;
        $this->trigger(self::EVENT_AFTER_SEND, $event);
    }

    /**
     * @param string $method
     * @param array|string $url
     * @param array|string $data
     * @param array $headers
     * @param array $options
     *
     * @throws \yii\base\InvalidConfigException
     *
     * @return Request request instance.
     */
    protected function createRequestShortcut($method, $url, $data, $headers, $options)
    {
        $request = $this->createRequest()
            ->setMethod($method)
            ->setUrl($url)
            ->addHeaders($headers)
            ->addOptions($options);
        if (is_array($data)) {
            $request->setData($data);
        } else {
            $request->setContent($data);
        }
        return $request;
    }
}
