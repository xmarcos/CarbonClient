<?php
namespace xmarcos\Carbon;

use InvalidArgumentException;

class Client
{
    protected $stream;
    protected $namespace;

    /**
     * Creates an instance of the Carbon Client
     *
     * @param resource $stream A php stream that knows how to talk to Carbon
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream must be a resource.');
        }

        $this->stream = $stream;
    }

    /**
     * Sets the namespace used to prepend metric's paths
     *
     * @param string $namespace
     *
     * @return self
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $this->sanitizePath($namespace);

        return $this;
    }

    /**
     * Returns the current namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Sends a metric to Carbon.
     *
     * @see http://graphite.readthedocs.org/en/latest/feeding-carbon.html
     *
     * @param string    $path      Metric Path
     * @param int|float $value     Metric Value
     * @param int|null  $timestamp Metric Timestamp
     *
     * @return bool
     */
    public function send($path, $value, $timestamp = null)
    {
        if (!is_resource($this->stream)
            || !is_string($path)
            || empty($path)
            || !is_numeric($value)
        ) {
            return false;
        }

        $value     = (float) $value;
        $timestamp = is_numeric($timestamp) ? (int) $timestamp : time();
        $full_path = $this->sanitizePath(
            sprintf('%s.%s', $this->getNamespace(), $path)
        );

        $data = sprintf("%s %f %d\n", $full_path, $value, $timestamp);
        $sent = fwrite($this->stream, $data);

        return is_int($sent) && $sent === strlen($data);
    }

    /**
     * Sanitizes a path string
     *
     * Carbon stores metrics using dot delimited paths
     * {@link http://graphite.readthedocs.org/en/latest/feeding-carbon.html}
     *
     * Replaces:
     * - whitespace with undercores
     * - consecutive dots with a single dot.
     *
     * Removes:
     * - the wildcard character (used by graphite)
     * - leading and trailing dots
     *
     * @param string $path the path string to sanitize
     *
     * @return string The sanitized path string or an empty one.
     */
    public function sanitizePath($string)
    {
        if (!is_string($string) || empty($string)) {
            return '';
        }

        $replace = [
            '/\s+/'    => '_',
            '/\*{1,}/' => '',
            '/\.{2,}/' => '.',
            '/^\./'    => '',
            '/\.$/'    => '',
        ];

        return preg_replace(
            array_keys($replace),
            array_values($replace),
            trim($string)
        );
    }

    /**
     * Closes the stream when the object is destructed
     */
    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
