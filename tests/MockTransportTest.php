<?php

namespace yiiunit\extensions\httpclient;

use yii\httpclient\Client;
use yii\httpclient\MockTransport;
use yii\httpclient\Request;
use yii\httpclient\Response;

final class MockTransportTest extends TestCase
{
    private \yii\httpclient\Client $client;

    private \yii\httpclient\MockTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
        $this->client = new Client(['transport' => $this->transport]);
    }

    public function testResponseIsGivenByTheUser(): void
    {
        $request = $this->client->createRequest();
        $response = $this->client->createResponse();

        $this->transport->appendResponse($response);

        $this->assertSame($response, $this->client->send($request));
        $this->assertSame([$request], $this->transport->flushRequests());
    }

    public function testCallingSendWithoutSettingTheResponseRaiseException(): void
    {
        $this->expectException('yii\httpclient\Exception');

        $this->client->send($this->client->createRequest());
    }

    public function testBatchResponsesAreFlushedInGivenOrder(): void
    {
        $requests = [
            $this->client->createRequest(),
            $this->client->createRequest(),
        ];
        $responses = [
            $this->client->createResponse(),
            $this->client->createResponse(),
        ];

        foreach ($responses as $response) {
            $this->transport->appendResponse($response);
        }

        $this->assertSame($responses, $this->client->batchSend($requests));
        $this->assertSame($requests, $this->transport->flushRequests());
    }

    public function testParseResponseContentOnCustomResponseInjection(): void
    {
        $value = uniqid('foo_');

        $request = $this->client->createRequest();
        $response = new Response();
        $response->setContent(json_encode((object)[
            'custom_field' => $value,
        ], JSON_THROW_ON_ERROR));

        $this->transport->appendResponse($response);

        $actualResponse = $this->client->send($request);

        $this->assertSame($response, $actualResponse);

        $data = $actualResponse->getData();

        $this->assertArrayHasKey('custom_field', $data);
        $this->assertSame($value, $data['custom_field']);
    }
}
