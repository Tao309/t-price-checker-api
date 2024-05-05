<?php

namespace Repository;

use QueryPdo;
use Models\Entity;

class StockRepository
{
    public function __construct()
    {

    }

    public function saveStocks(int $positionId, array $stocks = []): void
    {
        if (empty($stocks)) {
            return;
        }

        $query = (new QueryPdo())
            ->insert(
                'products_stocks',
                [
                    'id' => ':position_id',
                    'qty' => ':qty',
                    'date' => ':date',
                    'log' => ':log',
                ],
                'qty = VALUES(qty), log = VALUES(log)'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        try {
            foreach($stocks as $stock) {
                $stmt->execute([
                    'position_id' => $positionId,
                    'qty' => $stock['qty'],
                    'date' => $stock['date'],
                    'log' => isset($stock['log']) ? json_encode($stock['log']) : null
                ]);
            }
        } catch(\PDOException $e) {
            processPdoException('saveStocks', ['position_id' => $positionId], $stocks, $stmt, $e);
        }
    }

    /**
     * Получаем данные стоков для списка продуктов, уже сгруппированные по id.
     *
     * @param array $ids Массив ID товаров.
     *
     * @return array Массив стоков.
     */
    public function getStocksForProducts(array $ids = []): array
    {
        $query = (new QueryPdo())
            ->select('*')
            ->from('products_stocks')
            ->where('id IN ('.implode(",", $ids).')')
            ->order('date');

        $result = [];

        foreach ($query->fetchAll() as $row) {
            if (!isset($result[$row[Entity::PARAM_ID]])) {
                $result[$row[Entity::PARAM_ID]] = [];
            }

            $result[$row[Entity::PARAM_ID]][] = $row;
        }

        return $result;
    }

    public function getStock(array $stockData)
    {
        $query = (new QueryPdo())
            ->select(['*'])
            ->from(['products_stocks'])
            ->where('id = :id')
            ->where('qty = :qty')
            ->where('date = :date');

        return $query->fetch([
            'id' => $stockData['id'],
            'qty' => $stockData['qty'],
            'date' => $stockData['date']
        ]);
    }

    public function deleteStock(array $stockData): int
    {
        if (empty($stockData['id']) || empty($stockData['qty']) || empty($stockData['date'])) {
            throw new \Exception('Не все поля заполнены для удаления');
        }

        $query = (new QueryPdo())
            ->delete(
                'products_stocks',
                [
                    'id' => ':id',
                    'qty' => ':qty',
                    'date' => ':date'
                ]
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $stmt->execute([
            'id' => $stockData['id'],
            'qty' => $stockData['qty'],
            'date' => $stockData['date']
        ]);

        if (!$stmt->rowCount()) {
            throw new \Exception('Stock is not removed. Not row affected.');
        }

        return $stmt->rowCount();
    }
}