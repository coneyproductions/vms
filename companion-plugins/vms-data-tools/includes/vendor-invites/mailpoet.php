<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_mailpoet_is_active')) {
    function vms_dt_vio_mailpoet_is_active(): bool
    {
        return class_exists('\\MailPoet\\API\\API');
    }
}

if (!function_exists('vms_dt_vio_mailpoet_api')) {
    /**
     * @return object|WP_Error
     */
    function vms_dt_vio_mailpoet_api()
    {
        if (!vms_dt_vio_mailpoet_is_active()) {
            return new WP_Error('vms_dt_vio_mailpoet_inactive', __('MailPoet is not active.', 'vms-data-tools'));
        }

        try {
            /** @var object $api */
            $api = \MailPoet\API\API::MP('v1');
            return $api;
        } catch (Throwable $e) {
            return new WP_Error('vms_dt_vio_mailpoet_api', sprintf(
                /* translators: %s is an exception message. */
                __('MailPoet API unavailable: %s', 'vms-data-tools'),
                $e->getMessage()
            ));
        }
    }
}

if (!function_exists('vms_dt_vio_mailpoet_can_call')) {
    /**
     * Accept both concrete methods and APIs that expose endpoints via __call.
     *
     * @param object $api
     */
    function vms_dt_vio_mailpoet_can_call($api, string $method): bool
    {
        if (!is_object($api)) {
            return false;
        }

        if (is_callable(array($api, $method))) {
            return true;
        }

        if (method_exists($api, $method)) {
            return true;
        }

        return method_exists($api, '__call');
    }
}

if (!function_exists('vms_dt_vio_mailpoet_call')) {
    /**
     * @param object $api
     * @param array<int,mixed> $args
     * @return mixed
     */
    function vms_dt_vio_mailpoet_call($api, string $method, array $args = array())
    {
        if (!vms_dt_vio_mailpoet_can_call($api, $method)) {
            return new WP_Error('vms_dt_vio_mailpoet_method_missing', sprintf(
                /* translators: %s is a MailPoet API method name. */
                __('MailPoet method missing: %s', 'vms-data-tools'),
                $method
            ));
        }

        try {
            return call_user_func_array(array($api, $method), $args);
        } catch (Throwable $e) {
            return new WP_Error('vms_dt_vio_mailpoet_call_failed', sprintf(
                /* translators: 1: method name, 2: error message. */
                __('MailPoet %1$s failed: %2$s', 'vms-data-tools'),
                $method,
                $e->getMessage()
            ));
        }
    }
}

if (!function_exists('vms_dt_vio_mailpoet_get_segments_index')) {
    /**
     * @param object $api
     * @return array<string,int>
     */
    function vms_dt_vio_mailpoet_get_segments_index($api): array
    {
        $methods = array('getSegments', 'getTags', 'getLists');
        $segments = null;

        foreach ($methods as $method) {
            if (!vms_dt_vio_mailpoet_can_call($api, $method)) {
                continue;
            }
            $result = vms_dt_vio_mailpoet_call($api, $method, array());
            if (is_wp_error($result)) {
                continue;
            }
            if (is_array($result)) {
                $segments = $result;
                break;
            }
        }

        if (!is_array($segments)) {
            return array();
        }

        $index = array();
        foreach ($segments as $segment) {
            if (is_object($segment)) {
                $segment = get_object_vars($segment);
            }
            if (!is_array($segment) || empty($segment)) {
                continue;
            }
            $id = absint($segment['id'] ?? ($segment['segment_id'] ?? ($segment['list_id'] ?? 0)));
            if ($id <= 0) {
                continue;
            }

            $name = sanitize_key((string) ($segment['name'] ?? ''));
            $slug = sanitize_key((string) ($segment['slug'] ?? ($segment['key'] ?? '')));
            if ($name !== '') {
                $index[$name] = $id;
            }
            if ($slug !== '') {
                $index[$slug] = $id;
            }
        }

        return $index;
    }
}

if (!function_exists('vms_dt_vio_mailpoet_extract_segment_id')) {
    /**
     * @param mixed $segment
     */
    function vms_dt_vio_mailpoet_extract_segment_id($segment): int
    {
        if (is_numeric($segment)) {
            return absint($segment);
        }

        if (is_array($segment)) {
            return absint($segment['id'] ?? 0);
        }

        if (is_object($segment) && isset($segment->id)) {
            return absint($segment->id);
        }

        return 0;
    }
}

if (!function_exists('vms_dt_vio_mailpoet_get_or_create_segment_id')) {
    /**
     * @param object $api
     * @return int|WP_Error
     */
    function vms_dt_vio_mailpoet_get_or_create_segment_id($api, string $tag_name)
    {
        $tag_name = sanitize_key($tag_name);
        if ($tag_name === '') {
            return new WP_Error('vms_dt_vio_mailpoet_tag_empty', __('MailPoet tag name cannot be empty.', 'vms-data-tools'));
        }

        $segments = vms_dt_vio_mailpoet_get_segments_index($api);
        if (isset($segments[$tag_name])) {
            return (int) $segments[$tag_name];
        }

        $description = __('Created by VMS Vendor Invite Orchestrator', 'vms-data-tools');
        $create_attempts = array(
            array(
                'method' => 'addSegment',
                'payloads' => array(
                    array('name' => $tag_name, 'description' => $description, 'type' => 'default'),
                    array('name' => $tag_name, 'description' => $description),
                    array('name' => $tag_name),
                    $tag_name,
                ),
            ),
            array(
                'method' => 'addTag',
                'payloads' => array(
                    array('name' => $tag_name, 'description' => $description),
                    array('name' => $tag_name),
                    $tag_name,
                ),
            ),
            array(
                'method' => 'addList',
                'payloads' => array(
                    array('name' => $tag_name, 'description' => $description),
                    array('name' => $tag_name),
                    $tag_name,
                ),
            ),
            array(
                'method' => 'createSegment',
                'payloads' => array(
                    array('name' => $tag_name, 'description' => $description),
                    array('name' => $tag_name),
                    $tag_name,
                ),
            ),
        );

        $errors = array();
        $attempted_method = false;

        foreach ($create_attempts as $attempt) {
            $method = sanitize_key((string) ($attempt['method'] ?? ''));
            if ($method === '' || !vms_dt_vio_mailpoet_can_call($api, $method)) {
                continue;
            }

            $attempted_method = true;
            $payloads = isset($attempt['payloads']) && is_array($attempt['payloads']) ? $attempt['payloads'] : array();
            foreach ($payloads as $payload) {
                $created = vms_dt_vio_mailpoet_call($api, $method, array($payload));

                if (is_wp_error($created)) {
                    $errors[] = $created->get_error_message();
                    // Segment may have been created despite API error (race/conflict); re-check index.
                    $segments = vms_dt_vio_mailpoet_get_segments_index($api);
                    if (isset($segments[$tag_name])) {
                        return (int) $segments[$tag_name];
                    }
                    continue;
                }

                $id = vms_dt_vio_mailpoet_extract_segment_id($created);
                if ($id > 0) {
                    return $id;
                }

                // Some MailPoet API versions return non-standard create payloads.
                // Re-read segments after create attempt before failing.
                $segments = vms_dt_vio_mailpoet_get_segments_index($api);
                if (isset($segments[$tag_name])) {
                    return (int) $segments[$tag_name];
                }
            }
        }

        if (!$attempted_method) {
            return new WP_Error(
                'vms_dt_vio_mailpoet_tag_create_method_missing',
                __('MailPoet tag creation method is unavailable in this installation.', 'vms-data-tools')
            );
        }

        $detail = '';
        if (!empty($errors)) {
            $detail = ' ' . sprintf(
                /* translators: %s is a semicolon-delimited list of API error messages. */
                __('Details: %s', 'vms-data-tools'),
                implode('; ', array_unique(array_filter(array_map('sanitize_text_field', $errors))))
            );
        }

        return new WP_Error('vms_dt_vio_mailpoet_tag_create_failed', sprintf(
            /* translators: 1: tag name, 2: optional error details. */
            __('Failed to create MailPoet tag: %1$s.%2$s', 'vms-data-tools'),
            $tag_name,
            $detail
        ));
    }
}

if (!function_exists('vms_dt_vio_mailpoet_verify_setup')) {
    /**
     * @param string[] $required_tags
     * @return array<string,mixed>
     */
    function vms_dt_vio_mailpoet_verify_setup(array $settings, array $required_tags = array()): array
    {
        $result = array(
            'ok' => false,
            'messages' => array(),
            'errors' => array(),
        );

        if (!vms_dt_vio_mailpoet_is_active()) {
            $result['errors'][] = __('MailPoet plugin is not active.', 'vms-data-tools');
            return $result;
        }

        $api = vms_dt_vio_mailpoet_api();
        if (is_wp_error($api)) {
            $result['errors'][] = $api->get_error_message();
            return $result;
        }

        $field_keys = array(
            sanitize_key((string) ($settings['claim_field_key'] ?? 'vms_claim_link')),
            sanitize_key((string) ($settings['open_dates_field_key'] ?? 'vms_open_dates_snippet')),
        );
        $field_keys = array_values(array_unique(array_filter($field_keys)));

        $can_get_fields = vms_dt_vio_mailpoet_can_call($api, 'getCustomFields');
        $can_add_field = vms_dt_vio_mailpoet_can_call($api, 'addCustomField');
        if ($can_get_fields && $can_add_field) {
            $fields = vms_dt_vio_mailpoet_call($api, 'getCustomFields', array());
            if (is_wp_error($fields) || !is_array($fields)) {
                $result['errors'][] = is_wp_error($fields)
                    ? $fields->get_error_message()
                    : __('Unable to read MailPoet custom fields.', 'vms-data-tools');
                return $result;
            }

            $field_index = array();
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $name = sanitize_key((string) ($field['name'] ?? ''));
                $key = sanitize_key((string) ($field['key'] ?? ''));
                if ($name !== '') {
                    $field_index[$name] = true;
                }
                if ($key !== '') {
                    $field_index[$key] = true;
                }
            }

            foreach ($field_keys as $field_key) {
                if (isset($field_index[$field_key])) {
                    $result['messages'][] = sprintf(
                        /* translators: %s is a custom field key. */
                        __('MailPoet field exists: %s', 'vms-data-tools'),
                        $field_key
                    );
                    continue;
                }

                $created = vms_dt_vio_mailpoet_call($api, 'addCustomField', array(array(
                    'name' => $field_key,
                    'type' => 'text',
                )));

                if (is_wp_error($created)) {
                    $result['errors'][] = sprintf(
                        /* translators: 1: field key, 2: API error details. */
                        __('Unable to create MailPoet field %1$s (%2$s).', 'vms-data-tools'),
                        $field_key,
                        $created->get_error_message()
                    );
                } else {
                    $result['messages'][] = sprintf(
                        /* translators: %s is a custom field key. */
                        __('Created MailPoet field: %s', 'vms-data-tools'),
                        $field_key
                    );
                }
            }
        } else {
            $result['messages'][] = __('MailPoet custom field API methods are unavailable in this installation. Field verification was skipped; invite commit will still report any field-sync errors per row.', 'vms-data-tools');
        }

        foreach ($required_tags as $tag_name) {
            $tag_name = sanitize_key((string) $tag_name);
            if ($tag_name === '') {
                continue;
            }
            $segment_id = vms_dt_vio_mailpoet_get_or_create_segment_id($api, $tag_name);
            if (is_wp_error($segment_id)) {
                $result['errors'][] = $segment_id->get_error_message();
                continue;
            }

            $result['messages'][] = sprintf(
                /* translators: 1: tag name, 2: segment ID. */
                __('MailPoet tag ready: %1$s (ID %2$d)', 'vms-data-tools'),
                $tag_name,
                $segment_id
            );
        }

        $result['ok'] = empty($result['errors']);
        return $result;
    }
}

if (!function_exists('vms_dt_vio_mailpoet_find_subscriber')) {
    /**
     * @param object $api
     * @return array<string,mixed>|null
     */
    function vms_dt_vio_mailpoet_find_subscriber($api, string $email): ?array
    {
        $email = sanitize_email($email);
        if ($email === '') {
            return null;
        }

        $subscriber = vms_dt_vio_mailpoet_call($api, 'getSubscriber', array($email));
        if (is_wp_error($subscriber) || !is_array($subscriber)) {
            return null;
        }

        return $subscriber;
    }
}

if (!function_exists('vms_dt_vio_mailpoet_prepare_custom_fields_payload')) {
    /**
     * @return array<string,string>
     */
    function vms_dt_vio_mailpoet_prepare_custom_fields_payload(array $settings, string $claim_url, string $open_dates_snippet): array
    {
        $claim_field_key = sanitize_key((string) ($settings['claim_field_key'] ?? 'vms_claim_link'));
        $open_field_key = sanitize_key((string) ($settings['open_dates_field_key'] ?? 'vms_open_dates_snippet'));

        $custom = array();
        if ($claim_field_key !== '' && trim($claim_url) !== '') {
            $custom[$claim_field_key] = $claim_url;
        }
        if ($open_field_key !== '' && trim($open_dates_snippet) !== '') {
            $custom[$open_field_key] = $open_dates_snippet;
        }

        return $custom;
    }
}

if (!function_exists('vms_dt_vio_mailpoet_sync_invite')) {
    /**
     * @return array<string,mixed>|WP_Error
     */
    function vms_dt_vio_mailpoet_sync_invite(
        string $email,
        string $vendor_name,
        string $tag_name,
        string $claim_url,
        string $open_dates_snippet,
        array $settings
    ) {
        $email = sanitize_email($email);
        if ($email === '') {
            return new WP_Error('vms_dt_vio_mailpoet_email_invalid', __('Invite email is invalid.', 'vms-data-tools'));
        }

        $tag_name = sanitize_key($tag_name);
        if ($tag_name === '') {
            return new WP_Error('vms_dt_vio_mailpoet_tag_invalid', __('Invite tag is invalid.', 'vms-data-tools'));
        }

        $api = vms_dt_vio_mailpoet_api();
        if (is_wp_error($api)) {
            return $api;
        }

        $segment_id = vms_dt_vio_mailpoet_get_or_create_segment_id($api, $tag_name);
        if (is_wp_error($segment_id)) {
            return $segment_id;
        }

        $custom_fields = vms_dt_vio_mailpoet_prepare_custom_fields_payload($settings, $claim_url, $open_dates_snippet);

        $existing = vms_dt_vio_mailpoet_find_subscriber($api, $email);
        $subscriber_id = absint($existing['id'] ?? 0);
        $create_on_invite = !empty($settings['create_subscribers_on_invite']);

        if ($subscriber_id <= 0 && !$create_on_invite) {
            return new WP_Error(
                'vms_dt_vio_mailpoet_subscriber_missing',
                __('MailPoet subscriber was not found and on-invite subscriber creation is disabled.', 'vms-data-tools')
            );
        }

        $payload = array(
            'email' => $email,
            'first_name' => trim($vendor_name),
            'status' => 'subscribed',
            'custom_fields' => $custom_fields,
        );

        if ($subscriber_id > 0) {
            if (!vms_dt_vio_mailpoet_can_call($api, 'updateSubscriber')) {
                return new WP_Error(
                    'vms_dt_vio_mailpoet_update_subscriber_missing',
                    __('MailPoet updateSubscriber method is unavailable for existing subscribers.', 'vms-data-tools')
                );
            }

            $updated = vms_dt_vio_mailpoet_call($api, 'updateSubscriber', array($subscriber_id, $payload));
            if (is_wp_error($updated)) {
                return $updated;
            }
        } else {
            if (!vms_dt_vio_mailpoet_can_call($api, 'addSubscriber')) {
                return new WP_Error('vms_dt_vio_mailpoet_add_subscriber_missing', __('MailPoet addSubscriber method is unavailable.', 'vms-data-tools'));
            }

            $created = vms_dt_vio_mailpoet_call($api, 'addSubscriber', array($payload));
            if (is_wp_error($created) || !is_array($created)) {
                return is_wp_error($created)
                    ? $created
                    : new WP_Error('vms_dt_vio_mailpoet_create_unknown', __('MailPoet subscriber creation returned an unknown response.', 'vms-data-tools'));
            }

            $subscriber_id = absint($created['id'] ?? 0);
            if ($subscriber_id <= 0) {
                // Attempt a follow-up lookup when API response omits id.
                $lookup = vms_dt_vio_mailpoet_find_subscriber($api, $email);
                $subscriber_id = absint($lookup['id'] ?? 0);
            }
        }

        if ($subscriber_id <= 0) {
            return new WP_Error('vms_dt_vio_mailpoet_subscriber_id_missing', __('MailPoet subscriber ID is missing.', 'vms-data-tools'));
        }

        // Apply invite tag/list/segment to trigger MailPoet automation.
        $subscribe_methods = array(
            'addSubscriberToSegment',
            'subscribeToSegment',
            'addSubscriberToTag',
            'subscribeToTag',
            'addSubscriberToList',
            'subscribeToList',
        );
        $subscribed = false;
        $subscribe_errors = array();
        foreach ($subscribe_methods as $method) {
            if (!vms_dt_vio_mailpoet_can_call($api, $method)) {
                continue;
            }
            $seg_result = vms_dt_vio_mailpoet_call($api, $method, array($subscriber_id, (int) $segment_id));
            if (is_wp_error($seg_result)) {
                $subscribe_errors[] = $seg_result->get_error_message();
                continue;
            }
            $subscribed = true;
            break;
        }
        if (!$subscribed) {
            $detail = '';
            if (!empty($subscribe_errors)) {
                $detail = ' ' . implode('; ', array_unique(array_filter(array_map('sanitize_text_field', $subscribe_errors))));
            }
            return new WP_Error(
                'vms_dt_vio_mailpoet_segment_method_missing',
                __('MailPoet segment subscription method is unavailable.', 'vms-data-tools') . $detail
            );
        }

        return array(
            'subscriber_id' => $subscriber_id,
            'segment_id' => (int) $segment_id,
            'tag' => $tag_name,
        );
    }
}
