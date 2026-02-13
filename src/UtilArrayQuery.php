<?php

namespace BangronDB;

class UtilArrayQuery
{
    /**
     * Get a value from an array using dot notation.
     *
     * @param array  $data    The array to search
     * @param string $path    The dot-notated path (e.g., 'user.name')
     * @param mixed  $default Default value if not found
     */
    public static function get(array $data, string $path, $default = null)
    {
        if (strpos($path, '.') === false) {
            return array_key_exists($path, $data) ? $data[$path] : $default;
        }

        foreach (explode('.', $path) as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return $default;
            }
            $data = $data[$key];
        }

        return $data;
    }

    public static function check($value, $condition)
    {
        $keys = \array_keys($condition);

        foreach ($keys as &$key) {
            if ($key == '$options') {
                continue;
            }

            if (!self::evaluate($key, $value, $condition[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match a full criteria array against a document array.
     * Supports $and, $or, $where and field comparisons (delegates to check()).
     */
    public static function match($criteria, $document)
    {
        if (!\is_array($criteria)) {
            return false;
        }

        foreach ($criteria as $key => $value) {
            switch ($key) {
                case '$and':
                    foreach ($value as $v) {
                        if (!self::match($v, $document)) {
                            return false;
                        }
                    }
                    break;

                case '$or':
                    $ok = false;
                    foreach ($value as $v) {
                        if (self::match($v, $document)) {
                            $ok = true;
                            break;
                        }
                    }
                    if (!$ok) {
                        return false;
                    }
                    break;

                case '$where':
                    if (\is_callable($value)) {
                        if (!$value($document)) {
                            return false;
                        }
                    }
                    break;

                default:
                    // resolve nested field value
                    $d = $document;
                    if (\strpos($key, '.') !== false) {
                        $keys = \explode('.', $key);
                        foreach ($keys as $k) {
                            if (!\is_array($d) || !\array_key_exists($k, $d)) {
                                $d = null;
                                break;
                            }
                            $d = $d[$k];
                        }
                    } else {
                        $d = \array_key_exists($key, $d) ? $d[$key] : null;
                    }

                    if (\is_array($value)) {
                        if (!self::check($d, $value)) {
                            return false;
                        }
                    } else {
                        if ($d != $value) {
                            return false;
                        }
                    }
            }
        }

        return true;
    }

    private static function evaluate($func, $a, $b)
    {
        $r = false;

        if (\is_null($a) && $func !== '$exists') {
            return false;
        }

        switch ($func) {
            case '$eq':
                $r = $a === $b;
                break;

            case '$ne':
                $r = $a !== $b;
                break;

            case '$gte':
                if ((\is_numeric($a) && \is_numeric($b)) || (\is_string($a) && \is_string($b))) {
                    $r = $a >= $b;
                }
                break;

            case '$gt':
                if ((\is_numeric($a) && \is_numeric($b)) || (\is_string($a) && \is_string($b))) {
                    $r = $a > $b;
                }
                break;

            case '$lte':
                if ((\is_numeric($a) && \is_numeric($b)) || (\is_string($a) && \is_string($b))) {
                    $r = $a <= $b;
                }
                break;

            case '$lt':
                if ((\is_numeric($a) && \is_numeric($b)) || (\is_string($a) && \is_string($b))) {
                    $r = $a < $b;
                }
                break;

            case '$in':
                if (\is_array($a)) {
                    $r = \is_array($b) ? \count(\array_intersect($a, $b)) : false;
                } else {
                    $r = \is_array($b) ? \in_array($a, $b) : false;
                }
                break;

            case '$nin':
                if (\is_array($a)) {
                    $r = \is_array($b) ? (\count(\array_intersect($a, $b)) === 0) : false;
                } else {
                    $r = \is_array($b) ? (\in_array($a, $b) === false) : false;
                }
                break;

            case '$has':
                if (\is_array($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $has array not supported');
                }
                if (!\is_array($a)) {
                    $a = @\json_decode($a, true) ?: [];
                }
                $r = \in_array($b, $a);
                break;

            case '$all':
                if (!\is_array($a)) {
                    $a = @\json_decode($a, true) ?: [];
                }
                if (!\is_array($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $all option must be array');
                }
                $r = \count(\array_intersect_key($a, $b)) === \count($b);
                break;

            case '$regex':
            case '$preg':
            case '$match':
            case '$not':
                $r = (bool) @\preg_match(isset($b[0]) && $b[0] === '/' ? $b : '/'.$b.'/iu', $a, $match);
                if ($func === '$not') {
                    $r = !$r;
                }
                break;

            case '$size':
                if (!\is_array($a)) {
                    $a = @\json_decode($a, true) ?: [];
                }
                $r = (int) $b === \count($a);
                break;

            case '$mod':
                if (!\is_array($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $mod option must be array');
                }
                $r = $a % $b[0] === ($b[1] ?? 0);
                break;

            case '$func':
            case '$fn':
            case '$f':
                if (!\is_callable($b)) {
                    throw new \InvalidArgumentException('Function should be callable');
                }
                $r = $b($a);
                break;

            case '$exists':
                $r = $b ? !\is_null($a) : \is_null($a);
                break;

            case '$fuzzy':
            case '$text':
                $distance = 3;
                $minScore = 0.7;

                if (\is_array($b) && isset($b['$search'])) {
                    if (isset($b['$minScore']) && \is_numeric($b['$minScore'])) {
                        $minScore = $b['$minScore'];
                    }
                    if (isset($b['$distance']) && \is_numeric($b['$distance'])) {
                        $distance = $b['$distance'];
                    }

                    $b = $b['search'];
                }

                $r = self::fuzzy_search($b, $a, $distance) >= $minScore;
                break;

            default:
                throw new \ErrorException("Condition not valid ... Use {$func} for custom operations");
                break;
        }

        return $r;
    }

    /**
     * Helper function for UTF-8 aware Levenshtein distance.
     */
    public static function levenshtein_utf8($s1, $s2)
    {
        $map = [];
        $utf8_to_extended_ascii = function ($str) use ($map) {
            // find all multibyte characters (cf. utf-8 encoding specs)
            $matches = [];

            if (!\preg_match_all('/[\xC0-\xF7][\x80-\xBF]+/', $str, $matches)) {
                return $str;
            } // plain ascii string

            // update the encoding map with the characters not already met
            foreach ($matches[0] as $mbc) {
                if (!isset($map[$mbc])) {
                    $map[$mbc] = \chr(128 + \count($map));
                }
            }

            // finally remap non-ascii characters
            return \strtr($str, $map);
        };

        return levenshtein($utf8_to_extended_ascii($s1), $utf8_to_extended_ascii($s2));
    }

    /**
     * Fuzzy search function with distance-based matching.
     */
    public static function fuzzy_search($search, $text, $distance = 3)
    {
        $needles = \explode(' ', \mb_strtolower($search, 'UTF-8'));
        $tokens = \explode(' ', \mb_strtolower($text, 'UTF-8'));
        $score = 0;

        foreach ($needles as $needle) {
            foreach ($tokens as $token) {
                if (\strpos($token, $needle) !== false) {
                    ++$score;
                } else {
                    $d = self::levenshtein_utf8($needle, $token);

                    if ($d <= $distance) {
                        $l = \mb_strlen($token, 'UTF-8');
                        $matches = $l - $d;
                        $score += ($matches / $l);
                    }
                }
            }
        }

        // Guard against division by zero if no needles
        $needleCount = \count($needles);
        if ($needleCount === 0) {
            return 0;
        }

        return $score / $needleCount;
    }

    /**
     * Generate a unique ID (UUID v4).
     */
    public static function generateId()
    {
        // Generate a UUID v4
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),

            // 16 bits for "time_mid"
            random_int(0, 0xFFFF),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            random_int(0, 0x0FFF) | 0x4000,

            // 16 bits, 8 bits for "clk_hi_res",
            // 8 bits for "clk_hi_res",
            // two most significant bits holds zero and one for variant DCE1.1
            random_int(0, 0x3FFF) | 0x8000,

            // 48 bits for "node"
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF)
        );
    }
}
