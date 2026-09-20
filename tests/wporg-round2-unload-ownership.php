<?php
/** Actual JavaScript event ownership; no browser navigation or network transport. */
passthru('node ' . escapeshellarg(__DIR__ . '/helpers/round2-unload-ownership.mjs'), $status);
if ($status) throw new RuntimeException('BVM must preserve foreign unload handlers.');
