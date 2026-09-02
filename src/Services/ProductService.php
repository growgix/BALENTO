<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductRepository;

/**
 * Product Business Logic Service.
 */
final class ProductService
{
    private ProductRepository $productRepository;
    private const MAX_PAGE_LIMIT = 50;
    private const DEFAULT_PAGE_LIMIT = 20;

    public function __construct(?ProductRepository $productRepository = null)
    {
        $this->productRepository = $productRepository ?? new ProductRepository();
    }

    public function getCatalog(array $queryParams): array
    {
        $category = isset($queryParams['category']) ? trim((string) $queryParams['category']) : null;
        $search = isset($queryParams['search']) ? trim((string) $queryParams['search']) : null;
        $sort = isset($queryParams['sort']) ? trim((string) $queryParams['sort']) : 'default';

        $page = isset($queryParams['page']) ? max(1, (int) $queryParams['page']) : 1;
        $limit = isset($queryParams['limit']) ? min(self::MAX_PAGE_LIMIT, max(1, (int) $queryParams['limit'])) : self::DEFAULT_PAGE_LIMIT;

        $filters = [
            'category' => $category,
            'search' => $search,
            'sort' => $sort,
        ];

        return $this->productRepository->findFiltered($filters, $page, $limit);
    }

    public function getProductDetail(string $slugOrId): ?array
    {
        $sanitized = trim($slugOrId);
        if ($sanitized === '') {
            return null;
        }

        return $this->productRepository->findBySlugOrId($sanitized);
    }
}
