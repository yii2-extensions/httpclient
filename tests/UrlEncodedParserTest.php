<?php

declare(strict_types=1);

namespace yiiunit\extensions\httpclient;

use yii\httpclient\UrlEncodedParser;
use yii\httpclient\Response;

final class UrlEncodedParserTest extends TestCase
{
    public function testParse(): void
    {
        $document = new Response();
        $data = [
            'name1' => 'value1',
            'name2' => 'value2',
        ];
        $document->setContent(http_build_query($data));

        $parser = new UrlEncodedParser();
        $this->assertEquals($data, $parser->parse($document));
    }
}
