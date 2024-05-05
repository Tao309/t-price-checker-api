<?php

namespace Repository;

use Models\Book;
use QueryPdo;
use Models\Entity;
use PDOException;

class BookRepository
{
    public function __construct()
    {

    }

    public function linkBookToProduct(int $positionId, int $bookId): void
    {
        $query = (new QueryPdo())
            ->update(
                'products',
                [
                    'book_id' => $bookId
                ],
                'id = :position_id'
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
                'products',
                [
                    'book_id' => null
                ],
                'id = :position_id'
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
        $query = $this->getBookQuery()
            ->where('b.id = :id');

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

    public function getBooksByTitle(string $title): array
    {
        $query = $this->getBookQuery()
            ->where('LOWER(b.title) LIKE :title')
            ->limit(7);

        $rows = $query->fetchAll([
            'title' => '%'.strtolower(trim($title)).'%'
        ]);

        return array_map(function ($row) {
            return new Book($row);
        }, $rows);
    }

    private function getBookQuery(): QueryPdo
    {
        return (new QueryPdo())
            ->select([
                'b.*'
            ])
            ->from(['b' => 'books'])
            ->leftJoin(
                ['bbt' => 'book_binding_type'],
                'bbt.id = b.binding_type',
                [
                    'bbt.id AS \''.Book::PARAM_BINDING_TYPE_ID.'\'',
                    'bbt.label AS \''.Book::PARAM_BINDING_TYPE_LABEL.'\''
                ]
            );
    }

    private function getPreparedBookData(array $bookData): array
    {
        $result = [];
        foreach(Book::RECORDABLE_PARAMS as $param) {
            $result[$param] = $bookData[$param] ?: null;
        }

        $result[Book::PARAM_RELEASE_DATE] = $bookData[Book::PARAM_RELEASE_DATE] ?: null;

        if (isset($bookData[Book::PARAM_BINDING_TYPE])) {
            $result[Book::PARAM_BINDING_TYPE] = $bookData[Book::PARAM_BINDING_TYPE]['id'];
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
        $arrayValues = [];
        foreach(Book::RECORDABLE_PARAMS as $param) {
            $arrayValues[$param] = ':' . $param;
        }

        $arrayValues[Book::PARAM_RELEASE_DATE] = ':' . Book::PARAM_RELEASE_DATE;

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