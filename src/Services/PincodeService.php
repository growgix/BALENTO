<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PincodeRepository;
use App\Validation\Validator;

/**
 * Pincode Serviceability and Delivery Estimation Service.
 */
final class PincodeService
{
    private PincodeRepository $pincodeRepository;

    public function __construct(?PincodeRepository $pincodeRepository = null)
    {
        $this->pincodeRepository = $pincodeRepository ?? new PincodeRepository();
    }

    public function checkServiceability(string $pincode): array
    {
        $validator = Validator::make(['pincode' => $pincode])->required('pincode')->pincode('pincode');
        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->getErrors(),
            ];
        }

        $cleanPin = trim($pincode);
        $record = $this->pincodeRepository->findByPincode($cleanPin);

        if ($record) {
            return [
                'valid' => true,
                'serviceable' => $record['is_serviceable'],
                'pincode' => $cleanPin,
                'city' => $record['city'],
                'state' => $record['state'],
                'cod_available' => $record['cod_available'],
                'estimated_days' => $record['estimated_days'],
                'shipping_zone' => $record['shipping_zone'],
                'message' => "✓ Express Delivery: {$record['estimated_days']} business days to {$record['city']} ({$cleanPin}) • COD Available.",
            ];
        }

        // Standard National Delivery estimate for valid Indian PINs not in priority seed list
        return [
            'valid' => true,
            'serviceable' => true,
            'pincode' => $cleanPin,
            'city' => 'India',
            'state' => 'Standard Zone',
            'cod_available' => true,
            'estimated_days' => 4,
            'shipping_zone' => 'National',
            'message' => "✓ Standard Insured Delivery: 4-5 business days to PIN {$cleanPin} • COD & 7-Day Returns Available.",
        ];
    }
}
