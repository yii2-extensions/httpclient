<?php

declare(strict_types=1);

namespace yiiunit\extensions\httpclient\Provider;

use yii\httpclient\Client;
use yii\httpclient\JsonFormatter;
use yii\httpclient\JsonParser;
use yii\httpclient\UrlEncodedFormatter;
use yii\httpclient\UrlEncodedParser;
use yii\httpclient\XmlFormatter;
use yii\httpclient\XmlParser;

final class Data
{
    public static function defaultFormatters(): array
    {
        return [
            [Client::FORMAT_JSON, JsonFormatter::className()],
            [Client::FORMAT_URLENCODED, UrlEncodedFormatter::className()],
            [Client::FORMAT_RAW_URLENCODED, UrlEncodedFormatter::className()],
            [Client::FORMAT_XML, XmlFormatter::className()],
        ];
    }

    public static function defaultParsers(): array
    {
        return [
            [Client::FORMAT_JSON, JsonParser::className()],
            [Client::FORMAT_URLENCODED, UrlEncodedParser::className()],
            [Client::FORMAT_RAW_URLENCODED, UrlEncodedParser::className()],
            [Client::FORMAT_XML, XmlParser::className()],
        ];
    }

    public static function detectFormatByContent(): array
    {
        return [
            [
                'name1=value1&name2=value2',
                Client::FORMAT_URLENCODED
            ],
            [
                '{"name1":"value1", "name2":"value2"}',
                Client::FORMAT_JSON
            ],
            [
                '[{"name1":"value1", "name2":"value2"},{"name1":"value3", "name2":"value4"}]',
                Client::FORMAT_JSON
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?><root></root>',
                Client::FORMAT_XML
            ],
            [
                'access_token=begin|end',
                Client::FORMAT_URLENCODED
            ],
            [
                'some-plain-string',
                null
            ],
            [   // do not detect HTML as XML
                <<<HTML
<!DOCTYPE html>
<html>
<head><title>Some title</title></head>
<body>some text</body>
</html>
HTML
                ,
                null
            ],
        ];
    }

    public static function detectFormatByHeaders(): array
    {
        return [
            [
                'application/x-www-form-urlencoded',
                Client::FORMAT_URLENCODED
            ],
            [
                'application/json',
                Client::FORMAT_JSON
            ],
            [
                'text/xml',
                Client::FORMAT_XML
            ],
        ];
    }

    public static function getFullUrl(): array
    {
        return [
            [
                'http://some-domain.com',
                'test/url',
                'http://some-domain.com/test/url'
            ],
            [
                'http://some-domain.com',
                'http://another-domain.com/test',
                'http://another-domain.com/test',
            ],
            [
                'http://some-domain.com',
                ['test/url', 'param1' => 'name1'],
                'http://some-domain.com/test/url?param1=name1'
            ],
            [
                'http://some-domain.com?base-param=base',
                null,
                'http://some-domain.com?base-param=base',
            ],
            [
                'http://some-domain.com?base-param=base',
                ['param1' => 'name1'],
                'http://some-domain.com?base-param=base&param1=name1',
            ],
            [
                'http://some-domain.com/',
                '/test/url',
                'http://some-domain.com/test/url'
            ],
            [
                'http://some-domain.com/',
                'test/url',
                'http://some-domain.com/test/url'
            ],
            [
                'http://some-domain.com',
                '/test/url',
                'http://some-domain.com/test/url'
            ],
        ];
    }

    public static function isOk(): array
    {
        return [
            [100, false],
            [200, true],
            [201, true],
            [226, true],
            [400, false],
        ];
    }

    public static function parseBody(): array
    {
        return [
            [
                'name=value&age=30',
                Client::FORMAT_URLENCODED,
                ['name' => 'value', 'age' => '30'],
            ],
            [
                '0',
                Client::FORMAT_JSON,
                0,
            ],
            [
                '"0"',
                Client::FORMAT_JSON,
                '0',
            ],
            [
                'null',
                Client::FORMAT_JSON,
                null,
            ],
            [
                'false',
                Client::FORMAT_JSON,
                false,
            ],
        ];
    }
}
