<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Restaurant Entity
 *
 * @property int $id
 * @property int $osm_id
 * @property string $name
 * @property string|null $cuisine
 * @property string|null $city
 * @property string|null $address
 * @property array|null $tags
 * @property string $description
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property string|null $embedding
 */
class Restaurant extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'osm_id' => true,
        'name' => true,
        'cuisine' => true,
        'city' => true,
        'address' => true,
        'tags' => true,
        'description' => true,
        'created' => true,
        'modified' => true,
        'embedding' => true,
    ];
}
