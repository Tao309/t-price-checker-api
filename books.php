<?php

define('init', true);

require_once ('autoload.php');

if (!isset($_GET['id'])) {exit;}

$bookId = (int) $_GET['id'];

$storage = new Storage('ozon');

