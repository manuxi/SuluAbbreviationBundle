<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Trash;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationRestoredEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\RouteBundle\Entity\Route;
use Sulu\Bundle\TrashBundle\Application\DoctrineRestoreHelper\DoctrineRestoreHelperInterface;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfiguration;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfigurationProviderInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\RestoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\StoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;

class AbbreviationsTrashItemHandler implements StoreTrashItemHandlerInterface, RestoreTrashItemHandlerInterface, RestoreConfigurationProviderInterface
{
    private TrashItemRepositoryInterface $trashItemRepository;
    private EntityManagerInterface $entityManager;
    private DoctrineRestoreHelperInterface $doctrineRestoreHelper;
    private DomainEventCollectorInterface $domainEventCollector;

    public function __construct(
        TrashItemRepositoryInterface   $trashItemRepository,
        EntityManagerInterface         $entityManager,
        DoctrineRestoreHelperInterface $doctrineRestoreHelper,
        DomainEventCollectorInterface  $domainEventCollector
    )
    {
        $this->trashItemRepository = $trashItemRepository;
        $this->entityManager = $entityManager;
        $this->doctrineRestoreHelper = $doctrineRestoreHelper;
        $this->domainEventCollector = $domainEventCollector;
    }

    public static function getResourceKey(): string
    {
        return Abbreviation::RESOURCE_KEY;
    }

    public function store(object $resource, array $options = []): TrashItemInterface
    {
        $image = $resource->getImage();

        $data = [
            "type" => $resource->getType(),
            "title" => $resource->getTitle(),
            "subtitle" => $resource->getName(),
            "summary" => $resource->getExplanation(),
            "text" => $resource->getDescription(),
            "footer" => $resource->getFooter(),
            "slug" => $resource->getRoutePath(),
            "ext" => $resource->getExt(),
            "imageId" => $image ? $image->getId() : null,
            "published" => $resource->isPublished(),
            "publishedAt" => $resource->getPublishedAt()
        ];
        return $this->trashItemRepository->create(
            Abbreviation::RESOURCE_KEY,
            (string)$resource->getId(),
            $resource->getTitle(),
            $data,
            null,
            $options,
            Abbreviation::SECURITY_CONTEXT,
            null,
            null
        );
    }

    public function restore(TrashItemInterface $trashItem, array $restoreFormData = []): object
    {
        $data = $trashItem->getRestoreData();
        $abbreviationId = (int)$trashItem->getResourceId();
        $abbreviation = new Abbreviation();
        $abbreviation->setType($data['type']);

        $abbreviation->setTitle($data['title']);
        $abbreviation->setName($data['subtitle']);
        $abbreviation->setExplanation($data['summary']);
        $abbreviation->setDescription($data['text']);
        $abbreviation->setFooter($data['footer']);

        $abbreviation->setPublished($data['published']);

        $abbreviation->setRoutePath($data['slug']);

        $abbreviation->setExt($data['ext']);

        if($data['imageId']){
            $abbreviation->setImage($this->entityManager->find(MediaInterface::class, $data['imageId']));
        }

        if(isset($data['publishedAt'])){
            $abbreviation->setPublishedAt(new DateTime($data['publishedAt']['date']));
        }
        $this->domainEventCollector->collect(
            new AbbreviationRestoredEvent($abbreviation, $data)
        );

        $this->doctrineRestoreHelper->persistAndFlushWithId($abbreviation, $abbreviationId);
        $this->createRoute($this->entityManager, $abbreviationId, $abbreviation->getRoutePath(), Abbreviation::class);
        $this->entityManager->flush();
        return $abbreviation;
    }

    private function createRoute(EntityManagerInterface $manager, int $id, string $slug, string $class)
    {
        $route = new Route();
        $route->setPath($slug);
        $route->setLocale('en');
        $route->setEntityClass($class);
        $route->setEntityId($id);
        $route->setHistory(0);
        $route->setCreated(new DateTime());
        $route->setChanged(new DateTime());
        $manager->persist($route);
    }

    public function getConfiguration(): RestoreConfiguration
    {
        return new RestoreConfiguration(
            null,
            AbbreviationsAdmin::EDIT_FORM_VIEW,
            ['id' => 'id']
        );
    }
}
