Libsodium & Halite
===========

Install ``paragonie/halite:^5.0`` and enable PHP's ``sodium`` extension.
Halite is a high-level cryptography interface: https://github.com/paragonie/halite

For the HaliteSymmetricTransformer you need to generate an encryption key:

.. code-block:: php

    <?php

    use ParagonIE\Halite\KeyFactory;

    $encKey = KeyFactory::generateEncryptionKey();
    if (!KeyFactory::save($encKey, '/path/outside/webroot/encryption.key')) {
        throw new \RuntimeException('Unable to save the encryption key');
    }

Pass this file path to ``HaliteSymmetricTransformer``. Its default ``binary``
option hex-encodes plaintext before encryption and decodes it after decryption;
Halite's ciphertext remains encoded in both modes. Keep the same option when
reading existing data.