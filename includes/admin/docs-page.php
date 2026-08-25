<?php
if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', 'vms_docs_admin_menu');

function vms_docs_query_arg($key) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only docs routing only changes admin display state.
    return vms_request_read_scalar($_GET, (string) $key);
}

function vms_docs_admin_menu() {
    // Parent slug depends on how you registered your VMS menu.
    // If your VMS top-level menu slug is different, change 'vms' below to match.
    $parent_slug = 'vms-dashboard';

    add_submenu_page(
        $parent_slug,
		'Backstage Venue Manager Docs',
        'Docs',
        'manage_options',
        'vms-docs',
        'vms_docs_admin_page_render'
    );
}

function vms_docs_admin_page_render() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to view this page.');
    }

    $index = vms_docs_index();

    $active_module = sanitize_key(vms_docs_query_arg('mod'));
    if ($active_module === '') {
        $active_module = 'vms';
    }
    $active_slug = sanitize_title(vms_docs_query_arg('doc'));

    // Pick first available module if requested one is missing.
    if (empty($index[$active_module])) {
        $mods = array_keys($index);
        $active_module = $mods ? $mods[0] : 'vms';
    }

    $docs = isset($index[$active_module]) ? $index[$active_module] : [];

    // Find doc by slug.
    $active_doc = null;
    if ($active_slug) {
        foreach ($docs as $d) {
            if ($d['slug'] === $active_slug) { $active_doc = $d; break; }
        }
    }
    if (!$active_doc && !empty($docs)) {
        $active_doc = $docs[0];
    }

    echo '<div class="wrap vms-docs-admin">';
    echo '<h1>Backstage Venue Manager Documentation</h1>';

    echo '<div class="vms-docs-layout">';

    // Left: module + doc nav
    echo '<div class="vms-docs-sidebar">';
    echo '<h2 class="vms-docs-no-top">Modules</h2>';
    echo '<ul class="vms-docs-list">';
    foreach ($index as $mod => $list) {
        $label = $list[0]['module_label'] ?? $mod;
        $url = admin_url('admin.php?page=vms-docs&mod=' . urlencode($mod));
        $is_active = ($mod === $active_module);
        echo '<li>';
        echo $is_active ? '<strong>' : '';
        echo '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        echo $is_active ? '</strong>' : '';
        echo '</li>';
    }
    echo '</ul>';

    echo '<h2>Docs</h2>';
    if (!$docs) {
        echo '<p>No docs found for this module.</p>';
    } else {
        echo '<ul class="vms-docs-list">';
        foreach ($docs as $d) {
            $url = admin_url('admin.php?page=vms-docs&mod=' . urlencode($active_module) . '&doc=' . urlencode($d['slug']));
            $is_active = ($active_doc && $d['slug'] === $active_doc['slug']);
            echo '<li>';
            echo $is_active ? '<strong>' : '';
            echo '<a href="' . esc_url($url) . '">' . esc_html($d['title']) . '</a>';
            echo $is_active ? '</strong>' : '';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    // Right: doc content
    echo '<div class="vms-docs-content">';

    if (!$active_doc) {
        echo '<p>No documentation selected.</p>';
    } else {
        echo '<h2 class="vms-docs-no-top">' . esc_html($active_doc['title']) . '</h2>';

        if (!empty($active_doc['since'])) {
            echo '<p class="vms-docs-since">Applies since: <code>' . esc_html($active_doc['since']) . '</code></p>';
        }

        $md = vms_docs_get_markdown($active_doc['file']);
        echo wp_kses(
            vms_docs_render_markdown($md),
            array(
                'a' => array(
                    'href' => true,
                    'rel' => true,
                    'target' => true,
                ),
                'code' => array(),
                'em' => array(),
                'h1' => array(),
                'h2' => array(),
                'h3' => array(),
                'h4' => array(),
                'h5' => array(),
                'h6' => array(),
                'li' => array(),
                'p' => array(),
                'pre' => array(),
                'strong' => array(),
                'ul' => array(),
            )
        );
    }

    echo '</div>'; // right
    echo '</div>'; // flex
    echo '</div>'; // wrap
}
