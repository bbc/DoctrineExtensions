<?php

namespace Gedmo\Tree\Strategy\ORM;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\UnitOfWork;
use Gedmo\Tree\Strategy\AbstractMaterializedPath;
use Gedmo\Tool\Wrapper\AbstractWrapper;

/**
 * This strategy makes tree using materialized path strategy
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class MaterializedPath extends AbstractMaterializedPath
{
    /**
     * {@inheritdoc}
     */
    public function removeNode($om, $meta, $config, $node)
    {
        if (!$config['cascade_deletes']) {
            return;
        }
        $uow = $om->getUnitOfWork();
        $wrapped = AbstractWrapper::wrap($node, $om);

        $path = addcslashes($wrapped->getPropertyValue($config['path']), '%');

        // Remove node's children
        $qb = $om->createQueryBuilder();
        $qb->select('e')
            ->from($config['useObjectClass'], 'e')
            ->where($qb->expr()->like('e.'.$config['path'], $qb->expr()->literal($path.'%')));
        $results = $qb->getQuery()
            ->execute();

        foreach ($results as $node) {
            $uow->scheduleForDelete($node);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reparentChildNodes($om, $meta, $config, $node)
    {
        if ($config['cascade_deletes']) {
            return;
        }
        $uow = $om->getUnitOfWork();

        if (method_exists($meta, 'getIdentifierValue')) {
            $identifier = $meta->getIdentifierValue($node);
        } else {
            $identifierProp = $meta->getReflectionProperty($meta->getSingleIdentifierFieldName());
            $identifierProp->setAccessible(true);
            $identifier = $identifierProp->getValue($node);
        }
        // Set immediate child nodes parent to null
        $qb = $om->createQueryBuilder();

        $qb->select('e')
            ->from($config['useObjectClass'], 'e')
            ->where('e.'.$config['parent'].' = :id')
            ->setParameter('id', $identifier);
        $results = $qb->getQuery()
            ->execute();

        foreach ($results as $node) {
            $wrappedNode = AbstractWrapper::wrap($node, $om);
            $wrappedNode->setPropertyValue($config['parent'], null);
            $uow->scheduleForUpdate($node);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren($om, $meta, $config, $path)
    {
        $path = addcslashes($path, '%');
        $qb = $om->createQueryBuilder($config['useObjectClass']);
        $qb->select('e')
            ->from($config['useObjectClass'], 'e')
            ->where($qb->expr()->like('e.'.$config['path'], $qb->expr()->literal($path.'%')))
            ->andWhere('e.'.$config['path'].' != :path')
            ->orderBy('e.'.$config['path'], 'asc');      // This may save some calls to updateNode
        $qb->setParameter('path', $path);

        return $qb->getQuery()
            ->execute();
    }
}
