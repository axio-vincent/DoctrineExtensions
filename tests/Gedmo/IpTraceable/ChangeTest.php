<?php

namespace Gedmo\Tests\IpTraceable;

use Doctrine\Common\EventManager;
use Gedmo\IpTraceable\IpTraceableListener;
use Gedmo\Tests\IpTraceable\Fixture\TitledArticle;
use Gedmo\Tests\Tool\BaseTestCaseORM;

/**
 * These are tests for IpTraceable behavior
 *
 * @author Pierre-Charles Bertineau <pc.bertineau@alterphp.com>
 *
 * @see http://www.gediminasm.org
 *
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
final class ChangeTest extends BaseTestCaseORM
{
    public const TEST_IP = '34.234.1.10';
    public const FIXTURE = TitledArticle::class;

    protected $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = new IpTraceableListener();
        $this->listener->setIpValue(self::TEST_IP);

        $evm = new EventManager();
        $evm->addEventSubscriber($this->listener);

        $this->getMockSqliteEntityManager($evm);
    }

    public function testChange()
    {
        $test = new TitledArticle();
        $test->setTitle('Test');
        $test->setText('Test');

        $this->em->persist($test);
        $this->em->flush();
        $this->em->clear();

        $test = $this->em->getRepository(self::FIXTURE)->findOneBy(['title' => 'Test']);
        $test->setTitle('New Title');
        $this->em->persist($test);
        $this->em->flush();
        $this->em->clear();
        //Changed
        static::assertEquals(self::TEST_IP, $test->getChtitle());

        $this->listener->setIpValue('127.0.0.1');

        $test = $this->em->getRepository(self::FIXTURE)->findOneBy(['title' => 'New Title']);
        $test->setText('New Text');
        $this->em->persist($test);
        $this->em->flush();
        $this->em->clear();
        //Not Changed
        static::assertEquals(self::TEST_IP, $test->getChtitle());
    }

    protected function getUsedEntityFixtures()
    {
        return [
            self::FIXTURE,
        ];
    }
}
