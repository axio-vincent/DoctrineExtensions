<?php

namespace Gedmo\Tests\Tree;

use Doctrine\Common\EventManager;
use Gedmo\Tests\Tool\BaseTestCaseORM;
use Gedmo\Tests\Tree\Fixture\ForeignRootCategory;
use Gedmo\Tests\Tree\Fixture\RootCategory;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
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
final class NestedTreeRootTest extends BaseTestCaseORM
{
    public const CATEGORY = RootCategory::class;

    protected function setUp(): void
    {
        parent::setUp();

        $evm = new EventManager();
        $evm->addEventSubscriber(new TreeListener());

        $this->getMockSqliteEntityManager($evm);
        $this->populate();
    }

    /**
     * @test
     */
    public function shouldRemoveAndSynchronize()
    {
        $repo = $this->em->getRepository(self::CATEGORY);
        $vegies = $repo->findOneBy(['title' => 'Vegitables']);

        $this->em->remove($vegies);
        $this->em->flush();

        $food = $repo->findOneBy(['title' => 'Food']);

        static::assertEquals(1, $food->getLeft());
        static::assertEquals(4, $food->getRight());

        $vegies = new RootCategory();
        $vegies->setTitle('Vegies');
        $repo->persistAsFirstChildOf($vegies, $food);

        $this->em->flush();
        static::assertEquals(1, $food->getLeft());
        static::assertEquals(6, $food->getRight());

        static::assertEquals(2, $vegies->getLeft());
        static::assertEquals(3, $vegies->getRight());
    }

    /*public function testHeavyLoad()
    {
        $start = microtime(true);
        $dumpTime = function($start, $msg) {
            $took = microtime(true) - $start;
            $minutes = intval($took / 60); $seconds = $took % 60;
            echo sprintf("%s --> %02d:%02d", $msg, $minutes, $seconds) . PHP_EOL;
        };
        $repo = $this->em->getRepository(self::CATEGORY);
        $parent = null;
        $num = 800;
        for($i = 0; $i < 500; $i++) {
            $cat = new RootCategory;
            $cat->setParent($parent);
            $cat->setTitle('cat'.$i);
            $this->em->persist($cat);
            // siblings
            $rnd = rand(0, 3);
            for ($j = 0; $j < $rnd; $j++) {
                $siblingCat = new RootCategory;
                $siblingCat->setTitle('cat'.$i.$j);
                $siblingCat->setParent($cat);
                $this->em->persist($siblingCat);
            }
            $num += $rnd;
            $parent = $cat;
        }
        $this->em->flush();
        $dumpTime($start, $num.' - inserts took:');
        $start = microtime(true);
        // test moving
        $target = $repo->findOneBy(['title' => 'cat300']);
        $dest = $repo->findOneBy(['title' => 'cat2000']);
        $target->setParent($dest);

        $target2 = $repo->findOneBy(['title' => 'cat450']);
        $dest2 = $repo->findOneBy(['title' => 'cat2500']);
        $target2->setParent($dest2);

        $this->em->flush();
        $dumpTime($start, 'moving took:');
    }*/

    public function testTheTree()
    {
        $repo = $this->em->getRepository(self::CATEGORY);
        $node = $repo->findOneBy(['title' => 'Food']);

        static::assertEquals(1, $node->getRoot());
        static::assertEquals(1, $node->getLeft());
        static::assertEquals(0, $node->getLevel());
        static::assertEquals(10, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Sports']);

        static::assertEquals(2, $node->getRoot());
        static::assertEquals(1, $node->getLeft());
        static::assertEquals(0, $node->getLevel());
        static::assertEquals(2, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Fruits']);

        static::assertEquals(1, $node->getRoot());
        static::assertEquals(2, $node->getLeft());
        static::assertEquals(1, $node->getLevel());
        static::assertEquals(3, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Vegitables']);

        static::assertEquals(1, $node->getRoot());
        static::assertEquals(4, $node->getLeft());
        static::assertEquals(1, $node->getLevel());
        static::assertEquals(9, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Carrots']);

        static::assertEquals(1, $node->getRoot());
        static::assertEquals(5, $node->getLeft());
        static::assertEquals(2, $node->getLevel());
        static::assertEquals(6, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Potatoes']);

        static::assertEquals(1, $node->getRoot());
        static::assertEquals(7, $node->getLeft());
        static::assertEquals(2, $node->getLevel());
        static::assertEquals(8, $node->getRight());
    }

    public function testSetParentToNull()
    {
        $repo = $this->em->getRepository(self::CATEGORY);
        $node = $repo->findOneBy(['title' => 'Vegitables']);
        $node->setParent(null);

        $this->em->persist($node);
        $this->em->flush();
        $this->em->clear();

        $node = $repo->findOneBy(['title' => 'Vegitables']);
        static::assertEquals(4, $node->getRoot());
        static::assertEquals(1, $node->getLeft());
        static::assertEquals(6, $node->getRight());
        static::assertEquals(0, $node->getLevel());
    }

    public function testTreeUpdateShiftToNextBranch()
    {
        $repo = $this->em->getRepository(self::CATEGORY);
        $sport = $repo->findOneBy(['title' => 'Sports']);
        $food = $repo->findOneBy(['title' => 'Food']);

        $sport->setParent($food);
        $this->em->persist($sport);
        $this->em->flush();
        $this->em->clear();

        $node = $repo->findOneBy(['title' => 'Food']);

        static::assertEquals(1, $node->getLeft());
        static::assertEquals(12, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Sports']);

        static::assertEquals(1, $node->getRoot());
        static::assertEquals(2, $node->getLeft());
        static::assertEquals(1, $node->getLevel());
        static::assertEquals(3, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Vegitables']);

        static::assertEquals(6, $node->getLeft());
        static::assertEquals(11, $node->getRight());
    }

    public function testTreeUpdateShiftToRoot()
    {
        $repo = $this->em->getRepository(self::CATEGORY);
        $vegies = $repo->findOneBy(['title' => 'Vegitables']);

        $vegies->setParent(null);
        $this->em->persist($vegies);
        $this->em->flush();
        $this->em->clear();

        $node = $repo->findOneBy(['title' => 'Food']);

        static::assertEquals(1, $node->getLeft());
        static::assertEquals(4, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Vegitables']);

        static::assertEquals(4, $node->getRoot());
        static::assertEquals(1, $node->getLeft());
        static::assertEquals(0, $node->getLevel());
        static::assertEquals(6, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Potatoes']);

        static::assertEquals(4, $node->getRoot());
        static::assertEquals(4, $node->getLeft());
        static::assertEquals(1, $node->getLevel());
        static::assertEquals(5, $node->getRight());
    }

    public function testTreeUpdateShiftToOtherParent()
    {
        $repo = $this->em->getRepository(self::CATEGORY);
        $carrots = $repo->findOneBy(['title' => 'Carrots']);
        $food = $repo->findOneBy(['title' => 'Food']);

        $carrots->setParent($food);
        $this->em->persist($carrots);
        $this->em->flush();
        $this->em->clear();

        $node = $repo->findOneBy(['title' => 'Food']);

        static::assertEquals(1, $node->getLeft());
        static::assertEquals(10, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Carrots']);

        static::assertEquals(1, $node->getRoot());
        static::assertEquals(2, $node->getLeft());
        static::assertEquals(1, $node->getLevel());
        static::assertEquals(3, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Potatoes']);

        static::assertEquals(1, $node->getRoot());
        static::assertEquals(7, $node->getLeft());
        static::assertEquals(2, $node->getLevel());
        static::assertEquals(8, $node->getRight());
    }

    public function testTreeUpdateShiftToChildParent()
    {
        $this->expectException('UnexpectedValueException');
        $repo = $this->em->getRepository(self::CATEGORY);
        $vegies = $repo->findOneBy(['title' => 'Vegitables']);
        $food = $repo->findOneBy(['title' => 'Food']);

        $food->setParent($vegies);
        $this->em->persist($food);
        $this->em->flush();
        $this->em->clear();
    }

    public function testTwoUpdateOperations()
    {
        $repo = $this->em->getRepository(self::CATEGORY);

        $sport = $repo->findOneBy(['title' => 'Sports']);
        $food = $repo->findOneBy(['title' => 'Food']);
        $sport->setParent($food);

        $vegies = $repo->findOneBy(['title' => 'Vegitables']);
        $vegies->setParent(null);

        $this->em->persist($vegies);
        $this->em->persist($sport);
        $this->em->flush();
        $this->em->clear();

        $node = $repo->findOneBy(['title' => 'Carrots']);

        static::assertEquals(4, $node->getRoot());
        static::assertEquals(2, $node->getLeft());
        static::assertEquals(1, $node->getLevel());
        static::assertEquals(3, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Vegitables']);

        static::assertEquals(4, $node->getRoot());
        static::assertEquals(1, $node->getLeft());
        static::assertEquals(0, $node->getLevel());
        static::assertEquals(6, $node->getRight());

        $node = $repo->findOneBy(['title' => 'Sports']);

        static::assertEquals(1, $node->getRoot());
        static::assertEquals(2, $node->getLeft());
        static::assertEquals(1, $node->getLevel());
        static::assertEquals(3, $node->getRight());
    }

    public function testRemoval()
    {
        $repo = $this->em->getRepository(self::CATEGORY);
        $vegies = $repo->findOneBy(['title' => 'Vegitables']);

        $this->em->remove($vegies);
        $this->em->flush();
        $this->em->clear();

        $node = $repo->findOneBy(['title' => 'Food']);

        static::assertEquals(1, $node->getLeft());
        static::assertEquals(4, $node->getRight());
    }

    /**
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testTreeWithRootPointingAtAnotherTable()
    {
        // depopulate, i don't want the other stuff in db
        /** @var NestedTreeRepository $repo */
        $repo = $this->em->getRepository(ForeignRootCategory::class);
        $all = $repo->findAll();
        foreach ($all as $one) {
            $this->em->remove($one);
        }
        $this->em->flush();

        $fiction = new ForeignRootCategory();
        $fiction->setTitle('Fiction Books');
        $fiction->setRoot(1);  // Lets pretend this points to another table, and root id 1 is "Books"

        $fact = new ForeignRootCategory();
        $fact->setTitle('Fact Books');
        $fact->setRoot(1);

        $action = new ForeignRootCategory();
        $action->setTitle('Action');
        $action->setRoot(2); // Lets pretend this points to another table, and root id 2 is "Movies"

        $comedy = new ForeignRootCategory();
        $comedy->setTitle('Comedy');
        $comedy->setRoot(2);

        $horror = new ForeignRootCategory();
        $horror->setTitle('Horror');
        $horror->setRoot(2);

        // Child categories now
        $lotr = new ForeignRootCategory();
        $lotr->setTitle('Lord of the Rings');
        $lotr->setParent($fiction);
        $lotr->setRoot(1);

        $warlock = new ForeignRootCategory();
        $warlock->setTitle('The Warlock of Firetop Mountain');
        $warlock->setParent($fiction);
        $warlock->setRoot(1);

        $php = new ForeignRootCategory();
        $php->setTitle('PHP open source development');
        $php->setParent($fact);
        $php->setRoot(1);

        $dracula = new ForeignRootCategory();
        $dracula->setTitle('Hammer Horror Dracula');
        $dracula->setParent($horror);
        $dracula->setRoot(2);

        $frankenstein = new ForeignRootCategory();
        $frankenstein->setTitle('Hammer Horror Frankenstein');
        $frankenstein->setParent($horror);
        $frankenstein->setRoot(2);

        $this->em->persist($fact);
        $this->em->persist($fiction);
        $this->em->persist($comedy);
        $this->em->persist($horror);
        $this->em->persist($action);
        $this->em->persist($lotr);
        $this->em->persist($warlock);
        $this->em->persist($php);
        $this->em->persist($dracula);
        $this->em->persist($frankenstein);
        $this->em->flush();

        static::assertEquals(1, $fact->getLeft());
        static::assertEquals(4, $fact->getRight());
        static::assertEquals(0, $fact->getLevel());
        static::assertEquals(1, $fact->getRoot());
        static::assertNull($fact->getParent());

        static::assertEquals(5, $fiction->getLeft());
        static::assertEquals(10, $fiction->getRight());
        static::assertEquals(0, $fiction->getLevel());
        static::assertEquals(1, $fiction->getRoot());
        static::assertNull($fiction->getParent());

        static::assertEquals(6, $lotr->getLeft());
        static::assertEquals(7, $lotr->getRight());
        static::assertEquals(1, $lotr->getLevel());
        static::assertEquals(1, $lotr->getRoot());
        static::assertEquals($fiction, $lotr->getParent());

        static::assertEquals(8, $warlock->getLeft());
        static::assertEquals(9, $warlock->getRight());
        static::assertEquals(1, $warlock->getLevel());
        static::assertEquals(1, $warlock->getRoot());
        static::assertEquals($fiction, $warlock->getParent());

        static::assertEquals(2, $php->getLeft());
        static::assertEquals(3, $php->getRight());
        static::assertEquals(1, $php->getLevel());
        static::assertEquals(1, $php->getRoot());
        static::assertEquals($fact, $php->getParent());

        static::assertEquals(1, $comedy->getLeft());
        static::assertEquals(2, $comedy->getRight());
        static::assertEquals(0, $comedy->getLevel());
        static::assertEquals(2, $comedy->getRoot());
        static::assertNull($comedy->getParent());

        static::assertEquals(3, $horror->getLeft());
        static::assertEquals(8, $horror->getRight());
        static::assertEquals(0, $horror->getLevel());
        static::assertEquals(2, $horror->getRoot());
        static::assertNull($horror->getParent());

        static::assertEquals(9, $action->getLeft());
        static::assertEquals(10, $action->getRight());
        static::assertEquals(0, $action->getLevel());
        static::assertEquals(2, $action->getRoot());
        static::assertNull($action->getParent());

        static::assertEquals(4, $dracula->getLeft());
        static::assertEquals(5, $dracula->getRight());
        static::assertEquals(1, $dracula->getLevel());
        static::assertEquals(2, $dracula->getRoot());
        static::assertEquals($horror, $dracula->getParent());

        static::assertEquals(6, $frankenstein->getLeft());
        static::assertEquals(7, $frankenstein->getRight());
        static::assertEquals(1, $frankenstein->getLevel());
        static::assertEquals(2, $frankenstein->getRoot());
        static::assertEquals($horror, $frankenstein->getParent());

        // Now move the action movie category up
        $repo->moveUp($action);

        static::assertEquals(1, $comedy->getLeft());
        static::assertEquals(2, $comedy->getRight());
        static::assertEquals(0, $comedy->getLevel());
        static::assertEquals(2, $comedy->getRoot());
        static::assertNull($comedy->getParent());

        static::assertEquals(3, $action->getLeft());
        static::assertEquals(4, $action->getRight());
        static::assertEquals(0, $action->getLevel());
        static::assertEquals(2, $action->getRoot());
        static::assertNull($action->getParent());

        static::assertEquals(5, $horror->getLeft());
        static::assertEquals(10, $horror->getRight());
        static::assertEquals(0, $horror->getLevel());
        static::assertEquals(2, $horror->getRoot());
        static::assertNull($horror->getParent());

        static::assertEquals(6, $dracula->getLeft());
        static::assertEquals(7, $dracula->getRight());
        static::assertEquals(1, $dracula->getLevel());
        static::assertEquals(2, $dracula->getRoot());
        static::assertEquals($horror, $dracula->getParent());

        static::assertEquals(8, $frankenstein->getLeft());
        static::assertEquals(9, $frankenstein->getRight());
        static::assertEquals(1, $frankenstein->getLevel());
        static::assertEquals(2, $frankenstein->getRoot());
        static::assertEquals($horror, $frankenstein->getParent());

        $this->em->clear();
    }

    protected function getUsedEntityFixtures()
    {
        return [
            self::CATEGORY,
            ForeignRootCategory::class,
        ];
    }

    /**
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function populate()
    {
        $root = new RootCategory();
        $root->setTitle('Food');

        $root2 = new RootCategory();
        $root2->setTitle('Sports');

        $child = new RootCategory();
        $child->setTitle('Fruits');
        $child->setParent($root);

        $child2 = new RootCategory();
        $child2->setTitle('Vegitables');
        $child2->setParent($root);

        $childsChild = new RootCategory();
        $childsChild->setTitle('Carrots');
        $childsChild->setParent($child2);

        $potatoes = new RootCategory();
        $potatoes->setTitle('Potatoes');
        $potatoes->setParent($child2);

        $this->em->persist($root);
        $this->em->persist($root2);
        $this->em->persist($child);
        $this->em->persist($child2);
        $this->em->persist($childsChild);
        $this->em->persist($potatoes);
        $this->em->flush();
        $this->em->clear();
    }
}
