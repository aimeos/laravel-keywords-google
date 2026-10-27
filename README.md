# Laravel Keywords Google

Google Ads Keyword Planner driver for the
[Laravel Keywords Bridge](https://github.com/aimeos/laravel-keywords-bridge).
It uses the REST API of the Google Ads `KeywordPlanIdeaService` and needs no
Google SDK.

## Installation

```bash
composer require aimeos/laravel-keywords-google
```

## Configuration

Add these settings to your `.env` file and set `KEYWORDS_DRIVER=google`:

```
GOOGLEADS_CUSTOMER_ID=123-456-7890
GOOGLEADS_LOGIN_CUSTOMER_ID=
GOOGLEADS_DEVELOPER_TOKEN=...
GOOGLEADS_CLIENT_ID=...
GOOGLEADS_CLIENT_SECRET=...
GOOGLEADS_REFRESH_TOKEN=...
```

* `GOOGLEADS_CUSTOMER_ID`: ID of the Google Ads account used for the requests
* `GOOGLEADS_LOGIN_CUSTOMER_ID`: ID of the manager account if the account above is accessed through it
* `GOOGLEADS_DEVELOPER_TOKEN`: Developer token from the API Center of your manager account (at least basic access)
* `GOOGLEADS_CLIENT_ID`/`GOOGLEADS_CLIENT_SECRET`: OAuth client of your Google Cloud project with the Google Ads API enabled
* `GOOGLEADS_REFRESH_TOKEN`: OAuth refresh token of a user with access to the account for the `https://www.googleapis.com/auth/adwords` scope

The access token is fetched using the refresh token and cached in the Laravel
cache until shortly before it expires.

Optional settings in the `google` section of `./config/keywords-bridge.php`:

* `version`: Google Ads API version (default: `v26`)
* `timeout`: Request timeout in seconds (default: `30`)
* `geo`: Additional or different geo target constant IDs by country code, e.g. `['xk' => 9999]`

## Usage

```php
use Aimeos\KeywordsBridge\Facades\Keywords;

// worldwide search volume
$result = Keywords::volume(['laravel cms', 'headless cms']);

// related keywords in Germany
$result = Keywords::related('laravel cms', 'de', 100);
```

* Without a country, all locations are used. Data is always for all languages.
* Google merges close variants like "car" and "cars" and returns the same volume for both.
* Keywords with more than 80 characters or 10 words are rejected by Google and returned with a `null` volume.
* The volume is the average of the last 12 months as estimated by Google Ads.
* Google allows one keyword planning request per second, so the driver waits between consecutive requests.

## License

This package is released under the LGPL-2.1+ License.
