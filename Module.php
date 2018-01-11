<?php
namespace ResourceHistory;

use Doctrine\DBAL\Driver\PDOException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\ResultSetMapping;
use Omeka\Api\Request;
use Omeka\Module\AbstractModule;
use ResourceHistory\Entity\ResourceHistory;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver;

class Module extends AbstractModule
{

    const RESOURCE = 'items';
    const EAC_RECORD_PROPERTY = 'eac:p_entityType';
    const RECORD_ID_PROPERTY = 'eac:p_recordId';

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

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
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

        $revisionEvents = [
            'api.update.pre'
        ];

        $autoFieldsEvents = [
            'api.create.post',
            'api.update.post'
        ];

        foreach ($triggerIdentifiers as $identifier) {
            foreach ($revisionEvents as $event) {
                $sharedEventManager->attach(
                    $identifier,
                    $event,
                    array($this, 'saveRevision')
                );
            }

            foreach ($autoFieldsEvents as $event) {
                $sharedEventManager->attach(
                    $identifier,
                    $event,
                    function (\Zend\EventManager\Event $event) {
                        $response = $event->getParam('response');
                        $item = $response->getContent();

                        $content = $event->getParam('request')->getContent();

                        if (array_key_exists(self::EAC_RECORD_PROPERTY, $content)) {
                            $recordId = sprintf('GPP-%08d', $item->getId());

                            if (array_key_exists(self::RECORD_ID_PROPERTY, $content)) {
                                $content[self::RECORD_ID_PROPERTY][0]['@value'] = $recordId;
                            } else {
                                $content[self::RECORD_ID_PROPERTY][0]['@value'] = $recordId;
                                $content[self::RECORD_ID_PROPERTY][0]['property_id'] = 1021;
                                $content[self::RECORD_ID_PROPERTY][0]['type'] = 'literal';
                            }
                        }

                        $request = new Request(Request::UPDATE, self::RESOURCE);
                        $request->setId($item->getId());
                        $request->setContent($content);

                        $itemAdapter = $event->getTarget();
                        $itemAdapter->update($request);
                    }
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

        $request = $event->getParam('request');
        $id = $request->getId();
        $resource = $event->getTarget()->getEntityManager()->find('Omeka\Entity\Resource', $id);

        $response = $this->getServiceLocator()->get('Omeka\ApiManager')->read('resources', $id);
        $resourceRepresentation = $response->getContent();

        $version = new ResourceHistory;
        $version->setResource($resource);
        $version->setEvent($event->getName());
        $version->setAuthor($resource->getOwner());
        $version->setCreated($resource->getModified());
        $version->setContent(json_encode($resourceRepresentation->values()));

        $this->manager->persist($version);
        $this->manager->flush();

        $this->logger->debug(
            sprintf(
                'Saved version %d for resource %d with event %s',
                $version->getId(),
                $version->getResourceId(),
                $version->getEvent()
            )
        );
    }


    /**
     * The view function which adds content to the admin panel
     */
    public function handleRevisions(Event $event)
    {
        // Get the id via $item->id()
        $item = $event->getTarget()->item;

        $revision_changed = false;

        if (isset($_POST['revision_id']) && isset($_POST['revision_content']) && !empty($_POST['revision_id']) && !empty($_POST['revision_content'])) {
            // This is the values() data from the saved revision
            $revision_item = json_decode($_POST['revision_content'], true);

            $resource_id = $item->id();

            $resource = $this->manager->find('Omeka\Entity\Resource', $resource_id);

            $response = $this->getServiceLocator()->get('Omeka\ApiManager')->read('resources', $resource_id);
            $resourceRepresentation = $response->getContent();

            $version = new ResourceHistory;
            $version->setResource($resource);
            $version->setEvent($event->getName());
            $version->setAuthor($resource->getOwner());
            $version->setCreated($resource->getModified());
            $version->setContent(json_encode($resourceRepresentation->values()));

            $this->manager->persist($version);
            $this->manager->flush();

            $this->logger->debug(
                sprintf(
                    'Saved version %d for resource %d with event %s',
                    $version->getId(),
                    $version->getResourceId(),
                    $version->getEvent()
                )
            );

            $rsm = new ResultSetMapping();
            $query_string = "INSERT INTO value (resource_id, property_id, type, value) VALUES";

            // First we delete:
            try {
                $query = $this->manager->createNativeQuery("DELETE FROM value WHERE resource_id = '$resource_id';", $rsm)->getResult();
            } catch (PDOException $e) {
            }


            foreach ($revision_item as $r) {
                $property_id = utf8_encode($r['values'][0]['property_id']);
                $type = utf8_encode($r['values'][0]['type']);
                $value = utf8_encode($r['values'][0]['@value']);

                $query_string = $query_string . " ('$resource_id', '$property_id', '$type', '$value')";

                if ($r === end($revision_item)) {
                    $query_string = $query_string . ";";
                } else {
                    $query_string = $query_string . ",";
                }
            }

            try {
                $query = $this->manager->createNativeQuery($query_string, $rsm)->getResult();
            } catch (PDOException $e) {
            }

            // Set a flag for UI display
            $revision_changed = true;
            header("Refresh:0");
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
