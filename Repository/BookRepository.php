<?php

namespace Repository;

use Models\Book;
use Models\Product;
use QueryPdo;
use Models\Entity;
use PDOException;

class BookRepository extends Repository
{
    protected string $entityModel = Book::class;

    public function __construct()
    {
        parent::__construct();
    }

    public function linkBookToProduct(int $positionId, int $bookId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_BOOK_ID => $bookId
                ]
            )
            ->where(Product::PARAM_ID, ':id');

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query->assemble());

        $variables = [
            Product::PARAM_ID => $positionId
        ];

        try {
            $stmt->execute($variables);
        } catch(PDOException $e) {
            processPdoException(
                'linkBookToProduct', $variables, ['position_id' => $positionId, 'book_id' => $bookId], $stmt, $e
            );
        }
    }

    public function unlinkBookFromProduct(int $positionId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_BOOK_ID => null
                ]
            )
            ->where(Product::PARAM_ID, ':id');

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query->assemble());

        $variables = [
            Product::PARAM_ID => $positionId
        ];

        try {
            $stmt->execute($variables);
        } catch(PDOException $e) {
            processPdoException(
                'unlinkBookFromProduct', $variables, ['position_id' => $positionId], $stmt, $e
            );
        }
    }

    public function get(int $id): Book|null
    {
        $query = $this->getListQueryNew();
        $query->where('id', ':id');

        $data = $query->fetch([
            Product::PARAM_ID => $id
        ]);

        if (!$data) {
            return null;
        }

        return new Book($data);
    }

    /**
     * @return Book[]
     */
    public function getBooksByTitle(string $title): array
    {
        $query = $this->getListQueryNew();

        $query
            ->where('LOWER('.Book::TABLE_PREFIX.'.title) LIKE :title')
            ->limit(7);

        $rows = $query->fetchAll([
            Product::PARAM_TITLE => '%'.strtolower(trim($title)).'%'
        ]);

        return array_map(function ($row) {
            return new Book($row);
        }, $rows);
    }

    /**
     * @return Book[]
     */
    public function getBooks(): array
    {
        $query = $this->getListQueryNew();

        $query->order(Book::TABLE_PREFIX . '.title')
            ->limit(100);

        $rows = $query->fetchAll();

        return array_map(function ($row) {
            return new Book($row);
        }, $rows);
    }

    /**
     * @param array $data
     *
     * @return int ID книги.
     */
    public function save(array $data): int
    {
        if (isset($data[Entity::PARAM_ID])) {
            return $this->update($data);
        }

        return $this->create($data);
    }

    private function getPreparedBookData(array $bookData): array
    {
        $result = [];
        foreach([
                    Book::PARAM_TITLE,
                    Book::PARAM_AUTHOR,
                    Book::PARAM_ISBN,
                    Book::PARAM_PAGES,
                    Book::PARAM_CIRCULATION,
                    Book::PARAM_SIZE,
                    Book::PARAM_PUBLISH_YEAR,
                    Book::PARAM_RELEASE_DATE,
                    Book::PARAM_LISTEN_PRICE_VALUE
                ] as $param) {
            $result[$param] = $bookData[$param] ?: null;
        }

        if (isset($bookData[Book::PARAM_BINDING_TYPE])) {
            $result[Book::PARAM_BINDING_TYPE_ID] = $bookData[Book::PARAM_BINDING_TYPE][Entity::PARAM_ID];
            unset($bookData[Book::PARAM_BINDING_TYPE]);
        }

        return $result;
    }

    private function update(array $entityData): int
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);

        $query = (new QueryPdo())
            ->update(
                Book::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            )
            ->where(Book::PARAM_ID, ':id');

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query->assemble());

        $variables = [
            Book::PARAM_ID => $entityDataBuilder->getEntityData(Book::PARAM_ID)
        ];

        try {
            $stmt->execute($variables);

            return $entityDataBuilder->getEntityData(Book::PARAM_ID);
        } catch(PDOException $e) {
            processPdoException('BookRepository.update', $variables, $entityDataBuilder->getQueryPreparedData(), $stmt, $e);
        }
    }

    private function create(array $entityData): int
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);

        $query = (new QueryPdo())
            ->insert(Book::TABLE_NAME, $entityDataBuilder->getQueryPreparedData());

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query->assemble());

        $variables = $query->getPreparedData();

        try {
            $stmt->execute($variables);

            return $dbh->lastInsertId();
        } catch(PDOException $e) {
            processPdoException('BookRepository.create', $variables, $entityData, $stmt, $e);
        }
    }

}