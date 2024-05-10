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
                ],
                Product::PARAM_ID . ' = :position_id'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $variables = [
            'position_id' => $positionId
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
                ],
                Product::PARAM_ID . ' = :position_id'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $variables = [
            'position_id' => $positionId
        ];

        try {
            $stmt->execute($variables);
        } catch(PDOException $e) {
            processPdoException(
                'unlinkBookFromProduct', $variables, ['position_id' => $positionId], $stmt, $e
            );
        }
    }

    public function getBook(int $id): Book|null
    {
        $query = $this->getListQueryNew();
        $query->where(Book::TABLE_PREFIX.'.id = :id');

        $data = $query->fetch([
            'id' => $id
        ]);

        if (!$data) {
            return null;
        }

        return new Book($data);
    }

    /**
     * @param array $data
     *
     * @return int ID книги.
     */
    public function saveBook(array $data): int
    {
        if (isset($data[Entity::PARAM_ID])) {
            return $this->updateBook($data);
        }

        return $this->createBook($data);
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
            'title' => '%'.strtolower(trim($title)).'%'
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

    private function updateBook(array $bookData): int
    {
        $bookId = $bookData[Entity::PARAM_ID];
        $data = $this->getPreparedBookData($bookData);

        $query = (new QueryPdo())
            ->update(
                'books',
                $data,
                'id = :book_id'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $variables = [
            'book_id' => $bookId
        ];

        try {
            $stmt->execute($variables);

            return $bookId;
        } catch(PDOException $e) {
            processPdoException('updateBook', $variables, $bookData, $stmt, $e);
        }
    }

    private function createBook(array $bookData): int
    {
        $arrayValues = $this->assembleInsertValues([
            Book::PARAM_TITLE,
            Book::PARAM_AUTHOR,
            Book::PARAM_ISBN,
            Book::PARAM_PAGES,
            Book::PARAM_CIRCULATION,
            Book::PARAM_SIZE,
            Book::PARAM_PUBLISH_YEAR,
            Book::PARAM_RELEASE_DATE,
            Book::PARAM_LISTEN_PRICE_VALUE
        ]);

        if (isset($bookData[Book::PARAM_BINDING_TYPE])) {
            $arrayValues[Book::PARAM_BINDING_TYPE] = ':binding_type';
        }

        $query = (new QueryPdo())
            ->insert('books', $arrayValues);

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $variables = $this->getPreparedBookData($bookData);

        try {
            $stmt->execute($variables);

            return $dbh->lastInsertId();
        } catch(PDOException $e) {
            processPdoException('insertNewBook', $variables, $bookData, $stmt, $e);
        }
    }

}