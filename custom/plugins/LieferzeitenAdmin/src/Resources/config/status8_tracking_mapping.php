<?php declare(strict_types=1);

return [
    'version' => 1,
    'global' => [
        'paketshop_non_retire' => false,
        'paketshop_not_collected' => false,
        'paketshop_retire' => true,
        'paketshop_collected' => true,
        'retoure' => false,
        'refus' => false,
        'verweigert' => false,
        'douane' => false,
        'zoll_abgelehnt' => false,
        'nicht_zustellbar' => false,
        'ablageort' => true,
        'zugestellt' => true,
        'delivered' => true,
        'completed' => true,
        '8' => true,
    ],
    'carriers' => [
        'dhl' => [
            'paketshop_non_retire' => false,
            'paketshop_retire' => true,
            'retoure' => false,
            'verweigert' => false,
            'zoll_abgelehnt' => false,
            'zugestellt' => true,
        ],
        'gls' => [
            'paketshop_not_collected' => false,
            'parcelshop_not_collected' => false,
            'retoure' => false,
            'refus' => false,
            'douane' => false,
            'delivered' => true,
        ],
    ],
];
