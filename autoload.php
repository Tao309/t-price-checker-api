<?php

if (!defined('init')) {exit;}

define('rootPath' , dirname(__FILE__));

date_default_timezone_set('Europe/Moscow');

spl_autoload_extensions('.php');
spl_autoload_register(function($class) {
    $extension = ".php";
    $file = rootPath . '/' . str_replace("\\", "/", $class) . $extension;

    if (!file_exists($file) || !is_readable($file)) {
        throw new \Exception('File ' . $class . ' is not found');
    }

    require_once($file);
});

try {
    $env = parse_ini_file('.env');
    foreach ($env as $key => $value) {
        putenv($key . '=' . $value);
    }
} catch (\Throwable $e) {
    throw new RuntimeException('Unable to parse the environment file.');
}

function processPdoException(\Exception\CustomPdoException $e): void
{
    $data = $e->getQueryPdo()->getPreparedData();
    $stmt = $e->getQueryPdo()->getStmt();

    echo "\n".$e->getRequestMethod().":\n";
    if ($e->getMessage()) {
        echo $e->getMessage() . "\n";
    }

    echo $stmt->queryString . "\n";
    print_r($stmt->errorInfo());
    if ($data) {
        print_r($data);
    }
    echo "Variables:\n";
    print_r($e->getQueryPdo()->getBindParams());
    exit;
}

function logMe($data)
{
    echo '<pre>';

    print_r($data);
    echo '</pre>';
}