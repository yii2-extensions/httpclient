<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\httpclient;

final class MockTransport extends Transport
{
    /**
     * @var Request[]
     */
    private array $requests = [];
    /**
     * @var Response[]
     */
    private array $responses = [];

    public function appendResponse(Response $response): void
    {
        $this->responses[] = $response;
    }

    /**
     * @return Request[]
     */
    public function flushRequests()
    {
        $requests = $this->requests;
        $this->requests = [];

        return $requests;
    }

    /**
     * {@inheritdoc}
     */
    public function send($request)
    {
        if (empty($this->responses)) {
            throw new Exception('No Response available');
        }

        $nextResponse = array_shift($this->responses);
        if (null === $nextResponse->client) {
            $nextResponse->client = $request->client;
        }

        $this->requests[] = $request;

        return $nextResponse;
    }
}
