<?php
if (!defined('ABSPATH')) { exit; }

add_action('wp_enqueue_scripts', 'bvmgr_docs_public_assets', 15);

function bvmgr_docs_public_assets() {
    if (!get_query_var('vms_doc_module')) {
        return;
    }

    bvmgr_enqueue_public_style_stack();
    bvmgr_enqueue_style_asset(
        'bvmgr-docs-public',
        'assets/css/docs-public.css',
        array('bvmgr-ui')
    );
}

add_action('init', 'bvmgr_docs_register_rewrite');

function bvmgr_docs_register_rewrite() {
    add_rewrite_rule(
        '^docs/vms/([^/]+)/([^/]+)/?$',
        'index.php?vms_doc_module=$matches[1]&vms_doc_slug=$matches[2]',
        'top'
    );

    add_rewrite_rule(
        '^docs/vms/([^/]+)/?$',
        'index.php?vms_doc_module=vms&vms_doc_slug=$matches[1]',
        'top'
    );
}

add_filter('query_vars', function($vars) {
    $vars[] = 'vms_doc_module';
    $vars[] = 'vms_doc_slug';
    return $vars;
});

add_action('template_redirect', 'bvmgr_docs_public_render');

function bvmgr_docs_public_render() {
    $module = get_query_var('vms_doc_module');
    $slug   = get_query_var('vms_doc_slug');

    if (!$module || !$slug) {
        return;
    }

    $index = bvmgr_docs_index();
    if (empty($index[$module])) {
        wp_die('Documentation not found.');
    }

    $doc = null;
    foreach ($index[$module] as $d) {
        if ($d['slug'] === $slug) {
            $doc = $d;
            break;
        }
    }

    if (!$doc) {
        wp_die('Documentation not found.');
    }

    status_header(200);
    nocache_headers();

    get_header();

    echo '<main class="vms-docs-public">';
    echo '<h1>' . esc_html($doc['title']) . '</h1>';

    if (!empty($doc['since'])) {
        echo '<p class="vms-docs-meta">Applies since v' . esc_html($doc['since']) . '</p>';
    }

    $md = bvmgr_docs_get_markdown($doc['file']);
    $rendered_markdown = bvmgr_docs_render_markdown($md);
    echo wp_kses($rendered_markdown, bvmgr_docs_rendered_allowed_html());

    echo '</main>';

    get_footer();

    exit;
}
