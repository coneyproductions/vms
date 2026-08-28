<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/wporg-prefix-b4.php';

$root = dirname(__DIR__);
$mode = $argv[1] ?? '--check-browser-assets';
if (!in_array($mode, array('--plan-browser-assets', '--apply-browser-assets', '--check-browser-assets'), true)) {
	fwrite(STDERR, "Usage: php scripts/apply-wporg-prefix-b4.php [--plan-browser-assets|--apply-browser-assets|--check-browser-assets]\n");
	exit(2);
}

$map = BVMGR_WPORG_Prefix_B4::loadJson($root . '/' . BVMGR_WPORG_Prefix_B4::MAP_PATH);
$browser = array();
foreach ((array) ($map['categories']['browser_globals'] ?? array()) as $row) {
	$browser[(string) $row['legacy_identifier']] = (string) $row['canonical_identifier'];
}
uksort($browser, static fn(string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));

$handleSites = array();
foreach ((array) ($map['categories']['asset_handles'] ?? array()) as $row) {
	$legacy = (string) $row['legacy_identifier'];
	$canonical = (string) $row['canonical_identifier'];
	foreach (array('registration_source_sites', 'dependency_sites', 'consumer_sites') as $siteType) {
		foreach ((array) ($row[$siteType] ?? array()) as $site) {
			$file = (string) $site['file'];
			$line = (int) $site['line'];
			$context = (string) $site['context'];
			$handleSites[$file][$line][$context][$legacy] = $canonical;
		}
	}
}

$phpFiles = array_keys($handleSites);
foreach ((array) ($map['categories']['browser_globals'] ?? array()) as $row) {
	foreach (array('producer_sites', 'consumer_sites') as $siteType) {
		foreach ((array) ($row[$siteType] ?? array()) as $site) {
			if (str_ends_with((string) $site['file'], '.php')) {
				$phpFiles[] = (string) $site['file'];
			}
		}
	}
}
$phpFiles = array_values(array_unique($phpFiles));
sort($phpFiles, SORT_STRING);

$jsFiles = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/assets', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
	if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'js') {
		$jsFiles[] = substr(str_replace('\\', '/', $fileInfo->getPathname()), strlen($root) + 1);
	}
}
sort($jsFiles, SORT_STRING);

$changed = array();
$counts = array('browser' => 0, 'handles' => 0);
foreach ($phpFiles as $relative) {
	$path = $root . '/' . $relative;
	$source = (string) file_get_contents($path);
	$tokens = token_get_all($source);
	$tokenCount = count($tokens);
	$replaceToken = array();

	for ($i = 0; $i < $tokenCount; $i++) {
		$token = $tokens[$i];
		if (!is_array($token) || $token[0] !== T_STRING) {
			continue;
		}
		$name = strtolower($token[1]);
		$siteMap = array();
		foreach ((array) ($handleSites[$relative] ?? array()) as $siteContexts) {
			foreach ($siteContexts as $context => $pairs) {
				if (strtolower((string) $context) === $name) {
					$siteMap += $pairs;
				}
			}
		}
		if ($siteMap === array()) {
			continue;
		}
		$open = $i + 1;
		while ($open < $tokenCount && is_array($tokens[$open]) && in_array($tokens[$open][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			$open++;
		}
		if (($tokens[$open] ?? null) !== '(') {
			throw new RuntimeException("Expected call opening parenthesis at {$relative}:{$token[2]} ({$name}).");
		}
		$depth = 0;
		for ($j = $open; $j < $tokenCount; $j++) {
			$text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
			if ($text === '(') {
				$depth++;
			} elseif ($text === ')') {
				$depth--;
				if ($depth === 0) {
					break;
				}
			}
			if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_CONSTANT_ENCAPSED_STRING) {
				continue;
			}
			$value = eval('return ' . $tokens[$j][1] . ';');
			if (is_string($value) && isset($siteMap[$value])) {
				$quote = $tokens[$j][1][0];
				$replaceToken[$j] = $quote . $siteMap[$value] . $quote;
			}
		}
	}

	// Four reviewed data-flow sites carry handles through variables/helper
	// arguments rather than a direct WordPress asset API call.
	foreach ((array) ($handleSites[$relative] ?? array()) as $line => $contexts) {
		foreach ($contexts as $context => $pairs) {
			if (!in_array($context, array('dynamic $handle passed to wp_enqueue_script', 'dynamic dependency array append', 'dynamic dependency array merge'), true)) {
				continue;
			}
			foreach ($tokens as $index => $token) {
				if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING || (int) $token[2] !== (int) $line) {
					continue;
				}
				$value = eval('return ' . $token[1] . ';');
				if (is_string($value) && isset($pairs[$value])) {
					$quote = $token[1][0];
					$replaceToken[$index] = $quote . $pairs[$value] . $quote;
				}
			}
		}
	}

	$rewritten = '';
	foreach ($tokens as $index => $token) {
		if (isset($replaceToken[$index])) {
			$rewritten .= $replaceToken[$index];
			$counts['handles']++;
		} else {
			$rewritten .= is_array($token) ? $token[1] : $token;
		}
	}
	foreach ($browser as $legacy => $canonical) {
		$hits = substr_count($rewritten, $legacy);
		if ($hits > 0) {
			$rewritten = str_replace($legacy, $canonical, $rewritten);
			$counts['browser'] += $hits;
		}
	}
	if ($rewritten !== $source) {
		$changed[$relative] = $rewritten;
	}
}

foreach ($jsFiles as $relative) {
	$path = $root . '/' . $relative;
	$source = (string) file_get_contents($path);
	$rewritten = $source;
	foreach ($browser as $legacy => $canonical) {
		$hits = substr_count($rewritten, $legacy);
		if ($hits > 0) {
			$rewritten = str_replace($legacy, $canonical, $rewritten);
			$counts['browser'] += $hits;
		}
	}
	if ($rewritten !== $source) {
		$changed[$relative] = $rewritten;
	}
}

if ($mode === '--check-browser-assets') {
	if ($changed !== array()) {
		fwrite(STDERR, 'B4 browser/asset cutover is incomplete in ' . count($changed) . " files.\n");
		exit(1);
	}
	echo "B4 browser/asset semantic cutover check passed.\n";
	exit(0);
}

echo 'B4 browser/asset plan: files=' . count($changed) . ' browser_replacements=' . $counts['browser'] . ' handle_replacements=' . $counts['handles'] . PHP_EOL;
foreach (array_keys($changed) as $relative) {
	echo $relative . PHP_EOL;
}
if ($mode === '--plan-browser-assets') {
	exit(0);
}
foreach ($changed as $relative => $rewritten) {
	if (file_put_contents($root . '/' . $relative, $rewritten) === false) {
		throw new RuntimeException('Unable to write ' . $relative);
	}
}
