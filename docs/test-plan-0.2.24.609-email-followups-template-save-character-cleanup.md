# VMS Test Plan — 0.2.24.609 — Email Follow-Ups Template Save + Character Cleanup

🚨 Codex/staging recommended before enabling automatic scheduled sends.

## Version checks

1. Confirm plugin header reports `0.2.24.609`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.609`.
3. Confirm `vms-build.txt` contains `0.2.24.609`.

## Email Follow-Ups template UI

1. Open `VMS → Marketing & Social → Email Follow-Ups → Templates`.
2. Confirm every existing template card has a **Save Template Changes** button directly below the body textarea.
3. Edit a template body near the middle of the page and click that card's **Save Template Changes** button.
4. Confirm the page reloads with a success notice and the edit persists.
5. Confirm the bottom **Save Templates** button still works.
6. Add a new template in the **Add Template** card and click **Save New Template**.
7. Confirm the custom template appears as an editable template card and is available in Preview & Test.

## Character cleanup regression

1. In a template body, paste/save intentionally bad text such as:
   - `Ã¢Â€Â¢ No outside food`
   - `â€¢ Beer and wine available`
   - `Weâ€™ll see you soon â€” thanks!`
   - `Â This has a stray prefix`
2. Save the template.
3. Confirm the saved body displays clean text such as:
   - `- No outside food`
   - `- Beer and wine available`
   - `We'll see you soon - thanks!`
   - `This has a stray prefix`
4. Confirm the rendered Preview & Test email does not show `Ã¢Â€Â¢`, `â€¢`, `â€™`, or stray `Â` artifacts.

## Existing-template migration

1. Before installing, if possible, seed a saved Email Follow-Ups template containing `Ã¢Â€Â¢` or `â€¢`.
2. Install/activate 0.2.24.609.
3. Open Email Follow-Ups → Templates.
4. Confirm the one-time migration repaired saved template text, custom-template labels/descriptions, signature, and from-name fields.

## Send safety regression

1. Confirm automatic scheduled sends remain off unless explicitly enabled.
2. Confirm test email still sends successfully.
3. Confirm manual Send to Eligible Recipients still requires the confirmation checkbox.
4. Confirm post-event feedback URLs still render in the Post-Event Thank You template.
