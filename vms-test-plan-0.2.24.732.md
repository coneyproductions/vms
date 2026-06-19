# VMS 0.2.24.732 Test Plan — Email Follow-Ups Admin Nav Dedupe

## Pre-checks

1. Activate VMS `0.2.24.732`.
2. Confirm version markers:
   - plugin header shows `0.2.24.732`
   - `VMS_VERSION` is `0.2.24.732`
   - `vms/vms-build.txt` begins with `0.2.24.732`

## Email Follow-Ups shell checks

1. Open `wp-admin/admin.php?page=vms-email-followups`.
2. Confirm only one primary VMS nav row appears.
3. Confirm only one Marketing & Social subnav row appears.
4. Confirm the primary active tab is `Marketing & Social`.
5. Confirm the active secondary pill is `Email Follow-Ups`.

## Email Follow-Ups tabs

1. On `Overview`, confirm the page loads with no PHP warning/notice output.
2. Open `Templates` and confirm the page loads with one VMS nav stack.
3. Open `Preview & Test` and confirm the page loads with one VMS nav stack.
4. Open `Logs` and confirm the page loads with one VMS nav stack.

## Neighboring Marketing & Social pages

1. Open `wp-admin/admin.php?page=vms-social-sharing`.
2. Confirm only one VMS primary row and one Marketing & Social secondary row appear.

1. Open each Meta Ads screen if the add-on is active:
   - `wp-admin/admin.php?page=vms-ma-ads-builder`
   - `wp-admin/admin.php?page=vms-ma-ads-promote`
   - `wp-admin/admin.php?page=vms-ma-ads-performance`
   - `wp-admin/admin.php?page=vms-ma-ads-logs`
   - `wp-admin/admin.php?page=vms-ma-ads-settings`
2. Confirm each page still shows only one VMS primary row and one Marketing & Social secondary row.

## Regression guardrails

1. Confirm the WordPress left admin menu still renders normally.
2. Confirm the Email Follow-Ups page heading/content still renders.
3. Confirm no change to public Event Feedback behavior from `0.2.24.731`.
4. Confirm no change to Email Follow-Ups sending, templates, logs, MailPoet sync, or scheduling behavior.
