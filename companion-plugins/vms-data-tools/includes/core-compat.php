<?php
defined('ABSPATH') || exit;

/**
 * Resolve a public BVM runtime function, retaining legacy VMS fallback.
 */
function vms_dt_core_function(string $legacy_name): string {
	$public_name = strpos($legacy_name, 'vms_') === 0
		? 'bvmgr_' . substr($legacy_name, 4)
		: $legacy_name;

	if (function_exists($public_name)) {
		return $public_name;
	}

	return function_exists($legacy_name) ? $legacy_name : '';
}

function vms_dt_has_core_function(string $legacy_name): bool {
	return vms_dt_core_function($legacy_name) !== '';
}

function vms_dt_call_core_function(string $legacy_name, ...$args) {
	$function = vms_dt_core_function($legacy_name);
	return $function !== '' ? $function(...$args) : null;
}

/**
 * Resolve a public BVM runtime constant, retaining legacy VMS fallback.
 */
function vms_dt_core_constant(string $legacy_name, $default = null) {
	$public_name = strpos($legacy_name, 'VMS_') === 0
		? 'BVMGR_' . substr($legacy_name, 4)
		: $legacy_name;

	if (defined($public_name)) {
		return constant($public_name);
	}
	if (defined($legacy_name)) {
		return constant($legacy_name);
	}

	return $default;
}

/**
 * Resolve a public BVM runtime class, retaining legacy VMS fallback.
 */
function vms_dt_core_class(string $legacy_name): string {
	$public_name = strpos($legacy_name, 'VMS_') === 0
		? 'BVMGR_' . substr($legacy_name, 4)
		: $legacy_name;

	if (class_exists($public_name)) {
		return $public_name;
	}

	return class_exists($legacy_name) ? $legacy_name : '';
}
