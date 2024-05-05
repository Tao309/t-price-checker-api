<?php

if (!defined('init')) {exit;}

define('rootPath' , dirname(__FILE__));

date_default_timezone_set('Europe/Moscow');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    echo $errno . ' ' . $errstr . ' ' .  $errfile . ' ' .  $errline . PHP_EOL;
});

spl_autoload_extensions('.php');
spl_autoload_register(function($class) {
    $extension = ".php";
    $file = rootPath . '/' . str_replace("\\", "/", $class) . $extension;

    if (!file_exists($file) || !is_readable($file)) {
        throw new \Exception('File ' . $class . ' is not found');
    }

    require_once($file);
});

function expect($condition, $message, $code = 0) {
    if (!$condition) {
        throw new \Exception\ResponseException($message, $code);
    }
}

function processPdoException(string $type, array $variables, array $data, PDOStatement $stmt, \Exception $e): void
{
    echo "\n".$type.":\n";
    if ($e->getMessage()) {
        echo $e->getMessage() . "\n";
    }

    echo $stmt->queryString . "\n";
    print_r($stmt->errorInfo());
    if ($data) {
        print_r($data);
    }
    echo "Variables:\n";
    print_r($variables);
    exit;
}