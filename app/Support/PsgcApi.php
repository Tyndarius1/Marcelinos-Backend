<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Philippine Standard Geographic Code (PSGC) HTTP API — same source as the frontend
 * {@see https://psgc.gitlab.io/api/ }.
 */
final class PsgcApi
{
    public const BASE_URL = 'https://psgc.gitlab.io/api';

    /** National Capital Region — provinces list is N/A; cities load from the region. */
    public const NCR_REGION_CODE = '130000000';

    public static function isNcr(?string $regionCode): bool
    {
        return $regionCode === self::NCR_REGION_CODE;
    }

    /**
     * @return array<string, string> code => name
     */
    public static function regionOptions(): array
    {
        return Cache::remember('psgc.regions.v1', 86_400, function (): array {
            $response = Http::timeout(25)->acceptJson()->get(self::BASE_URL.'/regions/');
            if (! $response->successful()) {
                Log::warning('PSGC regions fetch failed', ['status' => $response->status()]);

                return [];
            }

            return self::pairsSortedByName($response->json() ?? []);
        });
    }

    /**
     * @return array<string, string> code => name
     */
    public static function provinceOptions(string $regionCode): array
    {
        if ($regionCode === '') {
            return [];
        }

        $key = 'psgc.provinces.'.md5($regionCode);

        return Cache::remember($key, 86_400, function () use ($regionCode): array {
            $response = Http::timeout(25)->acceptJson()->get(self::BASE_URL."/regions/{$regionCode}/provinces");
            if (! $response->successful()) {
                Log::warning('PSGC provinces fetch failed', ['region' => $regionCode, 'status' => $response->status()]);

                return [];
            }

            return self::pairsSortedByName($response->json() ?? []);
        });
    }

    /**
     * @return array<string, string> code => name
     */
    public static function municipalityOptions(?string $regionCode, ?string $provinceCode): array
    {
        if (! $regionCode) {
            return [];
        }

        if (self::isNcr($regionCode)) {
            $key = 'psgc.cities_muni.region.'.md5($regionCode);

            return Cache::remember($key, 86_400, function () use ($regionCode): array {
                $response = Http::timeout(25)->acceptJson()->get(self::BASE_URL."/regions/{$regionCode}/cities-municipalities");
                if (! $response->successful()) {
                    Log::warning('PSGC cities-municipalities (region) failed', ['region' => $regionCode]);

                    return [];
                }

                return self::pairsSortedByName($response->json() ?? []);
            });
        }

        if (! $provinceCode) {
            return [];
        }

        $key = 'psgc.cities_muni.province.'.md5($provinceCode);

        return Cache::remember($key, 86_400, function () use ($provinceCode): array {
            $response = Http::timeout(25)->acceptJson()->get(self::BASE_URL."/provinces/{$provinceCode}/cities-municipalities");
            if (! $response->successful()) {
                Log::warning('PSGC cities-municipalities (province) failed', ['province' => $provinceCode]);

                return [];
            }

            return self::pairsSortedByName($response->json() ?? []);
        });
    }

    /**
     * @return array<string, string> code => name
     */
    public static function barangayOptions(string $municipalityCode): array
    {
        if ($municipalityCode === '') {
            return [];
        }

        $key = 'psgc.barangays.'.md5($municipalityCode);

        return Cache::remember($key, 86_400, function () use ($municipalityCode): array {
            $response = Http::timeout(25)->acceptJson()->get(self::BASE_URL."/cities-municipalities/{$municipalityCode}/barangays");
            if (! $response->successful()) {
                Log::warning('PSGC barangays fetch failed', ['city_municipality' => $municipalityCode]);

                return [];
            }

            return self::pairsSortedByName($response->json() ?? []);
        });
    }

    public static function regionLabel(string $code): ?string
    {
        return self::entityName("/regions/{$code}");
    }

    public static function provinceLabel(string $code): ?string
    {
        return self::entityName("/provinces/{$code}");
    }

    public static function municipalityLabel(string $code): ?string
    {
        return self::entityName("/cities-municipalities/{$code}");
    }

    public static function barangayLabel(string $code): ?string
    {
        return self::entityName("/barangays/{$code}");
    }

    private static function entityName(string $path): ?string
    {
        $key = 'psgc.entity.'.md5($path);

        return Cache::remember($key, 86_400, function () use ($path): ?string {
            $response = Http::timeout(20)->acceptJson()->get(self::BASE_URL.$path);
            if (! $response->successful()) {
                return null;
            }
            $decoded = $response->json();

            return isset($decoded['name']) ? (string) $decoded['name'] : null;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, string>
     */
    private static function pairsSortedByName(array $items): array
    {
        if ($items === []) {
            return [];
        }

        usort($items, fn (array $a, array $b) => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        $out = [];
        foreach ($items as $row) {
            if (! empty($row['code'])) {
                $out[(string) $row['code']] = (string) ($row['name'] ?? '');
            }
        }

        return $out;
    }
}
