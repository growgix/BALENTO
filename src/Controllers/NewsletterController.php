<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\NewsletterService;

/**
 * Controller handling newsletter subscriptions.
 */
final class NewsletterController
{
    private NewsletterService $newsletterService;

    public function __construct(?NewsletterService $newsletterService = null)
    {
        $this->newsletterService = $newsletterService ?? new NewsletterService();
    }

    /**
     * POST /api/newsletter/subscribe
     */
    public function subscribe(Request $request): Response
    {
        $email = (string) $request->body('email', '');
        $source = (string) $request->body('source', 'footer');

        $result = $this->newsletterService->subscribe($email, $source);

        if (!$result['success']) {
            return Response::error(
                $result['message'],
                $result['errors'] ?? [],
                $result['status_code'] ?? 422
            );
        }

        return Response::success([], $result['message']);
    }
}
