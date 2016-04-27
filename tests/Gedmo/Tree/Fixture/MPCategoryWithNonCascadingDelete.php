<?php

namespace Tree\Fixture;

use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Gedmo\Tree\Entity\Repository\MaterializedPathRepository")
 * @Gedmo\Tree(type="materializedPath", cascadeDeletes=false)
 */
class MPCategoryWithNonCascadingDelete
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @Gedmo\TreePath
     * @ORM\Column(name="path", type="string", length=3000, nullable=true)
     */
    private $path;

    /**
     * @Gedmo\TreePathSource
     * @ORM\Column(name="title", type="string", length=64)
     */
    private $title;

    /**
     * @Gedmo\TreeParent
     * @ORM\ManyToOne(targetEntity="MPCategoryWithNonCascadingDelete", inversedBy="children")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent_id", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $parentId;

    /**
     * @Gedmo\TreeLevel
     * @ORM\Column(name="lvl", type="integer", nullable=true)
     */
    private $level;

    /**
     * @ORM\OneToMany(targetEntity="MPCategoryWithNonCascadingDelete", mappedBy="parent")
     */
    private $children;

    /**
     * @ORM\OneToMany(targetEntity="Article", mappedBy="category")
     */
    private $comments;

    public function getId()
    {
        return $this->id;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setParent(MPCategoryWithNonCascadingDelete $parent = null)
    {
        $this->parentId = $parent;
    }

    public function getParent()
    {
        return $this->parentId;
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getLevel()
    {
        return $this->level;
    }
}
