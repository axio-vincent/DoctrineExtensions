<?php

namespace Gedmo\Tests\Loggable\Fixture\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ODM\Document
 * @Gedmo\Loggable
 */
class RelatedArticle
{
    /**
     * @ODM\Id
     */
    private $id;

    /**
     * @Gedmo\Versioned
     * @ODM\Field(type="string")
     */
    private $title;

    /**
     * @Gedmo\Versioned
     * @ODM\Field(type="string")
     */
    private $content;

    /**
     * @ODM\ReferenceMany(targetDocument="Gedmo\Tests\Loggable\Fixture\Document\Comment", mappedBy="article")
     */
    private $comments;

    public function getId()
    {
        return $this->id;
    }

    public function addComment(Comment $comment)
    {
        $comment->setArticle($this);
        $this->comments[] = $comment;
    }

    public function getComments()
    {
        return $this->comments;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setContent($content)
    {
        $this->content = $content;
    }

    public function getContent()
    {
        return $this->content;
    }
}
