<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ProductService;

/**
 * Controller handling public Product API endpoints.
 */
final class ProductController
{
    private ProductService $productService;

    public function __construct(?ProductService $productService = null)
    {
        $this->productService = $productService ?? new ProductService();
    }

    /**
     * GET /api/products
     */
    public function index(Request $request): Response
    {
        $result = $this->productService->getCatalog($request->query());

        return Response::success([
            'products' => $result['items'],
            'pagination' => $result['pagination'],
        ], 'Products retrieved successfully.', 200, [
            'Cache-Control' => 'public, max-age=60, s-maxage=300, stale-while-revalidate=120',
        ]);
    }

    /**
     * GET /api/products/{slug_or_id}
     */
    public function show(Request $request): Response
    {
        $identifier = (string) $request->param('slug_or_id', '');
        $product = $this->productService->getProductDetail($identifier);

        if (!$product) {
            return Response::notFound("Product '{$identifier}' not found.");
        }

        return Response::success($product, 'Product details retrieved successfully.', 200, [
            'Cache-Control' => 'public, max-age=60, s-maxage=300, stale-while-revalidate=120',
        ]);
    }
}
