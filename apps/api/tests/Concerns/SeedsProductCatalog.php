<?php

namespace Tests\Concerns;

use Database\Seeders\ProductCatalogSeeder;

trait SeedsProductCatalog
{
    protected function seedProductCatalog(): void
    {
        $this->seed(ProductCatalogSeeder::class);
    }
}
