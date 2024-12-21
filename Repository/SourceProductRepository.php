<?php

namespace Repository;

use Core\Config;
use Exception\CustomPdoException;
use Models\Entity;
use Models\Product;
use Models\SourceProduct;
use Models\SourceProductUserData;
use Models\Stock;
use PDO;
use QueryPdo;
use PDOException;

class SourceProductRepository extends Repository
{
    protected string $entityModel = SourceProduct::class;
    protected ?string $userDataRepositoryModel = SourceProductUserDataRepository::class;

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

    /**
     * @return SourceProduct[]
     */
    public function getSourceProductsByTitle(string $title): array
    {
        $query = $this->getListQueryNew()
            ->where('LOWER('.SourceProduct::TABLE_PREFIX.'.title) LIKE :title')
//            ->where(SourceProduct::PARAM_USER_ID, ':user_id')
            ->limit(7);

        $fetchData = [
            'title' => '%'.strtolower(trim($title)).'%',
//            SourceProduct::PARAM_USER_ID => Config::getCurrentUserid()
        ];

        $explodeTitle = explode(' ', $title);
        $splitTitle = reset($explodeTitle);

        if (!empty($splitTitle)) {
            $query->orWhere('LOWER('.SourceProduct::TABLE_PREFIX.'.title) LIKE :split_title');
            $fetchData['split_title'] = '%'.strtolower(trim($splitTitle)).'%';
        }

        $query->bindParams($fetchData);

        $rows = $query->fetchAll();

        return array_map(function ($row) {
            return new SourceProduct($row);
        }, $rows);
    }
}