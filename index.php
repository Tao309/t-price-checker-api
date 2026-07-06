<?php

use Core\ApiCaller;
use Core\ArrayHandler;
use Core\Cache;
use Core\Config;
use Core\tResponse;
use Exception\CustomPdoException;
use Exception\NoRightsException;

define('init', true);

require_once ('autoload.php');
require_once ('error_handler.php');

ob_start();

try {
    Config::initShopType(ArrayHandler::getValueAsString('shop_type', $_POST));

    $tResponse = new tResponse();
    $cache = new Cache();

    $tResponse->checkPostData($_POST);

    Config::checkHeadersAndApplyAccess();
    Config::initSourceProductTypes();

    $actionMethod = ArrayHandler::getValueAsString('action', $_POST);
    $data = json_decode($_POST['data'], true);

    $storage = new ApiCaller($tResponse);

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
} catch(CustomPdoException $e) {
    processPdoException($e);
}  catch(NoRightsException $e) {
    $tResponse->setSuccess(false);
    $tResponse->setMessage($e->getMessage());
} catch(\Throwable $e) {
    $tResponse->setSuccess(false);
    $tResponse->setMessage($e->getMessage());
    $tResponse->setTrace($e->getTraceAsString());

    if ($e->getPrevious()) {
        $tResponse->setPreviousTrace($e->getPrevious()->getTraceAsString());
    }
}

echo $tResponse;

ob_end_flush();