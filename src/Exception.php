<?php

declare(strict_types=1);

namespace yii\httpclient;

/**
 * Exception represents an exception that is caused during HTTP requests.
 */
class Exception extends \yii\base\Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'HTTP Client Exception';
    }
}
