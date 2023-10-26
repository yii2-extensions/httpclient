<?php

declare(strict_types=1);

namespace yiiunit\extensions\httpclient;

use yii\httpclient\Client;
use yii\httpclient\Response;
use yii\web\Cookie;

final class ResponseTest extends TestCase
{
    /**
     * @dataProvider yiiunit\extensions\httpclient\provider\Data::detectFormatByHeaders
     *
     * @param string $contentType
     * @param string $expectedFormat
     */
    public function testDetectFormatByHeaders($contentType, $expectedFormat): void
    {
        $response = new Response();
        $response->setHeaders(['Content-type' => $contentType]);
        $this->assertEquals($expectedFormat, $response->getFormat());
    }

    /**
     * @depends testDetectFormatByHeaders
     */
    public function testDetectFormatByHeadersMultiple(): void
    {
        $response = new Response();
        $response->setHeaders(['Content-type' => [
            'text/html; charset=utf-8',
            'application/json',
        ]]);
        $this->assertEquals(Client::FORMAT_JSON, $response->getFormat());
    }

    /**
     * @dataProvider yiiunit\extensions\httpclient\provider\Data::detectFormatByContent
     *
     * @param string $content
     * @param string $expectedFormat
     */
    public function testDetectFormatByContent($content, $expectedFormat): void
    {
        $response = new Response();
        $response->setContent($content);
        $this->assertEquals($expectedFormat, $response->getFormat());
    }

    /**
     * @dataProvider yiiunit\extensions\httpclient\provider\Data::parseBody
     *
     * @param string $content
     * @param string $format
     */
    public function testParseBody($content, $format, mixed $expected): void
    {
        $response = new Response([
            'client' => new Client(),
            'format' => $format,
        ]);

        $response->setContent($content);
        $this->assertSame($expected, $response->getData());
    }

    public function testGetStatusCode(): void
    {
        $response = new Response();

        $statusCode = 123;
        $response->setHeaders(['http-code' => $statusCode]);
        $this->assertEquals($statusCode, $response->getStatusCode());

        $statusCode = 123;
        $response->setHeaders(['http-code' => [
            $statusCode + 10,
            $statusCode,
        ]]);
        $this->assertEquals($statusCode, $response->getStatusCode());
    }

    public function testUnableToGetStatusCode(): void
    {
        $response = new Response();
        $this->expectException('\yii\httpclient\Exception');
        $this->expectExceptionMessage('Unable to get status code: referred header information is missing.');
        $response->setHeaders([]);
        $response->getStatusCode();
    }

    /**
     * @dataProvider yiiunit\extensions\httpclient\provider\Data::isOk
     *
     * @depends      testGetStatusCode
     *
     * @param int $statusCode
     * @param bool $isOk
     */
    public function testIsOk($statusCode, $isOk): void
    {
        $response = new Response();
        $response->setHeaders(['http-code' => $statusCode]);
        $this->assertEquals($isOk, $response->getIsOk());
    }

    public function testParseCookieHeader(): void
    {
        $response = new Response();
        $this->assertEquals(0, $response->getCookies()->count());

        $response = new Response();
        $response->setHeaders(['set-cookie' => 'name1=value1; path=/; httponly']);
        $this->assertEquals(1, $response->getCookies()->count());
        $cookie = $response->getCookies()->get('name1');
        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertEquals('value1', $cookie->value);
        $this->assertEquals('/', $cookie->path);
        $this->assertEquals(true, $cookie->httpOnly);

        $response = new Response();
        $response->setHeaders(['set-cookie' => 'COUNTRY=NA%2C195.177.208.1; expires=Thu, 23-Jul-2015 13:39:41 GMT; path=/; domain=.php.net']);
        $cookie = $response->getCookies()->get('COUNTRY');
        $this->assertInstanceOf(Cookie::class, $cookie);

        $response = new Response();
        $response->setHeaders(['set-cookie' => [
            'name1=value1; path=/; httponly',
            'name2=value2; path=/; httponly',
        ]]);
        $this->assertEquals(2, $response->getCookies()->count());

        // @see https://github.com/yiisoft/yii2-httpclient/issues/29
        $response = new Response();
        $response->setHeaders(['set-cookie' => 'extraParam=maxAge; path=/; httponly; Max-Age=3600']);
        $cookie = $response->getCookies()->get('extraParam');
        $this->assertInstanceOf(Cookie::class, $cookie);
    }

    public function testToString(): void
    {
        $response = new Response([
            'headers' => [
                'content-type' => 'text/html; charset=UTF-8',
            ],
            'content' => '<html>Content</html>',
        ]);

        $expectedResult = <<<EOL
Content-Type: text/html; charset=UTF-8

<html>Content</html>
EOL;
        $this->assertEqualsWithoutLE($expectedResult, $response->toString());
    }
}
