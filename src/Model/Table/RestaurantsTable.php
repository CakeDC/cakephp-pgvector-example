<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Vector\EmbeddingGenerator;
use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Restaurants Model
 *
 * @method \App\Model\Entity\Restaurant newEmptyEntity()
 * @method \App\Model\Entity\Restaurant newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Restaurant> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Restaurant get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Restaurant findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Restaurant patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Restaurant> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Restaurant|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Restaurant saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Restaurant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Restaurant>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Restaurant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Restaurant> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Restaurant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Restaurant>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Restaurant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Restaurant> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RestaurantsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('restaurants');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->getSchema()->setColumnType('embedding', 'vector');
    }

    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if ($entity->isNew() || $entity->isDirty('description')) {
            $entity->set('embedding', (new EmbeddingGenerator())->embed((string)$entity->get('description')));
        }
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('osm_id', 'create')
            ->notEmptyString('osm_id')
            ->add('osm_id', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('cuisine')
            ->maxLength('cuisine', 255)
            ->allowEmptyString('cuisine');

        $validator
            ->scalar('city')
            ->maxLength('city', 255)
            ->allowEmptyString('city');

        $validator
            ->scalar('address')
            ->maxLength('address', 255)
            ->allowEmptyString('address');

        $validator
            ->allowEmptyString('tags');

        $validator
            ->scalar('description')
            ->requirePresence('description', 'create')
            ->notEmptyString('description');

        return $validator;
    }

    /**
     * @param array<int, float> $vector
     */
    public function findSimilarTo(SelectQuery $query, array $vector): SelectQuery
    {
        // Two distinct placeholders (not one reused) because native pgsql
        // prepares don't support the same named parameter appearing twice.
        $query
            ->bind(':vector1', $vector, 'vector')
            ->bind(':vector2', $vector, 'vector');

        return $query
            ->where(['embedding IS NOT' => null])
            ->selectAlso(['distance' => $query->expr('embedding <=> :vector1')])
            ->orderByAsc($query->expr('embedding <=> :vector2'));
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['osm_id']), ['errorField' => 'osm_id']);

        return $rules;
    }
}
