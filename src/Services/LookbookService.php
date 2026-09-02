<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LookbookRepository;

/**
 * Editorial Lookbook Service.
 */
final class LookbookService
{
    private LookbookRepository $lookbookRepository;

    public function __construct(?LookbookRepository $lookbookRepository = null)
    {
        $this->lookbookRepository = $lookbookRepository ?? new LookbookRepository();
    }

    public function getLookbook(): array
    {
        return $this->lookbookRepository->getActiveItems();
    }
}
