<?php

declare(strict_types=1);

namespace yii\httpclient;

use yii\base\BaseObject;
use yii\helpers\Json;

/**
 * JsonParser parses HTTP message content as JSON.
 */
class JsonParser extends BaseObject implements ParserInterface
{
    /**
     * @var bool whether to return objects in terms of associative arrays.
     */
    public $asArray = true;


    /**
     * {@inheritdoc}
     */
    public function parse(Response $response)
    {
        return Json::decode($response->getContent(), $this->asArray);
    }
}
