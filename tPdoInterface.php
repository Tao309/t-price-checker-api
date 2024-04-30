<?php

interface tPdoInterface {
    public function getProducts(array $productIds): array;

    public function saveProduct(array $data);
}