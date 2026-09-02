<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\LookbookService;

/**
 * Controller handling public Lookbook street style content.
 */
final class LookbookController
{
    private LookbookService $lookbookService;

    public function __construct(?LookbookService $lookbookService = null)
    {
        $this->lookbookService = $lookbookService ?? new LookbookService();
    }

    /**
     * GET /api/lookbook
     */
    public function index(Request $request): Response
    {
        $items = $this->lookbookService->getLookbook();
        return Response::success($items, 'Lookbook items retrieved successfully.');
    }
}
