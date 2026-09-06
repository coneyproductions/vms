# Test Plan

- Install `vmsx-weather-risk` 0.1.12 over the current build.
- Open Show Risk for an Event Plan whose title previously showed broken characters.
- Confirm the page heading, shell title, picker label, and meta line now show plain quotes/dashes instead of strings like `â€™`, `Ã¢â‚¬â„¢`, `â€“`, or stray `Â`.
- Confirm normal titles still render cleanly and do not gain unwanted hyphens from spacing cleanup.

# Test Plan

- Verify a future event with a complete venue address can auto-resolve coordinates without manual fallback settings.
- Check the debug log for structured Census attempts and address variants if auto-resolution still fails.
- Confirm previously garbled title text displays cleanly across the page heading, picker, and meta line.

### 0.1.10 geocoder diagnostics + rural address regression

- Remove manual fallback coordinates from Show Risk Settings.
- Open a future Event Plan at a venue with a normal U.S. street address.
- Click Refresh Show Risk.
- Confirm the debug log now shows the attempted address variants and provider-by-provider geocoding result summary.
- Confirm a U.S. rural address such as `Farm to Market` can auto-resolve without manual coordinates when the address is valid.

# Test Plan

- Install `vmsx-weather-risk` 0.1.9 over the prior build.
- Open a future Event Plan in Show Risk Advisor and click **Refresh Show Risk**.
- Confirm the **Debug / log** table now shows the attempted address string and address source for location resolution.
- Confirm failed geocoding entries list the attempted providers instead of a vague failure only.
- Confirm broken characters like `â€`, `Â·`, and stray `Â` no longer appear in the page title, subtitle, event picker, or meta lines when rendered by the add-on.

### 0.1.6 geocode + title cleanup regression

- Install the updated `vmsx-weather-risk` add-on over the current local build.
- Open a future Event Plan linked to a venue that has a normal saved address but blank latitude/longitude.
- Click **Refresh Show Risk** and confirm the page can resolve coordinates without manual lat/lng entry.
- Open the linked venue and confirm latitude/longitude are now stored on the VMS venue.
- If the venue is linked to a TEC venue that already has location data, confirm blank VMS venue address fields are backfilled cleanly.
- Confirm the Show Risk page title/subtitle no longer show mojibake such as `â€` or `Â·`.
- Confirm previously working provider responses and ticket snapshot values still render correctly after refresh.


## 0.1.8 smoke
- Remove manual fallback coordinates from Show Risk Settings.
- Open a future Event Plan already linked to VMS venue + TEC event data.
- Refresh Show Risk and confirm the page can resolve coordinates without manual settings when venue/TEC address data exists.
- Confirm Ticket Snapshot still shows DT website truth or Fresh VMS ticketing.
