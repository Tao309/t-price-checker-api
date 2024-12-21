<?php

namespace Repository;

use Core\AccessRight\AccessRight;
use Core\Config;
use Exception\CustomPdoException;
use Exception\ResponseException;
use Models\Book;
use Models\BookUserData;
use Models\Entity;
use Models\Product;
use PDOException;
use QueryPdo;

class BookRepository extends Repository
{
    protected string $entityModel = Book::class;

    private BookUserDataRepository $bookUserDataRepository;

    public function __construct()
    {
        parent::__construct();

        $this->bookUserDataRepository = new BookUserDataRepository();
    }

    public function linkBookToProduct(int $entityId, int $bookId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_BOOK_ID => $bookId
                ]
            )
            ->where(Product::PARAM_ID, ':id')
            ->bindParam(Product::PARAM_ID, $entityId);

        try {
            $query->execute();
        } catch(PDOException $e) {
            throw new CustomPdoException('BookRepository.linkBookToProduct', $query, $e);
        }
    }

    public function unlinkBookFromProduct(int $entityId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_BOOK_ID => null
                ]
            )
            ->where(Product::PARAM_ID, ':id')
            ->bindParam(Product::PARAM_ID, $entityId);

        try {
            $query->execute();
        } catch(PDOException $e) {
            throw new CustomPdoException('BookRepository.unlinkBookFromProduct', $query, $e);
        }
    }

    public function get(int $id): Book|null
    {
        $query = $this->getListQueryNew();
        $query->where('id', ':id')
            ->bindParams([
                Product::PARAM_ID => $id
            ]);

        $data = $query->fetch();

        return $data ? Book::create($data) : null;
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
//                    Book::PARAM_RELEASE_DATE,
//                    Book::PARAM_LISTEN_PRICE_VALUE
                ] as $param) {
            $result[$param] = $bookData[$param] ?: null;
        }

        if (isset($bookData[Book::PARAM_BINDING_TYPE])) {
            $result[Book::PARAM_BINDING_TYPE_ID] = $bookData[Book::PARAM_BINDING_TYPE][Entity::PARAM_ID];
            unset($bookData[Book::PARAM_BINDING_TYPE]);
        }

        return $result;
    }

    /**
     * @param array $entityData Входящие данные модели.
     *
     * @return int ID книги.
     */
    public function save(array $entityData): int
    {
        if (isset($entityData[Entity::PARAM_ID])) {
            $entityId = $this->update($entityData);
        } else {
            $entityId = $this->create($entityData);
        }

        if (isset($entityData[Book::PARAM_BOOK_USER_DATA])) {
            $entityData[Book::PARAM_BOOK_USER_DATA][BookUserData::PARAM_BOOK_ID] = $entityId;
            $entityData[Book::PARAM_BOOK_USER_DATA][BookUserData::PARAM_USER_ID] = Config::getCurrentUserid();

            $bud = $this->bookUserDataRepository->get($entityId);

            if (!$bud) {
                $this->bookUserDataRepository->create($entityData[Book::PARAM_BOOK_USER_DATA]);
            } else {
                $this->bookUserDataRepository->update($entityData[Book::PARAM_BOOK_USER_DATA]);
            }
        }

        return $entityId;
    }

    protected function update(array $entityData): int
    {
        if (!AccessRight::hasAccess('book.save')) {
            throw new \RuntimeException('Save book is not granted');
        }

        $entityDataBuilder = $this->getEntityDataBuilder($entityData);

        $query = (new QueryPdo())
            ->update(
                Book::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            )
            ->where(Book::PARAM_ID, ':id')
            ->where(Product::PARAM_USER_ID, ':user_id')
            ->bindParams([
                Book::PARAM_ID => $entityDataBuilder->getEntityData(Book::PARAM_ID),
                Product::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        try {
            $query->execute();

            return $entityDataBuilder->getEntityData(Book::PARAM_ID);
        } catch(PDOException $e) {
            throw new CustomPdoException('BookRepository.update', $query, $e);
        }
    }

    protected function create(array $entityData): int
    {
        if (!AccessRight::hasAccess('book.create')) {
            throw new \RuntimeException('Create book is not granted');
        }

        if (!isset($entityData[Book::PARAM_TITLE])) {
            throw new ResponseException('Title is empty');
        }

        $query = $this->getListQueryNew()
            ->where(Book::PARAM_TITLE, $entityData[Book::PARAM_TITLE]);

        if ($query->fetch()) {
            throw new ResponseException(sprintf('Book "%s" is already exists', $entityData[Book::PARAM_TITLE]));
        }

        $entityDataBuilder = $this->getEntityDataBuilder($entityData);
        $entityDataBuilder->appendPreparedData([
            Product::PARAM_USER_ID => Config::getCurrentUserid(),
        ]);

        $query = (new QueryPdo())
            ->insert(Book::TABLE_NAME, $entityDataBuilder->getQueryPreparedData());

        try {
            $query->execute();

            return $query->getLastInsertId();
        } catch(PDOException $e) {
            throw new CustomPdoException('BookRepository.create', $query, $e);
        }
    }
}