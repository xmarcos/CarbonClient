<?php
namespace xmarcos\Carbon;

use Exception;
use ErrorException;
use InvalidArgumentException;

class Client
{
    protected $stream;
    protected $namespace;
    protected $throw_exceptions;

    /**
     * Creates an instance of the Carbon Client
     *
     * @param resource $stream A php stream that knows how to talk to Carbon.
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream must be a resource.');
        }

        $this->stream = $stream;
        $this->throwExceptions(false);
    }

    /**
     * Controls whether failed calls to Carbon will throw an Exception.
     *
     * @see send()
     *
     * @param boolean $throw
     *
     * @return self
     */
    public function throwExceptions($throw = true)
    {
        $this->throw_exceptions = (bool) $throw;

        return $this;
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
     * @throws ErrorException If $this->throw_exceptions is true
     * @return bool
     */
    public function send($path, $value, $timestamp = null)
    {
        $result    = false;
        $exception = null;

        set_error_handler(function ($code, $message, $file = null, $line = 0) {
            throw new ErrorException($message, $code, null, $file, $line);
        });

        try {
            if (!is_string($path) || empty($path)) {
                throw new InvalidArgumentException('$path must be a non-empty string');
            }

            if (!is_numeric($value)) {
                throw new InvalidArgumentException(
                    sprintf('$value must be of type int|float, %s given.', gettype($value))
                );
            }

            $value     = (float) $value;
            $timestamp = is_numeric($timestamp) ? (int) $timestamp : time();
            $full_path = $this->sanitizePath(
                sprintf('%s.%s', $this->getNamespace(), $path)
            );

            $data   = sprintf("%s %f %d\n", $full_path, $value, $timestamp);
            $sent   = fwrite($this->stream, $data);
            $result = is_int($sent) && $sent === strlen($data);
        } catch (Exception $e) {
            $exception = $e;
        }
        restore_error_handler();

        if (!empty($exception) && $this->throw_exceptions) {
            throw $exception;
        }

        return $result;
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
     *
     * @param string $string
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
