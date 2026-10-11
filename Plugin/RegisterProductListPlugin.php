<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Plugin;

use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\RequestInterface;
use CommerceGears\Ga4\Model\Config;
use CommerceGears\Ga4\Model\ListRegistry;

/**
 * Category and search result listings (Luma and Hyvä both use ListProduct).
 */
class RegisterProductListPlugin
{
    public function __construct(
        private readonly ListRegistry $registry,
        private readonly Config $config,
        private readonly RequestInterface $request,
        private readonly LayerResolver $layerResolver
    ) {
    }

    /**
     * @param ListProduct $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetLoadedProductCollection(ListProduct $subject, mixed $result): mixed
    {
        if (!$this->config->isActive() || !is_iterable($result)) {
            return $result;
        }
        $action = $this->request->getFullActionName();
        if (str_starts_with($action, 'catalogsearch_')) {
            $this->registry->add('search_results', (string)__('Search results'), $result);
            return $result;
        }
        $category = null;
        if ($action === 'catalog_category_view') {
            try {
                $category = $this->layerResolver->get()->getCurrentCategory();
            } catch (\Throwable) {
                $category = null;
            }
        }
        if ($category && $category->getId()) {
            $this->registry->add(
                'category_' . $category->getId(),
                (string)$category->getName(),
                $result,
                (int)$category->getId()
            );
        } else {
            $this->registry->add('product_list', (string)__('Product list'), $result);
        }
        return $result;
    }
}
