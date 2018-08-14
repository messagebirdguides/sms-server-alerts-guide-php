<?php

require_once "vendor/autoload.php";

// Create app
$app = new Slim\App;

// Load configuration with dotenv
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

// Get container
$container = $app->getContainer();

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

// Demo Regular Route
$app->get('/', function($request, $response) {
    return "Hello World :)";
});

// Demo Error Route
$app->get('/simulateError', function($request, $response) {
    $response->write("This should trigger error handling!");
    return $response->withStatus(500);
});

// Demo Error Route
$app->get('/makeLogEntries', function($request, $response) {
    $this->logger->debug("This is a test at debug level.");
    $this->logger->info("This is a test at info level.");
    $this->logger->warn("This is a test at warning level.");
    $this->logger->error("This is a test at error level.");

    return "You should see some log entries.";
});

// Start the application
$app->run();