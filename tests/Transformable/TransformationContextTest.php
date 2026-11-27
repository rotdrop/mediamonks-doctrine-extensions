<?php

namespace MediaMonks\Doctrine\Tests\Transformable;

use Doctrine\Common\EventManager;
use Doctrine\ORM\Mapping as ORM;
use MediaMonks\Doctrine\Mapping\Transformable;
use MediaMonks\Doctrine\Tests\Tool\BaseTestCaseORM;
use MediaMonks\Doctrine\Transformable\TransformableSubscriber;
use MediaMonks\Doctrine\Transformable\Transformer\AbstractTransformer;
use MediaMonks\Doctrine\Transformable\Transformer\TransformerPool;

class TransformationContextTest extends BaseTestCaseORM
{
    protected function getUsedEntityFixtures(): array
    {
        return [ContextEntity::class];
    }

    /**
     * @dataProvider contextModes
     */
    public function testContextSurvivesInsertReloadAndUpdate(bool $cachable, ?array $initialContexts): void
    {
        $transformer = new class($cachable) extends AbstractTransformer {
            public function __construct(private bool $cachable)
            {
            }

            public function isCachable(): bool
            {
                return $this->cachable && parent::isCachable();
            }

            public function transform(?string $value, mixed &$context = null): string
            {
                $context = ($context ?? 0) + 1;
                return json_encode([$value, $context], JSON_THROW_ON_ERROR);
            }

            public function reverseTransform(?string $value, mixed &$context = null): string
            {
                [$plaintext, $context] = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                return $plaintext;
            }
        };
        $pool = new TransformerPool();
        $pool->set('context', $transformer);
        $events = new EventManager();
        $events->addEventSubscriber(new TransformableSubscriber($pool));
        $this->getDefaultMockSqliteEntityManager($events);

        $entity = new ContextEntity($initialContexts);
        $other = new ContextEntity();
        $this->em->persist($entity);
        $this->em->persist($other);
        $this->em->flush();
        $id = $entity->id;
        $otherId = $other->id;
        $expectedContexts = $initialContexts ?? [];
        $expectedContexts['first'] = ($expectedContexts['first'] ?? 0) + 1;
        $expectedContexts['second'] = 1;
        self::assertSame(6, $entity->getScalarContext());
        self::assertSame($expectedContexts, $entity->getContexts());
        self::assertSame(['first' => 1, 'second' => 1], $other->getContexts());
        $original = $this->fetchAssociative('SELECT scalar, first, second FROM context_entities WHERE id = ?', [$id]);
        self::assertSame(['scalar value', 6], json_decode($original['scalar'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['first value', $expectedContexts['first']], json_decode($original['first'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['second value', 1], json_decode($original['second'], true, 512, JSON_THROW_ON_ERROR));

        $this->em->clear();
        $entity = $this->em->find(ContextEntity::class, $id);
        $other = $this->em->find(ContextEntity::class, $otherId);
        self::assertSame('scalar value', $entity->scalar);
        self::assertSame('first value', $entity->first);
        self::assertSame('second value', $entity->second);
        self::assertSame(6, $entity->getScalarContext());
        self::assertSame($expectedContexts['first'], $entity->getContexts()['first']);
        self::assertSame(1, $entity->getContexts()['second']);
        self::assertSame(['first' => 1, 'second' => 1], $other->getContexts());

        $entity->scalar = 'changed';
        $this->em->flush();
        self::assertSame('changed', $entity->scalar);
        self::assertSame(7, $entity->getScalarContext());
        $updated = $this->fetchAssociative('SELECT scalar, first, second FROM context_entities WHERE id = ?', [$id]);
        self::assertSame(['changed', 7], json_decode($updated['scalar'], true, 512, JSON_THROW_ON_ERROR));
        $increment = $cachable ? 0 : 1;
        self::assertSame($expectedContexts['first'] + $increment, $entity->getContexts()['first']);
        self::assertSame(1 + $increment, $entity->getContexts()['second']);
        if ($cachable) {
            self::assertSame($original['first'], $updated['first']);
            self::assertSame($original['second'], $updated['second']);
        } else {
            self::assertNotSame($original['first'], $updated['first']);
            self::assertNotSame($original['second'], $updated['second']);
        }

        $this->em->getUnitOfWork()->scheduleForUpdate($entity);
        $this->em->flush();
        $scheduled = $this->fetchAssociative('SELECT scalar, first, second FROM context_entities WHERE id = ?', [$id]);
        self::assertSame('changed', $entity->scalar);
        self::assertSame(7 + $increment, $entity->getScalarContext());
        self::assertSame($expectedContexts['first'] + 2 * $increment, $entity->getContexts()['first']);
        self::assertSame(1 + 2 * $increment, $entity->getContexts()['second']);
        if ($cachable) {
            self::assertSame($updated, $scheduled);
        } else {
            self::assertSame(['changed', 8], json_decode($scheduled['scalar'], true, 512, JSON_THROW_ON_ERROR));
            self::assertSame(['first value', $expectedContexts['first'] + 2], json_decode($scheduled['first'], true, 512, JSON_THROW_ON_ERROR));
            self::assertSame(['second value', 3], json_decode($scheduled['second'], true, 512, JSON_THROW_ON_ERROR));
        }

        $entity->second = null;
        $this->em->flush();
        self::assertNull($entity->second);
        self::assertSame(1 + 2 * $increment, $entity->getContexts()['second']);
        self::assertNull($this->fetchAssociative('SELECT second FROM context_entities WHERE id = ?', [$id])['second']);
        self::assertSame(['first' => 1, 'second' => 1], $other->getContexts());
    }

    public static function contextModes(): array
    {
        return [
            'cached, null array' => [true, null],
            'cached, seeded array' => [true, ['first' => 7, 'unrelated' => 99]],
            'uncached, null array' => [false, null],
            'uncached, seeded array' => [false, ['first' => 7, 'unrelated' => 99]],
        ];
    }

    /**
     * @dataProvider contextOnlyUpdates
     */
    public function testChangedReaderListsArePersistedWithUnchangedPlaintext(bool $changeAnotherField): void
    {
        $transformer = new class extends AbstractTransformer {
            public int $transformCalls = 0;

            public function isCachable(): bool
            {
                return false;
            }

            public function transform(?string $value, mixed &$context = null): string
            {
                ++$this->transformCalls;
                return json_encode([$value, $context], JSON_THROW_ON_ERROR);
            }

            public function reverseTransform(?string $value, mixed &$context = null): string
            {
                [$plaintext, $context] = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                return $plaintext;
            }
        };
        $pool = new TransformerPool();
        $pool->set('context', $transformer);
        $events = new EventManager();
        $events->addEventSubscriber(new TransformableSubscriber($pool));
        $this->getDefaultMockSqliteEntityManager($events);

        $contexts = [
            'first' => ['reader-a'],
            'second' => ['reader-b'],
            'unrelated' => ['keep'],
        ];
        $entity = new ContextEntity($contexts, ['reader-c']);
        $this->em->persist($entity);
        $this->em->flush();
        $id = $entity->id;
        $original = $this->fetchAssociative('SELECT scalar, first, second FROM context_entities WHERE id = ?', [$id]);
        self::assertSame(3, $transformer->transformCalls);
        self::assertSame(['scalar value', ['reader-c']], json_decode($original['scalar'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['first value', ['reader-a']], json_decode($original['first'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['second value', ['reader-b']], json_decode($original['second'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame($contexts, $entity->getContexts());

        $entity->setScalarContext(['reader-c', 'reader-d']);
        $contexts['first'] = ['reader-e'];
        $entity->setContexts($contexts);
        if ($changeAnotherField) {
            $entity->scalar = 'changed scalar value';
        } else {
            $this->em->flush();
            self::assertSame(3, $transformer->transformCalls);
            self::assertSame($original, $this->fetchAssociative('SELECT scalar, first, second FROM context_entities WHERE id = ?', [$id]));
        }

        $this->em->getUnitOfWork()->scheduleForUpdate($entity);
        $this->em->flush();
        $updated = $this->fetchAssociative('SELECT scalar, first, second FROM context_entities WHERE id = ?', [$id]);
        $scalarValue = $changeAnotherField ? 'changed scalar value' : 'scalar value';
        self::assertSame(6, $transformer->transformCalls);
        self::assertSame([$scalarValue, ['reader-c', 'reader-d']], json_decode($updated['scalar'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['first value', ['reader-e']], json_decode($updated['first'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame($original['second'], $updated['second']);
        self::assertSame($scalarValue, $entity->scalar);
        self::assertSame('first value', $entity->first);
        self::assertSame('second value', $entity->second);
        self::assertSame(['reader-c', 'reader-d'], $entity->getScalarContext());
        self::assertSame($contexts, $entity->getContexts());

        $this->em->clear();
        $entity = $this->em->find(ContextEntity::class, $id);
        self::assertSame($scalarValue, $entity->scalar);
        self::assertSame('first value', $entity->first);
        self::assertSame('second value', $entity->second);
        self::assertSame(['reader-c', 'reader-d'], $entity->getScalarContext());
        self::assertSame(['first' => ['reader-e'], 'second' => ['reader-b']], $entity->getContexts());
        $this->em->flush();
        self::assertSame(6, $transformer->transformCalls);
        self::assertSame($updated, $this->fetchAssociative('SELECT scalar, first, second FROM context_entities WHERE id = ?', [$id]));
    }

    public static function contextOnlyUpdates(): array
    {
        return [
            'no existing changeset' => [false],
            'another field changed' => [true],
        ];
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'context_entities')]
class ContextEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'text')]
    #[Transformable(name: 'context', context: 'scalarContext')]
    public string $scalar = 'scalar value';

    #[ORM\Column(type: 'text')]
    #[Transformable(name: 'context', context: 'contexts[]')]
    public string $first = 'first value';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Transformable(name: 'context', context: 'contexts[]')]
    public ?string $second = 'second value';

    private mixed $scalarContext = 5;

    private ?array $contexts = null;

    public function __construct(?array $contexts = null, mixed $scalarContext = 5)
    {
        $this->contexts = $contexts;
        $this->scalarContext = $scalarContext;
    }

    public function getScalarContext(): mixed
    {
        return $this->scalarContext;
    }

    public function setScalarContext(mixed $context): void
    {
        $this->scalarContext = $context;
    }

    public function getContexts(): ?array
    {
        return $this->contexts;
    }

    public function setContexts(?array $contexts): void
    {
        $this->contexts = $contexts;
    }
}
