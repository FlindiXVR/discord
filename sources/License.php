<?php

namespace IPS\discord;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use IPS\Data\Store;
use IPS\Patterns\Singleton;
use IPS\Settings;
use OutOfRangeException;

class License extends Singleton
{
    protected static string $baseUrl = 'https://api.lemonsqueezy.com';

    protected static string $cacheKey = 'discord_license_cache';

    /**
     * @throws ConnectionException
     */
    public function active(): bool
    {
        $licenseKey = Settings::i()->discord_license_key;
        $instanceId = Settings::i()->discord_license_instance_id;

        if (! $licenseKey || ! $instanceId) {
            return false;
        }

        $cache = null;
        try {
            $cache = Store::i()->{License::$cacheKey};
        } catch (OutOfRangeException $exception) {
        }

        if (blank($cache) || Carbon::parse(data_get($cache, 'last_fetched_at', Carbon::now()))->addDay()->isPast()) {
            $results = $this->validate($licenseKey, $instanceId);

            if ($results->successful()) {
                Store::i()->{License::$cacheKey} = $cache = [
                    'results' => $results->json(),
                    'last_fetched_at' => Carbon::now()->getTimestamp(),
                ];
            }
        }

        return TRUE;
    }

    /**
     * @throws ConnectionException
     */
    public function activate(string $licenseKey): PromiseInterface|Response
    {
        $boardName = Settings::i()->board_name;
        $boardUrl = Settings::i()->base_url;

        return $this
            ->factory()
            ->post('v1/licenses/activate', [
                'license_key' => $licenseKey,
                'instance_name' => "$boardName ($boardUrl)",
            ]);
    }

    /**
     * @throws ConnectionException
     */
    public function deactivate(string $licenseKey, string $instanceId): PromiseInterface|Response
    {
        return $this
            ->factory()
            ->post('v1/licenses/deactivate', [
                'license_key' => $licenseKey,
                'instance_id' => $instanceId,
            ]);
    }

    /**
     * @throws ConnectionException
     */
    public function validate(string $licenseKey, string $instanceId): PromiseInterface|Response
    {
        return $this
            ->factory()
            ->post('v1/licenses/validate', [
                'license_key' => $licenseKey,
                'instance_id' => $instanceId,
            ]);
    }

    private function factory()
    {
        $factory = new Factory;

        return $factory
            ->baseUrl(License::$baseUrl)
            ->acceptJson()
            ->asJson()
            ->withUserAgent('DiscordForInvisionCommunity');
    }
}
