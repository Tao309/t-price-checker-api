<?php

use Core\Config;
use Repository\ProductRepository;
use Repository\BookRepository;

define('init', true);

require_once ('autoload.php');

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : null;

$page = '';
$title = '';

try {
    Config::initShopType(Config::TYPE_OZON);

    $productRepository = new ProductRepository();
    $bookRepository = new BookRepository();
} catch (\Throwable $e) {
    die($e->getMessage());
}

function cutDate(string $date)
{
    return explode(' ', $date)[0] ?? $date;
}

if ($bookId) {
    try {

        $book = $bookRepository->getBook($bookId);

        if (!$book) {exit;}

        $products = $productRepository->getProductsByBookId($bookId);
    } catch(\Throwable $e) {
        echo $e->getMessage();
        echo $e->getTraceAsString();
        exit;
    }

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
    $page .= '<th class="prices no-sort">Цены</th>';
    $page .= '<th class="stocks no-sort">Стоки</th>';
    $page .= '<th>Доступен</th>';
    $page .= '<th>Добавлен</th>';
    $page .= '<th>Последнее обновление</th>';
    $page .= '</thead>';

    try {
        foreach ($products as $product) {
            $datePrices = [];
            $stocks = [];

            foreach ($product->getPriceDates() as $priceDate) {
                $datePrices[] = '<div class="price-date">'.$priceDate->getDate()->format('d.m.Y').': '.$priceDate->getPrice().'</div>';
            }

            foreach ($product->getStocks() as $stock) {
                $stocks[] = '<div class="stock">'.$stock->getDate()->format('d.m.Y').': '.$stock->getQty().'</div>';
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
            $page .= '<td class="date date_created">' . $product->getDateCreated()->format('d.m.Y') . '</td>';
            $page .= '<td class="date date_updated">' . $product->getDateUpdated()->format('d.m.Y') . '</td>';
            $page .= '</tr>';
        }
    } catch (\Throwable $e) {
        echo $e->getMessage();
        echo $e->getTraceAsString();
        exit;
    }

    $page .= '</table>';
} else {
    $title = 'Книги';

    try {
        $books = $bookRepository->getBooks();

        $page .= '<table class="table sortable asc">';
        $page .= '<thead class="head">';
        $page .= '<th>#</th>';
        $page .= '<th>Наименование</th>';
        $page .= '<th>Автор</th>';
        $page .= '<th class="isbn no-sort">ISBN</th>';
        $page .= '<th>Страниц</th>';
        $page .= '<th>Тираж</th>';
        $page .= '<th class="size">Размер</th>';
        $page .= '<th class="binding_type">Переплёт</th>';
        $page .= '<th>Год выпуска</th>';
        $page .= '<th>Добавлен</th>';
        $page .= '</thead>';

        $i = 0;
        foreach ($books as $book) {
            $page .= '<tr class="row">';
            $page .= '<td>' . ++$i. '</td>';
            $page .= '<td><a href="./books.php?id=' . $book->getId() . '">' . $book->getTitle(). '</a></td>';
            $page .= '<td>' . $book->getAuthor(). '</td>';
            $page .= '<td>' . $book->getIsbn(). '</td>';
            $page .= '<td>' . $book->getPages(). '</td>';
            $page .= '<td>' . $book->getCirculation(). '</td>';
            $page .= '<td>' . $book->getSize(). '</td>';
            $page .= '<td>' . ($book->getBindingType() ? $book->getBindingType()->getLabel() : '') . '</td>';
            $page .= '<td>' . $book->getPublishYear(). '</td>';
            $page .= '<td>' . $book->getDateCreated()->format('d.m.Y'). '</td>';
        }
    } catch(\Throwable $e) {
        echo $e->getMessage();
        echo $e->getTraceAsString();
        exit;
    }
}


// https://github.com/tofsjonas/sortable#a-use-links-in-the-html

echo '
<!DOCTYPE html>
<html lang="ru-RU">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>' . $title . '</title>
    <link rel="stylesheet" href="./style/books.css?v=7">
    <link href="./style/sortable.min.css" rel="stylesheet" />
    <script src="./scripts/sortable.min.js"></script>
</head>
<body>
' . $page . '
</body>
';

