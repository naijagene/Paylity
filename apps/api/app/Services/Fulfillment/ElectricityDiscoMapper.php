<?php

namespace App\Services\Fulfillment;

use App\Exceptions\FulfillmentException;

class ElectricityDiscoMapper
{
    public const DISCO_SERVICE_IDS = [
        'aedc' => 'abuja-electric',
        'ekedc' => 'ekedc',
        'ikedc' => 'ikeja-electric',
        'phed' => 'phed',
        'ibedc' => 'ibedc',
        'kedco' => 'kedco',
    ];

    /** @var array<string, string> */
    private const DISCO_ALIASES = [
        'aedc' => 'aedc',
        'abuja-electric' => 'aedc',
        'ekedc' => 'ekedc',
        'eko-electric' => 'ekedc',
        'ikedc' => 'ikedc',
        'ikeja-electric' => 'ikedc',
        'phed' => 'phed',
        'ibedc' => 'ibedc',
        'ibadan-electric' => 'ibedc',
        'kedco' => 'kedco',
        'kano-electric' => 'kedco',
    ];

    public function normalizeDisco(string $disco): string
    {
        $normalized = strtolower(trim(str_replace(['_', ' '], '-', $disco)));

        return self::DISCO_ALIASES[$normalized] ?? $normalized;
    }

    /**
     * @return list<string>
     */
    public function supportedDiscos(): array
    {
        return array_keys(self::DISCO_SERVICE_IDS);
    }

    public function resolveServiceId(string $disco): string
    {
        return self::DISCO_SERVICE_IDS[$this->resolveDiscoKey($disco)];
    }

    public function resolveDiscoKey(string $disco): string
    {
        $discoKey = $this->normalizeDisco($disco);

        if (! isset(self::DISCO_SERVICE_IDS[$discoKey])) {
            throw new FulfillmentException(
                'Unsupported electricity disco for VTPass fulfillment.',
                'UNSUPPORTED_DISCO',
            );
        }

        return $discoKey;
    }
}
