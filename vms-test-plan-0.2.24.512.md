# VMS Test Plan — 0.2.24.512

## Scope
Vendor application polish for non-band categories.

## Verify
1. **Band / Artist**
   - Name label reads **Band / Artist Name**
   - Website label remains standard
   - Band booking card still shows
   - Turnout + compensation remain required
   - Social section shows Facebook, Instagram, X / Twitter, TikTok, YouTube, Spotify
   - EPK still shows only for bands

2. **Food Truck**
   - Name label reads **Business Name**
   - Concession section shows **Cuisine / Food Type**
   - Menu label reads **Menu Link (optional)**
   - Social section shows Facebook, Instagram, TikTok only
   - Spotify / YouTube / X do not show

3. **Drink Truck**
   - Name label reads **Business Name**
   - Concession section shows **Beverage / Drink Type**
   - Menu label reads **Menu / Service Link (optional)**
   - Social section shows Facebook, Instagram, TikTok only

4. **Dessert Truck**
   - Name label reads **Business Name**
   - Concession section shows **Dessert Type**
   - Social section shows Facebook, Instagram, TikTok only

5. **Photographer**
   - Name label reads **Photographer / Business Name**
   - Website label reads **Portfolio / Website URL (optional)**
   - Social section shows Facebook + Instagram only
   - Music-specific links do not show

6. Switch between types and confirm hidden fields are disabled and do not remain required.

7. Submit one band application and one non-band application and confirm the notification email only includes populated fields.
