=== IP Location Block ===
Contributors: darkog, ideologix
Tags: geo blocking, country block, state blocker, region blocker, ip blocker
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.4.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Block or allow WordPress visitors by country, US state or region, IP, CIDR, or ASN. Protect public pages, login, and wp-admin.

== Description ==

IP Location Block is a WordPress geo blocking plugin for blocking or allowing visitors by country, US state or equivalent region, IP address, CIDR range, or ASN.

Simple view covers common rules. Advanced view adds login, registration, comment, XML-RPC, bot, response, logging, privacy, provider, and diagnostic controls.

For the recommended setup, connect the [IP Location Block Cloud provider](https://iplocationblock.com/docs/providers/ip-location-block/?utm_source=plugin&utm_medium=wporgpage&utm_campaign=readme) for premium, frequently updated data, better country accuracy, ASN data, and state or region precision.

= WordPress geo blocking by country, state, or region =

Use a blocklist to deny selected locations or an allowlist to limit access. Regional rules use the administrative area returned for a country, such as a state, province, prefecture, territory, or region. This supports regional availability, service areas, store access, and site policies. Country rules remain active when precision is unavailable.

= Key features =

* Block or allow visitors by country and state or equivalent region.
* Add priority rules for IP addresses, IPv4 or IPv6 CIDR ranges, and ASNs.
* Protect public pages, login, registration, comments, XML-RPC, and selected wp-admin requests.
* Configure blocked responses and control failed logins or unwanted bots.
* Search IPs and review validation logs, statistics, and diagnostics.
* Use frequently updated data from the IP Location Block Cloud provider with country-level fallbacks.
* Encrypt stored IP addresses, with optional anonymization.

= IP Location Block Cloud provider =

* **Better accuracy:** Premium geolocation databases are updated frequently for more reliable country results.
* **Regional precision:** Add state, province, prefecture, territory, or equivalent regional rules.
* **Broader data:** Use IPv4, IPv6, and ASN information through one managed provider.

Legacy local and third-party providers remain supported for existing installations and country-level fallback rules. Regional precision requires the IP Location Block Cloud provider.

= External services and privacy =

* **IP Location Block Cloud provider:** Selecting this provider sends the visitor IP and configured credential to IP Location Block for lookup. Review its [privacy policy](https://iplocationblock.com/privacy-policy/) and [terms](https://iplocationblock.com/terms-and-conditions/).
* **IP2Location LITE:** For compatibility with this provider, the plugin downloads country databases on activation and scheduled updates. Review its [terms](https://lite.ip2location.com/terms-of-use) and [privacy policy](https://www.ip2location.com/privacy-policy).
* **MaxMind GeoLite2:** For compatibility with this provider, the plugin uses your license key to download databases. Review its [license](https://www.maxmind.com/en/geolite2/eula) and [privacy policy](https://www.maxmind.com/en/privacy-policy).
* **Other remote providers:** Selecting IPInfoDB, IPinfo.io, ipapi, or ipstack sends the visitor IP and configured credential to that service for lookup. No remote provider is selected automatically.

Other remote provider policies: [IPInfoDB privacy](https://www.ipinfodb.com/privacy-policy) and [service agreement](https://www.ipinfodb.com/agreement.pdf); [IPinfo.io privacy](https://ipinfo.io/privacy-policy) and [terms](https://ipinfo.io/terms-of-service); [ipapi privacy](https://ipapi.com/privacy) and [terms](https://ipapi.com/terms); [ipstack privacy](https://ipstack.com/privacy) and [terms](https://ipstack.com/terms).

The administrator chooses the providers and is responsible for any required consent or disclosure.

= Documentation, support, and credits =

Use the [documentation](https://iplocationblock.com/docs/?utm_source=plugin&utm_medium=wporgpage&utm_campaign=readme), [troubleshooting guide](https://iplocationblock.com/docs/troubleshooting/), [support forum](https://wordpress.org/support/plugin/ip-location-block/), or [GitHub](https://github.com/ip-location-block/ip-location-block).

Independently maintained, IP Location Block is based on IP Geo Block by tokkonopapa. It uses IP2Location LITE and GeoLite2 data under their licenses.

== Installation ==

1. Open **Plugins > Add New Plugin**, search for **IP Location Block**, install it, and activate it.
2. Open **Settings > IP Location Block**.
3. In **Simple** view, choose whether to block or allow locations and where protection applies.
4. Connect the IP Location Block Cloud provider for the recommended accuracy, ASN data, and regional precision.
5. Verify representative IPs in **Search**, save the rules, and use **Advanced** view when you need more control.

See the [getting started guide](https://iplocationblock.com/docs/getting-started/) for a complete walkthrough.

== Frequently Asked Questions ==

= Can I block visitors from specific US states? =

Yes. Connect the IP Location Block Cloud provider, add a United States rule, then select states under **Regional rules**. Other countries use their equivalent administrative areas. See [state or region rules](https://iplocationblock.com/docs/blocking-rules/state-region/).

= What does the IP Location Block Cloud provider add? =

It uses premium, frequently updated geolocation data for better country accuracy, ASN information, and state or region precision.

= Which parts of WordPress can the plugin protect? =

Public pages, login, registration, comments, XML-RPC, and selected wp-admin requests. Advanced view controls each target.

= Does it work with page caches, CDNs, and reverse proxies? =

Yes, when configured correctly. Page caches may respond before WordPress runs, while a CDN or proxy can hide the visitor IP. Review the [page-cache guide](https://iplocationblock.com/docs/blocking-rules/page-cache/) and test **Search**.

= What if I block myself from wp-admin? =

Bookmark the private emergency link before enforcing admin rules. If blocked, follow the [admin access steps](https://iplocationblock.com/docs/troubleshooting/#admin-access-problem).

= Can I migrate settings from IP Geo Block? =

Yes. When old settings are detected, Plugin settings offers a migration preview before saving them.

== Screenshots ==

1. Searchable country blocking with European Union shortcuts.
2. Block selected United States states or regions alongside country rules.
3. Manage provider precision, IPv6, ASN, quota, and connection status.
4. Use curated bot presets or control individual crawler rules.
5. Configure every validation target and advanced security control.
6. Review blocked requests, countries, IP versions, and provider performance.
7. Filter, inspect, and export blocked or allowed validation logs.
8. Preview country, state or region, ASN, and map results.
9. Check configuration, providers, compatibility, and emergency access.

== Changelog ==

= 1.4.0 =

*Release Date - 24 Aug 2026*

* Redesigned the admin with Simple and Advanced views, provider setup, statistics, logs, search, and diagnostics.
* Added searchable state or region rules and a modern bot-rule builder.
* Prioritized the IP Location Block provider for regional rules while retaining country fallbacks.
* Rebuilt internals with PSR-4 and scoped dependencies, plus broad provider, cache, database, filesystem, and interface fixes.
* Requires PHP 8.1 and WordPress 6.5 or newer. The redesigned admin requires WordPress 6.6.

Older release history is available in `changelog.txt` included with the plugin.

== Upgrade Notice ==

= 1.4.0 =

Requires WordPress 6.5 or newer and PHP 8.1 or newer. Version 1.4.0 introduces Simple and Advanced views and removes custom geolocation provider registration. Existing settings and legacy global classes remain compatible throughout the 1.x series.
