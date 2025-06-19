<?php

namespace IPS\discord\Api;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use IPS\discord\Discord;
use IPS\discord\Exceptions\DiscordException;
use IPS\discord\Exceptions\RateLimitException;
use IPS\Log;
use IPS\Member;
use IPS\Settings;
use Throwable;

use function in_array;

class Api
{
    protected static string $baseUrl = 'https://discord.com/api/';

    protected ?string $botToken = null;

    protected Member $member;

    private bool $requestAsBot = false;

    private bool $requestAsMember = false;

    /**
     * @throws Throwable
     */
    public function request(string $url, string $method = 'GET', array $body = [], ?array $query = null, bool $alwaysThrow = false, bool $mergeMemberAccessToken = false): mixed
    {
        return $this->withRateLimitHandler(function () use ($url, $method, $body, $query, $alwaysThrow, $mergeMemberAccessToken) {
            $accessTokenType = match (true) {
                $this->requestAsBot => 'Bot',
                default => 'Bearer'
            };

            $accessToken = match (true) {
                $this->requestAsMember && filled($this->member) => $this->accessTokenForMember($this->member),
                $this->requestAsBot && filled($this->botToken) => $this->botToken,
                default => null
            };

            if (blank($accessToken)) {
                throw new DiscordException('No access token set for request');
            }

            if ($mergeMemberAccessToken && filled($this->member)) {
                $body = array_merge($body, [
                    'access_token' => $this->accessTokenForMember($this->member),
                ]);
            }

            Log::debug(json_encode([
                'member_id' => Member::loggedIn()->member_id ?? null,
                'endpoint' => $url,
                'method' => $method,
                'body' => $body,
                'query' => $query,
                'access_token_type' => $accessTokenType,
            ], JSON_PRETTY_PRINT), 'discord_api_request');

            $method = Str::upper($method);

            $factory = new Factory;

            $request = $factory
                ->baseUrl(Api::$baseUrl)
                ->timeout(10)
                ->withToken($accessToken, $accessTokenType)
                ->acceptJson()
                ->withUserAgent('DiscordForInvisionCommunity (1.0)')
                ->asJson();

            if (in_array($method, ['PUT', 'PATCH', 'POST'])) {
                $request->withBody(json_encode($body));
            }

            if ($method === 'GET' && filled($query)) {
                $request->withQueryParameters($query);
            }

            $response = $request->send($method, $url);

            $data = $response->json();

            Log::debug(json_encode([
                'status' => $response->status(),
                'headers' => $response->headers(),
                'data' => $data,
            ], JSON_PRETTY_PRINT), 'discord_api_response');

            if ($response->status() === 429) {
                throw RateLimitException::withData($data);
            }

            if (! $response->successful()) {
                $message = data_get($data, 'message') ?? 'There was an error while performing a request to the Discord API. Please try again.';
                $data = null;

                throw_if($response->serverError() || $alwaysThrow, new DiscordException($message));
            }

            if ($response->successful() && is_null($data)) {
                $data = true;
            }

            return $data;
        });
    }

    private function withRateLimitHandler(Closure $callback, int $maxRetries = 5): mixed
    {
        $retryCount = 0;
        while ($retryCount < $maxRetries) {
            try {
                return value($callback);
            } catch (RateLimitException $exception) {
                $retryCount++;

                $retryAfter = data_get($exception->getData(), 'retry_after', 10);

                Sleep::until(Carbon::now()->addMilliseconds($retryAfter));
            }
        }

        return null;
    }

    public function withMember(?Member $member = null): static
    {
        $this->member = $member ?? Member::loggedIn();

        return $this;
    }

    public function asMember(?Member $member = null): static
    {
        $this->requestAsMember = true;

        return $this;
    }

    public function asBot(?string $token = null): static
    {
        $this->requestAsBot = true;
        $this->botToken = $token ?? Settings::i()->discord_bot_token;

        return $this;
    }

    private function link(Member $member): ?array
    {
        $discord = new Discord;

        $handler = $discord->handler();

        if (! $handler || ! $handler->canProcess($member)) {
            return null;
        }

        return $handler->link($member);
    }

    /**
     * @throws DiscordException
     */
    private function accessTokenForMember(Member $member): string
    {
        $link = $this->link($member);

        if (! $link || ! isset($link['token_access_token'])) {
            throw new DiscordException('No access token. Please reauthenticate.');
        }

        return $link['token_access_token'];
    }
}
