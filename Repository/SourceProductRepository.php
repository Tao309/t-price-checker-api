<?php

namespace Repository;

use Exception\CustomPdoException;
use Models\Product;
use Models\SourceProduct;
use PDOException;
use Query\QueryPdo;

/**
 * @method SourceProduct find(int $id)
 * @method SourceProduct[] findByParams(array $params, array $filters = [])
 */
class SourceProductRepository extends Repository
{
    protected string $entityModel = SourceProduct::class;
    protected ?string $userDataRepositoryModel = SourceProductUserDataRepository::class;

    public function linkToProduct(int $productId, int $sourceProductId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_SOURCE_PRODUCT_ID => $sourceProductId
                ]
            )
            ->where(Product::PARAM_ID, ':id')
            ->bindParam(Product::PARAM_ID, $productId);

        try {
            $query->execute();
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductRepository.linkToProduct', $query, $e);
        }
    }

    public function unlinkFromProduct(int $productId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_SOURCE_PRODUCT_ID => null
                ]
            )
            ->where(Product::PARAM_ID, ':id')
            ->bindParam(Product::PARAM_ID, $productId);

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
        $title = $this->prepareForSearch($title);

        $query = $this->getQuery()
            ->where('LOWER('.SourceProduct::TABLE_PREFIX.'.title) LIKE :title')
            ->limit(5);

        $fetchData = [
            'title' => '%'.$title.'%'
        ];

        $query->orderText("CASE title
            WHEN title = '".$title."' THEN 1
            WHEN title LIKE '".$title."%' THEN 2
            WHEN title LIKE '%".$title."%' THEN 3
            WHEN title LIKE '%".$title."' THEN 4
            ELSE 5
            END");

        if (str_contains($title, ':')) {
            $explodeTitle = explode(':', $title);
            $splitTitle = reset($explodeTitle);

            $query->orWhere('LOWER('.SourceProduct::TABLE_PREFIX.'.title) LIKE :title_split_colon');
            $fetchData['title_split_colon'] = '%'.$splitTitle.'%';
        }

        if (str_contains($title, '.')) {
            $explodeTitle = explode('.', $title);
            $splitTitle = reset($explodeTitle);

            $query->orWhere('LOWER('.SourceProduct::TABLE_PREFIX.'.title) LIKE :title_split_dot');
            $fetchData['title_split_dot'] = '%'.$splitTitle.'%';
        }

        if (str_contains($title, ' ')) {
            $explodeTitle = explode(' ', $title);
            $splitTitles = array_slice($explodeTitle, 0, 2);

            $query->orWhere('LOWER('.SourceProduct::TABLE_PREFIX.'.title) LIKE :title_split_space');
            $fetchData['title_split_space'] = '%'.implode(' ', $splitTitles).'%';
        }

        $query->bindParams($fetchData);

        $rows = $query->fetchAll();

        return array_map(function ($row) {
            return new SourceProduct($row);
        }, $rows);
    }
}