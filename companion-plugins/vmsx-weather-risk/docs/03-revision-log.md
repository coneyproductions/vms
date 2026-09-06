
## 0.1.12
- Reworked display-text repair so Event Plan titles can recover from single- and double-encoded mojibake instead of only the simpler broken-character cases.
- Normalized repaired smart quotes and dashes into safe display characters for the Show Risk heading, subtitle, and picker labels.
- Removed an over-aggressive spacing fallback that could turn stray cleanup gaps into unwanted hyphens inside titles.

## 0.1.11
- Added structured U.S. Census geocoding before one-line geocoding fallbacks.
- Added stronger rural-road address variants, including no-ZIP and no-direction variants.
- Expanded debug summaries for geocoding attempts.
- Hardened mojibake cleanup for weather add-on display text.

## 0.1.10

- Added a U.S.-focused Census geocoder ahead of the existing geocoder fallbacks so normal saved venue addresses have a much better chance of auto-resolving without manual settings.
- Added address normalization / variant tries for geocoding (including `, USA` and common road-name abbreviations like `Farm to Market` → `FM`).
- Added per-attempt geocoding summaries to the debug log so failed auto-location now shows which provider/variant was attempted and whether it returned zero results or an HTTP/request error.
- Strengthened mojibake cleanup with a guarded text-repair pass for broken dashes/quotes that were still leaking into titles and labels.

## 0.1.9

- Added event-screen debug log details so each location-resolution attempt can show the exact address string and source the add-on tried to geocode.
- Added richer geocoding failure context in the debug log, including provider attempts and linked venue/event IDs when available.
- Strengthened mojibake cleanup for broken dashes, bullets, quotes, and stray `Â`/`â€` artifacts that were still leaking into titles and meta text.

## 0.1.6

- Improved venue auto-location so the add-on can import coordinates from a linked TEC venue before falling back to geocoding.
- Backfilled blank VMS venue address fields from the linked TEC venue when that data already exists.
- Saved event-refresh geocoding results back to the VMS venue so future events at the same venue can reuse them.
- Hardened display-text cleanup for mojibake-style broken dashes and bullet separators in titles and subtitles.

## 0.1.5

- Changed ticket snapshot resolution to prefer DT website ticket truth when available.
- Added a fresh VMS ticketing recalculation fallback for events where DT enrichment is unavailable.
- Persist refreshed live VMS ticket stats back to the Event Plan cache so adjacent VMS surfaces stay in sync.

## 0.1.4

- Added Open-Meteo as a second free provider and enabled it by default for new installs, so the free baseline is no longer NOAA-only.
- Hardened NOAA request headers with a contact-aware user agent and broader JSON accept header.
- Replaced null money placeholders with plain `N/A` to avoid mojibake-style output in some admin environments.
- Added display-text cleanup for common mojibake title/label cases such as broken dashes and quotes.

## 0.1.3 — 2026-04-16

- Added automatic venue geocoding on venue save when the venue has a usable saved address but no stored latitude/longitude.
- Added lazy venue auto-geocoding during Show Risk refresh so existing venues can self-heal without manual coordinate lookup.
- Persisted auto-resolved coordinates back to venue meta and synced linked TEC venue coordinates when available.
- Updated missing-coordinate messaging to steer operators toward saving a complete venue address or using fallback coordinates only as a backup.

## 0.1.2 — 2026-04-16

- Added address-based geocoding fallback so a venue with a saved address can still resolve coordinates when precise lat/lng fields are empty.
- Fixed details-page schema mismatches for sales context, DT context, and provider-hour rows.
- Added clearer setup notices for past events, missing coordinates, partial provider setup, and zero-provider responses.
- Improved skipped-provider messaging so disabled providers and missing API keys are explained separately.
- Replaced garbled placeholder output with plain `N/A` labels on the details page.

## 0.1.1 — 2026-04-16

- Renamed admin page slugs to `vms-weather-risk` and `vms-weather-risk-settings` so the add-on is recognized by the VMS admin UI.
- Added compact top-nav integration under Planning and Settings.
- Added redirects from the legacy `vmsx-...` page slugs.
- Opted the add-on into the shared VMS admin shell for consistent navigation and page chrome.
- Updated asset loading, settings redirects, and manifest settings URL to the new slugs.

## 0.1.0 — 2026-04-16

- Created the standalone `vmsx-weather-risk` premium add-on scaffold.
- Added explainable advisory scoring that combines weather, ticket context, and optional DT pace context.
- Added provider adapters for NOAA, OpenWeather, and WeatherAPI.
- Added Event Plan compact card, admin details page, settings page, AJAX refresh, and scheduled refresh support.
- Added packaged docs and a focused test plan.

## 0.1.8
- Venue location resolver now uses canonical VMS venue location data when available instead of relying only on scattered legacy meta lookups.
- Event risk resolution now falls back to ticketing/VMS helpers to find the linked TEC event and TEC venue for a plan before declaring that auto-location failed.
- Venue coordinate reads now honor canonical VMS venue coordinates before legacy meta aliases.
