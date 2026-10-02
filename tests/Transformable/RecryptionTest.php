<?php

namespace MediaMonks\Doctrine\Tests\Transformable;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use MediaMonks\Doctrine\Mapping\Transformable;
use MediaMonks\Doctrine\Transformable\TransformableSubscriber;
use MediaMonks\Doctrine\Transformable\Transformer\TransformerInterface;
use MediaMonks\Doctrine\Transformable\Transformer\TransformerPool;
use PHPUnit\Framework\TestCase;

class RecryptionTest extends TestCase
{
    /**
     * @dataProvider existingChanges
     */
    public function testScheduledRecryptionPersistsNewCiphertext(bool $changeAnotherField): void
    {
        $transformer = new class implements TransformerInterface {
            public string $key = 'old';

            public function transform(?string $value, mixed &$context = null): mixed
            {
                $iv = random_bytes(12);
                $ciphertext = openssl_encrypt($value, 'aes-256-gcm', hash('sha256', $this->key, true), OPENSSL_RAW_DATA, $iv, $tag);
                if ($ciphertext === false) {
                    throw new \RuntimeException('Encryption failed');
                }
                return $iv . $tag . $ciphertext;
            }

            public function reverseTransform(?string $value, mixed &$context = null): mixed
            {
                $plaintext = $this->decrypt($value, $this->key);
                if ($plaintext === false) {
                    throw new \RuntimeException('Decryption failed');
                }
                return $plaintext;
            }

            public function decrypt(string $value, string $key): string|false
            {
                return openssl_decrypt(substr($value, 28), 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, substr($value, 0, 12), substr($value, 12, 16));
            }

            public function isCachable(): bool
            {
                return false;
            }
        };
        $pool = new TransformerPool();
        $pool->set('mocked', $transformer);
        $events = new EventManager();
        $events->addEventSubscriber(new TransformableSubscriber($pool));
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/Fixture'], true);
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config, $events);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(RecryptionEntity::class)]);

        $entity = new RecryptionEntity();
        $entity->value = 'plaintext';
        $entity->blob = 'blob contents';
        $em->persist($entity);
        $em->flush();
        $id = $entity->id;
        $original = $em->getConnection()->fetchAssociative('SELECT value, blob FROM tests WHERE id = ?', [$id]);
        self::assertSame('plaintext', $transformer->decrypt($original['value'], 'old'));
        self::assertSame('blob contents', $transformer->decrypt($original['blob'], 'old'));
        $em->clear();

        $entity = $em->find(RecryptionEntity::class, $id);
        self::assertSame('plaintext', $entity->value);
        self::assertSame('blob contents', $entity->blob);
        $transformer->key = 'new';
        if ($changeAnotherField) {
            $entity->updated = true;
        }
        $em->getUnitOfWork()->scheduleForUpdate($entity);
        $em->flush();

        $updated = $em->getConnection()->fetchAssociative('SELECT value, blob FROM tests WHERE id = ?', [$id]);
        self::assertNotSame($original['value'], $updated['value']);
        self::assertNotSame($original['blob'], $updated['blob']);
        self::assertSame('plaintext', $transformer->decrypt($updated['value'], 'new'));
        self::assertSame('blob contents', $transformer->decrypt($updated['blob'], 'new'));
        self::assertFalse($transformer->decrypt($updated['value'], 'old'));
        self::assertFalse($transformer->decrypt($updated['blob'], 'old'));
        self::assertSame('plaintext', $entity->value);
        self::assertSame('blob contents', $entity->blob);
        $em->flush();
        self::assertSame($updated, $em->getConnection()->fetchAssociative('SELECT value, blob FROM tests WHERE id = ?', [$id]));
        $em->clear();
        $entity = $em->find(RecryptionEntity::class, $id);
        self::assertSame('plaintext', $entity->value);
        self::assertSame('blob contents', $entity->blob);
        self::assertSame($changeAnotherField, $entity->updated);

        $entity->value = 'changed';
        $em->flush();
        $updated = $em->getConnection()->fetchAssociative('SELECT value, blob FROM tests WHERE id = ?', [$id]);
        self::assertSame('changed', $transformer->decrypt($updated['value'], 'new'));
        self::assertSame('blob contents', $transformer->decrypt($updated['blob'], 'new'));
        self::assertSame('changed', $entity->value);

        $entity->value = null;
        $entity->blob = null;
        $em->flush();
        self::assertSame(['value' => null, 'blob' => null], $em->getConnection()->fetchAssociative('SELECT value, blob FROM tests WHERE id = ?', [$id]));
        self::assertNull($entity->value);
        self::assertNull($entity->blob);
    }

    public static function existingChanges(): array
    {
        return [
            'no existing changeset' => [false],
            'another field changed' => [true],
        ];
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'tests')]
class RecryptionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    public int $id;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Transformable(name: 'mocked')]
    public ?string $value = null;

    #[ORM\Column(type: 'blob', nullable: true)]
    #[Transformable(name: 'mocked')]
    public mixed $blob = null;

    #[ORM\Column(type: 'boolean')]
    public bool $updated = false;
}
