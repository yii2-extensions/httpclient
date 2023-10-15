<?php

declare(strict_types=1);

namespace yii\httpclient;

use yii\base\Event;

/**
 * RequestEvent represents the event parameter used for an request events.
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
