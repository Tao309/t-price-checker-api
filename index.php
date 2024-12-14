<?php

use Core\Config;
use Core\Cache;

define('init', true);

require_once ('autoload.php');
require_once ('error_handler.php');

ob_start();

try {
    try {
        $env = parse_ini_file('.env');
        foreach ($env as $key => $value) {
            putenv($key . '=' . $value);
        }
    } catch (\Throwable $e) {
        throw new RuntimeException('Unable to parse the environment file.');
    }

    Config::checkHeaders();

    $tResponse = new tResponse();
    $cache = new Cache();

    $tResponse->checkPostData($_POST);

    Config::initShopType($_POST['shop_type']);
    Config::initSourceProductTypes();

    $actionMethod = $_POST['action'];
    $data = json_decode($_POST['data'], true);

    $storage = new Storage($tResponse);

    if (!method_exists($storage, $actionMethod)) {
        throw new RuntimeException("The called method " . $actionMethod . " is not exists.");
    }

    $reflection = new ReflectionMethod($storage, $actionMethod);
    if (!$reflection->isPublic()) {
        throw new RuntimeException("The called method " . $actionMethod . " is not public.");
    }

    $storage->preDispatch($actionMethod, $data);
    $storage->$actionMethod($data);
    $storage->postDispatch($actionMethod, $data);
} catch(\Exception\CustomPdoException $e) {
    processPdoException($e);
} catch(\Throwable $e) {
    $tResponse->setSuccess(false);
    $tResponse->setMessage($e->getMessage());
    $tResponse->setTrace($e->getTraceAsString());

    if ($e->getPrevious()) {
        $tResponse->setPreviousTrace($e->getPrevious()->getTraceAsString());
    }

//    logMe($e);
}

echo $tResponse;

ob_end_flush();