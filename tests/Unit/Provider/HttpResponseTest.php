<?php

/**
 * Unit tests for HTTP response value object.
 *
 * @package CloudBridge\Tests\Unit\Provider
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Provider;

use CloudBridge\Provider\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

class HttpResponseTest extends TestCase
{
    public function test_rejects_associative_multi_value_header_arrays(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a list of strings');

        new HttpResponse(
            200,
            'ok',
            array(
                'x-test' => array(
                    'one' => 'a',
                    'two' => 'b',
                ),
            )
        );
    }

    public function test_accepts_list_multi_value_header_arrays(): void
    {
        $response = new HttpResponse(
            200,
            'ok',
            array(
                'x-test' => array('a', 'b'),
            )
        );

        $this->assertSame(array('a', 'b'), $response->headers['x-test']);
    }
}
