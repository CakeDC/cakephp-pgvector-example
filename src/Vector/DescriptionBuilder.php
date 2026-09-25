<?php
declare(strict_types=1);

namespace App\Vector;

class DescriptionBuilder
{
    /**
     * @param array<string, string> $tags
     */
    public function build(array $tags): string
    {
        $parts = [];

        $cuisine = $tags['cuisine'] ?? null;
        $city = $tags['addr:city'] ?? null;

        $subject = $cuisine ? (ucfirst($cuisine) . ' restaurant') : 'Restaurant';
        if ($city) {
            $subject .= ' in ' . $city;
        }
        $parts[] = $subject;

        $drinks = [];
        if (($tags['cocktails'] ?? null) === 'yes') {
            $drinks[] = 'cocktails';
        }
        foreach (['wine', 'beer', 'coffee'] as $drink) {
            if (($tags['drink:' . $drink] ?? null) === 'yes') {
                $drinks[] = $drink;
            }
        }
        if ($drinks !== []) {
            $parts[] = 'serving ' . implode(' and ', $drinks);
        }

        $amenities = [];
        if (($tags['outdoor_seating'] ?? null) === 'yes') {
            $amenities[] = 'outdoor seating';
        }
        if (($tags['diet:vegan'] ?? null) === 'yes') {
            $amenities[] = 'vegan options';
        } elseif (($tags['diet:vegetarian'] ?? null) === 'yes') {
            $amenities[] = 'vegetarian options';
        }
        if ($amenities !== []) {
            $parts[] = 'with ' . implode(', ', $amenities);
        }

        if ($this->isOpenLate($tags['opening_hours'] ?? null)) {
            $parts[] = 'open late';
        }

        return implode(', ', $parts) . '.';
    }

    private function isOpenLate(?string $openingHours): bool
    {
        if ($openingHours === null) {
            return false;
        }
        preg_match_all('/-(\d{2}):\d{2}/', $openingHours, $matches);

        foreach ($matches[1] as $closingHour) {
            $hour = (int)$closingHour;
            if ($hour >= 22 || $hour <= 5) {
                return true;
            }
        }

        return false;
    }
}
