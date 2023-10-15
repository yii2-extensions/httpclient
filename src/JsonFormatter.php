<?php

declare(strict_types=1);

namespace yii\httpclient;

use yii\base\BaseObject;
use yii\helpers\Json;

/**
 * JsonFormatter formats HTTP message as JSON.
 */
class JsonFormatter extends BaseObject implements FormatterInterface
{
    /**
     * @var int the encoding options. For more details please refer to
     * <https://www.php.net/manual/en/function.json-encode.php>.
     */
    public int $encodeOptions = 0;

    /**
     * {@inheritdoc}
     */
    public function format(Request $request)
    {
        $request->getHeaders()->set('Content-Type', 'application/json; charset=UTF-8');

        if ($request->getData() !== null) {
            $request->setContent(Json::encode($request->getData(), $this->encodeOptions));
        }

        return $request;
    }
}
