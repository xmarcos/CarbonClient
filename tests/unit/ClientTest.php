<?php
namespace xmarcos\Carbon;

use ErrorException;
use ReflectionClass;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;

class ClientTest extends PHPUnit_Framework_TestCase
{
    private $stream;

    protected function setUp()
    {
        $this->stream = fopen('php://memory', 'r+');
        $this->assertTrue(is_resource($this->stream));
    }

    public function testConstruct()
    {
        $carbon = new Client($this->stream);
        $this->assertInstanceOf('xmarcos\Carbon\Client', $carbon);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructThrowsException()
    {
        $carbon = new Client(null);
    }

    public function testDestructClosesStream()
    {
        $carbon = new Client($this->stream);
        $this->assertTrue(is_resource($this->stream));

        unset($carbon);
        $this->assertFalse(is_resource($this->stream));
    }

    /**
     * @dataProvider providerNamespaces
     */
    public function testSetNamespace($namespace, $expected)
    {
        $carbon = new Client($this->stream);
        $carbon->setNamespace($namespace);
        $this->assertEquals($expected, $carbon->getNamespace());
    }

    public function testThrowExceptionsSetting()
    {
        $carbon = new Client($this->stream);

        $throw_exceptions = (new ReflectionClass($carbon))->getProperty('throw_exceptions');
        $throw_exceptions->setAccessible(true);
        $this->assertFalse($throw_exceptions->getValue($carbon));

        $carbon->throwExceptions(true);
        $this->assertTrue($throw_exceptions->getValue($carbon));
    }

    public function providerNamespaces()
    {
        return [
            ['some.namespace', 'some.namespace'],
            [' some.name space.with spaces ', 'some.name_space.with_spaces'],
            ['.leading_dot.namespace', 'leading_dot.namespace'],
            ['trailing_dot..namespace.', 'trailing_dot.namespace'],
            ['..consecutive_dots..namespace..', 'consecutive_dots.namespace'],
            ['some.namespace.*', 'some.namespace'],
            [' some.namespace*..', 'some.namespace'],
            ['', ''],
            [null, ''],
            [1.2, ''],
        ];
    }

    /**
     * @dataProvider providerMetrics
     */
    public function testSend(
        $namespace,
        $metric,
        $full_path,
        $value,
        $timestamp,
        $expected_sent
    ) {
        $carbon = new Client($this->stream);
        $carbon->setNamespace($namespace);

        $sent = $carbon->send($metric, $value, $timestamp);
        $this->assertEquals($expected_sent, $sent);

        if ($sent) {
            rewind($this->stream);
            $expected_metric    = sprintf("%s %f %d\n", $full_path, $value, $timestamp);
            $actual_metric_sent = fgets($this->stream);

            $this->assertEquals($expected_metric, $actual_metric_sent);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSendErrorOnInvalidPath()
    {
        $carbon = new Client($this->stream);

        $sent = $carbon->send(['x'], 1);
        $this->assertFalse($sent);

        $carbon->throwExceptions(true);
        $carbon->send(['x'], 1);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSendErrorOnInvalidValue()
    {
        $carbon = new Client($this->stream);

        $sent = $carbon->send('x', 'string');
        $this->assertFalse($sent);

        $carbon->throwExceptions(true);
        $carbon->send('x', []);
    }

    /**
     * @expectedException ErrorException
     */
    public function testSendErrorOnClosedStream()
    {
        $carbon = new Client($this->stream);

        // simulate network failure
        fclose($this->stream);

        $sent = $carbon->send('metric', 1);
        $this->assertFalse($sent);

        $carbon->throwExceptions(true);
        $sent = $carbon->send('metric', 1);
    }

    public function testSendErrorOnReadOnlyStream()
    {
        $read_only_stream = fopen('php://memory', 'r');
        $carbon = new Client($read_only_stream);

        $sent = $carbon->send('metric', 1);
        $this->assertFalse($sent);
    }

    public function providerMetrics()
    {
        return [
            [
                'some.namespace',             // namespace
                'some.metric',                // metric
                'some.namespace.some.metric', // full_path
                10,                           // value
                time(),                       // timestamp
                true,                         // expected_sent
            ],
            [
                '.some...namespace.',
                'some.other metric',
                'some.namespace.some.other_metric',
                0.345,
                time(),
                true,
            ],
            [
                'namespace',
                'metric',
                '',
                null,
                time(),
                false,
            ]
        ];
    }

    protected function tearDown()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
