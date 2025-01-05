<?php

namespace Repository;

use Exception\CustomPdoException;
use Models\Book;
use Models\Product;
use PDOException;
use Query\QueryPdo;

/**
 * @method Book find(int $id)
 * @method Book[] findByParams(array $params, array $filters = [])
 */
class BookRepository extends Repository
{
    protected string $entityModel = Book::class;
    protected ?string $userDataRepositoryModel = BookUserDataRepository::class;

    public function linkBookToProduct(int $productId, int $bookId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_BOOK_ID => $bookId
                ]
            )
            ->where(Product::PARAM_ID, ':id')
            ->bindParam(Product::PARAM_ID, $productId);

        try {
            $query->execute();
        } catch(PDOException $e) {
            throw new CustomPdoException('BookRepository.linkBookToProduct', $query, $e);
        }
    }

    public function unlinkBookFromProduct(int $productId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_BOOK_ID => null
                ]
            )
            ->where(Product::PARAM_ID, ':id')
            ->bindParam(Product::PARAM_ID, $productId);

        try {
            $query->execute();
        } catch(PDOException $e) {
            throw new CustomPdoException('BookRepository.unlinkBookFromProduct', $query, $e);
        }
    }

    /**
     * @return Book[]
     */
    public function getBooksByTitle(string $title): array
    {
        $query = $this->getQuery();

        $query
            ->where('LOWER('.Book::TABLE_PREFIX.'.title) LIKE :title')
            ->limit(7);

        $fetchData = [
            Book::PARAM_TITLE => '%'.strtolower(trim($title)).'%'
        ];

        $explodeTitle = explode('.', $title);
        $splitTitle = reset($explodeTitle);

        if (!empty($splitTitle)) {
            $query->orWhere('LOWER('.Book::TABLE_PREFIX.'.title) LIKE :split_title');
            $fetchData['split_title'] = '%'.strtolower(trim($splitTitle)).'%';
        }

        $query->bindParams($fetchData);

        $rows = $query->fetchAll();

        return array_map(function ($row) {
            return new Book($row);
        }, $rows);
    }

    /**
     * @return Book[]
     */
    public function getBooks(): array
    {
        $query = $this->getQuery();

        $query->order(Book::TABLE_PREFIX . '.title')
            ->limit(100);

        $rows = $query->fetchAll();

        return array_map(function ($row) {
            return new Book($row);
        }, $rows);
    }
}