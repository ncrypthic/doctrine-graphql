<?php

namespace LLA\DoctrineGraphQLTest\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Post
 *
 * @ORM\Table(name="Post")
 * @ORM\Entity
 */
class Post
{
    /**
     * @var uuid
     *
     * @ORM\Column(name="postsId", type="uuid", precision=0, scale=0, nullable=false, unique=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $postsId;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", precision=0, scale=0, nullable=false, unique=false)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="content", type="string", precision=0, scale=0, nullable=false, unique=false)
     */
    private $content;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="createdAt", type="datetime", precision=0, scale=0, nullable=false, unique=false)
     */
    private $createdAt;

    /**
     * @var int
     *
     * @ORM\Column(name="status", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $status;

    /**
     * @var \LLA\DoctrineGraphQLTest\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="LLA\DoctrineGraphQLTest\Entity\User", inversedBy="posts")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="user_id", nullable=false)
     * })
     */
    private $author;


    /**
     * Set postsId.
     *
     * @param \uuid $postsId
     *
     * @return Post
     */
    public function setPostsId(\uuid $postsId)
    {
        $this->postsId = $postsId;

        return $this;
    }

    /**
     * Get postsId.
     *
     * @return \uuid
     */
    public function getPostsId()
    {
        return $this->postsId;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
     * @return Post
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set content.
     *
     * @param string $content
     *
     * @return Post
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return Post
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set status.
     *
     * @param int $status
     *
     * @return Post
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set author.
     *
     * @param \LLA\DoctrineGraphQLTest\Entity\User $author
     *
     * @return Post
     */
    public function setAuthor(\LLA\DoctrineGraphQLTest\Entity\User $author)
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Get author.
     *
     * @return \LLA\DoctrineGraphQLTest\Entity\User
     */
    public function getAuthor()
    {
        return $this->author;
    }
}
