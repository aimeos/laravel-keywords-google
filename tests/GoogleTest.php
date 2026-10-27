<?php

namespace Tests;

use Aimeos\KeywordsBridge\Drivers\Google;
use Aimeos\KeywordsBridge\Facades\Keywords;
use Aimeos\KeywordsBridge\Keyword;
use Aimeos\KeywordsBridge\ServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Orchestra\Testbench\TestCase;


class GoogleTest extends TestCase
{
    private const URL = 'https://googleads.googleapis.com/v26/customers/1234567890';


    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
    }


    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }


    protected function defineEnvironment($app)
    {
        $app['config']->set('keywords-bridge.default', 'google');
        $app['config']->set('keywords-bridge.drivers.google', [
            'customer' => '123-456-7890',
            'login' => '111-222-3333',
            'token' => 'devtoken',
            'client' => 'clientid',
            'secret' => 'clientsecret',
            'refresh' => 'refreshtoken',
        ]);
    }


    public function testVolume(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'access', 'expires_in' => 3599]),
            self::URL . ':generateKeywordHistoricalMetrics' => Http::response(['results' => [
                ['text' => 'car', 'closeVariants' => ['cars'], 'keywordMetrics' => ['avgMonthlySearches' => '201000']],
                ['text' => 'laravel cms', 'keywordMetrics' => ['avgMonthlySearches' => '1900']],
                ['text' => 'rare keyword', 'keywordMetrics' => []],
            ]]),
        ]);

        $result = Keywords::volume(['Laravel  CMS', 'cars', 'car', 'rare keyword', 'unknown', ' ', 'laravel cms']);

        $this->assertEquals([
            new Keyword('laravel cms', 1900),
            new Keyword('cars', 201000),
            new Keyword('car', 201000),
            new Keyword('rare keyword', null),
            new Keyword('unknown', null),
        ], $result);

        Http::assertSent(fn(Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'refreshtoken');

        Http::assertSent(fn(Request $request) => str_ends_with($request->url(), ':generateKeywordHistoricalMetrics')
            && $request->hasHeader('Authorization', 'Bearer access')
            && $request->hasHeader('developer-token', 'devtoken')
            && $request->hasHeader('login-customer-id', '1112223333')
            && $request['keywords'] === ['laravel cms', 'cars', 'car', 'rare keyword', 'unknown']
            && $request['keywordPlanNetwork'] === 'GOOGLE_SEARCH'
            && !isset($request['geoTargetConstants']) && !isset($request['language']));
    }


    public function testVolumeCountry(): void
    {
        $this->fakeToken();
        Http::fake([self::URL . ':*' => Http::response(['results' => []])]);

        Keywords::volume(['laravel cms'], 'DE');

        Http::assertSent(fn(Request $request) => str_ends_with($request->url(), ':generateKeywordHistoricalMetrics')
            && $request['geoTargetConstants'] === ['geoTargetConstants/2276']);
    }


    public function testVolumeCountryConfig(): void
    {
        $this->fakeToken();
        Http::fake([self::URL . ':*' => Http::response(['results' => []])]);

        Keywords::driver('google', ['geo' => ['xk' => 9999]])->volume(['laravel cms'], 'xk');

        Http::assertSent(fn(Request $request) => str_ends_with($request->url(), ':generateKeywordHistoricalMetrics')
            && $request['geoTargetConstants'] === ['geoTargetConstants/9999']);
    }


    public function testVolumeCountryUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Keywords::volume(['laravel cms'], 'zz');
    }


    public function testVolumeInvalidKeywords(): void
    {
        $this->fakeToken();
        Http::fake([self::URL . ':*' => Http::response(['results' => []])]);

        $long = str_repeat('a', 81);
        $result = Keywords::volume([$long, 'a b c d e f g h i j k', 'laravel cms']);

        $this->assertEquals([new Keyword($long), new Keyword('a b c d e f g h i j k'), new Keyword('laravel cms')], $result);
        Http::assertSent(fn(Request $request) => str_ends_with($request->url(), ':generateKeywordHistoricalMetrics')
            && $request['keywords'] === ['laravel cms']);
    }


    public function testVolumeNoValidKeywords(): void
    {
        Http::fake();

        $this->assertEquals([new Keyword(str_repeat('a', 81))], Keywords::volume([str_repeat('a', 81), '']));
        Http::assertNothingSent();
    }


    public function testRelated(): void
    {
        $this->fakeToken();
        Http::fake([self::URL . ':generateKeywordIdeas' => Http::sequence()
            ->push(['results' => [
                ['text' => 'laravel cms', 'keywordIdeaMetrics' => ['avgMonthlySearches' => '1900']],
                ['text' => 'Laravel Headless CMS', 'keywordIdeaMetrics' => ['avgMonthlySearches' => '320']],
            ], 'nextPageToken' => 'next'])
            ->push(['results' => [
                ['text' => 'laravel admin panel', 'keywordIdeaMetrics' => ['avgMonthlySearches' => '2400']],
                ['text' => 'laravel blog', 'keywordIdeaMetrics' => []],
            ], 'nextPageToken' => 'last'])
        ]);

        $result = Keywords::related(' Laravel CMS ', 'us', 3);

        $this->assertEquals([
            new Keyword('laravel cms', 1900),
            new Keyword('laravel headless cms', 320),
            new Keyword('laravel admin panel', 2400),
        ], $result);

        Http::assertSentCount(3);
        Http::assertSent(fn(Request $request) => str_ends_with($request->url(), ':generateKeywordIdeas')
            && $request['keywordSeed'] === ['keywords' => ['laravel cms']]
            && $request['geoTargetConstants'] === ['geoTargetConstants/2840']
            && $request['pageSize'] === 3 && !isset($request['pageToken']));
        Http::assertSent(fn(Request $request) => str_ends_with($request->url(), ':generateKeywordIdeas')
            && ($request['pageToken'] ?? null) === 'next' && $request['pageSize'] === 3);
        Sleep::assertSleptTimes(1);
    }


    public function testRelatedLastPage(): void
    {
        $this->fakeToken();
        Http::fake([self::URL . ':generateKeywordIdeas' => Http::response(['results' => [
            ['text' => 'laravel cms', 'keywordIdeaMetrics' => ['avgMonthlySearches' => '1900']],
        ]])]);

        $this->assertEquals([new Keyword('laravel cms', 1900)], Keywords::related('laravel cms'));
        Http::assertSentCount(2);
        Http::assertSent(fn(Request $request) => str_ends_with($request->url(), ':generateKeywordIdeas')
            && !isset($request['geoTargetConstants']));
    }


    public function testRelatedEmpty(): void
    {
        Http::fake();

        $this->assertSame([], Keywords::related('  '));
        Http::assertNothingSent();
    }


    public function testTokenCached(): void
    {
        $this->fakeToken();
        Http::fake([self::URL . ':*' => Http::response(['results' => []])]);

        Keywords::volume(['laravel cms']);
        Keywords::volume(['headless cms']);

        Http::assertSentCount(3);
    }


    public function testTokenRenewed(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::sequence()
                ->push(['access_token' => 'old', 'expires_in' => 3599])
                ->push(['access_token' => 'new', 'expires_in' => 3599]),
            self::URL . ':*' => Http::sequence()
                ->push(['error' => ['message' => 'Unauthenticated']], 401)
                ->push(['results' => [['text' => 'laravel cms', 'keywordMetrics' => ['avgMonthlySearches' => '1900']]]]),
        ]);

        $this->assertEquals([new Keyword('laravel cms', 1900)], Keywords::volume(['laravel cms']));
        Http::assertSent(fn(Request $request) => str_contains($request->url(), 'googleads') && $request->hasHeader('Authorization', 'Bearer new'));
    }


    public function testTokenFailed(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP status 400');
        Keywords::volume(['laravel cms']);
    }


    public function testRequestFailed(): void
    {
        $this->fakeToken();
        Http::fake([self::URL . ':*' => Http::response(['error' => ['message' => 'The developer token is not approved.']], 403)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP status 403: The developer token is not approved.');
        Keywords::volume(['laravel cms']);
    }


    public function testRequestNotRetried(): void
    {
        $this->fakeToken();
        Http::fake([self::URL . ':*' => Http::response(['error' => ['message' => 'Resource has been exhausted']], 429)]);

        try {
            Keywords::volume(['laravel cms']);
            $this->fail('Exception expected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP status 429: Resource has been exhausted', $e->getMessage());
        }

        Http::assertSentCount(2); // token and API request
    }


    public function testConnectionError(): void
    {
        $this->fakeToken();
        Http::fake([self::URL . ':*' => fn() => throw new ConnectionException('Timeout')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google Ads API request failed: Timeout');
        Keywords::volume(['laravel cms']);
    }


    public function testRequestWait(): void
    {
        $this->fakeToken();
        Http::fake([self::URL . ':*' => Http::response(['results' => []])]);

        Keywords::volume(['laravel cms']);
        Sleep::assertNeverSlept();

        Keywords::related('laravel cms');
        Sleep::assertSleptTimes(1);
    }


    public function testConfigMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('[refresh]');
        new Google(['customer' => '1', 'token' => 'a', 'client' => 'b', 'secret' => 'c']);
    }


    public function testConfigVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Google(['customer' => '1', 'token' => 'a', 'client' => 'b', 'secret' => 'c', 'refresh' => 'd', 'version' => 'v1/../x']);
    }


    private function fakeToken(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'access', 'expires_in' => 3599])]);
    }
}
