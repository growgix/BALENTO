<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\PincodeService;

/**
 * Controller handling pincode delivery and serviceability estimation.
 */
final class PincodeController
{
    private PincodeService $pincodeService;

    public function __construct(?PincodeService $pincodeService = null)
    {
        $this->pincodeService = $pincodeService ?? new PincodeService();
    }

    /**
     * POST /api/pincode/check
     */
    public function check(Request $request): Response
    {
        $pincode = (string) $request->body('pincode', '');
        $result = $this->pincodeService->checkServiceability($pincode);

        if (!$result['valid']) {
            return Response::unprocessable($result['errors'], 'Invalid PIN code provided.');
        }

        return Response::success($result, 'Serviceability retrieved successfully.');
    }
}
