<?php

namespace Gedmo\Tests\Tree;

use Doctrine\Common\EventManager;
use Gedmo\Tests\Tool\BaseTestCaseORM;
use Gedmo\Tests\Tree\Fixture\Category;
use Gedmo\Tests\Tree\Fixture\RootCategory;
use Gedmo\Tree\TreeListener;

/**
 * These are tests for Tree behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @see http://www.gediminasm.org
 *
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
final class NestedTreePositionTest extends BaseTestCaseORM
{
    public const CATEGORY = Category::class;
    public const ROOT_CATEGORY = RootCategory::class;

    protected function setUp(): void
    {
        parent::setUp();

        $evm = new EventManager();
        $evm->addEventSubscriber(new TreeListener());

        $this->getMockSqliteEntityManager($evm);
    }

    /**
     * @test
     */
    public function shouldFailToPersistRootSibling()
    {
        $food = new Category();
        $food->setTitle('Food');

        $sport = new Category();
        $sport->setTitle('Sport');

        $repo = $this->em->getRepository(self::CATEGORY);

        $repo->persistAsFirstChild($food);
        $repo->persistAsNextSiblingOf($sport, $food);

        $this->em->flush();
        static::assertSame(0, $sport->getLevel());
        static::assertSame(3, $sport->getLeft());
        static::assertSame(4, $sport->getRight());
    }

    /**
     * @test
     */
    public function shouldFailToPersistRootAsSiblingForRootBasedTree()
    {
        $this->expectException('UnexpectedValueException');
        $food = new RootCategory();
        $food->setTitle('Food');

        $sport = new RootCategory();
        $sport->setTitle('Sport');

        $repo = $this->em->getRepository(self::ROOT_CATEGORY);

        $repo->persistAsFirstChild($food);
        $repo->persistAsNextSiblingOf($sport, $food);

        $this->em->flush();
    }

    public function testTreeChildPositionMove2()
    {
        $this->populate();
        $repo = $this->em->getRepository(self::ROOT_CATEGORY);

        $oranges = $repo->findOneBy(['title' => 'Oranges']);
        $meat = $repo->findOneBy(['title' => 'Meat']);

        static::assertEquals(2, $oranges->getLevel());
        static::assertEquals(7, $oranges->getLeft());
        static::assertEquals(8, $oranges->getRight());

        $repo->persistAsNextSiblingOf($meat, $oranges);
        $this->em->flush();

        $oranges = $repo->findOneBy(['title' => 'Oranges']);
        $meat = $repo->findOneBy(['title' => 'Meat']);

        static::assertEquals(7, $oranges->getLeft());
        static::assertEquals(8, $oranges->getRight());

        //Normal test that pass
        static::assertEquals(9, $meat->getLeft());
        static::assertEquals(10, $meat->getRight());

        // Raw query to show the issue #108 with wrong left value by Doctrine
        $dql = 'SELECT c FROM '.self::ROOT_CATEGORY.' c';
        $dql .= ' WHERE c.id = 5'; //5 == meat
        $meat_array = $this->em->createQuery($dql)->getScalarResult();

        static::assertEquals(9, $meat_array[0]['c_lft']);
        static::assertEquals(10, $meat_array[0]['c_rgt']);
        static::assertEquals(2, $meat_array[0]['c_level']);
    }

    public function testTreeChildPositionMove3()
    {
        $this->populate();
        $repo = $this->em->getRepository(self::ROOT_CATEGORY);

        $oranges = $repo->findOneBy(['title' => 'Oranges']);
        $milk = $repo->findOneBy(['title' => 'Milk']);

        static::assertEquals(2, $oranges->getLevel());
        static::assertEquals(7, $oranges->getLeft());
        static::assertEquals(8, $oranges->getRight());

        $repo->persistAsNextSiblingOf($milk, $oranges);
        $this->em->flush();

        static::assertEquals(7, $oranges->getLeft());
        static::assertEquals(8, $oranges->getRight());

        //Normal test that pass
        static::assertEquals(9, $milk->getLeft());
        static::assertEquals(10, $milk->getRight());

        // Raw query to show the issue #108 with wrong left value by Doctrine
        $dql = 'SELECT c FROM '.self::ROOT_CATEGORY.' c';
        $dql .= ' WHERE c.id = 4 '; //4 == Milk
        $milk_array = $this->em->createQuery($dql)->getScalarResult();
        static::assertEquals(9, $milk_array[0]['c_lft']);
        static::assertEquals(10, $milk_array[0]['c_rgt']);
        static::assertEquals(2, $milk_array[0]['c_level']);
    }

    public function testPositionedUpdates()
    {
        $this->populate();
        $repo = $this->em->getRepository(self::ROOT_CATEGORY);

        $citrons = $repo->findOneBy(['title' => 'Citrons']);
        $vegitables = $repo->findOneBy(['title' => 'Vegitables']);

        $repo->persistAsNextSiblingOf($vegitables, $citrons);
        $this->em->flush();

        static::assertEquals(5, $vegitables->getLeft());
        static::assertEquals(6, $vegitables->getRight());
        static::assertEquals(2, $vegitables->getParent()->getId());

        $fruits = $repo->findOneBy(['title' => 'Fruits']);
        static::assertEquals(2, $fruits->getLeft());
        static::assertEquals(9, $fruits->getRight());

        $milk = $repo->findOneBy(['title' => 'Milk']);
        $repo->persistAsFirstChildOf($milk, $fruits);
        $this->em->flush();

        static::assertEquals(3, $milk->getLeft());
        static::assertEquals(4, $milk->getRight());

        static::assertEquals(2, $fruits->getLeft());
        static::assertEquals(11, $fruits->getRight());
    }

    public function testTreeChildPositionMove()
    {
        $this->populate();
        $repo = $this->em->getRepository(self::ROOT_CATEGORY);

        $oranges = $repo->findOneBy(['title' => 'Oranges']);
        $fruits = $repo->findOneBy(['title' => 'Fruits']);

        static::assertEquals(2, $oranges->getLevel());

        $repo->persistAsNextSiblingOf($oranges, $fruits);
        $this->em->flush();

        static::assertEquals(1, $oranges->getLevel());
        static::assertCount(1, $repo->children($fruits, true));

        $vegies = $repo->findOneBy(['title' => 'Vegitables']);
        static::assertEquals(2, $vegies->getLeft());
        $repo->persistAsNextSiblingOf($vegies, $fruits);
        $this->em->flush();

        static::assertEquals(6, $vegies->getLeft());
        $this->em->flush();
        static::assertEquals(6, $vegies->getLeft());
    }

    public function testOnRootCategory()
    {
        // need to check if this does not produce errors
        $repo = $this->em->getRepository(self::ROOT_CATEGORY);

        $fruits = new RootCategory();
        $fruits->setTitle('Fruits');

        $vegitables = new RootCategory();
        $vegitables->setTitle('Vegitables');

        $milk = new RootCategory();
        $milk->setTitle('Milk');

        $meat = new RootCategory();
        $meat->setTitle('Meat');

        $repo
            ->persistAsFirstChild($fruits)
            ->persistAsFirstChild($vegitables)
            ->persistAsLastChild($milk)
            ->persistAsLastChild($meat);

        $cookies = new RootCategory();
        $cookies->setTitle('Cookies');

        $drinks = new RootCategory();
        $drinks->setTitle('Drinks');

        $repo
            ->persistAsNextSibling($cookies)
            ->persistAsPrevSibling($drinks);

        $this->em->flush();
        $dql = 'SELECT COUNT(c) FROM '.self::ROOT_CATEGORY.' c';
        $dql .= ' WHERE c.lft = 1 AND c.rgt = 2 AND c.parent IS NULL AND c.level = 0';
        $count = $this->em->createQuery($dql)->getSingleScalarResult();
        static::assertEquals(6, $count);

        $repo = $this->em->getRepository(self::CATEGORY);

        $fruits = new Category();
        $fruits->setTitle('Fruits');

        $vegitables = new Category();
        $vegitables->setTitle('Vegitables');

        $milk = new Category();
        $milk->setTitle('Milk');

        $meat = new Category();
        $meat->setTitle('Meat');

        $repo
            ->persistAsFirstChild($fruits)
            ->persistAsFirstChild($vegitables)
            ->persistAsLastChild($milk)
            ->persistAsLastChild($meat);

        $cookies = new Category();
        $cookies->setTitle('Cookies');

        $drinks = new Category();
        $drinks->setTitle('Drinks');

        $repo
            ->persistAsNextSibling($cookies)
            ->persistAsPrevSibling($drinks);

        $this->em->flush();
        $dql = 'SELECT COUNT(c) FROM '.self::CATEGORY.' c';
        $dql .= ' WHERE c.parentId IS NULL AND c.level = 0';
        $dql .= ' AND c.lft BETWEEN 1 AND 11';
        $count = $this->em->createQuery($dql)->getSingleScalarResult();
        static::assertEquals(6, $count);
    }

    public function testRootTreePositionedInserts()
    {
        $repo = $this->em->getRepository(self::ROOT_CATEGORY);

        // test child positioned inserts
        $food = new RootCategory();
        $food->setTitle('Food');

        $fruits = new RootCategory();
        $fruits->setTitle('Fruits');

        $vegitables = new RootCategory();
        $vegitables->setTitle('Vegitables');

        $milk = new RootCategory();
        $milk->setTitle('Milk');

        $meat = new RootCategory();
        $meat->setTitle('Meat');

        $repo
            ->persistAsFirstChild($food)
            ->persistAsFirstChildOf($fruits, $food)
            ->persistAsFirstChildOf($vegitables, $food)
            ->persistAsLastChildOf($milk, $food)
            ->persistAsLastChildOf($meat, $food);

        $this->em->flush();

        static::assertEquals(4, $fruits->getLeft());
        static::assertEquals(5, $fruits->getRight());

        static::assertEquals(2, $vegitables->getLeft());
        static::assertEquals(3, $vegitables->getRight());

        static::assertEquals(6, $milk->getLeft());
        static::assertEquals(7, $milk->getRight());

        static::assertEquals(8, $meat->getLeft());
        static::assertEquals(9, $meat->getRight());

        // test sibling positioned inserts
        $cookies = new RootCategory();
        $cookies->setTitle('Cookies');

        $drinks = new RootCategory();
        $drinks->setTitle('Drinks');

        $repo
            ->persistAsNextSiblingOf($cookies, $milk)
            ->persistAsPrevSiblingOf($drinks, $milk);

        $this->em->flush();

        static::assertEquals(6, $drinks->getLeft());
        static::assertEquals(7, $drinks->getRight());

        static::assertEquals(10, $cookies->getLeft());
        static::assertEquals(11, $cookies->getRight());

        static::assertTrue($repo->verify());
    }

    public function testRootlessTreeTopLevelInserts()
    {
        $repo = $this->em->getRepository(self::CATEGORY);

        // test top level positioned inserts
        $fruits = new Category();
        $fruits->setTitle('Fruits');

        $vegetables = new Category();
        $vegetables->setTitle('Vegetables');

        $milk = new Category();
        $milk->setTitle('Milk');

        $meat = new Category();
        $meat->setTitle('Meat');

        $repo
            ->persistAsFirstChild($fruits)
            ->persistAsFirstChild($vegetables)
            ->persistAsLastChild($milk)
            ->persistAsLastChild($meat);

        $this->em->flush();

        static::assertEquals(3, $fruits->getLeft());
        static::assertEquals(4, $fruits->getRight());

        static::assertEquals(1, $vegetables->getLeft());
        static::assertEquals(2, $vegetables->getRight());

        static::assertEquals(5, $milk->getLeft());
        static::assertEquals(6, $milk->getRight());

        static::assertEquals(7, $meat->getLeft());
        static::assertEquals(8, $meat->getRight());

        // test sibling positioned inserts
        $cookies = new Category();
        $cookies->setTitle('Cookies');

        $drinks = new Category();
        $drinks->setTitle('Drinks');

        $repo
            ->persistAsNextSiblingOf($cookies, $milk)
            ->persistAsPrevSiblingOf($drinks, $milk);

        $this->em->flush();

        static::assertEquals(5, $drinks->getLeft());
        static::assertEquals(6, $drinks->getRight());

        static::assertEquals(9, $cookies->getLeft());
        static::assertEquals(10, $cookies->getRight());

        static::assertTrue($repo->verify());
    }

    public function testSimpleTreePositionedInserts()
    {
        $repo = $this->em->getRepository(self::CATEGORY);

        // test child positioned inserts
        $food = new Category();
        $food->setTitle('Food');
        $repo->persistAsFirstChild($food);

        $fruits = new Category();
        $fruits->setTitle('Fruits');
        $fruits->setParent($food);
        $repo->persistAsFirstChild($fruits);

        $vegitables = new Category();
        $vegitables->setTitle('Vegitables');
        $vegitables->setParent($food);
        $repo->persistAsFirstChild($vegitables);

        $milk = new Category();
        $milk->setTitle('Milk');
        $milk->setParent($food);
        $repo->persistAsLastChild($milk);

        $meat = new Category();
        $meat->setTitle('Meat');
        $meat->setParent($food);
        $repo->persistAsLastChild($meat);

        $this->em->flush();

        static::assertEquals(4, $fruits->getLeft());
        static::assertEquals(5, $fruits->getRight());

        static::assertEquals(2, $vegitables->getLeft());
        static::assertEquals(3, $vegitables->getRight());

        static::assertEquals(6, $milk->getLeft());
        static::assertEquals(7, $milk->getRight());

        static::assertEquals(8, $meat->getLeft());
        static::assertEquals(9, $meat->getRight());

        // test sibling positioned inserts
        $cookies = new Category();
        $cookies->setTitle('Cookies');
        $cookies->setParent($milk);
        $repo->persistAsNextSibling($cookies);

        $drinks = new Category();
        $drinks->setTitle('Drinks');
        $drinks->setParent($milk);
        $repo->persistAsPrevSibling($drinks);

        $this->em->flush();

        static::assertEquals(6, $drinks->getLeft());
        static::assertEquals(7, $drinks->getRight());

        static::assertEquals(10, $cookies->getLeft());
        static::assertEquals(11, $cookies->getRight());

        static::assertTrue($repo->verify());
    }

    private function populate()
    {
        $repo = $this->em->getRepository(self::ROOT_CATEGORY);

        $food = new RootCategory();
        $food->setTitle('Food');

        $fruits = new RootCategory();
        $fruits->setTitle('Fruits');

        $vegitables = new RootCategory();
        $vegitables->setTitle('Vegitables');

        $milk = new RootCategory();
        $milk->setTitle('Milk');

        $meat = new RootCategory();
        $meat->setTitle('Meat');

        $oranges = new RootCategory();
        $oranges->setTitle('Oranges');

        $citrons = new RootCategory();
        $citrons->setTitle('Citrons');

        $repo
            ->persistAsFirstChild($food)
            ->persistAsFirstChildOf($fruits, $food)
            ->persistAsFirstChildOf($vegitables, $food)
            ->persistAsLastChildOf($milk, $food)
            ->persistAsLastChildOf($meat, $food)
            ->persistAsFirstChildOf($oranges, $fruits)
            ->persistAsFirstChildOf($citrons, $fruits);

        $this->em->flush();
    }

    protected function getUsedEntityFixtures()
    {
        return [
            self::CATEGORY,
            self::ROOT_CATEGORY,
        ];
    }
}
