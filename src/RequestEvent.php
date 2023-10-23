<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\httpclient;

use yii\base\Event;

/**
 * RequestEvent represents the event parameter used for an request events.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 *
 * @since 2.0.1
 */
class RequestEvent extends Event
{
    /**
     * @var Request|null related HTTP request instance.
     */
    public Request|null $request = null;
    /**
     * @var Response|null related HTTP response.
     * This field will be filled only in case some response is already received, e.g. after request is sent.
     */
    public Response|null $response = null;
}
