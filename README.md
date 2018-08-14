# SMS Server Alerts
### ⏱ 30 min build time

## Why build SMS server alerts? 

For any online service advertising guaranteed uptime north of 99%, being available and reliable is extremely important. Therefore it is essential that any errors in the system are fixed as soon as possible, and the prerequisite for that is that error reports are delivered quickly to the engineers on duty. Providing those error logs via SMS ensures a faster response time compared to email reports and helps companies keep their uptime promises.

In this MessageBird Developer Guide, we will shouw you how to build an integration of SMS alerts into a PHP application that uses the [Monolog](https://packagist.org/packages/monolog/monolog) logging framework.

## Logging Primer

Logging is the default approach for gaining insights into running applications. To follow this example, you should understand two fundamental concepts of logging: levels and handlers.

**Levels** indicate the severity of the log item. Common log levels are _debug_, _info_, _warning_ and _error_. For example, a user trying to log in could have the _info_ level, a user entering the wrong password during login could be _warning_ as it's a potential attack, and a user not able to access the login form due to a subsystem failure would trigger an  _error_.

**Handlers** are different channels into which the logger writes its data. Typical channels are the console, files, log collection servers, and services or communication channels such as email, SMS or push notifications.

It's possible and common to set up multiple kinds of handlers for the same logger but set different levels for each. In our example, we write entries of all severities to the console and everything above _info_ to a log file while sending SMS notifications only for log items that have the _error_ level (or higher, when using more levels).

## Getting Started

The sample application is built in PHP and, as mentioned above, uses Monolog as the logging library. We have also included a middleware for the [Slim](https://www.slimframework.com/) framework to demonstrate web application request logging. Many other frameworks already have some built-in Monolog support.

Make sure you have PHP installed on your machine. If you're using a Mac, PHP is already installed. For Windows, you can [get it from windows.php.net](https://windows.php.net/download/). Linux users, please check your system's default package manager. You also need Composer, which is available from [getcomposer.org](https://getcomposer.org/download/).

We've provided the source code in the [MessageBird Developer Guides GitHub repository](https://github.com/messagebirdguides/sms-server-alerts-guide-php), so you can either clone the sample application with git or download a ZIP file with the code to your computer.

To install Monolog, the [MessageBird SDK for PHP](https://github.com/messagebird/php-rest-api), and the framework, open a console pointed at the directory into which you've stored the sample code and run the following command:

````bash
composer install
````

## Building a MessageBird Log Handler

Monolog enables developers to build custom handlers and use them with the logger just like built-in handlers such as the StreamHandler. The easiest way to build a new handler is writing a new class that extends `Monolog\Handler\AbstractProcessingHandler`. It needs to implement a constructor for initialization as well as the `write()` method. 

We have created one in the file `MessageBirdHandler.php`.

Our SMS alert functionality needs the following information to work:
- A functioning MessageBird API key.
- An originator, i.e., a sender ID for the messages it sends.
- One or more recipients, i.e., the phone numbers of the system engineers that should be informed about problems with the server.

To keep the custom handler self-contained and independent from the way the application wants to provide the configuration we take all this as an options array in our constructor. Here's the code:

````php
class MessageBirdHandler extends AbstractProcessingHandler {

    private $messagebird;
    private $message;

    public function __construct(array $options, $level = Logger::DEBUG, bool $bubble = true) {
        if (!isset($options['apiKey']) || !isset($options['originator']) || !isset($options['recipients']))
            throw new \Exception("Incomplete configuration parameters. Required: apiKey, originator, recipients");

        $this->messagebird = new Client($options['apiKey']);
        $this->message = new Message;
        $this->message->originator = $options['originator'];
        $this->message->recipients = $options['recipients'];

        parent::__construct($level, $bubble);
    }
````

As you can see, the constructor first verifies that all necessary configuration has been provided and then loads and initializes the MessageBird SDK client (`MessageBird\Client`) with the API key. It also initializes a `MessageBird\Objects\Message` object as a template for all messages and assigns the originator and recipients to it. Both are stored as members of the handler object. Finally, it calls the parent constructor with the standard handler parameters `$level` and `$bubble`.

Now, in the `write()` method, we shorten the formatted log entry, to make sure it fits in the 160 characters of a single SMS so that notifications won't incur unnecessary costs or break limits, and assign it as the body of our message object which we prepared in the constructor:

````php
    protected function write(array $record) {
        // Shorten log entry
        $this->message->body = (strlen($record['formatted']) > 140)
            ? substr($record['formatted'], 0, 140) . ' ...'
            : $record['formatted'];
````

Finally, we call `messages->create()` to send an SMS notification. This method takes one parameter, our message object:

````php
    // Send notification with MessageBird SDK
    try {
        $this->messagebird->messages->create($this->message);
    } catch (Exception $e) {
        error_log(get_class($e).": ".$e->getMessage());
    }
}
````

In the catch block for exceptions thrown by the MessageBird SDK, we only log the response to the console and don't do anything else (we can't record it with Monolog here because then we might get stuck in an infinite loop ...).

## Initializing Monolog with our Handler

For our sample application, we use [Dotenv](https://packagist.org/packages/vlucas/phpdotenv) to load configuration data from a `.env` file.

Copy `env.example` to `.env` and store your information:

````
MESSAGEBIRD_API_KEY=YOUR-API-KEY
MESSAGEBIRD_ORIGINATOR=Monolog
MESSAGEBIRD_RECIPIENTS=31970XXXXXXX,31970YYYYYYY
````

You can create or retrieve an API key [in your MessageBird account](https://dashboard.messagebird.com/en/developers/access). The originator can be a phone number you registered through MessageBird or, for countries that support it, an alphanumeric sender ID with at most 11 characters. You can provide one or more comma-separated phone numbers as recipients.

In `index.php`, the primary file of our application, we start of by including initializing the framework and Dotenv. Then, we add Monolog to Slim's dependency injection container:

````php
// Initialize Logger
$container['logger'] = function() {
    $logger = new Monolog\Logger('App');
    $logger->pushHandler(new Monolog\Handler\ErrorLogHandler(0, Monolog\Logger::DEBUG));
    $logger->pushHandler(new Monolog\Handler\StreamHandler('app.log', Monolog\Logger::INFO));
    $logger->pushHandler(new MessageBirdHandler([
        'apiKey' => getenv('MESSAGEBIRD_API_KEY'),
        'originator' => getenv('MESSAGEBIRD_ORIGINATOR'),
        'recipients' => explode(',', getenv('MESSAGEBIRD_RECIPIENTS'))
    ], Monolog\Logger::ERROR));
    
    return $logger;
};
````

The `Monolog\Logger` constructor takes a name for the logger as its parameter. Using the `pushHandler()` method, you can define one or more handlers. As you see in the example, we have added three of them:
- The `Monolog\Handler\ErrorLogHandler`, which logs everything starting with the _debug_ level to the default error log, which means writing log entries to the console output of PHP's default web server.
- The `Monolog\Handler\StreamHandler`, which logs _info_ and higher into a file called `app.log`.
- Our previously created `MessageBirdHandler` with all the configuration options taken from our environment file or environment variables using `getenv()`. We convert the comma-separated recipients into an array with `explode(',')`. This handler only sees log events with the _error_ level.

## Creating a Slim Middleware for Request Logging

After setting up a Slim app, you can call `$app->add()` to specify middleware. Middlewares are extensions to Slim that touch each request, and they are useful for globally required functionality such as authentication or, in our example, logging:

````php
// Set up middleware to log requests and responses
$app->add(function($request, $response, callable $next) {
    $response = $next($request, $response);
    $code = $response->getStatusCode();
    $logLine = '[' . $code . '] ' . $request->getMethod() . ' ' . (string)$request->getUri();
    if ($code >= 500)
        $this->logger->error($logLine);
    elseif ($code < 500 && $code >= 400)
        $this->logger->warn($logLine);
    else
        $this->logger->info($logLine);

    return $response;
});
````

Our Middleware does the following:
- It calls `$next()` to process the request and other middleware first so that logging happens afterward.
- It formulates a log line which contains the status code, request method, and request URI. Feel free to add more information here.
- Depending on the status code, it calls the logging method for the appropriate level. Codes 500 and higher, used for server errors, call `error()`. Codes between 400 and 500, used for client errors, call `warn()`. Other codes that typically indicate success or desired behavior call `info()`.

## Using the Logger directly

You are not limited to using your Monolog setup for automated request and response logging from the Slim middleware. If you want to log any error or information from your application logic you can always call the respective method on the `$this->logger` object.

We have added some code to demonstrate that:

````php
// Demo Error Route
$app->get('/makeLogEntries', function($request, $response) {
    $this->logger->debug("This is a test at debug level.");
    $this->logger->info("This is a test at info level.");
    $this->logger->warn("This is a test at warning level.");
    $this->logger->error("This is a test at error level.");

    return "You should see some log entries.";
});
````

## Testing the Application

We have created two Slim test routes to simulate a 200 success response and a 500 server response. To run the application, go to your console and type the following command:

````bash
php -S 0.0.0.0:8080 index.php
````

Navigate your browser to http://localhost:8080/. For the successful request, you will see a log entry on the console and in the file `app.log`.

Now, open http://localhost:8080/simulateError. For the error request, you should not only see a log entry on the console and in the file, but also a notification should arrive on your phone.

To receive even more log entries, go to http://localhost:8080/makeLogEntries. That creates multiple log entries of different severity levels, at least one of which pings your phone.

## Nice work!

And that's it. You've learned how to log with Winston and express-winston, create a custom MessageBird transport. You can now take these elements and integrate them into a Node.js production application. Don't forget to download the code from the [MessageBird Developer Guides GitHub repository](https://github.com/messagebirdguides/sms-server-alerts-guide-php).

## Next steps

Want to build something similar but not quite sure how to get started? Please feel free to let us know at support@messagebird.com, we'd love to help!
