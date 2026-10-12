# WPORG Release Notes 1.3.8

## Identity

- Public plugin: Backstage Venue Manager
- Public slug and text domain: backstage-venue-manager
- Public version: 1.3.8

## Summary

Version 1.3.8 prevents staffing email for Event Plans whose canonical performance
end has passed. It also fails closed when event timing is missing or invalid and
rechecks eligibility immediately before transport, including retries and cron
recovery.

The release does not prevent historical staffing edits, change notification
activation defaults, alter assignment permissions, or introduce a database
migration.
