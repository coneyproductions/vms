<?php
defined('ABSPATH') || exit;

/**
 * Provider-neutral distribution contract for a future delivery integration.
 *
 * Implementations live outside the dormant Phase A foundation. Context values
 * are deliberately opaque to BVM core.
 */
interface BVMGR_Admission_Offer_Distribution_Provider_Interface
{
	public function provider_key(): string;

	/** @return array<string,mixed> */
	public function validate_distribution_context(array $context): array;
}

/**
 * Provider-neutral pricing contract for a future commerce adapter.
 */
interface BVMGR_Admission_Offer_Pricing_Adapter_Interface
{
	public function provider_key(): string;

	/** @return array<string,mixed> */
	public function price_claim(array $claim, array $eligible_items): array;
}

/**
 * Provider-neutral fulfillment contract for future complimentary or paid
 * credential providers. A Claim is never itself a credential.
 */
interface BVMGR_Admission_Offer_Fulfillment_Provider_Interface
{
	public function provider_key(): string;

	/** @return array<string,mixed> */
	public function fulfill_claim(array $claim, array $context): array;
}
