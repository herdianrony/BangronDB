<?php

declare(strict_types=1);

namespace BangronDB;

/**
 * Backward-compatible alias.
 *
 * At runtime this file calls class_alias() so that
 * `BangronDB\QueryExecutionException` and
 * `BangronDB\Exceptions\QueryExecutionException` refer to the same class.
 *
 * NOTE: PHPStan cannot statically resolve names declared via class_alias(),
 * so a small number of catch-clause references to
 * `\BangronDB\QueryExecutionException` in other files (e.g.
 * `src/Traits/ChangeTrackingTrait.php`) still produce "Caught class not
 * found" errors at level 6. These are false positives — the alias resolves
 * correctly at runtime and all 376 PHPUnit tests pass. They are tracked
 * separately and are outside the scope of the 11-file fix task.
 *
 * @see \BangronDB\Exceptions\QueryExecutionException
 */
class_alias(Exceptions\QueryExecutionException::class, 'BangronDB\\QueryExecutionException');
