<?php
declare(strict_types=1);

namespace App\Test\TestCase\Database\Type;

use App\Database\Type\VectorType;
use Cake\Database\Driver\Postgres;
use PHPUnit\Framework\TestCase;

class VectorTypeTest extends TestCase
{
    public function testToDatabaseConvertsArrayToVectorLiteral(): void
    {
        $type = new VectorType('vector');
        $this->assertSame('[1,0.5,0]', $type->toDatabase([1.0, 0.5, 0.0], new Postgres()));
    }

    public function testToDatabaseConvertsNullToNull(): void
    {
        $type = new VectorType('vector');
        $this->assertNull($type->toDatabase(null, new Postgres()));
    }

    public function testToPhpConvertsVectorLiteralToArray(): void
    {
        $type = new VectorType('vector');
        $this->assertSame([1.0, 0.5, 0.0], $type->toPHP('[1,0.5,0]', new Postgres()));
    }

    public function testToPhpConvertsNullToNull(): void
    {
        $type = new VectorType('vector');
        $this->assertNull($type->toPHP(null, new Postgres()));
    }
}
