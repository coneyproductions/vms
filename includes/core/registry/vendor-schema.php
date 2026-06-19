<?php
defined('ABSPATH') || exit;

/**
 * Vendor Schema Registry (MASTER)
 *
 * Defines canonical vendor fields, where they live (post or meta), and types.
 * This is the single source of truth used by:
 * - CSV import (through adapter layer)
 * - Admin saves
 * - REST endpoints
 * - Validation
 * - Meta registration (derived)
 */
function vms_vendor_schema(): array
{
	return [

		/**
		 * Canonical identity (entity-level enforcement happens in importer),
		 * but we keep the field here for storage + label.
		 *
		 * NOTE: We are intentionally making this NOT strictly required at the field level
		 * so we can use email fallback title for “name missing” rows.
		 */
		'display_name' => [
			'storage'     => 'post_title',
			'required'    => false,
			'type'        => 'string',
			'label'       => 'Vendor Name',
			'is_identity' => true,
		],

		/**
		 * Primary contact (day-to-day)
		 */
		'primary_email' => [
			'storage'     => 'meta',
			'meta_key'    => (function_exists('vms_meta_key') ? vms_meta_key('vendor','primary_email') : '_vms_vendor_primary_email'),
			'required'    => false,
			'type'        => 'email',
			'label'       => 'Primary Contact Email',
			'is_identity' => true,
		],

		'primary_phone' => [
			'storage'  => 'meta',
			'meta_key' => (function_exists('vms_meta_key') ? vms_meta_key('vendor','primary_phone') : '_vms_vendor_primary_phone'),
			'required' => false,
			'type'     => 'string',
			'label'    => 'Primary Phone',
		],

		/**
		 * Legacy contact fields (kept for backward compatibility)
		 * We’ll treat these as deprecated in logic later, but keep them registered.
		 */
		'email' => [
			'storage'    => 'meta',
			'meta_key'   => vms_meta_key('vendor', 'email'), // _vms_vendor_email
			'required'   => false,
			'type'       => 'email',
			'label'      => 'Email (Legacy)',
			'deprecated' => true,
		],

		'phone' => [
			'storage'    => 'meta',
			'meta_key'   => vms_meta_key('vendor', 'phone'), // _vms_vendor_phone
			'required'   => false,
			'type'       => 'string',
			'label'      => 'Phone (Legacy)',
			'deprecated' => true,
		],

		'website' => [
			'storage'  => 'meta',
			'meta_key' => vms_meta_key('vendor', 'website'),
			'required' => false,
			'type'     => 'url',
			'label'    => 'Website',
		],

		'vendor_type' => [
			'storage'  => 'taxonomy',
			'taxonomy' => 'vms_vendor_type',
			'required' => false,
			'type'     => 'string',
			'label'    => 'Vendor Type',
		],

		/**
		 * Tax profile (vendor-facing labels; Tax1099 headers will be handled via aliases)
		 */
		'payee_legal_name' => [
			'storage'  => 'meta',
			'meta_key' => vms_meta_key('vendor', 'payee_legal_name'),
			'required' => false,
			'type'     => 'string',
			'label'    => 'Legal / Payee Name (as on W-9)',
		],

		'payee_dba' => [
			'storage'  => 'meta',
			'meta_key' => vms_meta_key('vendor', 'payee_dba'),
			'required' => false,
			'type'     => 'string',
			'label'    => 'Business Name / DBA (optional)',
		],

		'entity_type' => [
			'storage'  => 'meta',
			'meta_key' => vms_meta_key('vendor', 'entity_type'),
			'required' => false,
			'type'     => 'string',
			'label'    => 'Entity Type',
		],

		'w9_received_date' => [
			'storage'  => 'meta',
			'meta_key' => vms_meta_key('vendor', 'w9_received_date'),
			'required' => false,
			'type'     => 'string',
			'label'    => 'W-9 Received Date',
		],

		'w9_attested_at' => [
			'storage'  => 'meta',
			'meta_key' => vms_meta_key('vendor', 'w9_attested_at'),
			'required' => false,
			'type'     => 'int',
			'label'    => 'W-9 Attested Timestamp',
		],

		'w9_provider' => [
			'storage'  => 'meta',
			'meta_key' => vms_meta_key('vendor', 'w9_provider'),
			'required' => false,
			'type'     => 'string',
			'label'    => 'W-9 Offsite Provider',
		],

			'tax_profile_type' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'tax_profile_type'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Tax Profile Type',
			],

			'tax_tin_type' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'tax_tin_type'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Tax ID Type',
			],

			'tax_business_or_last_name' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'tax_business_or_last_name'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Tax Legal Name (Business or Last)',
			],

			'tax_first_name' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'tax_first_name'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Tax First Name',
			],

			'tax_middle_name' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'tax_middle_name'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Tax Middle Name',
			],

			'tax_suffix' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'tax_suffix'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Tax Name Suffix',
			],

		/**
		 * Dedicated tax email (you chose this, and it’s the right call)
		 *
		 * We will later deprecate _vms_vendor_recipient_email safely with a fallback bridge,
		 * but we do NOT need to register that legacy key as “truth” going forward.
		 */
			'tax_email' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'tax_email'),
				'required' => false,
				'type'     => 'email',
				'label'    => 'Tax Contact Email',
			],

			'tax_phone' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'tax_phone'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Tax Contact Phone',
			],

			'tax_country' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'tax_country'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Tax Country',
			],

		/**
		 * Sensitive: ingestable but NEVER persisted.
		 * (Do not include a meta_key; derived meta registry will not register it.)
		 */
		'tax_tin' => [
			'storage'    => 'ingest_only',
			'required'   => false,
			'type'       => 'string',
			'label'      => 'Taxpayer ID (TIN)',
			'persist'    => false,
			'sensitive'  => true,
			'importable' => true,
		],

		/**
		 * Tax mailing address (mapped to your existing keys for compatibility)
		 */
			'tax_attention_to' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'tax_attention_to'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Tax Attention To',
			],

			'tax_address_1' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'addr1'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Address Line 1',
			],

			'tax_address_2' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'addr2'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'Address Line 2',
			],

			'tax_city' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'city'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'City',
			],

			'tax_state' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'state'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'State / Province',
			],

			'tax_postal_code' => [
				'storage'  => 'meta',
				'meta_key' => vms_meta_key('vendor', 'zip'),
				'required' => false,
				'type'     => 'string',
				'label'    => 'ZIP / Postal Code',
			],

		/**
		 * Availability + internal notes (existing)
		 */
		'availability' => [
			'storage'  => 'meta',
			'meta_key' => vms_meta_key('vendor', 'availability'),
			'required' => false,
			'type'     => 'string',
			'label'    => 'Availability',
		],

		'notes_internal' => [
			'storage'  => 'meta',
			'meta_key' => vms_meta_key('vendor', 'notes_internal'),
			'required' => false,
			'type'     => 'string',
			'label'    => 'Internal Notes',
		],
	];
}
