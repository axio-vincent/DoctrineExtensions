<?php

namespace Gedmo\Tests\Sluggable;

use Doctrine\Common\EventManager;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use Gedmo\Tests\Sluggable\Fixture\Issue449\Article;
use Gedmo\Tests\Tool\BaseTestCaseORM;

/**
 * These are tests for Sluggable behavior
 *
 * @author Craig Marvelley <craig.marvelley@gmail.com>
 *
 * @see http://marvelley.com
 *
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
final class Issue449Test extends BaseTestCaseORM
{
    public const TARGET = Article::class;
    public const SOFT_DELETEABLE_FILTER_NAME = 'soft-deleteable';

    private $softDeleteableListener;

    protected function setUp(): void
    {
        parent::setUp();

        $evm = new EventManager();
        $sluggableListener = new SluggableListener();
        $sluggableListener->addManagedFilter(self::SOFT_DELETEABLE_FILTER_NAME, true);
        $evm->addEventSubscriber($sluggableListener);

        $this->softDeleteableListener = new SoftDeleteableListener();
        $evm->addEventSubscriber($this->softDeleteableListener);

        $config = $this->getMockAnnotatedConfig();
        $config->addFilter(self::SOFT_DELETEABLE_FILTER_NAME, SoftDeleteableFilter::class);

        $this->em = $this->getMockSqliteEntityManager($evm, $config);

        $this->em->getFilters()->enable(self::SOFT_DELETEABLE_FILTER_NAME);
    }

    protected function getUsedEntityFixtures()
    {
        return [
            self::TARGET,
        ];
    }

    /**
     * @test
     */
    public function shouldBuildUniqueSlugAfterSoftDeleteFilterIsDisabled()
    {
        $article = new Article();
        $article->setTitle('the soft title');
        $article->setCode('my soft code');

        $this->em->persist($article);
        $this->em->flush();

        $slug = $article->getSlug();

        $this->em->remove($article);
        $this->em->flush();

        $article = new Article();
        $article->setTitle('the soft title');
        $article->setCode('my soft code');

        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        static::assertNotEquals($slug, $article->getSlug());
    }
}
