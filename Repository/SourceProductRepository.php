<?php

namespace Repository;

use Exception\CustomPdoException;
use Models\Entity;
use Models\Product;
use Models\SourceProduct;
use QueryPdo;
use PDOException;

class SourceProductRepository extends Repository
{
    protected string $entityModel = SourceProduct::class;

    public function linkToProduct(int $entityId, int $sourceProductId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_SOURCE_PRODUCT_ID => $sourceProductId
                ]
            )
            ->where(Product::PARAM_ID, ':id')
            ->bindParam(Product::PARAM_ID, $entityId);

        try {
            $query->execute();
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductRepository.linkToProduct', $query, $e);
        }
    }

    public function unlinkFromProduct(int $entityId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_SOURCE_PRODUCT_ID => null
                ]
            )
            ->where(Product::PARAM_ID, ':id')
            ->bindParam(Product::PARAM_ID, $entityId);

        try {
            $query->execute();
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductRepository.unlinkFromProduct', $query, $e);
        }
    }

    public function get(int $id): SourceProduct|null
    {
        $query = $this->getListQueryNew();
        $query->where('id', ':id');

        $data = $query->fetch([
            Entity::PARAM_ID => $id
        ]);

        if (!$data) {
            return null;
        }

        return new SourceProduct($data);
    }

    /**
     * @return SourceProduct[]
     */
    public function getSourceProductsByTitle(string $title): array
    {
        $query = $this->getListQueryNew();

        $query
            ->where('LOWER('.SourceProduct::TABLE_PREFIX.'.title) LIKE :title')
            ->limit(7);

        $fetchData = [
            'title' => '%'.strtolower(trim($title)).'%'
        ];

        $explodeTitle = explode(' ', $title);
        $splitTitle = reset($explodeTitle);

        if (!empty($splitTitle)) {
            $query->orWhere('LOWER('.SourceProduct::TABLE_PREFIX.'.title) LIKE :split_title');
            $fetchData['split_title'] = '%'.strtolower(trim($splitTitle)).'%';
        }

        $rows = $query->fetchAll($fetchData);

        return array_map(function ($row) {
            return new SourceProduct($row);
        }, $rows);
    }

    /**
     * @param array $entityData Входящие данные модели.
     *
     * @return int ID книги.
     */
    public function save(array $entityData): int
    {
        if (isset($entityData[Entity::PARAM_ID])) {
            return $this->update($entityData);
        }

        return $this->create($entityData);
    }

    protected function update(array $entityData): int
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);

        $query = (new QueryPdo())
            ->update(
                SourceProduct::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            )
            ->where(SourceProduct::PARAM_ID, ':id')
            ->bindParam(SourceProduct::PARAM_ID, $entityDataBuilder->getEntityData(SourceProduct::PARAM_ID));

        try {
            $query->execute();

            return $entityDataBuilder->getEntityData(SourceProduct::PARAM_ID);
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductRepository.update', $query, $e);
        }
    }

    protected function create(array $entityData): int
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);

        $query = (new QueryPdo())
            ->insert(SourceProduct::TABLE_NAME, $entityDataBuilder->getQueryPreparedData());

        try {
            $query->execute();

            return $query->getLastInsertId();
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductRepository.create', $query, $e);
        }
    }
}