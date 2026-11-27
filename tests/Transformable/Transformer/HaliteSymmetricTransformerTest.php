<?php

namespace MediaMonks\Doctrine\Tests\Transformable\Transformer;

use MediaMonks\Doctrine\Transformable\Transformer\HaliteSymmetricTransformer;
use ParagonIE\Halite\Alerts\InvalidMessage;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\HiddenString\HiddenString;
use PHPUnit\Framework\TestCase;

class HaliteSymmetricTransformerTest extends TestCase
{
    const VALUE_TO_ENCRYPT = 'foobar';

    private ?string $encryptionKeyPath = null;

    protected function setUp(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Libsodium not installed');
        }
        $path = tempnam(sys_get_temp_dir(), 'halite-test-');
        self::assertNotFalse($path);
        $this->encryptionKeyPath = $path;
        self::assertTrue(KeyFactory::save(
            KeyFactory::generateEncryptionKey(),
            $this->encryptionKeyPath
        ));
    }

    protected function tearDown(): void
    {
        if ($this->encryptionKeyPath !== null) {
            unlink($this->encryptionKeyPath);
        }
    }

    protected function getTransformerHex(): HaliteSymmetricTransformer
    {
        return new HaliteSymmetricTransformer($this->encryptionKeyPath, ['binary' => false]);
    }

    protected function getTransformerBinary(): HaliteSymmetricTransformer
    {
        return new HaliteSymmetricTransformer($this->encryptionKeyPath);
    }

    public function testBinaryDefaultEnabled(): void
    {
        $transformer = new HaliteSymmetricTransformer($this->encryptionKeyPath);
        $this->assertTrue($transformer->getBinary());
    }

    public function testTransformHex(): void
    {
        $transformer = $this->getTransformerHex();
        $this->assertSame(self::VALUE_TO_ENCRYPT, $transformer->reverseTransform($transformer->transform(self::VALUE_TO_ENCRYPT)));
    }

    public function testTransformNullValue(): void
    {
        $transformer = $this->getTransformerHex();
        $this->assertFalse($transformer->transform(null));
        $this->assertNull($transformer->reverseTransform(null));
        $this->assertFalse($transformer->transform(''));
        $this->assertNull($transformer->reverseTransform(''));
    }

    public function testTransformBinary(): void
    {
        $transformer = $this->getTransformerBinary();
        $this->assertSame(self::VALUE_TO_ENCRYPT, $transformer->reverseTransform($transformer->transform(self::VALUE_TO_ENCRYPT)));
    }

    public function testTransformBinaryData(): void
    {
        $transformer = $this->getTransformerBinary();
        $value = "\x00\x80\xff";
        $this->assertSame($value, $transformer->reverseTransform($transformer->transform($value)));
    }

    /**
     * @dataProvider binaryModes
     */
    public function testCompatibleWithHaliteCiphertext(bool $binary): void
    {
        $transformer = new HaliteSymmetricTransformer($this->encryptionKeyPath, ['binary' => $binary]);
        $key = KeyFactory::loadEncryptionKey($this->encryptionKeyPath);
        $value = "\x00\x80\xff";
        $encodedValue = $binary ? sodium_bin2hex($value) : $value;
        $ciphertext = $transformer->transform($value);
        self::assertSame($encodedValue, Crypto::decrypt($ciphertext, $key)->getString());
        self::assertSame($value, $transformer->reverseTransform(Crypto::encrypt(new HiddenString($encodedValue), $key)));
        self::assertNotSame($ciphertext, $transformer->transform($value));
    }

    public function testRejectsCiphertextEncryptedWithAnotherKey(): void
    {
        $ciphertext = Crypto::encrypt(new HiddenString(self::VALUE_TO_ENCRYPT), KeyFactory::generateEncryptionKey());
        $this->expectException(InvalidMessage::class);
        $this->getTransformerHex()->reverseTransform($ciphertext);
    }

    public static function binaryModes(): array
    {
        return [
            'hex-encoded plaintext' => [true],
            'unencoded plaintext' => [false],
        ];
    }
}
