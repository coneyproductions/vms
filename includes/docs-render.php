<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Very small Markdown renderer.
 * Supports: headings, paragraphs, unordered lists, code fences, inline code, links, bold, italics.
 * Note: This is not a full Markdown implementation. It’s designed for stable docs output.
 */

function vms_docs_render_markdown($md) {
    $md = str_replace("\r\n", "\n", (string)$md);

    $lines = explode("\n", $md);
    $html = '';
    $in_code = false;
    $code_buf = '';
    $in_ul = false;

    $flush_ul = function() use (&$html, &$in_ul) {
        if ($in_ul) {
            $html .= "</ul>\n";
            $in_ul = false;
        }
    };

    foreach ($lines as $line) {
        // Code fence ``` toggles code block.
        if (preg_match('/^\s*```/', $line)) {
            if (!$in_code) {
                $flush_ul();
                $in_code = true;
                $code_buf = '';
            } else {
                $in_code = false;
                $html .= "<pre><code>" . esc_html(rtrim($code_buf, "\n")) . "</code></pre>\n";
                $code_buf = '';
            }
            continue;
        }

        if ($in_code) {
            $code_buf .= $line . "\n";
            continue;
        }

        $trim = trim($line);

        // Blank line: close lists and separate paragraphs.
        if ($trim === '') {
            $flush_ul();
            continue;
        }

        // Headings.
        if (preg_match('/^(#{1,6})\s+(.*)$/', $trim, $m)) {
            $flush_ul();
            $level = strlen($m[1]);
            $text = vms_docs_render_inlines($m[2]);
            $html .= "<h{$level}>{$text}</h{$level}>\n";
            continue;
        }

        // Unordered list items.
        if (preg_match('/^[-*]\s+(.*)$/', $trim, $m)) {
            if (!$in_ul) {
                $html .= "<ul>\n";
                $in_ul = true;
            }
            $html .= "<li>" . vms_docs_render_inlines($m[1]) . "</li>\n";
            continue;
        }

        // Paragraph.
        $flush_ul();
        $html .= "<p>" . vms_docs_render_inlines($trim) . "</p>\n";
    }

    // Close anything left open.
    if ($in_code) {
        $html .= "<pre><code>" . esc_html(rtrim($code_buf, "\n")) . "</code></pre>\n";
    }
    if ($in_ul) {
        $html .= "</ul>\n";
    }

    return $html;
}

function vms_docs_render_inlines($text) {
    $text = (string)$text;

    // Inline code `code`
    $text = preg_replace_callback('/`([^`]+)`/', function($m) {
        return '<code>' . esc_html($m[1]) . '</code>';
    }, $text);

    // Bold **text**
    $text = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $text);

    // Italic *text*
    $text = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $text);

    // Links [text](url)
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($m) {
        $label = esc_html($m[1]);
        $url = esc_url($m[2]);
        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
    }, $text);

    // Escape any remaining raw HTML brackets (basic safety).
    // We do this last to preserve the tags we intentionally added above.
    // This is a compromise renderer; we can harden further later.
    $text = wp_kses($text, [
        'a' => ['href' => true, 'target' => true, 'rel' => true],
        'strong' => [],
        'em' => [],
        'code' => [],
    ]);

    return $text;
}
