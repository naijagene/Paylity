<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\ProviderService;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['key' => 'airtime', 'name' => 'Airtime'],
            ['key' => 'data', 'name' => 'Data'],
            ['key' => 'electricity', 'name' => 'Electricity'],
        ];

        foreach ($categories as $category) {
            ProductCategory::query()->updateOrCreate(
                ['key' => $category['key']],
                ['name' => $category['name'], 'is_active' => true],
            );
        }

        $airtimeServices = [
            ['service_name' => 'mtn', 'service_id' => 'mtn', 'display_name' => 'MTN'],
            ['service_name' => 'airtel', 'service_id' => 'airtel', 'display_name' => 'Airtel'],
            ['service_name' => 'glo', 'service_id' => 'glo', 'display_name' => 'Glo'],
            ['service_name' => '9mobile', 'service_id' => 'etisalat', 'display_name' => '9mobile'],
        ];

        foreach ($airtimeServices as $service) {
            $this->upsertService('airtime', $service);
        }

        $dataServices = [
            ['service_name' => 'mtn', 'service_id' => 'mtn-data', 'display_name' => 'MTN'],
            ['service_name' => 'airtel', 'service_id' => 'airtel-data', 'display_name' => 'Airtel'],
            ['service_name' => 'glo', 'service_id' => 'glo-data', 'display_name' => 'Glo'],
            ['service_name' => '9mobile', 'service_id' => 'etisalat-data', 'display_name' => '9mobile'],
        ];

        foreach ($dataServices as $service) {
            $this->upsertService('data', $service);
        }

        $electricityServices = [
            ['service_name' => 'ikedc', 'service_id' => 'ikeja-electric', 'display_name' => 'Ikeja Electric (IKEDC)'],
            ['service_name' => 'ekedc', 'service_id' => 'eko-electric', 'display_name' => 'Eko Electric (EKEDC)'],
            ['service_name' => 'aedc', 'service_id' => 'abuja-electric', 'display_name' => 'Abuja Electric (AEDC)'],
            ['service_name' => 'phed', 'service_id' => 'portharcourt-electric', 'display_name' => 'Port Harcourt Electric (PHED)'],
            ['service_name' => 'ibedc', 'service_id' => 'ibadan-electric', 'display_name' => 'Ibadan Electric (IBEDC)'],
            ['service_name' => 'kedco', 'service_id' => 'kano-electric', 'display_name' => 'Kano Electric (KEDCO)'],
        ];

        foreach ($electricityServices as $service) {
            $this->upsertService('electricity', $service);
        }
    }

    /**
     * @param  array{service_name: string, service_id: string, display_name: string}  $service
     */
    private function upsertService(string $categoryKey, array $service): void
    {
        ProviderService::query()->updateOrCreate(
            [
                'provider' => 'vtpass',
                'category_key' => $categoryKey,
                'service_name' => $service['service_name'],
            ],
            [
                'service_id' => $service['service_id'],
                'display_name' => $service['display_name'],
                'is_active' => true,
            ],
        );
    }
}
