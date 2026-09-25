<?php
declare(strict_types=1);

namespace App\Database\Type;

use Cake\Database\Driver;
use Cake\Database\Type\BaseType;
use InvalidArgumentException;
use PDO;

class VectorType extends BaseType
{
    public function toDatabase(mixed $value, Driver $driver): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('VectorType expects an array of floats or null.');
        }

        return '[' . implode(',', array_map(
            static fn (float $v): string => rtrim(rtrim(sprintf('%.10f', $v), '0'), '.') ?: '0',
            array_map('floatval', $value)
        )) . ']';
    }

    public function toPHP(mixed $value, Driver $driver): ?array
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string)$value, '[]');
        if ($trimmed === '') {
            return [];
        }

        return array_map('floatval', explode(',', $trimmed));
    }

    public function toStatement(mixed $value, Driver $driver): int
    {
        return PDO::PARAM_STR;
    }

    public function marshal(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map('floatval', $value);
        }

        return $value;
    }
}
