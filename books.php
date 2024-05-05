<?php

use Core\Config;
use Repository\ProductRepository;
use Repository\BookRepository;

define('init', true);

require_once ('autoload.php');

if (!isset($_GET['id'])) {exit;}

$bookId = (int) $_GET['id'];

try {
    Config::initShopType(Config::TYPE_OZON);

    $productRepository = new ProductRepository();
    $bookRepository = new BookRepository();

    $book = $bookRepository->getBook($bookId);

    if (!$book) {exit;}

    $products = $productRepository->getProductsByBookId($bookId);
} catch(\Throwable $e) {
    die($e->getMessage());
}


$page = '';

$page .= '<br/>';
$page .= '<h1>';

$title = $book->getTitle();
if ($book->getAuthor()) {
    $title .= ' / ' .  $book->getAuthor();
}
if ($book->getPublishYear()) {
    $title .= ' (' .  $book->getPublishYear() . ')';
}

$page .= $title;
$page .= '</h1>';
$page .= '<br/><br/>';

$page .= '<table class="table sortable asc">';
$page .= '<thead class="head">';
$page .= '<th>Наименование</th>';
$page .= '<th>Маркетплейс</th>';
$page .= '<th>Мин. цена</th>';
$page .= '<th>Последний сток</th>';
$page .= '<th class="no-sort">Цены</th>';
$page .= '<th class="no-sort">Стоки</th>';
$page .= '<th>Доступен</th>';
$page .= '<th>Добавлен</th>';
$page .= '<th>Последнее обновление</th>';
$page .= '</thead>';

function cutDate(string $date)
{
    return explode(' ', $date)[0] ?? $date;
}

try {
    foreach ($products as $product) {
        $datePrices = [];
        $stocks = [];

        foreach ($product->getPriceDates() as $priceDate) {
            $datePrices[] = '<div class="price-date">'.cutDate($priceDate['date']).': '.$priceDate['price'].'</div>';
        }

        foreach ($product->getStocks() as $stock) {
            $stocks[] = '<div class="stock">'.cutDate($stock['date']).': '.$stock['qty'].'</div>';
        }

        $page .= '<tr class="row">';
        $page .= '<td class="title">';
        $page .= '<a href="'.$product->getUrl().'" target="_blank">' . $product->getTitle() . '</a>';
        $page .= '</td>';
        $page .= '<td class="shop_type">' . $product->getShopType() . '</td>';
        $page .= '<td class="price min_price">' . $product->getMinPrice() . '</td>';
        $page .= '<td class="price last_qty">' . $product->getLastQty() . '</td>';
        $page .= '<td class="date_prices">' . implode('', $datePrices) . '</td>';
        $page .= '<td class="stocks">' . implode('', $stocks) . '</td>';
        $page .= '<td class="available">' . ($product->getAvailable() ? 'Да' : 'Нет') . '</td>';
        $page .= '<td class="date date_created">' . cutDate($product->getDateCreated()) . '</td>';
        $page .= '<td class="date date_updated">' . cutDate($product->getDateUpdated()) . '</td>';
        $page .= '</tr>';
    }
} catch (\Throwable $e) {
    die($e->getMessage());
}

$page .= '</table>';

// https://github.com/tofsjonas/sortable#a-use-links-in-the-html

echo '
<!DOCTYPE html>
<html lang="ru-RU">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>' . $title . '</title>
    <link rel="stylesheet" href="./style/books.css?v=5">
    <link href="./style/sortable.min.css" rel="stylesheet" />
    <script src="./scripts/sortable.min.js"></script>
</head>
<body>
' . $page . '
</body>
';

