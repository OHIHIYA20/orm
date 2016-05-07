<?php

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Tests\Models\CMS\CmsUser;
use Doctrine\Tests\Models\CMS\CmsArticle;
use Doctrine\Tests\OrmFunctionalTestCase;
use Doctrine\Common\Cache\ArrayCache;

/**
 * ResultCacheTest
 *
 * @author robo
 */
class ResultCacheTest extends OrmFunctionalTestCase
{
   /**
     * @var \ReflectionProperty
     */
    private $cacheDataReflection;

    protected function setUp() {
        $this->cacheDataReflection = new \ReflectionProperty("Doctrine\Common\Cache\ArrayCache", "data");

        $this->cacheDataReflection->setAccessible(true);

        $this->useModelSet('cms');

        parent::setUp();
    }

    /**
     * @param   ArrayCache $cache
     * @return  integer
     */
    private function getCacheSize(ArrayCache $cache)
    {
        return sizeof($this->cacheDataReflection->getValue($cache));
    }

    public function testResultCache()
    {
        $user = new CmsUser;

        $user->name = 'Roman';
        $user->username = 'romanb';
        $user->status = 'dev';

        $this->_em->persist($user);
        $this->_em->flush();

        $query = $this->_em->createQuery('select ux from Doctrine\Tests\Models\CMS\CmsUser ux');
        $cache = new ArrayCache();

        $query->setResultCacheDriver($cache)->setResultCacheId('my_cache_id');

        self::assertFalse($cache->contains('my_cache_id'));

        $users = $query->getResult();

        self::assertTrue($cache->contains('my_cache_id'));
        self::assertEquals(1, count($users));
        self::assertEquals('Roman', $users[0]->name);

        $this->_em->clear();

        $query2 = $this->_em->createQuery('select ux from Doctrine\Tests\Models\CMS\CmsUser ux');
        $query2->setResultCacheDriver($cache)->setResultCacheId('my_cache_id');

        $users = $query2->getResult();

        self::assertTrue($cache->contains('my_cache_id'));
        self::assertEquals(1, count($users));
        self::assertEquals('Roman', $users[0]->name);
    }

    public function testSetResultCacheId()
    {
        $cache = new ArrayCache;
        $query = $this->_em->createQuery('select ux from Doctrine\Tests\Models\CMS\CmsUser ux');

        $query->setResultCacheDriver($cache);
        $query->setResultCacheId('testing_result_cache_id');

        self::assertFalse($cache->contains('testing_result_cache_id'));

        $users = $query->getResult();

        self::assertTrue($cache->contains('testing_result_cache_id'));
    }

    public function testUseResultCache()
    {
        $cache = new ArrayCache();
        $query = $this->_em->createQuery('select ux from Doctrine\Tests\Models\CMS\CmsUser ux');

        $query->useResultCache(true);
        $query->setResultCacheDriver($cache);
        $query->setResultCacheId('testing_result_cache_id');

        $users = $query->getResult();

        self::assertTrue($cache->contains('testing_result_cache_id'));

        $this->_em->getConfiguration()->setResultCacheImpl(new ArrayCache());
    }

    /**
     * @group DDC-1026
     */
    public function testUseResultCacheParams()
    {
        $cache    = new ArrayCache();
        $sqlCount = count($this->_sqlLoggerStack->queries);
        $query    = $this->_em->createQuery('select ux from Doctrine\Tests\Models\CMS\CmsUser ux WHERE ux.id = ?1');

        $query->setParameter(1, 1);
        $query->setResultCacheDriver($cache);
        $query->useResultCache(true);
        $query->getResult();

        $query->setParameter(1, 2);
        $query->getResult();

        self::assertEquals($sqlCount + 2, count($this->_sqlLoggerStack->queries), "Two non-cached queries.");

        $query->setParameter(1, 1);
        $query->useResultCache(true);
        $query->getResult();

        $query->setParameter(1, 2);
        $query->getResult();

        self::assertEquals($sqlCount + 2, count($this->_sqlLoggerStack->queries), "The next two sql should have been cached, but were not.");
    }

    /**
     * @return \Doctrine\ORM\NativeQuery
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function testNativeQueryResultCaching()
    {
        $cache = new ArrayCache();
        $rsm   = new ResultSetMapping();

        $rsm->addScalarResult('id', 'u', Type::getType('integer'));

        $query = $this->_em->createNativeQuery('select u.id FROM cms_users u WHERE u.id = ?', $rsm);

        $query->setParameter(1, 10);
        $query->setResultCacheDriver($cache)->useResultCache(true);

        self::assertEquals(0, $this->getCacheSize($cache));

        $query->getResult();

        self::assertEquals(1, $this->getCacheSize($cache));

        return $query;
    }

    /**
     * @param string $query
     * @depends testNativeQueryResultCaching
     */
    public function testResultCacheNotDependsOnQueryHints($query)
    {
        $cache      = $query->getResultCacheDriver();
        $cacheCount = $this->getCacheSize($cache);

        $query->setHint('foo', 'bar');
        $query->getResult();

        self::assertEquals($cacheCount, $this->getCacheSize($cache));
    }

    /**
     * @param <type> $query
     * @depends testNativeQueryResultCaching
     */
    public function testResultCacheDependsOnParameters($query)
    {
        $cache = $query->getResultCacheDriver();
        $cacheCount = $this->getCacheSize($cache);

        $query->setParameter(1, 50);
        $query->getResult();

        self::assertEquals($cacheCount + 1, $this->getCacheSize($cache));
    }

    /**
     * @param <type> $query
     * @depends testNativeQueryResultCaching
     */
    public function testResultCacheNotDependsOnHydrationMode($query)
    {
        $cache = $query->getResultCacheDriver();
        $cacheCount = $this->getCacheSize($cache);

        self::assertNotEquals(\Doctrine\ORM\Query::HYDRATE_ARRAY, $query->getHydrationMode());
        $query->getArrayResult();

        self::assertEquals($cacheCount, $this->getCacheSize($cache));
    }

    /**
     * @group DDC-909
     */
    public function testResultCacheWithObjectParameter()
    {
        $user1 = new CmsUser;
        $user1->name = 'Roman';
        $user1->username = 'romanb';
        $user1->status = 'dev';

        $user2 = new CmsUser;
        $user2->name = 'Benjamin';
        $user2->username = 'beberlei';
        $user2->status = 'dev';

        $article = new CmsArticle();
        $article->text = "foo";
        $article->topic = "baz";
        $article->user = $user1;

        $this->_em->persist($article);
        $this->_em->persist($user1);
        $this->_em->persist($user2);
        $this->_em->flush();

        $query = $this->_em->createQuery('select a from Doctrine\Tests\Models\CMS\CmsArticle a WHERE a.user = ?1');
        $query->setParameter(1, $user1);

        $cache = new ArrayCache();

        $query->setResultCacheDriver($cache)->useResultCache(true);

        $articles = $query->getResult();

        self::assertEquals(1, count($articles));
        self::assertEquals('baz', $articles[0]->topic);

        $this->_em->clear();

        $query2 = $this->_em->createQuery('select a from Doctrine\Tests\Models\CMS\CmsArticle a WHERE a.user = ?1');
        $query2->setParameter(1, $user1);

        $query2->setResultCacheDriver($cache)->useResultCache(true);

        $articles = $query2->getResult();

        self::assertEquals(1, count($articles));
        self::assertEquals('baz', $articles[0]->topic);

        $query3 = $this->_em->createQuery('select a from Doctrine\Tests\Models\CMS\CmsArticle a WHERE a.user = ?1');
        $query3->setParameter(1, $user2);

        $query3->setResultCacheDriver($cache)->useResultCache(true);

        $articles = $query3->getResult();

        self::assertEquals(0, count($articles));
    }
}
