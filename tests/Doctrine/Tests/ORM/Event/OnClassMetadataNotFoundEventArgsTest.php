<?php

namespace Doctrine\Tests\ORM;

use Doctrine\ORM\Event\OnClassMetadataNotFoundEventArgs;
use PHPUnit_Framework_TestCase;

/**
 * Tests for {@see \Doctrine\ORM\Event\OnClassMetadataNotFoundEventArgs}
 *
 * @covers \Doctrine\ORM\Event\OnClassMetadataNotFoundEventArgs
 */
class OnClassMetadataNotFoundEventArgsTest extends PHPUnit_Framework_TestCase
{
    public function testEventArgsMutability()
    {
        /* @var $objectManager \Doctrine\Common\Persistence\ObjectManager */
        $objectManager = $this->getMock('Doctrine\Common\Persistence\ObjectManager');

        $args = new OnClassMetadataNotFoundEventArgs('foo', $objectManager);

        self::assertSame('foo', $args->getClassName());
        self::assertSame($objectManager, $args->getObjectManager());

        self::assertNull($args->getFoundMetadata());

        /* @var $metadata \Doctrine\Common\Persistence\Mapping\ClassMetadata */
        $metadata = $this->getMock('Doctrine\Common\Persistence\Mapping\ClassMetadata');

        $args->setFoundMetadata($metadata);

        self::assertSame($metadata, $args->getFoundMetadata());

        $args->setFoundMetadata(null);

        self::assertNull($args->getFoundMetadata());
    }
}
