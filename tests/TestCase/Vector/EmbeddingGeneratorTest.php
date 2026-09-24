<?php
declare(strict_types=1);

namespace App\Test\TestCase\Vector;

use App\Vector\EmbeddingGenerator;
use PHPUnit\Framework\TestCase;

class EmbeddingGeneratorTest extends TestCase
{
    public function testSameInputProducesSameVector(): void
    {
        $generator = new EmbeddingGenerator();
        $this->assertSame($generator->embed('Italian restaurant'), $generator->embed('Italian restaurant'));
    }

    public function testVectorHas128Dimensions(): void
    {
        $generator = new EmbeddingGenerator();
        $this->assertCount(128, $generator->embed('any text at all'));
    }

    public function testVectorIsL2Normalized(): void
    {
        $generator = new EmbeddingGenerator();
        $vector = $generator->embed('Italian restaurant with outdoor seating');
        $magnitude = sqrt(array_sum(array_map(fn (float $v): float => $v * $v, $vector)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);
    }

    public function testDifferentInputsProduceDifferentVectors(): void
    {
        $generator = new EmbeddingGenerator();
        $this->assertNotSame($generator->embed('Italian restaurant'), $generator->embed('Sushi bar'));
    }

    public function testEmptyStringProducesAllZeroVector(): void
    {
        $generator = new EmbeddingGenerator();
        $this->assertSame(array_fill(0, 128, 0.0), $generator->embed(''));
    }

    public function testLongQueryDoesNotError(): void
    {
        $generator = new EmbeddingGenerator();
        $longText = str_repeat('quiet outdoor seating vegan wine ', 500);
        $vector = $generator->embed($longText);
        $this->assertCount(128, $vector);
    }
}
