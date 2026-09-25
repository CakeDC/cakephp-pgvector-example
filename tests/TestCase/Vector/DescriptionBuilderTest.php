<?php
declare(strict_types=1);

namespace App\Test\TestCase\Vector;

use App\Vector\DescriptionBuilder;
use PHPUnit\Framework\TestCase;

class DescriptionBuilderTest extends TestCase
{
    public function testFullTagSetProducesFullSentence(): void
    {
        $builder = new DescriptionBuilder();
        $description = $builder->build([
            'name' => 'Vinegar Hill House',
            'cuisine' => 'american',
            'addr:city' => 'Brooklyn',
            'cocktails' => 'yes',
            'drink:wine' => 'yes',
            'outdoor_seating' => 'yes',
            'opening_hours' => 'Mo-Th 17:30-21:30; Fr 17:30-22:00',
        ]);

        $this->assertStringContainsString('American restaurant in Brooklyn', $description);
        $this->assertStringContainsString('cocktails and wine', $description);
        $this->assertStringContainsString('outdoor seating', $description);
        $this->assertStringContainsString('open late', $description);
    }

    public function testMissingTagsAreOmittedNotPlaceholders(): void
    {
        $builder = new DescriptionBuilder();
        $description = $builder->build(['name' => 'Mystery Place']);

        $this->assertSame('Restaurant.', $description);
    }

    public function testVeganPreferredOverVegetarianWhenBothPresent(): void
    {
        $builder = new DescriptionBuilder();
        $description = $builder->build([
            'diet:vegan' => 'yes',
            'diet:vegetarian' => 'yes',
        ]);

        $this->assertStringContainsString('vegan options', $description);
        $this->assertStringNotContainsString('vegetarian options', $description);
    }

    public function testEarlyClosingTimeIsNotOpenLate(): void
    {
        $builder = new DescriptionBuilder();
        $description = $builder->build(['opening_hours' => 'Mo-Su 08:00-18:00']);

        $this->assertStringNotContainsString('open late', $description);
    }

    public function testClosingPastMidnightIsOpenLate(): void
    {
        $builder = new DescriptionBuilder();
        $description = $builder->build(['opening_hours' => 'Mo-Su 11:00-02:00']);

        $this->assertStringContainsString('open late', $description);
    }

    public function testLateClosingOnANonFinalDayIsStillOpenLate(): void
    {
        $builder = new DescriptionBuilder();
        // The last range in the string (Su 11:00-21:00) closes early, but an
        // earlier range (Fr 11:30-04:00) closes well past midnight.
        $description = $builder->build([
            'opening_hours' => 'Mo-Th 12:00-22:00; Fr 11:30-04:00; Su 11:00-21:00',
        ]);

        $this->assertStringContainsString('open late', $description);
    }
}
