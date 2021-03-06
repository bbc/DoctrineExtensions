<?php

namespace Gedmo\Tree;

use Doctrine\Common\EventManager;
use Tool\BaseTestCaseORM;

/**
 * These are tests for Tree behavior
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @link http://www.gediminasm.org
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class MaterializedPathORMTest extends BaseTestCaseORM
{
    const CATEGORY = "Tree\\Fixture\\MPCategory";
    const CATEGORY_WITHOUT_CASCADING_DELETE = "Tree\\Fixture\\MPCategoryWithNonCascadingDelete";

    protected $config;
    protected $listener;

    protected function setUp()
    {
        parent::setUp();

        $this->listener = new TreeListener();

        $evm = new EventManager();
        $evm->addEventSubscriber($this->listener);

        $this->getMockSqliteEntityManager($evm);

        $meta = $this->em->getClassMetadata(self::CATEGORY);
        $this->config = $this->listener->getConfiguration($this->em, $meta->name);
    }

    /**
     * @test
     */
    public function insertUpdateAndRemove()
    {
        // Insert
        $category = $this->createCategory();
        $category->setTitle('1');
        $category2 = $this->createCategory();
        $category2->setTitle('2');
        $category3 = $this->createCategory();
        $category3->setTitle('3');
        $category4 = $this->createCategory();
        $category4->setTitle('4');

        $category2->setParent($category);
        $category3->setParent($category2);

        $this->em->persist($category4);
        $this->em->persist($category3);
        $this->em->persist($category2);
        $this->em->persist($category);
        $this->em->flush();

        $this->em->refresh($category);
        $this->em->refresh($category2);
        $this->em->refresh($category3);
        $this->em->refresh($category4);

        $this->assertEquals($this->generatePath(array('1' => $category->getId())), $category->getPath());
        $this->assertEquals($this->generatePath(array('1' => $category->getId(), '2' => $category2->getId())), $category2->getPath());
        $this->assertEquals($this->generatePath(array('1' => $category->getId(), '2' => $category2->getId(), '3' => $category3->getId())), $category3->getPath());
        $this->assertEquals($this->generatePath(array('4' => $category4->getId())), $category4->getPath());
        $this->assertEquals(1, $category->getLevel());
        $this->assertEquals(2, $category2->getLevel());
        $this->assertEquals(3, $category3->getLevel());
        $this->assertEquals(1, $category4->getLevel());

        $this->assertEquals('1-4', $category->getTreeRootValue());
        $this->assertEquals('1-4', $category2->getTreeRootValue());
        $this->assertEquals('1-4', $category3->getTreeRootValue());
        $this->assertEquals('4-1', $category4->getTreeRootValue());

        // Update
        $category2->setParent(null);

        $this->em->persist($category2);
        $this->em->flush();

        $this->em->refresh($category);
        $this->em->refresh($category2);
        $this->em->refresh($category3);

        $this->assertEquals($this->generatePath(array('1' => $category->getId())), $category->getPath());
        $this->assertEquals($this->generatePath(array('2' => $category2->getId())), $category2->getPath());
        $this->assertEquals($this->generatePath(array('2' => $category2->getId(), '3' => $category3->getId())), $category3->getPath());
        $this->assertEquals(1, $category->getLevel());
        $this->assertEquals(1, $category2->getLevel());
        $this->assertEquals(2, $category3->getLevel());
        $this->assertEquals(1, $category4->getLevel());

        $this->assertEquals('1-4', $category->getTreeRootValue());
        $this->assertEquals('2-3', $category2->getTreeRootValue());
        $this->assertEquals('2-3', $category3->getTreeRootValue());
        $this->assertEquals('4-1', $category4->getTreeRootValue());

        // Remove
        $this->em->remove($category);
        $this->em->remove($category2);
        $this->em->flush();

        $result = $this->em->createQueryBuilder()->select('c')->from(self::CATEGORY, 'c')->getQuery()->execute();

        $firstResult = $result[0];

        $this->assertCount(1, $result);
        $this->assertEquals('4', $firstResult->getTitle());
        $this->assertEquals(1, $firstResult->getLevel());
        $this->assertEquals('4-1', $firstResult->getTreeRootValue());
    }

    /**
     * @test
     */
    public function nonCascadingRemove()
    {
        // Insert
        $category = $this->createNonCascadingDeleteCategory();
        $category->setTitle('1');
        $category2 = $this->createNonCascadingDeleteCategory();
        $category2->setTitle('2');
        $category3 = $this->createNonCascadingDeleteCategory();
        $category3->setTitle('3');
        $category4 = $this->createNonCascadingDeleteCategory();
        $category4->setTitle('4');
        $category5 = $this->createNonCascadingDeleteCategory();
        $category5->setTitle('5');

        $category2->setParent($category);
        $category3->setParent($category2);
        $category4->setParent($category3);

        $this->em->persist($category5);
        $this->em->persist($category4);
        $this->em->persist($category3);
        $this->em->persist($category2);
        $this->em->persist($category);
        $this->em->flush();

        $this->em->refresh($category);
        $this->em->refresh($category2);
        $this->em->refresh($category3);
        $this->em->refresh($category4);
        $this->em->refresh($category5);

        // Remove
        $this->em->remove($category2);
        $this->em->remove($category);
        $this->em->flush();

        $result = $this->em->createQueryBuilder()->select('c')->from(self::CATEGORY_WITHOUT_CASCADING_DELETE, 'c')->getQuery()->execute();

        $this->assertCount(3, $result);

        $cat3result = $result[2];
        $cat4result = $result[1];
        $cat5result = $result[0];

        $this->assertEquals('3', $cat3result->getTitle());
        $this->assertEquals(1, $cat3result->getLevel());
        $this->assertEquals('3-3', $cat3result->getTreeRootValue());
        $this->assertNull($cat3result->getParent());

        $this->assertEquals('4', $cat4result->getTitle());
        $this->assertEquals(2, $cat4result->getLevel());
        $this->assertEquals('3-3', $cat4result->getTreeRootValue());
        $this->assertEquals('3', $cat4result->getParent()->getTitle());

        $this->assertEquals('5', $cat5result->getTitle());
        $this->assertNull($cat5result->getParent());
    }

    /**
     * @test
     */
    public function useOfSeparatorInPathSourceShouldThrowAnException()
    {
        $this->setExpectedException('Gedmo\Exception\RuntimeException');

        $category = $this->createCategory();
        $category->setTitle('1'.$this->config['path_separator']);

        $this->em->persist($category);
        $this->em->flush();
    }

    public function createNonCascadingDeleteCategory()
    {
        $class = self::CATEGORY_WITHOUT_CASCADING_DELETE;

        return new $class();
    }

    public function createCategory()
    {
        $class = self::CATEGORY;

        return new $class();
    }

    protected function getUsedEntityFixtures()
    {
        return array(
            self::CATEGORY,
            self::CATEGORY_WITHOUT_CASCADING_DELETE,
        );
    }

    public function generatePath(array $sources)
    {
        $path = '';

        foreach ($sources as $p => $id) {
            $path .= $p.'-'.$id.$this->config['path_separator'];
        }

        return $path;
    }
}
