<?php

declare(strict_types=1);

namespace yii\httpclient;

use yii\base\BaseObject;

/**
 * UrlEncodedParser parses HTTP message content as 'application/x-www-form-urlencoded'.
 */
class UrlEncodedParser extends BaseObject implements ParserInterface
{
    /**
     * {@inheritdoc}
     */
    public function parse(Response $response)
    {
        $data = [];
        parse_str($response->getContent(), $data);
        return $data;
    }
}
