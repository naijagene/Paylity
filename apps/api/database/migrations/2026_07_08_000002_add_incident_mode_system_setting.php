<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SystemSetting;
use App\Support\Platform\SystemSettingKeys;

return new class extends Migration
{
    public function up(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => SystemSettingKeys::INCIDENT_MODE],
            [
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Pause checkout and show an incident banner during platform incidents.',
            ],
        );
    }

    public function down(): void
    {
        SystemSetting::query()->where('key', SystemSettingKeys::INCIDENT_MODE)->delete();
    }
};
