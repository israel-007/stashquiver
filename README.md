# APIStash Library

APIStash is a versatile PHP library designed to streamline API interactions. It offers robust features such as caching, rate limiting, error handling, and flexible HTTP request handling, making it an ideal choice for developers working with external APIs.

With APIStash, you can:
- Simplify API requests using a chainable, user-friendly interface.
- Cache API responses to reduce redundant API calls.
- Enforce rate limits to comply with API quotas.
- Retry failed requests with a configurable fallback mechanism.
- Seamlessly integrate with any PHP project using Composer.

---

## Features

- **Chainable API Requests**: Easily build and send API requests with a fluid interface.
- **Caching**: Cache API responses locally to minimize external requests.
- **Rate Limiting**: Prevent excessive requests to ensure compliance with API limits.
- **Error Handling**: Automatically retry failed requests with configurable fallback responses.
- **Guzzle Integration**: Use Guzzle for HTTP requests when available, with fallback options.
- **No Vendor Lock-In**: Customize or disable components like caching and rate limiting as needed.

## Installation

APIStash is available via Composer. To install it, run the following command:

```bash
composer require stashquiver/stashquiver

```

## Requirements

- **PHP 7.4 or higher**
- **Composer**

### Step 3: Basic Usage Example

#### Example

```markdown
## Basic Usage

Here's a quick example to demonstrate how to use APIStash to make a GET request:

```php

use StashQuiver\Requests;

// Create an instance of Requests
$requests = new Requests();

try {
    // Build and send the request
    $response = $requests
        ->url('https://api.example.com/data')
        ->method('GET')
        ->params(['param1' => 'value1']) // Optional query parameters
        ->headers(['Custom-Header' => 'HeaderValue']) // Optional headers
        ->send();

    // Output the raw API response
    echo $response;

} catch (\Exception $e) {
    // Handle any errors
    echo "Request failed: " . $e->getMessage();
}

---