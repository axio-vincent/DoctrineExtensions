<?php

namespace Gedmo\Tests\Sluggable;

use Doctrine\Common\EventManager;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\Tests\Sluggable\Fixture\Handler\Article;
use Gedmo\Tests\Sluggable\Fixture\Handler\ArticleRelativeSlug;
use Gedmo\Tests\Tool\BaseTestCaseORM;

/**
 * These are tests for Sluggable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @see http://www.gediminasm.org
 *
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
final class RelativeSlugHandlerTest extends BaseTestCaseORM
{
    public const SLUG = ArticleRelativeSlug::class;
    public const ARTICLE = Article::class;

    protected function setUp(): void
    {
        parent::setUp();

        $evm = new EventManager();
        $evm->addEventSubscriber(new SluggableListener());

        $this->getMockSqliteEntityManager($evm);
    }

    public function testSlugGeneration()
    {
        $this->populate();
        $repo = $this->em->getRepository(self::SLUG);

        $thomas = $repo->findOneBy(['title' => 'Thomas']);
        static::assertEquals('sport-test/thomas', $thomas->getSlug());

        $jen = $repo->findOneBy(['title' => 'Jen']);
        static::assertEquals('sport-test/jen', $jen->getSlug());

        $john = $repo->findOneBy(['title' => 'John']);
        static::assertEquals('cars-code/john', $john->getSlug());

        $single = $repo->findOneBy(['title' => 'Single']);
        static::assertEquals('single', $single->getSlug());
    }

    public function testUpdateOperations()
    {
        $this->populate();
        $repo = $this->em->getRepository(self::SLUG);

        $thomas = $repo->findOneBy(['title' => 'Thomas']);
        $thomas->setTitle('Ninja');
        $this->em->persist($thomas);
        $this->em->flush();

        static::assertEquals('sport-test/ninja', $thomas->getSlug());

        $sport = $this->em->getRepository(self::ARTICLE)->findOneBy(['title' => 'Sport']);
        $sport->setTitle('Martial Arts');

        $this->em->persist($sport);
        $this->em->flush();

        static::assertEquals('martial-arts-test/ninja', $thomas->getSlug());

        $jen = $repo->findOneBy(['title' => 'Jen']);
        static::assertEquals('martial-arts-test/jen', $jen->getSlug());

        $cars = $this->em->getRepository(self::ARTICLE)->findOneBy(['title' => 'Cars']);
        $jen->setArticle($cars);

        $this->em->persist($jen);
        $this->em->flush();

        static::assertEquals('cars-code/jen', $jen->getSlug());
    }

    protected function getUsedEntityFixtures()
    {
        return [
            self::SLUG,
            self::ARTICLE,
        ];
    }

    private function populate()
    {
        $sport = new Article();
        $sport->setTitle('Sport');
        $sport->setCode('test');
        $this->em->persist($sport);

        $cars = new Article();
        $cars->setTitle('Cars');
        $cars->setCode('code');
        $this->em->persist($cars);

        $thomas = new ArticleRelativeSlug();
        $thomas->setTitle('Thomas');
        $thomas->setArticle($sport);
        $this->em->persist($thomas);

        $jen = new ArticleRelativeSlug();
        $jen->setTitle('Jen');
        $jen->setArticle($sport);
        $this->em->persist($jen);

        $john = new ArticleRelativeSlug();
        $john->setTitle('John');
        $john->setArticle($cars);
        $this->em->persist($john);

        $single = new ArticleRelativeSlug();
        $single->setTitle('Single');
        $this->em->persist($single);

        $this->em->flush();
    }
}
