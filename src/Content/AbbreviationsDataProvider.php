<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Content;

use Countable;
use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Component\Serializer\ArraySerializerInterface;
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Component\SmartContent\DataProviderResult;
use Sulu\Component\SmartContent\Orm\BaseDataProvider;
use Sulu\Component\SmartContent\Orm\DataProviderRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AbbreviationsDataProvider extends BaseDataProvider
{
    private int $defaultLimit = 12;

    private RequestStack $requestStack;
    private EntityManagerInterface $entityManager;

    public function __construct(DataProviderRepositoryInterface $repository, ArraySerializerInterface $serializer, RequestStack $requestStack, EntityManagerInterface $entityManager)
    {
        parent::__construct($repository, $serializer);
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    public function getConfiguration(): ProviderConfigurationInterface
    {
        if (null === $this->configuration) {
            $this->configuration = self::createConfigurationBuilder()
                ->enableLimit()
                ->enablePagination()
                ->enablePresentAs()
                ->enableCategories()
                ->enableSorting([
                        ['column' => 'translation.name', 'title' => 'sulu_abbreviation.name'],
                        ['column' => 'translation.explanation', 'title' => 'sulu_abbreviation.explanation'],
                        ['column' => 'translation.published_at', 'title' => 'sulu_abbreviation.published_at']
                    ]
                )
                ->getConfiguration();
        }

        return parent::getConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function resolveResourceItems(
        array $filters,
        array $propertyParameter,
        array $options = [],
        $limit = null,
        $page = 1,
        $pageSize = null
    ) {

        $locale = $options['locale'];
        $request = $this->requestStack->getCurrentRequest();
        $options['page'] = $request->get('p');
        $abbreviation = $this->entityManager->getRepository(Abbreviation::class)->findByFilters($filters, $page, $pageSize, $limit, $locale, $options);
        return new DataProviderResult($abbreviation, $this->entityManager->getRepository(Abbreviation::class)->hasNextPage($filters, $page, $pageSize, $limit, $locale, $options));
    }

    /**
     * @param mixed[] $data
     * @return array
     */
    protected function decorateDataItems(array $data): array
    {
        return \array_map(
            static function ($item) {
                return new AbbreviationDataItem($item);
            },
            $data
        );
    }

    /**
     * Returns flag "hasNextPage".
     * It combines the limit/query-count with the page and page-size.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     * @param Countable $queryResult
     * @param int|null $limit
     * @param int $page
     * @param int|null $pageSize
     * @return bool
     */
    private function hasNextPage(Countable $queryResult, ?int $limit, int $page, ?int $pageSize): bool
    {
        $count = $queryResult->count();

        if (null === $pageSize || $pageSize > $this->defaultLimit) {
            $pageSize = $this->defaultLimit;
        }

        $offset = ($page - 1) * $pageSize;
        if ($limit && $offset + $pageSize > $limit) {
            return false;
        }

        return $count > ($page * $pageSize);
    }

}
