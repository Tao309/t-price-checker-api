<?php

define('init', true);

require_once ('autoload.php');

// Вынести в конфиг, чтобы находить user_id
$headers = getallheaders();

if (empty($headers['x-requested-with']) && $headers['x-requested-with'] !== 'tRequest') {
    die(tResponse::MESSAGE_ACCESS_LIMITED);
}

$myPriceCheckerId = 'ksfu83jfregjewyrfwefewhfdhs3e'; // tao309

// checkAuthToken from header
if (empty($headers['t-price-checker-id']) && $headers['t-price-checker-id'] !== $myPriceCheckerId) {
    die(tResponse::MESSAGE_ACCESS_LIMITED);
}

$tResponse = new tResponse();

try {
    $tResponse->checkPostData($_POST);

    $actionMethod = $_POST['action'];
    $data = json_decode($_POST['data'], true);

    $storage = new Storage($_POST['shop_type'], $tResponse);

    if (!method_exists($storage, $actionMethod)) {
        throw new RuntimeException("The called method " . $actionMethod . " is not exists.");
    }

    $reflection = new ReflectionMethod($storage, $actionMethod);
    if (!$reflection->isPublic()) {
        throw new RuntimeException("The called method " . $actionMethod . " is not public.");
    }

    $storage->$actionMethod($data);
} catch(\Throwable $e) {
    $tResponse->setSuccess(false);
    $tResponse->setMessage($e->getMessage());
}

echo $tResponse;