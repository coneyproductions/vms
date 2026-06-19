<?php
defined('ABSPATH') || exit;

/**
 * Vendor CSV Adapter (NOT a storage schema)
 *
 * Purpose:
 * - Map CSV headers (including aliases) to canonical vendor schema keys.
 * - Define import identity rules and adapter-only semantics.
 *
 * Non-goals:
 * - No direct meta keys
 * - No "meta:_vms_*" targets
 * - No post/meta storage decisions (that comes from vms_vendor_schema())
 */
final class VMS_Vendor_Schema_Registry
{

	/**
	 * Returns the adapter contract for vendor CSV imports.
	 *
	 * @param string $version Adapter version for future evolution.
	 * @return array<string, mixed>
	 */
	public static function get(string $version = 'v1'): array
	{

		if ($version !== 'v1') {
			$version = 'v1';
		}

		return [
			'entity'  => 'vendor',
			'version' => $version,

			/**
			 * Identity rules are canonical-key based.
			 *
			 * Hard rule: if neither identity field resolves, the row must be skipped
			 * and no vendor post should be created.
			 */
			'identity' => [
				'required'                 => true,
				'strategy'                 => 'email_then_name',
				'email_key'                => 'primary_email',
				'name_key'                 => 'display_name',
				'title_placeholder_prefix' => 'Vendor: ',
			],

			/**
			 * Adapter field map: canonical_key => adapter config
			 *
			 * - "headers": list of accepted CSV headers for this canonical key
			 * - "sanitize_cb": sanitation only (storage happens elsewhere)
			 */
			'fields' => [

				/* =========================
				 * Core identity/contact
				 * ========================= */

				'display_name' => [
					'headers'     => [
						'Vendor Name',
						'Display Name',
						'Name',
						'Business Name',
						'Company Name',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'primary_email' => [
					'headers' => [
						'Primary Contact Email',
						'Primary Email',
						'Vendor Email',
						'Email',
						'Email Address',
					],
					'sanitize_cb' => 'sanitize_email',
				],

				'primary_phone' => [
					'headers'     => [
						'Primary Phone',
						'Phone',
						'Phone Number',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'website' => [
					'headers'     => [
						'Website',
						'URL',
						'Web Site',
					],
					'sanitize_cb' => 'esc_url_raw',
				],

				/**
				 * Portrait maps to featured image (thumbnail).
				 * Storage handler decides how to download/set.
				 */
				'portrait' => [
					'headers'     => [
						'Portrait',
						'Image',
						'Photo',
						'Logo',
					],
					'sanitize_cb' => 'sanitize_text_field',
					'type'        => 'image',
				],

				/* =========================
				 * Tax profile (vendor-facing)
				 * ========================= */

				'vendor_type' => [
					'headers'     => [
						'Vendor Type',
						'Vendor Types',
						'Vendor Category',
						'Vendor Categories',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'payee_legal_name' => [
					'headers'     => [
						'Legal/Payee Name',
						'Legal / Payee Name',
						'Payee Legal Name',
						'W-9 Legal Name',
						'1099 Legal Name',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'payee_dba' => [
					'headers'     => [
						'Business Name / DBA',
						'Business Name',
						'DBA',
						'Trade Name',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'entity_type' => [
					'headers'     => [
						'Entity Type',
						'Tax Entity Type',
						'W-9 Entity Type',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'w9_received_date' => [
					'headers'     => [
						'W9 Received Date',
						'W-9 Received Date',
						'W9 Received',
						'W-9 Received',
						'Signed W9 Received',
						'W9 On File Date',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'w9_attested_at' => [
					'headers'     => [
						'W9 Attested At',
						'W-9 Attested At',
						'W9 Confirmed At',
						'W-9 Confirmed At',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'w9_provider' => [
					'headers'     => [
						'W9 Provider',
						'W-9 Provider',
						'Offsite W9 Provider',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_profile_type' => [
					'headers'     => [
						'Recipient Type',
						'Tax Profile Type',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_tin_type' => [
					'headers'     => [
						'Recipient TIN Type',
						'Tax ID Type',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				/**
				 * Tax1099 splits legal name into multiple columns.
				 * Your master schema can store these in dedicated keys, or you can
				 * later compute a combined "tax legal name" view.
				 */
				'tax_business_or_last_name' => [
					'headers'     => [
						'Business Name (If not individual) OR Last Name (If individual)',
						'Recipient Business Name',
						'Recipient Business Name or Last Name',
						'Recipient Last Name',
						'Tax Legal Name (Business or Last)',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_first_name' => [
					'headers'     => [
						'Recipient First Name',
						'Tax First Name',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_middle_name' => [
					'headers' => [
						'Recipient Middle Name',
						'Recipient Middle Name (Optional)',
						'Tax Middle Name',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_suffix' => [
					'headers' => [
						'Recipient Suffix',
						'Recipient Name Suffix (Optional)',
						'Tax Suffix',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				/**
				 * Dedicated tax email
				 */
				'tax_email' => [
					'headers'     => [
						'Tax Contact Email',
						'Recipient Email Address',
					],
					'sanitize_cb' => 'sanitize_email',
				],

				/**
				 * Sensitive: ingestable but never persisted.
				 * The master schema enforces persist=false; the importer should ignore storage.
				 */
				'tax_tin' => [
					'headers'     => [
						'Recipient TIN',
						'Taxpayer ID',
						'TIN',
					],
					'sanitize_cb' => 'sanitize_text_field',
					'sensitive'   => true,
				],

				/* =========================
				 * Tax mailing address
				 * ========================= */

				'tax_attention_to' => [
					'headers'     => [
						'Attention To',
						'Recipient Attention To',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_address_1' => [
					'headers'     => [
						'Recipient Address 1',
						'Address 1',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_address_2' => [
					'headers'     => [
						'Recipient Address 2 (Optional)',
						'Address 2',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_city' => [
					'headers'     => [
						'Recipient City',
						'City',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_state' => [
					'headers'     => [
						'Recipient State',
						'State',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_postal_code' => [
					'headers' => [
						'Recipient ZIP or Foreign Postal Code',
						'Recipient Postal Code',
						'ZIP',
						'Zip Code',
						'Postal Code',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

				'tax_country' => [
					'headers'     => [
						'Recipient Country',
						'Country',
					],
					'sanitize_cb' => 'sanitize_text_field',
				],

			],
		];
	}

	/**
	 * Normalize a header for matching.
	 *
	 * @param string $header
	 * @return string
	 */
	public static function normalize_header(string $header): string
	{
		$h = strtolower(trim($header));
		$h = preg_replace('/\s+/', ' ', $h);
		return $h ?? '';
	}

	/**
	 * Build a lookup map of normalized header => original header.
	 *
	 * @param string[] $headers
	 * @return array<string,string>
	 */
	public static function build_header_lookup(array $headers): array
	{
		$lookup = [];
		foreach ($headers as $header) {
			$norm = self::normalize_header((string) $header);
			if ($norm !== '' && !isset($lookup[$norm])) {
				$lookup[$norm] = (string) $header;
			}
		}
		return $lookup;
	}

	/**
	 * Resolve canonical field keys to actual CSV headers present.
	 *
	 * @param string[] $csv_headers
	 * @param string   $version
	 * @return array<string,string> canonical_key => matched_csv_header
	 */
	public static function resolve_fields(array $csv_headers, string $version = 'v1'): array
	{
		$contract = self::get($version);
		$fields   = $contract['fields'] ?? [];
		$lookup   = self::build_header_lookup($csv_headers);

		$resolved = [];

		foreach ($fields as $canonical_key => $cfg) {
			$aliases = $cfg['headers'] ?? [];
			foreach ($aliases as $alias) {
				$norm = self::normalize_header((string) $alias);
				if ($norm !== '' && isset($lookup[$norm])) {
					$resolved[$canonical_key] = $lookup[$norm];
					break;
				}
			}
		}

		return $resolved;
	}
}
