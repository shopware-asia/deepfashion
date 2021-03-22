<?php declare(strict_types=1);

namespace DeepFashion\Content\DeepFashionSeeder;

use bheller\ImagesGenerator\ImagesGeneratorProvider;
use Faker\Factory;
use Faker\Generator;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\Faker\Commerce;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DeepFashionSeeder
{
    private const DATA_DIR = '/custom/plugins/DeepFashion/files';
    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;
    /**
     * @var DeepFashionProductSeeder
     */
    private $fashionProductSeeder;
    /**
     * @var DeepFashionProductMediaSeeder
     */
    private $fashionProductMediaSeeder;

    public function __construct(
        string $projectDir,
        DefinitionInstanceRegistry $registry,
        DeepFashionProductSeeder $fashionProductSeeder,
        DeepFashionProductMediaSeeder $fashionProductMediaSeeder
    ) {
        $this->projectDir = $projectDir;
        $this->registry = $registry;
        $this->fashionProductSeeder = $fashionProductSeeder;
        $this->fashionProductMediaSeeder = $fashionProductMediaSeeder;
    }

    public function generate(DemodataRequest $request, Context $context, ?SymfonyStyle $console): DemodataContext
    {
        if (!$console) {
            $console = new ShopwareStyle(new ArgvInput(), new NullOutput());
        }

        $faker = $this->getFaker();

        $demodataContext = new DemodataContext($context, $faker, $this->projectDir, $console, $this->registry);

        $parentCategories = (new Finder())
            ->directories()
            ->depth(0)
            ->in($this->projectDir . self::DATA_DIR)
            ->sortByName()
            ->getIterator();

        $rootCategoryId = $this->getRootCategoryId($demodataContext->getContext());
        $pageIds = $this->getCmsPageIds($demodataContext->getContext());

        // Implement a DTO
        $payload = [
            'products' => [],
            'categories' => []
        ];

        $console->section('Preparing payload and images');

        $start = microtime(true);

        $this->createCategoryPayload($demodataContext, $parentCategories, $rootCategoryId, $pageIds, $payload);

        $console->section(sprintf('Saving %d categories and %d products', count($payload['categories']), count($payload['products'])));

        $this->registry->getRepository(CategoryDefinition::ENTITY_NAME)->create($payload['categories'], $context);
        $this->registry->getRepository(ProductDefinition::ENTITY_NAME)->create($payload['products'], $context);

        // Finish genrating
        $end = microtime(true) - $start;

        $console->note(sprintf('Took %f seconds', $end));


        return $demodataContext;
    }

    private function createCategoryPayload(DemodataContext $context, iterable $fileInfos, string $parentId, array $pageIds, array &$payload = []): array
    {
        $lastId = null;

        /** @var SplFileInfo $fileInfo */
        foreach ($fileInfos as $fileInfo) {
            $isCategory = $fileInfo->isDir();

            $childNodes = (new Finder())
                ->directories()
                ->depth(0)
                ->in($fileInfo->getPathname())
                ->sortByName();

            $childNodes = $childNodes->getIterator();

            $isCategory = $isCategory && $childNodes->current() instanceof SplFileInfo && $childNodes->current()->isDir();

            if ($isCategory) {
                $context->getConsole()->writeln('Start Generating Category payload for ' . $fileInfo->getFilename());

                $id = Uuid::randomHex();

                $payload['categories'][] = [
                    'id' => $id,
                    'name' => $fileInfo->getFilename(),
                    'parentId' => $parentId,
                    'afterCategoryId' => $lastId,
                    'active' => true,
                    'cmsPageId' => $context->getFaker()->randomElement($pageIds),
                    'mediaId' => null,
                    'description' => $context->getFaker()->text(),
                ];

                $lastId = $id;

                $this->createCategoryPayload($context, $childNodes, $id, $pageIds, $payload);

                $context->getConsole()->writeln('Finish Generating Category payload for ' . $fileInfo->getFilename());
            } else {
                $childNodes = (new Finder())
                    ->files()
                    ->depth(0)
                    ->in($fileInfo->getPathname())
                    ->sortByName();

                $context->getConsole()->writeln('Generating Products for ' . $fileInfo->getFilename());

                $payload['products'][] = $this->createProductPayload($context, $fileInfo->getFilename(), $childNodes->getIterator(), $parentId);

                if (\count($payload['products']) === 20) {
                    if (\count($payload['categories']) > 0) {
                        $context->getConsole()->writeln('Saving ' . \count($payload['categories']) . ' categories');

                        $this->registry->getRepository(CategoryDefinition::ENTITY_NAME)->create($payload['categories'], $context->getContext());
                        $payload['categories'] = [];
                    }

                    $context->getConsole()->writeln('Saving ' . \count($payload['products']) . ' products');

                    try {
                        $this->registry->getRepository(ProductDefinition::ENTITY_NAME)->create($payload['products'], $context->getContext());
                    } catch (\Throwable $exception) {

                    }

                    $payload['products'] = [];
                }
            }
        }

        return $payload;
    }

    private function getFaker(): Generator
    {
        $faker = Factory::create('de-DE');
        $faker->addProvider(new Commerce($faker));
        $faker->addProvider(new ImagesGeneratorProvider($faker));

        return $faker;
    }

    private function createProductPayload(DemodataContext $context, string $productNumber, iterable $fileInfos, string $categoryId): array
    {
        $count = \iterator_count($fileInfos);
        $product = $this->fashionProductSeeder->createProduct($context, $categoryId, $productNumber);

        if ($count > 0) {
            $mediaIds = [];
            /** @var SplFileInfo $media */
            foreach ($fileInfos as $media) {
                $mediaIds[] = [
                    'mediaId' => $this->fashionProductMediaSeeder->generate($context, $media->getPathname(), $media->getFilenameWithoutExtension())
                ];
            }

            $product['media'] = $mediaIds;
            $product['cover'] = $mediaIds[0];
        }

        return $product;
    }

    private function getRootCategoryId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('category.parentId', null));
        $criteria->addSorting(new FieldSorting('category.createdAt', FieldSorting::ASCENDING));

        $categories = $this->registry->getRepository(CategoryDefinition::ENTITY_NAME)->searchIds($criteria, $context)->getIds();

        return array_shift($categories);
    }

    private function getCmsPageIds(Context $getContext): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type', 'product_list'));
        $criteria->setLimit(500);

        return $this->registry->getRepository(CmsPageDefinition::ENTITY_NAME)->searchIds($criteria, $getContext)->getIds();
    }
}
