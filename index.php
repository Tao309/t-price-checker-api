<?php

define('init', true);

require_once ('autoload.php');

// Вынести в конфиг, чтобы находить user_id
$headers = getallheaders();

$errorMessage = 'Доступ ограничен';

if (empty($headers['x-requested-with']) && $headers['x-requested-with'] !== 'tRequest') {
    die($errorMessage);
}

$myPriceCheckerId = 'ksfu83jfregjewyrfwefewhfdhs3e'; // tao309

// checkAuthToken from header
if (empty($headers['t-price-checker-id']) && $headers['t-price-checker-id'] !== $myPriceCheckerId) {
    die($errorMessage);
}

$action = null;
$shopType = null;
$userId = null;

$availableMethods = [
    'saveProduct',
    'saveProducts',
    'deleteProduct',
    'getProductsByShopType',
    'importByShopType', // нужен только в начале
    'getBooksByTitle',
    'saveBook',
    'removeStock',
];

if (!in_array($_POST['action'], $availableMethods)) {
    die($errorMessage);
}

// Добавить обязательную проверрку наличия поля data в $_POST
if (isset($_POST['data'])) {
    $_POST['data'] = json_decode($_POST['data'], true);
}

try {
    $storage = new Storage($_POST['shop_type']);

    switch ($_POST['action']) {
        case 'removeStock':
            echo json_encode($storage->removeStock($_POST['data']));
            break;
        case 'saveBook':
            echo json_encode($storage->saveBook($_POST['data']));
            break;
        case 'getBooksByTitle':
            $title = $_POST['data']['title'];
            echo json_encode($storage->getBooksByTitle($title));
            break;
        case 'saveProduct':
//            $productData = json_decode($_POST['data'], true);
            echo json_encode($storage->saveProduct($_POST['data']));
            break;
        case 'deleteProduct':
            $productId = $_POST['product_id'];

            echo json_encode($storage->deleteProduct($productId), true);
            break;
        case 'getProductsByShopType':
            if (empty($_POST['ids'])) {
                throw new \Exception('Не указан передаваемый массив ID товаров.');
            }

            $ids = json_decode($_POST['ids'], true);

            if (!is_array($ids)) {
                throw new \Exception('Не корректен передаваемый массив ID товаров.');
            }

//            echo json_encode($storage->getProductsByShopType($ids), JSON_PRETTY_PRINT);
            echo json_encode($storage->getProductsByShopType($ids), true);
            break;
        case 'saveProducts':
        case 'importByShopType':
            if (empty($_POST['products'])) {
                throw new \Exception('Не указан передаваемый массив товаров для сохранения.');
            }

            $products = json_decode($_POST['products'], true);

            if (!is_array($products)) {
                throw new \Exception('Не корректен передаваемый массив товаров для сохранения.');
            }

//            $products = array_slice($products, 0, 2);

            echo json_encode($storage->saveProducts($products));
            break;
    }
} catch(\Throwable $e) {
    die($e->getMessage());
}
