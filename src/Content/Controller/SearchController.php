<?php declare(strict_types=1);

namespace DeepFashion\Content\Controller;

use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Suggest\SuggestPageLoader;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class SearchController extends StorefrontController
{
    /**
     * @var SearchPageLoader
     */
    private $searchPageLoader;

    /**
     * @var SuggestPageLoader
     */
    private $suggestPageLoader;

    /**
     * @var AbstractProductSearchRoute
     */
    private $productSearchRoute;

    public function __construct(
        SearchPageLoader $searchPageLoader,
        SuggestPageLoader $suggestPageLoader,
        AbstractProductSearchRoute $productSearchRoute
    ) {
        $this->searchPageLoader = $searchPageLoader;
        $this->suggestPageLoader = $suggestPageLoader;
        $this->productSearchRoute = $productSearchRoute;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/search-by-image", name="frontend.search.image", methods={"POST"}, defaults={"csrf_protected"=false, "XmlHttpRequest"=true})
     */
    public function doSearch(SalesChannelContext $context, Request $request): Response
    {
        /** @var UploadedFile $image */
        $image = $request->files->get('image');

        if (!$image) {
            return $this->forwardToRoute('frontend.home.page');
        }

        $originalSearch = $request->query->get('search');
        $request->query->set('search', empty($originalSearch) ? 'this is just a placeholder we will replace it with actual result from Tensorflow' : $originalSearch);

        $page = $this->searchPageLoader->load($request, $context);

        $page->assign(['imageBlob' => base64_encode(file_get_contents($image->getRealPath()))]);

        $page->setSearchTerm('');
        return $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', ['page' => $page]);
    }
}
