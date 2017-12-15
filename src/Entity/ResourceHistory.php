<?php
namespace ResourceHistory\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Resource;
use Omeka\Entity\User;

/**
 * @Entity
 */
class ResourceHistory extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
    * @ManyToOne(targetEntity="Omeka\Entity\Resource")
    * @JoinColumn(onDelete="SET NULL")
    */
    protected $resource;

    /**
     * @Column(type="string", length=32)
    */
    protected $event;

    /**
     * @ManyToOne(targetEntity="Omeka\Entity\User")
     * @JoinColumn(onDelete="SET NULL")
     */
    protected $author;

    /**
     * @Column(type="datetime")
     */
    protected $created;

    /**
     * @Column(type="string", length=32)
     */
    protected $content;

    public function getId()
    {
        return $this->id;
    }

    public function setResource(Resource $resource)
    {
        $this->resource = $resource;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function setEvent(String $event)
    {
        $this->event = $event;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function setAuthor(User $author = null)
    {
        $this->author = $author;
    }

    public function getAuthor()
    {
        return $this->author;
    }

    public function setCreated(DateTime $dateTime)
    {
        $this->created = $dateTime;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function setContent(String $content)
    {
        $this->content = $content;
    }

    public function getContent()
    {
        return $this->content;
    }
}
