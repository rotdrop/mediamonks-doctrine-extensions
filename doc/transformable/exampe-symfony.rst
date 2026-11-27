Example Symfony
===============

Configure services:

.. code-block:: yaml

    # config/services.yaml
    parameters:
        defuse_encryption_key: '%env(DEFUSE_ENCRYPTION_KEY)%'
        hmac_key: '%env(HMAC_KEY)%'

    services:
        mediamonks.doctrine.transformable.transformer.defuse_encrypt_key:
            class: MediaMonks\Doctrine\Transformable\Transformer\DefuseCryptoEncryptKeyTransformer
            arguments: ["%defuse_encryption_key%"]

        mediamonks.doctrine.transformable.transformer.php_hash:
            class: MediaMonks\Doctrine\Transformable\Transformer\PhpHashTransformer

        mediamonks.doctrine.transformable.transformer.php_hmac:
            class: MediaMonks\Doctrine\Transformable\Transformer\PhpHmacTransformer
            arguments: ["%hmac_key%"]

        mediamonks.doctrine.transformable.transformer_pool:
            class: MediaMonks\Doctrine\Transformable\Transformer\TransformerPool
            calls:
                - [set, ['defuse_encrypt_key', "@mediamonks.doctrine.transformable.transformer.defuse_encrypt_key"]]
                - [set, ['php_hash', "@mediamonks.doctrine.transformable.transformer.php_hash"]]
                - [set, ['php_hmac', "@mediamonks.doctrine.transformable.transformer.php_hmac"]]

        doctrine.transformable.subscriber:
            class: MediaMonks\Doctrine\Transformable\TransformableSubscriber
            arguments: ["@mediamonks.doctrine.transformable.transformer_pool"]
            tags:
             - { name: doctrine.event_listener, event: loadClassMetadata }
             - { name: doctrine.event_listener, event: onFlush }
             - { name: doctrine.event_listener, event: postLoad }
             - { name: doctrine.event_listener, event: postPersist }
             - { name: doctrine.event_listener, event: postUpdate }

Generate ``DEFUSE_ENCRYPTION_KEY`` with ``Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString()``
and provide a separate random ``HMAC_KEY`` through the environment.

Configure entity with attributes (Doctrine ORM 3):

.. code-block:: php

    <?php

    namespace App\Entity;

    use Doctrine\ORM\Mapping as ORM;
    use MediaMonks\Doctrine\Mapping as MediaMonks;

    #[ORM\Entity]
    #[ORM\Table(name: 'examples')]
    class Example
    {
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(type: 'integer')]
        private ?int $id = null;

        #[ORM\Column(type: 'blob', nullable: true)]
        #[MediaMonks\Transformable(name: 'defuse_encrypt_key')]
        protected mixed $email = null;

        #[ORM\Column(type: 'blob', nullable: true)]
        #[MediaMonks\Transformable(name: 'php_hash')]
        protected mixed $emailCanonical = null;
    }
