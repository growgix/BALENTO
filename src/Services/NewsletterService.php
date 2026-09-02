<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SubscriberRepository;
use App\Validation\Validator;

/**
 * Newsletter Subscription Service.
 */
final class NewsletterService
{
    private SubscriberRepository $subscriberRepository;

    public function __construct(?SubscriberRepository $subscriberRepository = null)
    {
        $this->subscriberRepository = $subscriberRepository ?? new SubscriberRepository();
    }

    public function subscribe(string $email, string $source = 'footer'): array
    {
        $validator = Validator::make(['email' => $email])->required('email')->email('email');
        if ($validator->fails()) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Please provide a valid email address.',
                'errors' => $validator->getErrors(),
            ];
        }

        $cleanEmail = strtolower(trim($email));
        $this->subscriberRepository->subscribe($cleanEmail, $source);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => '✓ Welcome to the Balento Inner Circle. You will receive private previews and styling dispatches.',
        ];
    }
}
