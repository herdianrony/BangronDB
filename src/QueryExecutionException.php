<?php

declare(strict_types=1);

namespace BangronDB;

/**
 * Backward-compatible alias.
 *
 * @see \BangronDB\Exceptions\QueryExecutionException
 */
class_alias(Exceptions\QueryExecutionException::class, QueryExecutionException::class);
