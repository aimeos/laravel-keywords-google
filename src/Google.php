<?php

namespace Aimeos\KeywordsBridge\Drivers;

use Aimeos\KeywordsBridge\Contracts\Driver;
use Aimeos\KeywordsBridge\Keyword;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;


/**
 * Google Ads Keyword Planner driver using the REST API
 */
class Google implements Driver
{
    /**
     * Geo target constant IDs of countries and regions by ISO country code
     */
    private const GEO = [
        'ad' => 2020, 'ae' => 2784, 'af' => 2004, 'ag' => 2028, 'ai' => 2660, 'al' => 2008, 'am' => 2051, 'ao' => 2024,
        'aq' => 2010, 'ar' => 2032, 'as' => 2016, 'at' => 2040, 'au' => 2036, 'aw' => 2533, 'az' => 2031, 'ba' => 2070,
        'bb' => 2052, 'bd' => 2050, 'be' => 2056, 'bf' => 2854, 'bg' => 2100, 'bh' => 2048, 'bi' => 2108, 'bj' => 2204,
        'bl' => 2652, 'bm' => 2060, 'bn' => 2096, 'bo' => 2068, 'bq' => 2535, 'br' => 2076, 'bs' => 2044, 'bt' => 2064,
        'bv' => 2074, 'bw' => 2072, 'by' => 2112, 'bz' => 2084, 'ca' => 2124, 'cc' => 2166, 'cd' => 2180, 'cf' => 2140,
        'cg' => 2178, 'ch' => 2756, 'ci' => 2384, 'ck' => 2184, 'cl' => 2152, 'cm' => 2120, 'cn' => 2156, 'co' => 2170,
        'cr' => 2188, 'cv' => 2132, 'cw' => 2531, 'cx' => 2162, 'cy' => 2196, 'cz' => 2203, 'de' => 2276, 'dj' => 2262,
        'dk' => 2208, 'dm' => 2212, 'do' => 2214, 'dz' => 2012, 'ec' => 2218, 'ee' => 2233, 'eg' => 2818, 'eh' => 2732,
        'er' => 2232, 'es' => 2724, 'et' => 2231, 'fi' => 2246, 'fj' => 2242, 'fk' => 2238, 'fm' => 2583, 'fo' => 2234,
        'fr' => 2250, 'ga' => 2266, 'gb' => 2826, 'gd' => 2308, 'ge' => 2268, 'gf' => 2254, 'gg' => 2831, 'gh' => 2288,
        'gi' => 2292, 'gl' => 2304, 'gm' => 2270, 'gn' => 2324, 'gp' => 2312, 'gq' => 2226, 'gr' => 2300, 'gs' => 2239,
        'gt' => 2320, 'gu' => 2316, 'gw' => 2624, 'gy' => 2328, 'hk' => 2344, 'hm' => 2334, 'hn' => 2340, 'hr' => 2191,
        'ht' => 2332, 'hu' => 2348, 'id' => 2360, 'ie' => 2372, 'il' => 2376, 'im' => 2833, 'in' => 2356, 'io' => 2086,
        'iq' => 2368, 'is' => 2352, 'it' => 2380, 'je' => 2832, 'jm' => 2388, 'jo' => 2400, 'jp' => 2392, 'ke' => 2404,
        'kg' => 2417, 'kh' => 2116, 'ki' => 2296, 'km' => 2174, 'kn' => 2659, 'kr' => 2410, 'kw' => 2414, 'ky' => 2136,
        'kz' => 2398, 'la' => 2418, 'lb' => 2422, 'lc' => 2662, 'li' => 2438, 'lk' => 2144, 'lr' => 2430, 'ls' => 2426,
        'lt' => 2440, 'lu' => 2442, 'lv' => 2428, 'ly' => 2434, 'ma' => 2504, 'mc' => 2492, 'md' => 2498, 'me' => 2499,
        'mf' => 2663, 'mg' => 2450, 'mh' => 2584, 'mk' => 2807, 'ml' => 2466, 'mm' => 2104, 'mn' => 2496, 'mo' => 2446,
        'mp' => 2580, 'mq' => 2474, 'mr' => 2478, 'ms' => 2500, 'mt' => 2470, 'mu' => 2480, 'mv' => 2462, 'mw' => 2454,
        'mx' => 2484, 'my' => 2458, 'mz' => 2508, 'na' => 2516, 'nc' => 2540, 'ne' => 2562, 'nf' => 2574, 'ng' => 2566,
        'ni' => 2558, 'nl' => 2528, 'no' => 2578, 'np' => 2524, 'nr' => 2520, 'nu' => 2570, 'nz' => 2554, 'om' => 2512,
        'pa' => 2591, 'pe' => 2604, 'pf' => 2258, 'pg' => 2598, 'ph' => 2608, 'pk' => 2586, 'pl' => 2616, 'pm' => 2666,
        'pn' => 2612, 'pr' => 2630, 'ps' => 2275, 'pt' => 2620, 'pw' => 2585, 'py' => 2600, 'qa' => 2634, 're' => 2638,
        'ro' => 2642, 'rs' => 2688, 'ru' => 2643, 'rw' => 2646, 'sa' => 2682, 'sb' => 2090, 'sc' => 2690, 'sd' => 2736,
        'se' => 2752, 'sg' => 2702, 'sh' => 2654, 'si' => 2705, 'sj' => 2744, 'sk' => 2703, 'sl' => 2694, 'sm' => 2674,
        'sn' => 2686, 'so' => 2706, 'sr' => 2740, 'ss' => 2728, 'st' => 2678, 'sv' => 2222, 'sx' => 2534, 'sy' => 2760,
        'sz' => 2748, 'tc' => 2796, 'td' => 2148, 'tf' => 2260, 'tg' => 2768, 'th' => 2764, 'tj' => 2762, 'tk' => 2772,
        'tl' => 2626, 'tm' => 2795, 'tn' => 2788, 'to' => 2776, 'tr' => 2792, 'tt' => 2780, 'tv' => 2798, 'tw' => 2158,
        'tz' => 2834, 'ua' => 2804, 'ug' => 2800, 'um' => 2581, 'us' => 2840, 'uy' => 2858, 'uz' => 2860, 'va' => 2336,
        'vc' => 2670, 've' => 2862, 'vg' => 2092, 'vi' => 2850, 'vn' => 2704, 'vu' => 2548, 'wf' => 2876, 'ws' => 2882,
        'xk' => 2900, 'ye' => 2887, 'yt' => 2175, 'za' => 2710, 'zm' => 2894, 'zw' => 2716,
    ];

    /** @var array<string, mixed> */
    private array $config;

    private float $last = 0;


    /**
     * @param array<string, mixed> $config Settings from ./config/keywords-bridge.php
     */
    public function __construct(array $config = [])
    {
        foreach (['customer', 'token', 'client', 'secret', 'refresh'] as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException("Google Ads setting [$key] is missing");
            }
        }

        if (!preg_match('/^v[0-9]+$/', $config['version'] ??= 'v26')) {
            throw new \InvalidArgumentException("Invalid Google Ads API version [{$config['version']}]");
        }

        $this->config = $config;
    }


    public function volume(array $keywords, ?string $country = null): array
    {
        $keywords = array_values(array_unique(array_filter(array_map(fn($keyword) => $this->normalize($keyword), $keywords), fn($keyword) => $keyword !== '')));
        // Google rejects keywords with more than 80 characters or 10 words
        $valid = array_filter($keywords, fn($keyword) => mb_strlen($keyword) <= 80 && count(explode(' ', $keyword)) <= 10);
        $volumes = [];

        foreach (array_chunk($valid, 10000) as $chunk) {
            $data = $this->request('generateKeywordHistoricalMetrics', ['keywords' => $chunk] + $this->target($country));

            foreach ($data['results'] ?? [] as $result) {
                $volume = $this->value($result['keywordMetrics'] ?? []);

                // close variants like "car" and "cars" are merged into one result
                foreach ([$result['text'] ?? '', ...($result['closeVariants'] ?? [])] as $text) {
                    $volumes[$this->normalize($text)] ??= $volume;
                }
            }
        }

        return array_map(fn($keyword) => new Keyword($keyword, $volumes[$keyword] ?? null), $keywords);
    }


    public function related(string $keyword, ?string $country = null, int $limit = 50): array
    {
        if (($keyword = $this->normalize($keyword)) === '') {
            return [];
        }

        $limit = max(1, min($limit, 10000));
        $body = ['keywordSeed' => ['keywords' => [$keyword]], 'pageSize' => $limit] + $this->target($country);
        $list = [];

        do {
            $data = $this->request('generateKeywordIdeas', $body);

            foreach ($data['results'] ?? [] as $result) {
                $list[] = new Keyword($this->normalize($result['text'] ?? ''), $this->value($result['keywordIdeaMetrics'] ?? []));
            }
        } while (count($list) < $limit && !empty($data['results']) && ($body['pageToken'] = $data['nextPageToken'] ?? null));

        return array_slice($list, 0, $limit);
    }


    /**
     * Returns the OAuth access token, cached until shortly before it expires
     *
     * @param bool $renew TRUE to fetch a new access token
     * @return string Access token
     */
    protected function access(bool $renew = false): string
    {
        $key = 'keywords-google:' . sha1($this->config['client'] . ':' . $this->config['refresh']);

        if (!$renew && is_string($token = Cache::get($key))) {
            return $token;
        }

        $response = Http::asForm()->acceptJson()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->config['client'],
            'client_secret' => $this->config['secret'],
            'refresh_token' => $this->config['refresh'],
        ]);

        if ($response->failed() || !is_string($token = $response->json('access_token'))) {
            throw new \RuntimeException('Fetching the Google OAuth access token failed with HTTP status ' . $response->status());
        }

        Cache::put($key, $token, max(60, (int) $response->json('expires_in', 3600) - 300));
        return $token;
    }


    /**
     * Returns the HTTP client for Google Ads API requests
     *
     * @param string $token OAuth access token
     * @return PendingRequest HTTP client
     */
    protected function client(string $token): PendingRequest
    {
        $headers = ['developer-token' => $this->config['token']];

        if (!empty($this->config['login'])) {
            $headers['login-customer-id'] = preg_replace('/[^0-9]/', '', (string) $this->config['login']);
        }

        return Http::withToken($token)->withHeaders($headers)->acceptJson()->timeout($this->config['timeout'] ?? 30);
    }


    /**
     * Normalizes the keyword
     *
     * @param string $keyword Keyword
     * @return string Trimmed keyword in lower case with single spaces
     */
    protected function normalize(string $keyword): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $keyword)));
    }


    /**
     * Sends the request to the Google Ads API
     *
     * @param string $method Name of the KeywordPlanIdeaService method
     * @param array<string, mixed> $body Request body
     * @return array<string, mixed> Decoded response
     */
    protected function request(string $method, array $body): array
    {
        $customer = preg_replace('/[^0-9]/', '', (string) $this->config['customer']);
        $url = "https://googleads.googleapis.com/{$this->config['version']}/customers/{$customer}:{$method}";

        // KeywordPlanIdeaService allows one request per second for each customer
        if (($wait = $this->last + 1 - microtime(true)) > 0) {
            Sleep::usleep((int) ($wait * 1000000));
        }

        try {
            $response = $this->client($this->access())->post($url, $body);

            if ($response->status() === 401) { // access token revoked or expired early
                $response = $this->client($this->access(true))->post($url, $body);
            }
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Google Ads API request failed: ' . $e->getMessage(), 0, $e);
        } finally {
            $this->last = microtime(true);
        }

        if ($response->failed()) {
            throw new \RuntimeException(sprintf('Google Ads API request failed with HTTP status %d: %s',
                $response->status(), $response->json('error.message', 'Unknown error')));
        }

        return (array) $response->json();
    }


    /**
     * Returns the targeting parameters for the country
     *
     * @param string|null $country Two letter ISO country code or NULL for all locations
     * @return array<string, mixed> Request parameters
     */
    protected function target(?string $country): array
    {
        $params = ['keywordPlanNetwork' => 'GOOGLE_SEARCH'];

        if ($country === null) {
            return $params;
        }

        $code = strtolower($country);
        $id = $this->config['geo'][$code] ?? self::GEO[$code] ?? throw new \InvalidArgumentException("Unknown country code [$country]");

        return $params + ['geoTargetConstants' => ['geoTargetConstants/' . $id]];
    }


    /**
     * Returns the average monthly searches
     *
     * @param array<string, mixed> $metrics Keyword metrics
     * @return int|null Average monthly searches or NULL if unknown
     */
    protected function value(array $metrics): ?int
    {
        return isset($metrics['avgMonthlySearches']) ? (int) $metrics['avgMonthlySearches'] : null;
    }
}
