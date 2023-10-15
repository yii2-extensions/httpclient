<?php

declare(strict_types=1);

namespace yii\httpclient;

use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

/**
 * Request represents HTTP request.
 *
 * @property string $fullUrl Full target URL.
 * @property string $method Request method.
 * @property array $options Request options.
 * @property array|string $url Target URL or URL parameters.
 */
class Request extends Message
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
     * @var array|string target URL.
     */
    private array|string|null $_url = null;
    /**
     * @var string|null full target URL.
     */
    private string|null $_fullUrl = null;
    /**
     * @var string request method.
     */
    private string $_method = 'GET';
    /**
     * @var array request options.
     */
    private array $_options = [];
    /**
     * @var bool whether request object has been prepared for sending or not.
     *
     * @see prepare()
     */
    private bool $isPrepared = false;
    /**
     * @var resource The file that the transfer should be written to.
     */
    private $_outputFile = null;
    /**
     * @var array Stores map (alias => name) of the content parameters
     */
    private array $_contentMap = [];
    /**
     * @var float stores the starttime of the current request with microsecond-precession
     */
    private string|float|null $_startTime = null;
    /**
     * @var float|null stores the seconds of how long does it take to get a response
     */
    private float|null $_timeElapsed = null;

    /**
     * Sets target URL.
     *
     * @param array|string $url use a string to represent a URL (e.g. `http://some-domain.com`, `item/list`),
     * or an array to represent a URL with query parameters (e.g. `['item/list', 'param1' => 'value1']`).
     *
     * @return $this self reference.
     */
    public function setUrl($url)
    {
        $this->_url = $url;
        $this->_fullUrl = null;
        return $this;
    }

    /**
     * Returns target URL.
     *
     * @return array|string|null target URL or URL parameters
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * Sets full target URL.
     * This method can be used during request formatting and preparation.
     * Do not use it for the target URL specification, use [[setUrl()]] instead.
     *
     * @param string $fullUrl full target URL.
     *
     * @return $this self reference.
     */
    public function setFullUrl($fullUrl)
    {
        $this->_fullUrl = $fullUrl;
        return $this;
    }

    /**
     * Returns full target URL, including [[Client::baseUrl]] as a string.
     *
     * @return string full target URL.
     */
    public function getFullUrl()
    {
        if ($this->_fullUrl === null) {
            $this->_fullUrl = $this->createFullUrl($this->getUrl());
        }
        return $this->_fullUrl;
    }

    /**
     * @param string $method request method
     *
     * @return $this self reference.
     */
    public function setMethod($method)
    {
        $this->_method = $method;
        return $this;
    }

    /**
     * @return string request method
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * Following options are supported:
     * - timeout: int, the maximum number of seconds to allow request to be executed.
     * - proxy: string, URI specifying address of proxy server. (e.g. tcp://proxy.example.com:5100).
     * - userAgent: string, the contents of the "User-Agent: " header to be used in a HTTP request.
     * - followLocation: bool, whether to follow any "Location: " header that the server sends as part of the HTTP header.
     * - maxRedirects: int, the max number of redirects to follow.
     * - protocolVersion: float|string, HTTP protocol version.
     * - sslVerifyPeer: bool, whether verification of the peer's certificate should be performed.
     * - sslCafile: string, location of Certificate Authority file on local filesystem which should be used with
     *   the 'sslVerifyPeer' option to authenticate the identity of the remote peer.
     * - sslCapath: string, a directory that holds multiple CA certificates.
     *
     * You may set options using keys, which are specific to particular transport, like `[CURLOPT_VERBOSE => true]` in case
     * there is a necessity for it.
     *
     * @param array $options request options.
     *
     * @return $this self reference.
     */
    public function setOptions(array $options)
    {
        $this->_options = $options;
        return $this;
    }

    /**
     * @return array request options.
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Adds more options to already defined ones.
     * Please refer to [[setOptions()]] on how to specify options.
     *
     * @param array $options additional options
     *
     * @return $this self reference.
     */
    public function addOptions(array $options)
    {
        // `array_merge()` will produce invalid result for cURL options,
        // while `ArrayHelper::merge()` is unable to override cURL options
        foreach ($options as $key => $value) {
            if (is_array($value) && isset($this->_options[$key])) {
                $value = ArrayHelper::merge($this->_options[$key], $value);
            }
            $this->_options[$key] = $value;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setData($data)
    {
        if ($this->isPrepared) {
            $this->setContent(null);
            $this->isPrepared = false;
        }

        return parent::setData($data);
    }

    /**
     * {@inheritdoc}
     */
    public function addData($data)
    {
        if ($this->isPrepared) {
            $this->setContent(null);
            $this->isPrepared = false;
        }

        return parent::addData($data);
    }

    /**
     * Adds a content part for multi-part content request.
     *
     * @param string $name part (form input) name.
     * @param string $content content.
     * @param array $options content part options, valid options are:
     *  - contentType - string, part content type
     *  - fileName - string, name of the uploading file
     *  - mimeType - string, part content type in case of file uploading
     *
     * @return $this self reference.
     */
    public function addContent($name, $content, $options = [])
    {
        $multiPartContent = $this->getContent();
        if (!is_array($multiPartContent)) {
            $multiPartContent = [];
        }
        $options['content'] = $content;
        $alias = $this->generateContentAlias($name);
        $this->addAliasToContentMap($name, $alias);
        $multiPartContent[$alias] = $options;
        $this->setContent($multiPartContent);
        return $this;
    }

    /**
     * Adds a file for upload as multi-part content.
     *
     * @see addContent()
     *
     * @param string $name part (form input) name
     * @param string $fileName full name of the source file.
     * @param array $options content part options, valid options are:
     *  - fileName - string, base name of the uploading file, if not set it base name of the source file will be used.
     *  - mimeType - string, file mime type, if not set it will be determine automatically from source file.
     *
     * @throws \yii\base\InvalidConfigException
     *
     * @return $this
     */
    public function addFile($name, $fileName, $options = [])
    {
        $content = file_get_contents($fileName);
        if (!isset($options['mimeType'])) {
            $options['mimeType'] = FileHelper::getMimeType($fileName);
        }
        if (!isset($options['fileName'])) {
            $options['fileName'] = basename($fileName);
        }
        return $this->addContent($name, $content, $options);
    }

    /**
     * Adds a string as a file upload.
     *
     * @see addContent()
     *
     * @param string $name part (form input) name
     * @param string $content file content.
     * @param array $options content part options, valid options are:
     *  - fileName - string, base name of the uploading file.
     *  - mimeType - string, file mime type, if not set it 'application/octet-stream' will be used.
     *
     * @return $this
     */
    public function addFileContent($name, $content, $options = [])
    {
        if (!isset($options['mimeType'])) {
            $options['mimeType'] = 'application/octet-stream';
        }
        if (!isset($options['fileName'])) {
            $options['fileName'] = $name . '.dat';
        }
        return $this->addContent($name, $content, $options);
    }

    /**
     * Prepares this request instance for sending.
     * This method should be invoked by transport before sending a request.
     * Do not call this method unless you know what you are doing.
     *
     * @return $this self reference.
     */
    public function prepare()
    {
        $content = $this->getContent();
        if ($content === null) {
            $this->getFormatter()->format($this);
        } elseif (is_array($content)) {
            $this->prepareMultiPartContent($content);
        }

        $this->isPrepared = true;

        return $this;
    }

    /**
     * Normalizes given URL value, filling it with actual string URL value.
     *
     * @param array|string $url raw URL,
     *
     * @return string full URL
     */
    private function createFullUrl($url)
    {
        if (is_array($url)) {
            $params = $url;
            if (isset($params[0])) {
                $url = (string)$params[0];
                unset($params[0]);
            } else {
                $url = '';
            }
        }

        if (!empty($this->client->baseUrl)) {
            if (empty($url)) {
                $url = $this->client->baseUrl;
            } elseif (!preg_match('/^https?:\\/\\//i', $url)) {
                $url = rtrim($this->client->baseUrl, '/') . '/' . ltrim($url, '/');
            }
        }

        if (!empty($params)) {
            if (!str_contains($url, '?')) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url .= http_build_query($params);
        }

        return $url ?? '';
    }

    /**
     * Prepares multi-part content.
     *
     * @param array $content multi part content.
     *
     * @see https://tools.ietf.org/html/rfc7578
     * @see https://tools.ietf.org/html/rfc2616#section-19.5.1 for the Content-Disposition header
     * @see https://tools.ietf.org/html/rfc6266 for more details on the Content-Disposition header
     */
    private function prepareMultiPartContent(array $content): void
    {
        static $disallowedChars = ["\0", '"', "\r", "\n"];

        $contentParts = [];

        $data = $this->getData();
        if (!empty($data)) {
            foreach ($this->composeFormInputs($data) as $name => $value) {
                $name = str_replace($disallowedChars, '_', $name);
                $contentDisposition = 'Content-Disposition: form-data; name="' . $name . '"';
                $contentParts[] = implode("\r\n", [$contentDisposition, '', $value]);
            }
        }

        // process content parts :
        foreach ($content as $name => $contentParams) {
            $headers = [];
            $name = $this->getNameByAlias($name);
            $name = str_replace($disallowedChars, '_', $name);
            $contentDisposition = 'Content-Disposition: form-data; name="' . $name . '"';
            if (isset($contentParams['fileName'])) {
                $fileName = str_replace($disallowedChars, '_', (string) $contentParams['fileName']);
                $contentDisposition .= '; filename="' . $fileName . '"';
            }
            $headers[] = $contentDisposition;
            if (isset($contentParams['contentType'])) {
                $headers[] = 'Content-Type: ' . $contentParams['contentType'];
            } elseif (isset($contentParams['mimeType'])) {
                $headers[] = 'Content-Type: ' . $contentParams['mimeType'];
            }
            $contentParts[] = implode("\r\n", [implode("\r\n", $headers), '', $contentParams['content']]);
        }

        // generate safe boundary :
        do {
            $boundary = '---------------------' . md5(random_int(0, PHP_INT_MAX) . microtime());
        } while (preg_grep("/{$boundary}/", $contentParts));

        // add boundary for each part :
        array_walk($contentParts, function (&$part) use ($boundary): void {
            $part = "--{$boundary}\r\n{$part}";
        });

        // add final boundary :
        $contentParts[] = "--{$boundary}--";
        $contentParts[] = '';

        $this->getHeaders()->set('content-type', "multipart/form-data; boundary={$boundary}");
        $this->setContent(implode("\r\n", $contentParts));
    }

    /**
     * Composes given data as form inputs submitted values, taking in account nested arrays.
     * Converts `['form' => ['name' => 'value']]` to `['form[name]' => 'value']`.
     *
     * @return array
     */
    private function composeFormInputs(array $data, int|string $baseKey = '')
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (!empty($baseKey)) {
                $key = $baseKey . '[' . $key . ']';
            }
            if (is_array($value)) {
                $result = array_merge($result, $this->composeFormInputs($value, $key));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function composeHeaderLines()
    {
        $headers = parent::composeHeaderLines();
        if ($this->hasCookies()) {
            $headers[] = $this->composeCookieHeader();
        }
        return $headers;
    }

    /**
     * Sends this request.
     *
     * @throws Exception
     *
     * @return Response response instance.
     */
    public function send()
    {
        return $this->client->send($this);
    }

    /**
     * This method is invoked right before this request is sent.
     * The method will invoke [[Client::beforeSend()]] and trigger the [[EVENT_BEFORE_SEND]] event.
     */
    public function beforeSend(): void
    {
        $this->client->beforeSend($this);

        $event = new RequestEvent();
        $event->request = $this;
        $this->trigger(self::EVENT_BEFORE_SEND, $event);
        $this->_startTime = microtime(true);
    }

    /**
     * This method is invoked right after this request is sent.
     * The method will invoke [[Client::afterSend()]] and trigger the [[EVENT_AFTER_SEND]] event.
     *
     * @param Response $response received response instance.
     */
    public function afterSend($response): void
    {
        $this->_timeElapsed = microtime(true)-$this->_startTime;
        $this->client->afterSend($this, $response);

        $event = new RequestEvent();
        $event->request = $this;
        $event->response = $response;
        $this->trigger(self::EVENT_AFTER_SEND, $event);
    }

    /**
     * Return the response time in seconds
     *
     * @return float the seconds elapsed from request to response
     */
    public function responseTime()
    {
        return $this->_timeElapsed;
    }

    /**
     * {@inheritdoc}
     */
    public function toString()
    {
        if (!$this->isPrepared) {
            $this->prepare();
        }

        $result = strtoupper($this->getMethod()) . ' ' . $this->getFullUrl();

        $parentResult = parent::toString();
        if ($parentResult !== '') {
            $result .= "\n" . $parentResult;
        }

        return $result;
    }

    /**
     * @return string cookie header value.
     */
    private function composeCookieHeader()
    {
        $parts = [];
        foreach ($this->getCookies() as $cookie) {
            $parts[] = $cookie->name . '=' . $cookie->value;
        }
        return 'Cookie: ' . implode(';', $parts);
    }

    /**
     * @throws \yii\base\InvalidConfigException
     *
     * @return FormatterInterface message formatter instance.
     */
    private function getFormatter()
    {
        return $this->client->getFormatter($this->getFormat());
    }

    /**
     * Gets the outputFile property
     *
     * @return resource
     */
    public function getOutputFile()
    {
        return $this->_outputFile;
    }

    /**
     * Used with [[CurlTransport]] to set the file that the transfer should be written to
     *
     * @see CURLOPT_FILE
     *
     * @param resource $file
     *
     * @return $this self reference.
     */
    public function setOutputFile($file)
    {
        $this->_outputFile = $file;

        return $this;
    }

    /**
     * Generates unique alias for the content
     *
     * @param $name string
     *
     * @return string
     */
    private function generateContentAlias($name)
    {
        $alias = $name;
        while ($this->hasContent($alias)) {
            $alias = uniqid($name . '_');
        }

        return $alias;
    }

    /**
     * Adds alias to the content map
     *
     * @param $name string
     * @param $alias string
     */
    private function addAliasToContentMap($name, $alias): void
    {
        $this->_contentMap[$alias] = $name;
    }

    /**
     * Returns name by alias from the content map
     *
     * @param $alias string
     *
     * @return string
     */
    private function getNameByAlias(int|string $alias)
    {
        return $this->_contentMap[$alias] ?? $alias;
    }
}
