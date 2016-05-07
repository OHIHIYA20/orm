<?php

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\Tests\Models\Cache\Country;
use Doctrine\Tests\Models\Cache\State;
use Doctrine\ORM\Events;

/**
 * @group DDC-2183
 */
class SecondLevelCacheTest extends SecondLevelCacheAbstractTest
{
    public function testPutOnPersist()
    {
        $this->loadFixturesCountries();
        $this->_em->clear();

        self::assertTrue($this->cache->containsEntity(Country::CLASSNAME, $this->countries[0]->getId()));
        self::assertTrue($this->cache->containsEntity(Country::CLASSNAME, $this->countries[1]->getId()));
        self::assertEquals(2, $this->secondLevelCacheLogger->getPutCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getRegionPutCount($this->getEntityRegion(Country::CLASSNAME)));
    }

    public function testPutAndLoadEntities()
    {
        $this->loadFixturesCountries();
        $this->_em->clear();

        self::assertEquals(2, $this->secondLevelCacheLogger->getPutCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getRegionPutCount($this->getEntityRegion(Country::CLASSNAME)));

        $this->cache->evictEntityRegion(Country::CLASSNAME);

        self::assertFalse($this->cache->containsEntity(Country::CLASSNAME, $this->countries[0]->getId()));
        self::assertFalse($this->cache->containsEntity(Country::CLASSNAME, $this->countries[1]->getId()));

        $c1 = $this->_em->find(Country::CLASSNAME, $this->countries[0]->getId());
        $c2 = $this->_em->find(Country::CLASSNAME, $this->countries[1]->getId());

        self::assertTrue($this->cache->containsEntity(Country::CLASSNAME, $this->countries[0]->getId()));
        self::assertTrue($this->cache->containsEntity(Country::CLASSNAME, $this->countries[1]->getId()));

        self::assertInstanceOf(Country::CLASSNAME, $c1);
        self::assertInstanceOf(Country::CLASSNAME, $c2);

        self::assertEquals($this->countries[0]->getId(), $c1->getId());
        self::assertEquals($this->countries[0]->getName(), $c1->getName());

        self::assertEquals($this->countries[1]->getId(), $c2->getId());
        self::assertEquals($this->countries[1]->getName(), $c2->getName());

        $this->_em->clear();

        $queryCount = $this->getCurrentQueryCount();

        $c3 = $this->_em->find(Country::CLASSNAME, $this->countries[0]->getId());
        $c4 = $this->_em->find(Country::CLASSNAME, $this->countries[1]->getId());

        self::assertEquals($queryCount, $this->getCurrentQueryCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getHitCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getRegionHitCount($this->getEntityRegion(Country::CLASSNAME)));

        self::assertInstanceOf(Country::CLASSNAME, $c3);
        self::assertInstanceOf(Country::CLASSNAME, $c4);
        
        self::assertEquals($c1->getId(), $c3->getId());
        self::assertEquals($c1->getName(), $c3->getName());

        self::assertEquals($c2->getId(), $c4->getId());
        self::assertEquals($c2->getName(), $c4->getName());
    }

    public function testRemoveEntities()
    {
        $this->loadFixturesCountries();
        $this->_em->clear();

        self::assertEquals(2, $this->secondLevelCacheLogger->getPutCount());

        $this->cache->evictEntityRegion(Country::CLASSNAME);
        $this->secondLevelCacheLogger->clearRegionStats($this->getEntityRegion(Country::CLASSNAME));

        self::assertFalse($this->cache->containsEntity(Country::CLASSNAME, $this->countries[0]->getId()));
        self::assertFalse($this->cache->containsEntity(Country::CLASSNAME, $this->countries[1]->getId()));

        $c1 = $this->_em->find(Country::CLASSNAME, $this->countries[0]->getId());
        $c2 = $this->_em->find(Country::CLASSNAME, $this->countries[1]->getId());

        self::assertEquals(2, $this->secondLevelCacheLogger->getPutCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getMissCount());

        self::assertTrue($this->cache->containsEntity(Country::CLASSNAME, $this->countries[0]->getId()));
        self::assertTrue($this->cache->containsEntity(Country::CLASSNAME, $this->countries[1]->getId()));

        self::assertInstanceOf(Country::CLASSNAME, $c1);
        self::assertInstanceOf(Country::CLASSNAME, $c2);

        self::assertEquals($this->countries[0]->getId(), $c1->getId());
        self::assertEquals($this->countries[0]->getName(), $c1->getName());

        self::assertEquals($this->countries[1]->getId(), $c2->getId());
        self::assertEquals($this->countries[1]->getName(), $c2->getName());

        $this->_em->remove($c1);
        $this->_em->remove($c2);
        $this->_em->flush();
        $this->_em->clear();

        self::assertFalse($this->cache->containsEntity(Country::CLASSNAME, $this->countries[0]->getId()));
        self::assertFalse($this->cache->containsEntity(Country::CLASSNAME, $this->countries[1]->getId()));

        self::assertNull($this->_em->find(Country::CLASSNAME, $this->countries[0]->getId()));
        self::assertNull($this->_em->find(Country::CLASSNAME, $this->countries[1]->getId()));

        self::assertEquals(2, $this->secondLevelCacheLogger->getPutCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getMissCount());
    }

    public function testUpdateEntities()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->_em->clear();

        self::assertEquals(6, $this->secondLevelCacheLogger->getPutCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getRegionPutCount($this->getEntityRegion(Country::CLASSNAME)));
        self::assertEquals(4, $this->secondLevelCacheLogger->getRegionPutCount($this->getEntityRegion(State::CLASSNAME)));

        $this->cache->evictEntityRegion(State::CLASSNAME);
        $this->secondLevelCacheLogger->clearRegionStats($this->getEntityRegion(State::CLASSNAME));

        self::assertFalse($this->cache->containsEntity(State::CLASSNAME, $this->states[0]->getId()));
        self::assertFalse($this->cache->containsEntity(State::CLASSNAME, $this->states[1]->getId()));

        $s1 = $this->_em->find(State::CLASSNAME, $this->states[0]->getId());
        $s2 = $this->_em->find(State::CLASSNAME, $this->states[1]->getId());

        self::assertEquals(4, $this->secondLevelCacheLogger->getPutCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getRegionPutCount($this->getEntityRegion(Country::CLASSNAME)));
        self::assertEquals(2, $this->secondLevelCacheLogger->getRegionPutCount($this->getEntityRegion(State::CLASSNAME)));

        self::assertTrue($this->cache->containsEntity(State::CLASSNAME, $this->states[0]->getId()));
        self::assertTrue($this->cache->containsEntity(State::CLASSNAME, $this->states[1]->getId()));

        self::assertInstanceOf(State::CLASSNAME, $s1);
        self::assertInstanceOf(State::CLASSNAME, $s2);

        self::assertEquals($this->states[0]->getId(), $s1->getId());
        self::assertEquals($this->states[0]->getName(), $s1->getName());

        self::assertEquals($this->states[1]->getId(), $s2->getId());
        self::assertEquals($this->states[1]->getName(), $s2->getName());

        $s1->setName("NEW NAME 1");
        $s2->setName("NEW NAME 2");

        $this->_em->persist($s1);
        $this->_em->persist($s2);
        $this->_em->flush();
        $this->_em->clear();

        self::assertTrue($this->cache->containsEntity(State::CLASSNAME, $this->states[0]->getId()));
        self::assertTrue($this->cache->containsEntity(State::CLASSNAME, $this->states[1]->getId()));

        self::assertEquals(6, $this->secondLevelCacheLogger->getPutCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getRegionPutCount($this->getEntityRegion(Country::CLASSNAME)));
        self::assertEquals(4, $this->secondLevelCacheLogger->getRegionPutCount($this->getEntityRegion(State::CLASSNAME)));

        $queryCount = $this->getCurrentQueryCount();

        $c3 = $this->_em->find(State::CLASSNAME, $this->states[0]->getId());
        $c4 = $this->_em->find(State::CLASSNAME, $this->states[1]->getId());

        self::assertEquals(2, $this->secondLevelCacheLogger->getHitCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getRegionHitCount($this->getEntityRegion(State::CLASSNAME)));

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertTrue($this->cache->containsEntity(State::CLASSNAME, $this->states[0]->getId()));
        self::assertTrue($this->cache->containsEntity(State::CLASSNAME, $this->states[1]->getId()));

        self::assertInstanceOf(State::CLASSNAME, $c3);
        self::assertInstanceOf(State::CLASSNAME, $c4);

        self::assertEquals($s1->getId(), $c3->getId());
        self::assertEquals("NEW NAME 1", $c3->getName());

        self::assertEquals($s2->getId(), $c4->getId());
        self::assertEquals("NEW NAME 2", $c4->getName());

        self::assertEquals(2, $this->secondLevelCacheLogger->getHitCount());
        self::assertEquals(2, $this->secondLevelCacheLogger->getRegionHitCount($this->getEntityRegion(State::CLASSNAME)));
    }

    public function testPostFlushFailure()
    {
        $listener = new ListenerSecondLevelCacheTest(array(Events::postFlush => function(){
            throw new \RuntimeException('post flush failure');
        }));

        $this->_em->getEventManager()
            ->addEventListener(Events::postFlush, $listener);

        $country = new Country("Brazil");

        $this->cache->evictEntityRegion(Country::CLASSNAME);

        try {

            $this->_em->persist($country);
            $this->_em->flush();
            $this->fail('Should throw exception');

        } catch (\RuntimeException $exc) {
            self::assertNotNull($country->getId());
            self::assertEquals('post flush failure', $exc->getMessage());
            self::assertTrue($this->cache->containsEntity(Country::CLASSNAME, $country->getId()));
        }
    }

    public function testPostUpdateFailure()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->_em->clear();

        $listener = new ListenerSecondLevelCacheTest(array(Events::postUpdate => function(){
            throw new \RuntimeException('post update failure');
        }));

        $this->_em->getEventManager()
            ->addEventListener(Events::postUpdate, $listener);

        $this->cache->evictEntityRegion(State::CLASSNAME);

        $stateId    = $this->states[0]->getId();
        $stateName  = $this->states[0]->getName();
        $state      = $this->_em->find(State::CLASSNAME, $stateId);
        
        self::assertTrue($this->cache->containsEntity(State::CLASSNAME, $stateId));
        self::assertInstanceOf(State::CLASSNAME, $state);
        self::assertEquals($stateName, $state->getName());

        $state->setName($stateName . uniqid());

        $this->_em->persist($state);

        try {
            $this->_em->flush();
            $this->fail('Should throw exception');

        } catch (\Exception $exc) {
            self::assertEquals('post update failure', $exc->getMessage());
        }

        $this->_em->clear();

        self::assertTrue($this->cache->containsEntity(State::CLASSNAME, $stateId));

        $state = $this->_em->find(State::CLASSNAME, $stateId);

        self::assertInstanceOf(State::CLASSNAME, $state);
        self::assertEquals($stateName, $state->getName());
    }

    public function testPostRemoveFailure()
    {
        $this->loadFixturesCountries();
        $this->_em->clear();

        $listener = new ListenerSecondLevelCacheTest(array(Events::postRemove => function(){
            throw new \RuntimeException('post remove failure');
        }));

        $this->_em->getEventManager()
            ->addEventListener(Events::postRemove, $listener);

        $this->cache->evictEntityRegion(Country::CLASSNAME);

        $countryId  = $this->countries[0]->getId();
        $country    = $this->_em->find(Country::CLASSNAME, $countryId);

        self::assertTrue($this->cache->containsEntity(Country::CLASSNAME, $countryId));
        self::assertInstanceOf(Country::CLASSNAME, $country);

        $this->_em->remove($country);

        try {
            $this->_em->flush();
            $this->fail('Should throw exception');

        } catch (\Exception $exc) {
            self::assertEquals('post remove failure', $exc->getMessage());
        }

        $this->_em->clear();

        self::assertFalse(
            $this->cache->containsEntity(Country::CLASSNAME, $countryId),
            'Removal attempts should clear the cache entry corresponding to the entity'
        );

        self::assertInstanceOf(Country::CLASSNAME, $this->_em->find(Country::CLASSNAME, $countryId));
    }

    public function testCachedNewEntityExists()
    {
        $this->loadFixturesCountries();

        $persister  = $this->_em->getUnitOfWork()->getEntityPersister(Country::CLASSNAME);
        $queryCount = $this->getCurrentQueryCount();

        self::assertTrue($persister->exists($this->countries[0]));

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertFalse($persister->exists(new Country('Foo')));
    }
}


class ListenerSecondLevelCacheTest
{
    public $callbacks;

    public function __construct(array $callbacks = array())
    {
        $this->callbacks = $callbacks;
    }

    private function dispatch($eventName, $args)
    {
        if (isset($this->callbacks[$eventName])) {
            call_user_func($this->callbacks[$eventName], $args);
        }
    }

    public function postFlush($args)
    {
        $this->dispatch(__FUNCTION__, $args);
    }

    public function postUpdate($args)
    {
        $this->dispatch(__FUNCTION__, $args);
    }

    public function postRemove($args)
    {
        $this->dispatch(__FUNCTION__, $args);
    }
}
