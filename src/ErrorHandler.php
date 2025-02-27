<?php
namespace StashQuiver;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ErrorHandler
{
    private $logger;
    private $retryLimit;
    private $fallbackResponse;
    private $retryDelay;
    private $backoffStrategy;

    /**
     * Initializes the ErrorHandler.
     * 
     * @param LoggerInterface|null $logger Logging instance (PSR-3 compatible).
     * @param int $retryLimit Number of retries before returning fallback response.
     * @param mixed $fallbackResponse Default fallback response if retries fail.
     * @param int $retryDelay Initial retry delay in seconds (default: 1 second).
     * @param string $backoffStrategy Backoff strategy ('fixed', 'exponential', 'linear').
     */
    public function __construct(
        LoggerInterface $logger = null,
        int $retryLimit = 3,
        $fallbackResponse = null,
        int $retryDelay = 1,
        string $backoffStrategy = 'exponential'
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->retryLimit = $retryLimit;
        $this->fallbackResponse = $fallbackResponse;
        $this->retryDelay = $retryDelay;
        $this->backoffStrategy = $backoffStrategy;
    }

    /**
     * Logs an error.
     * 
     * @param string $message Error message.
     * @param string $level Log level (error, warning, debug).
     */
    public function logError($message, $level = 'error')
    {
        switch ($level) {
            case 'warning':
                $this->logger->warning($message);
                break;
            case 'debug':
                $this->logger->debug($message);
                break;
            default:
                $this->logger->error($message);
                break;
        }
    }

    /**
     * Handles an exception, logging it with an optional context.
     * 
     * @param \Exception $exception Exception to handle.
     * @param string $context Optional context message.
     */
    public function handleError(\Exception $exception, $context = '')
    {
        $message = "Error in context '$context': " . $exception->getMessage();
        $this->logError($message);
    }

    /**
     * Retries a callable function up to a defined limit, with delay and backoff strategies.
     * 
     * @param callable $callback Function to retry.
     * @param int|null $retryLimit Custom retry limit (overrides constructor setting).
     * @return mixed Result of function or fallback response.
     */
    public function retry(callable $callback, int $retryLimit = null)
    {
        $attempts = $retryLimit ?? $this->retryLimit;
        $delay = $this->retryDelay;
        $lastException = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $result = $callback();
                if ($i > 1) {
                    $this->logError("✅ Retry succeeded on attempt $i", 'debug');
                }
                return $result;
            } catch (\Exception $e) {
                $lastException = $e;
                $this->logError("❌ Attempt $i failed: " . $e->getMessage());

                if ($i < $attempts) {
                    sleep($delay);
                    $delay = $this->calculateNextDelay($delay);
                }
            }
        }

        // Log final failure and return fallback response
        $this->logError("⚠️ All retry attempts failed. Returning fallback response.");
        return $this->fallbackResponse ?? $lastException;
    }

    /**
     * Calculates the next retry delay based on the chosen backoff strategy.
     * 
     * @param int $currentDelay Current delay in seconds.
     * @return int Next delay in seconds.
     */
    private function calculateNextDelay(int $currentDelay): int
    {
        switch ($this->backoffStrategy) {
            case 'linear':
                return $currentDelay + $this->retryDelay;
            case 'exponential':
                return $currentDelay * 2;
            case 'fixed':
            default:
                return $this->retryDelay;
        }
    }

    /**
     * Sets a custom retry delay.
     * 
     * @param int $seconds Delay in seconds.
     * @return self
     */
    public function setRetryDelay(int $seconds)
    {
        $this->retryDelay = $seconds;
        return $this;
    }

    /**
     * Sets the backoff strategy.
     * 
     * @param string $strategy 'fixed', 'linear', or 'exponential'.
     * @return self
     */
    public function setBackoffStrategy(string $strategy)
    {
        $this->backoffStrategy = in_array($strategy, ['fixed', 'linear', 'exponential'])
            ? $strategy
            : 'exponential';
        return $this;
    }

    /**
     * Sets the fallback response.
     * 
     * @param mixed $response Fallback response.
     * @return self
     */
    public function setFallbackResponse($response)
    {
        $this->fallbackResponse = $response;
        return $this;
    }

    public function getFallbackResponse()
    {
        return $this->fallbackResponse;
    }
}
