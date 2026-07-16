<?php

declare(strict_types=1);

/**
 * CURATED factual map for Vatican City (VA): the 3-digit bank code -> name +
 * BIC, consumed by {@see \Daycry\Iban\Import\Importers\VaticanCityImporter}.
 *
 * This is a project-authored factual map, NOT a copy of any source document.
 * Vatican City publishes no machine-readable bank directory; its supervisor
 * (ASIF) lists only a single supervised entity. The real universe is exactly
 * ONE bank -- the Istituto per le Opere di Religione (IOR), the only financial
 * institution issuing Vatican IBANs -- so the narrow curated-data exception
 * applies (see `docs/licensing.md`, "Curated micro-jurisdiction bank data",
 * and the facts-vs-compilation methodology in `docs/registry-authoring.md`).
 * The single fact below (the `001` bank code, legal name and BIC) is an
 * independently transcribed public fact, cross-checked against two official
 * Vatican IBANs (Basilica di San Pietro `VA90001000000011626001` and the
 * Dicastero per la Comunicazione `VA96001000000046371002`, both carrying bank
 * code `001`), not a redistribution of any single source.
 *
 * `bank_code` is the 3-digit code, kept as a STRING so its leading zeros
 * survive (the Vatican code is `001`).
 *
 * Refresh cadence: annual (a new supervised institution would be a factual
 * change to re-transcribe). Last confirmed: 2026-07.
 *
 * FRAMEWORK-FREE: a plain PHP data file with no dependency on the framework
 * package this library adapts for; it lives under the recursively-guarded
 * `Import/Importers` subtree, so `tests/Architecture/CoreIsFrameworkFreeTest.php`
 * covers it.
 *
 * @return list<array{bank_code: string, name: string, bic: string|null}>
 */
return [
    ['bank_code' => '001', 'name' => 'Istituto per le Opere di Religione (IOR)', 'bic' => 'IOPRVAVX'],
];
