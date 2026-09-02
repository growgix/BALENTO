<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Pincode Database Access Repository.
 */
final class PincodeRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findByPincode(string $pincode): ?array
    {
        $sql = "SELECT pincode, city, state, is_serviceable, cod_available, estimated_days, shipping_zone 
                FROM pincodes 
                WHERE pincode = :pincode 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':pincode', $pincode);
        $stmt->execute();

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'pincode' => $row['pincode'],
            'city' => $row['city'],
            'state' => $row['state'],
            'is_serviceable' => (bool) $row['is_serviceable'],
            'cod_available' => (bool) $row['cod_available'],
            'estimated_days' => (int) $row['estimated_days'],
            'shipping_zone' => $row['shipping_zone'],
        ];
    }
}
