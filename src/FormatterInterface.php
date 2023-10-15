<?php

declare(strict_types=1);

namespace yii\httpclient;

/**
 * FormatterInterface represents HTTP request message formatter.
 */
interface FormatterInterface
{
    /**
     * Formats given HTTP request message.
     * @param Request $request HTTP request instance.
     * @return Request formatted request.
     */
    public function format(Request $request);
}
