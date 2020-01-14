<?php

/*
 * Set basic environment settings
 */
error_reporting(-1);
ini_set('display_errors', 1);

/*
 * Include the Composer auto-loader
 */
include '../vendor/autoload.php';

// Load .env file
(new \Dotenv\Dotenv(__DIR__ . '/../'))->load();

if (! getenv('APPLICATION_ENV')) {
    throw new \RuntimeException("Application environment variable not set.");
}
define('APPLICATION_ENV', getenv('APPLICATION_ENV'));

$app = new \Slim\Slim(array(
    'mode' => APPLICATION_ENV,
    'debug' => true
));

/**
 * Bootstrap
 *
 * The hook 'slim.before' is called on the outset of every invocation.
 */
$app->hook('slim.before', function () use ($app) {
    \Import\Cli::init();
    /*
     * Get the response object and set the content type to JSON
     */
    $response = $app->response();
    $response['Content-Type'] = 'application/json';
});

/**
 * Spiders - Post a Items File
 *
 * Route:
 *  POST /spider/itemdata
 *
 * Parameters accepted:
 *  POST file  -- The spider file name
 */
$app->map('/spider/itemdata', function() use ($app) {
    $fileName = $app->request()->params('file');
    $vertical = $app->request()->params('vertical');

    echo '========================================' . $fileName;
    echo "\r\n";
    $Request = new \Import\Request\Adapter\Spider(array('file' => $fileName, 'vertical' => $vertical));

    $result = $Request->run();

    $resultsMessage = "Finished";
    if ($result['totals']['errors']) {
        $resultsMessage .= " with errors.";
    }
    else {
        $resultsMessage .= " without error.";
    }

    echo json_encode(array(
        'status'        => 'OK',
        'message'       => $resultsMessage
    ));

})->via('GET', 'POST');


$app->get('/environment', function () use ($app) {
    echo json_encode(array(
        'environment' => APPLICATION_ENV,
        'php_version' => PHP_VERSION
    ));
});

$app->get('/phpinfo', function () use ($app) {
    $app->response()->header("Content-Type", "text/html");
    phpinfo();
});

/**
 * 404 - Route not found
 *
 * This is the default 404 handler
 */
$app->notFound(function() use ($app) {
    /*
     * Error :(
     */
    $response = $app->response();
    $response->status(404);
    $response['Content-Type'] = 'application/json';

    echo json_encode(array(
        'status' => 'ERROR',
        'message' => 'The resource you requested could not be found.'
    ));
});


$app->error(function (\Exception $e) use ($app) {
    $response = $app->response();
    $response->status(500);
    $response['Content-Type'] = 'application/json';

    $result = array(
        'status' => 'ERROR',
        'message' => $e->getMessage()
    );

    $result['host']  = gethostname();
    $result['file']  = $e->getFile();
    $result['line']  = $e->getLine();
    $result['trace'] = $e->getTrace();

    echo @json_encode($result);
});

$app->run();
