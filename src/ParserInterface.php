<?php

declare(strict_types=1);

namespace yii\httpclient;

/**
 * ParserInterface represents HTTP response message parser.
 */
interface ParserInterface
{
    /**
     * Parses given HTTP response instance.
     * @param Response $response HTTP response instance.
     * @return mixed parsed content data.
     */
    public function parse(Response $response);
}
