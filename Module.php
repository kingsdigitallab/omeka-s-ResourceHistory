<?php

namespace ResourceHistory;

use Doctrine\ORM\EntityManager;
use ResourceHistory\Entity\ResourceHistory;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver;
use Zend\View\Model\ViewModel;

class Module extends AbstractModule
{

    protected $logger;

    protected $manager;

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $writer = new Stream('resourcehistory.log');

        $this->logger = new Logger;
        $this->logger->addWriter($writer);

        $this->manager = $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    /**
     * Attach listeners to events.
     *
     * @param SharedEventManagerInterface $sharedEventManager
     */
    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $triggerIdentifiers = [
            'Omeka\Api\Adapter\ItemAdapter',
                ];

        $events = [
            'api.update.post',
        ];

        foreach ($triggerIdentifiers as $identifier) {
            foreach ($events as $event) {
                $sharedEventManager->attach(
                    $identifier,
                    $event,
                    array($this, 'saveRevision')
                );
            }
        }


        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.after',
            array($this, 'handleRevisions')
        );

    }

    public function saveRevision(Event $event)
    {
        $response = $event->getParam('response');
        $resource = $response->getContent();

        $r = $this->getServiceLocator()->get('Omeka\ApiAdapterManager')->get('items')->getRepresentation($resource);

        $version = new ResourceHistory;
        $version->setResource($resource);
        $version->setEvent($event->getName());
        $version->setAuthor($resource->getOwner());
        $version->setCreated($resource->getModified());
        $version->setContent(json_encode($r->values()));

        $this->manager->persist($version);
        $this->manager->flush();

        $this->logger->debug(sprintf(
            'Saved version %d for resource %d with event %s',
            $version->getId(),
            $version->getResourceId(),
            $version->getEvent()
        ));
    }


    /**
     * The view function which adds content to the admin panel
     */
    public function handleRevisions(Event $event)
    {
        // Get the id via $item->id()
        $item = $event->getTarget()->item;
        $revision_changed = false;
        
        if(isset($_POST['revision_id']))
        {
            // Change the revision

            $revision_changed = true;
        }

        // Setup our renderers
        $renderer = new PhpRenderer();
        $resolver = new Resolver\TemplatePathStack();
        $resolver->setPaths(array(__DIR__ . '/src/Views/'));
        $renderer->setResolver($resolver);

        // Setup our model
        $model = new ViewModel();
        $model->setTemplate('resourceHistory');
        
        // Check for revisions
        $qb = $this->manager->createQueryBuilder()
                            ->select('c')
                            ->from('ResourceHistory\Entity\ResourceHistory', 'c')
                            ->where('c.resource = :resource_id')
                            ->setParameter('resource_id', $item->id())
                            ->orderBy('c.created', "DESC")
                            ->getQuery();

        $model->revisions = $qb->getArrayResult();
        $model->item_id = $item->id();
        $model->revision_changed = $revision_changed;


        // Render
        $html = $renderer->render($model);
        echo $html;
    }



    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->exec(
            'CREATE TABLE resource_history (
            id INT AUTO_INCREMENT NOT NULL,
            resource_id INT,
            event VARCHAR(32),
            author_id INT,
            created DATETIME DEFAULT NOW(),
            content json NOT NULL,
            INDEX IDX_RESOURCEHISTORY_RESOURCE (resource_id),
            INDEX IDX_RESOURCEHISTORY_AUTHOR (author_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;'
        );
        $conn->exec('ALTER TABLE resource_history ADD CONSTRAINT FK_RESOURCEHISTORY_RESOURCE FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE SET NULL;');
        $conn->exec('ALTER TABLE resource_history ADD CONSTRAINT FK_RESOURCEHISTORY_AUTHOR FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE SET NULL;');
    }



    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->exec('ALTER TABLE resource_history DROP FOREIGN KEY FK_RESOURCEHISTORY_RESOURCE;');
        $conn->exec('ALTER TABLE resource_history DROP FOREIGN KEY FK_RESOURCEHISTORY_AUTHOR;');
        $conn->exec('DROP TABLE resource_history');
    }
}
