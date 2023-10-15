<?php

declare(strict_types=1);

namespace yii\httpclient;

use Yii;
use yii\base\BaseObject;

/**
 * UrlEncodedFormatter formats HTTP message as 'application/x-www-form-urlencoded'.
 */
class UrlEncodedFormatter extends BaseObject implements FormatterInterface
{
    /**
     * @var int URL encoding type.
     * Possible values are:
     *  - PHP_QUERY_RFC1738 - encoding is performed per 'RFC 1738' and the 'application/x-www-form-urlencoded' media type,
     *    which implies that spaces are encoded as plus (+) signs. This is most common encoding type used by most web
     *    applications.
     *  - PHP_QUERY_RFC3986 - then encoding is performed according to 'RFC 3986', and spaces will be percent encoded (%20).
     *    This encoding type is required by OpenID and OAuth protocols.
     */
    public $encodingType = PHP_QUERY_RFC1738;
    /**
     * @var string the content charset. If not set, it will use the value of [[\yii\base\Application::charset]].
     */
    public $charset;


    /**
     * {@inheritdoc}
     */
    public function format(Request $request)
    {
        if (($data = $request->getData()) !== null) {
            $content = http_build_query((array)$data, '', '&', $this->encodingType);
        }

        if (strcasecmp('GET', $request->getMethod()) === 0) {
            if (!empty($content)) {
                $request->setFullUrl(null);
                $url = $request->getFullUrl();
                $url .= (!str_contains($url, '?')) ? '?' : '&';
                $url .= $content;
                $request->setFullUrl($url);
            }
            return $request;
        }

        $charset = $this->charset ?? Yii::$app->charset;
        $charset = $charset ? '; charset=' . $charset : '';
        $request->getHeaders()->set('Content-Type', 'application/x-www-form-urlencoded' . $charset);

        if (isset($content)) {
            $request->setContent($content);
        }

        if (!isset($content) && !isset($request->getOptions()[CURLOPT_INFILE])) {
            $request->getHeaders()->set('Content-Length', '0');
        }

        return $request;
    }
}
