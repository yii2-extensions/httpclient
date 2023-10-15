<?php

declare(strict_types=1);

use yii\httpclient\Exception;

final class ExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testGetName()
    {
        $exception = new Exception('Test Exception');

        $this->assertSame('HTTP Client Exception', $exception->getName());
    }
}
