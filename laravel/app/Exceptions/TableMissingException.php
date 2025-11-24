<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TableMissingException extends HttpException
{
    protected string $table;

    public function __construct(string $table, ?string $message = null, int $code = 0, ?\Throwable $previous = null)
    {
        $this->table = $table;
        $message = $message ?? "Table {$table} not found";
        parent::__construct(404, $message, $previous, [], $code);
    }

    public function getTableName(): string
    {
        return $this->table;
    }
}
