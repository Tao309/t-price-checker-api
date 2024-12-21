<?php

namespace Repository;

use Core\Config;
use Exception\CustomPdoException;
use Models\BookUserData;
use Models\SourceProductUserData;
use PDOException;
use QueryPdo;

class SourceProductUserDataRepository extends Repository
{
    protected string $entityModel = SourceProductUserData::class;
}