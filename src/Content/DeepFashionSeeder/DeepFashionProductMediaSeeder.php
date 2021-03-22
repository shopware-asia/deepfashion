<?php declare(strict_types=1);

namespace DeepFashion\Content\DeepFashionSeeder;

use bheller\ImagesGenerator\ImagesGeneratorProvider;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

class DeepFashionProductMediaSeeder
{
    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var FileSaver
     */
    private $mediaUpdater;

    /**
     * @var FileNameProvider
     */
    private $fileNameProvider;

    /**
     * @var EntityRepositoryInterface
     */
    private $defaultFolderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $folderRepository;

    /**
     * @var MediaDefinition
     */
    private $mediaDefinition;

    /**
     * @var EntitySearchResult|null
     */
    private $defaultFolders;

    public function __construct(
        EntityWriterInterface $writer,
        FileSaver $mediaUpdater,
        FileNameProvider $fileNameProvider,
        EntityRepositoryInterface $defaultFolderRepository,
        EntityRepositoryInterface $folderRepository,
        MediaDefinition $mediaDefinition
    ) {
        $this->writer = $writer;
        $this->mediaUpdater = $mediaUpdater;
        $this->fileNameProvider = $fileNameProvider;
        $this->defaultFolderRepository = $defaultFolderRepository;
        $this->folderRepository = $folderRepository;
        $this->mediaDefinition = $mediaDefinition;
    }

    public function getDefinition(): string
    {
        return MediaDefinition::class;
    }

    public function generate(DemodataContext $context, string $file, string $fileName): string
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $mediaFolderId = $this->getOrCreateDefaultFolder($context);

        $mediaId = Uuid::randomHex();
        $this->writer->insert(
            $this->mediaDefinition,
            [
                [
                    'id' => $mediaId,
                    'name' => $fileName,
                    'mediaFolderId' => $mediaFolderId,
                ],
            ],
            $writeContext
        );

        $this->mediaUpdater->persistFileToMedia(
            new MediaFile(
                $file,
                mime_content_type($file),
                pathinfo($file, \PATHINFO_EXTENSION),
                filesize($file)
            ),
            $this->fileNameProvider->provide(
                pathinfo($file, \PATHINFO_FILENAME),
                pathinfo($file, \PATHINFO_EXTENSION),
                $mediaId,
                $context->getContext()
            ),
            $mediaId,
            $context->getContext()
        );

        return $mediaId;
    }

    private function getOrCreateDefaultFolder(DemodataContext $context): ?string
    {
        $mediaFolderId = null;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entity', 'product'));
        $criteria->addAssociation('folder');
        $criteria->setLimit(1);

        $this->defaultFolders = $this->defaultFolders ?? $this->defaultFolderRepository->search($criteria, $context->getContext());

        if ($this->defaultFolders->count() <= 0) {
            return $mediaFolderId;
        }

        /** @var MediaDefaultFolderEntity $defaultFolder */
        $defaultFolder = $this->defaultFolders->first();

        if ($defaultFolder->getFolder()) {
            return $defaultFolder->getFolder()->getId();
        }

        $mediaFolderId = Uuid::randomHex();
        $this->folderRepository->upsert([
            [
                'id' => $mediaFolderId,
                'defaultFolderId' => $defaultFolder->getId(),
                'name' => 'Product Media',
                'useParentConfiguration' => false,
                'configuration' => [],
            ],
        ], $context->getContext());

        return $mediaFolderId;
    }
}
