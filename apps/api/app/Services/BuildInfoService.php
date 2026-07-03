<?php

namespace App\Services;

class BuildInfoService
{
    /**
     * @return array{
     *     application: string,
     *     version: string,
     *     build: string,
     *     environment: string
     * }
     */
    public function all(): array
    {
        $appName = (string) config('app.name', 'PAYLITY NG');

        return [
            'application' => str_ends_with($appName, ' API') ? $appName : $appName.' API',
            'version' => (string) config('app.version', '1.0.0-rc1'),
            'build' => (string) config('app.build', '2026.07.03-rc1'),
            'environment' => strtolower((string) config('app.env', 'local')),
        ];
    }
}
