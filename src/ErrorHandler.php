<?php
namespace StashQuiver;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ErrorHandler
{
    private $logger;
    private $retryLimit;
    private $fallbackResponse;

    public function __construct(LoggerInterface $logger = null, $retryLimit = 3, $fallbackResponse = null)
    {
        // Use a null logger if no logger is provided (avoids errors when logging isn't set up)
        $this->logger = $logger ?: new NullLogger();
        $this->retryLimit = $retryLimit;
        $this->fallbackResponse = $fallbackResponse;
    }

    /**
     * Logs an error.
     *
     * @param string $message Error to log.
     */
    public function logError($message)
    {
        $this->logger->error($message);
    }

    /**
     * General error handling, optionally logging errors.
     *
     * @param \Exception $exception Exception to handle.
     * @param string $context Optional context for the error.
     */
    public function handleError(\Exception $exception, $context = '')
    {
        $message = "Error in context '$context': " . $exception->getMessage();
        $this->logError($message);
    }

    /**
     * Retry a function a specified number of times.
     *
     * @param callable $callback Function to retry.
     * @param int|null $retryLimit Number of retry attempts.
     * @return mixed Result of the function or fallback response.
     */
    public function retry(callable $callback, $retryLimit = null)
    {
        $attempts = $retryLimit ?? $this->retryLimit;
        $lastException = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;
                $this->logError("Attempt " . ($i + 1) . " failed: " . $e->getMessage());
            }
        }

        // Log final failure and return fallback response if set
        $this->logError("All retry attempts failed. Returning fallback response.");
        return $this->fallbackResponse ?? null;
    }

    /**
     * Provides a default fallback response when an API call fails completely.
     *
     * @return mixed The fallback response, or null if not set.
     */
    public function getFallbackResponse()
    {
        return $this->fallbackResponse;
    }
}
