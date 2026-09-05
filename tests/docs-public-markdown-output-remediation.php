<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!function_exists('esc_html')) {
	function esc_html($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url): string
	{
		$url = trim((string) $url);
		if ($url === '' || preg_match('~^(?:javascript|data|vbscript):~i', $url)) {
			return '';
		}

		return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses($html, $allowed_html): string
	{
		return (string) preg_replace_callback(
			'~<(/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>~',
			static function (array $matches) use ($allowed_html): string {
				$closing = $matches[1] === '/';
				$tag = strtolower((string) $matches[2]);
				if (!array_key_exists($tag, $allowed_html)) {
					return '';
				}
				if ($closing) {
					return '</' . $tag . '>';
				}

				$attrs = '';
				$allowed_attrs = is_array($allowed_html[$tag]) ? $allowed_html[$tag] : array();
				if ($allowed_attrs !== array()) {
					preg_match_all(
						'~\s+([a-zA-Z0-9:-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~',
						(string) ($matches[3] ?? ''),
						$attr_matches,
						PREG_SET_ORDER
					);

					foreach ($attr_matches as $attr_match) {
						$name = strtolower((string) $attr_match[1]);
						if (!array_key_exists($name, $allowed_attrs)) {
							continue;
						}

						$value = '';
						if (array_key_exists(2, $attr_match) && $attr_match[2] !== '') {
							$value = (string) $attr_match[2];
						} elseif (array_key_exists(3, $attr_match) && $attr_match[3] !== '') {
							$value = (string) $attr_match[3];
						} elseif (array_key_exists(4, $attr_match) && $attr_match[4] !== '') {
							$value = (string) $attr_match[4];
						}

						if ($name === 'href' && preg_match('~^\s*(?:javascript|data|vbscript):~i', html_entity_decode($value, ENT_QUOTES, 'UTF-8'))) {
							continue;
						}

						$attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
					}
				}

				return '<' . $tag . $attrs . '>';
			},
			(string) $html
		);
	}
}

require_once dirname(__DIR__) . '/includes/docs-render.php';

$pluginRoot = dirname(__DIR__);
$docsPublicSource = file_get_contents($pluginRoot . '/includes/docs-public.php');
$docsRenderSource = file_get_contents($pluginRoot . '/includes/docs-render.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($docsPublicSource) && $docsPublicSource !== '', 'Public docs source should be readable.');
$assert(is_string($docsRenderSource) && $docsRenderSource !== '', 'Docs renderer source should be readable.');

$assert(!preg_match('~echo\s+bvmgr_docs_render_markdown\s*\(~', $docsPublicSource), 'Public docs should not directly echo an uncontracted Markdown renderer result.');
$assert(preg_match('~\$rendered_markdown\s*=\s*bvmgr_docs_render_markdown\s*\(\s*\$md\s*\)\s*;~', $docsPublicSource) === 1, 'Public docs should retain a separate rendered Markdown value before the final sink.');
$assert(preg_match('~echo\s+wp_kses\s*\(\s*\$rendered_markdown\s*,\s*bvmgr_docs_rendered_allowed_html\s*\(\s*\)\s*\)\s*;~s', $docsPublicSource) === 1, 'Public docs should apply the dedicated rendered Markdown allowlist at the output boundary.');
$assert(strpos($docsPublicSource, 'esc_html(bvmgr_docs_render_markdown(') === false, 'Public docs should not text-escape the finished rendered document.');
$assert(strpos($docsPublicSource, 'esc_html($rendered_markdown') === false, 'Public docs should not text-escape the finished rendered document value.');
$assert(strpos($docsPublicSource . $docsRenderSource, 'wp_kses_post(') === false, 'Docs Markdown output should not use the broad post-content allowlist helper.');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $docsPublicSource . $docsRenderSource), 'Docs Markdown output should not use the broad post allowlist.');
$assert(strpos($docsRenderSource, 'wp_kses($text, bvmgr_docs_inline_allowed_html())') !== false, 'Inline Markdown parsing should keep the narrow inline allowlist.');

$normalizeAllowedHtml = static function (array $allowed_html): array {
	ksort($allowed_html);
	foreach ($allowed_html as $tag => $attrs) {
		if (is_array($attrs)) {
			ksort($attrs);
			$allowed_html[$tag] = $attrs;
		}
	}

	return $allowed_html;
};

$expectedInlineAllowed = array(
	'a' => array('href' => true, 'target' => true, 'rel' => true),
	'strong' => array(),
	'em' => array(),
	'code' => array(),
);
$expectedRenderedAllowed = $expectedInlineAllowed + array(
	'h1' => array(),
	'h2' => array(),
	'h3' => array(),
	'h4' => array(),
	'h5' => array(),
	'h6' => array(),
	'p' => array(),
	'ul' => array(),
	'li' => array(),
	'pre' => array(),
);

$assert($normalizeAllowedHtml(bvmgr_docs_inline_allowed_html()) === $normalizeAllowedHtml($expectedInlineAllowed), 'Inline Markdown allowlist should contain only the renderer-created inline tags and attributes.');
$assert($normalizeAllowedHtml(bvmgr_docs_rendered_allowed_html()) === $normalizeAllowedHtml($expectedRenderedAllowed), 'Rendered Markdown allowlist should contain only the renderer-created document tags and attributes.');

$markdown = <<<'MARKDOWN'
# Heading

Paragraph with **bold**, *italic*, `inline <b>x</b>`, and [safe link](https://example.com/docs).

- First item

```
<div onclick="evil()">code</div>
```

<script>alert(1)</script>
<span onclick="alert(1)">bad</span>
<a href="javascript:alert(1)" onclick="alert(1)">raw bad link</a>
MARKDOWN;

$rendered = bvmgr_docs_render_markdown($markdown);
$safe = wp_kses($rendered, bvmgr_docs_rendered_allowed_html());

$assert(strpos($safe, '<h1>Heading</h1>') !== false, 'Rendered Markdown should preserve headings.');
$assert(strpos($safe, '<p>Paragraph with <strong>bold</strong>, <em>italic</em>, <code>inline &lt;b&gt;x&lt;/b&gt;</code>, and <a href="https://example.com/docs" target="_blank" rel="noopener noreferrer">safe link</a>.</p>') !== false, 'Rendered Markdown should preserve paragraphs, emphasis, inline code, and safe link attributes.');
$assert(strpos($safe, '<ul>') !== false && strpos($safe, '<li>First item</li>') !== false, 'Rendered Markdown should preserve unordered lists and list items.');
$assert(strpos($safe, '<pre><code>&lt;div onclick=&quot;evil()&quot;&gt;code&lt;/div&gt;</code></pre>') !== false, 'Rendered Markdown should preserve fenced code as escaped text.');
$assert(strpos($safe, '<script') === false && strpos($safe, '</script>') === false, 'Unsupported script markup should not survive.');
$assert(strpos($safe, '<span') === false && strpos($safe, '</span>') === false, 'Unsupported span markup should not survive.');
$assert(preg_match('~<[^>]+\son[a-z]+\s*=~i', $safe) === 0, 'Event-handler attributes should not survive as HTML attributes.');
$assert(stripos($safe, 'javascript:') === false, 'Unsafe link protocols should not survive.');
$assert(strpos($safe, '<div onclick=') === false, 'HTML-like code text should not execute as HTML.');

fwrite(STDOUT, "Docs public Markdown output remediation OK.\n");
