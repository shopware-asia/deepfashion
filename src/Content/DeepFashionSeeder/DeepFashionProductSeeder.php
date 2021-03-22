<?php declare(strict_types=1);

namespace DeepFashion\Content\DeepFashionSeeder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

class DeepFashionProductSeeder
{
    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array
     */
    private $visibilities = [];

    public function __construct(
        EntityRepositoryInterface $taxRepository,
        Connection $connection
    ) {
        $this->taxRepository = $taxRepository;
        $this->connection = $connection;
    }

    public function getDefinition(): string
    {
        return ProductDefinition::class;
    }

    public function createProduct(DemodataContext $context, string $categoryId, string $productNumber): array
    {
        $visibilities = $this->buildVisibilities();

        $taxes = $this->getTaxes($context->getContext());

        $product = $this->createSimpleProduct($context, $taxes, $categoryId, $productNumber);

        $product['visibilities'] = $visibilities;

        return $product;
    }

    private function getTaxes(Context $context)
    {
        $taxes = $this->taxRepository->search(new Criteria(), $context);
        if ($taxes->count() > 0) {
            return $taxes;
        }

        $tax = ['name' => 'High tax', 'taxRate' => 19];
        $this->taxRepository->create([$tax], $context);

        return $this->taxRepository->search(new Criteria(), $context);
    }

    private function createSimpleProduct(DemodataContext $context, EntitySearchResult $taxes, string $categoryId, string $productNumber): array
    {
        $price = $context->getFaker()->randomFloat(2, 1, 1000);
        $tax = $taxes->get(array_rand($taxes->getIds()));
        $reverseTaxrate = 1 + ($tax->getTaxRate() / 100);

        $faker = $context->getFaker();
        $product = [
            'id' => Uuid::randomHex(),
            'productNumber' => $productNumber,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / $reverseTaxrate, 'linked' => true]],
            'name' => $faker->productName,
            'description' => $faker->text(),
            'descriptionLong' => $faker->text(),
            'taxId' => $tax->getId(),
            'manufacturerId' => $context->getRandomId('product_manufacturer'),
            'active' => true,
            'height' => 500,
            'width' => 500,
            'categories' => [
                ['id' => $categoryId],
            ],
            'stock' => 10,
        ];

        $purchasePrice = $context->getFaker()->randomFloat(2, 1, 100);
        $product['purchasePrices'] = [
            [
                'currencyId' => Defaults::CURRENCY,
                'gross' => $purchasePrice,
                'net' => $purchasePrice / $reverseTaxrate,
                'linked' => true,
            ],
        ];

        return $product;
    }

    private function buildVisibilities()
    {
        if (!empty($this->visibilities)) {
            return $this->visibilities;
        }
        $ids = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id FROM sales_channel LIMIT 100');

        $this->visibilities = array_map(function ($id) {
            return ['salesChannelId' => $id['id'], 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL];
        }, $ids);

        return $this->visibilities;
    }
}
