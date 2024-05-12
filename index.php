<?php

use Core\Config;

define('init', true);

require_once ('autoload.php');
require_once ('error_handler.php');

ob_start();

Config::checkHeaders();

$tResponse = new tResponse();

try {
    $tResponse->checkPostData($_POST);

    Config::initShopType($_POST['shop_type']);

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

    $storage->$actionMethod($data);
} catch(\Exception\CustomPdoException $e) {
    processPdoException($e);
} catch(\Throwable $e) {
    $tResponse->setSuccess(false);
    $tResponse->setMessage($e->getMessage());
    $tResponse->setTrace($e->getTraceAsString());
    $tResponse->setTrace($e->getPrevious()->getTraceAsString());
    logMe($e);
}

echo $tResponse;

ob_end_flush();