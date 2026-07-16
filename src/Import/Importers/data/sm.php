<?php

declare(strict_types=1);

/**
 * CURATED factual map for San Marino (SM): the 5-digit ABI bank code -> name +
 * BIC, consumed by {@see \Daycry\Iban\Import\Importers\SanMarinoImporter}.
 *
 * This is a project-authored factual map, NOT a copy of any source document.
 * San Marino uses the Italian ABI numbering, but the Italian ABI directories
 * do NOT list Sammarinese banks -- the only source is the Banca Centrale della
 * Repubblica di San Marino's "Operating Banks" page, a tiny, stable set of
 * FOUR banks. This package deliberately ships no HTML/PDF-bundled data, so
 * rather than scrape four rows the narrow curated-data exception applies (see
 * `docs/licensing.md`, "Curated micro-jurisdiction bank data", and the
 * facts-vs-compilation methodology in `docs/registry-authoring.md`). The four
 * rows below are independently transcribed public facts (the 5-digit ABI code,
 * legal name and BIC), cross-checked against EPC/SWIFT for the BIC, not a
 * redistribution of any single source.
 *
 * `bank_code` is the 5-digit ABI code, kept as a STRING so its leading zeros
 * survive (Sammarinese ABIs start `0` -- e.g. `03034`).
 *
 * Refresh cadence: annual (a merger/new licence is a factual change to
 * re-transcribe). Last confirmed: 2026-07.
 *
 * FRAMEWORK-FREE: a plain PHP data file with no dependency on the framework
 * package this library adapts for; it lives under the recursively-guarded
 * `Import/Importers` subtree, so `tests/Architecture/CoreIsFrameworkFreeTest.php`
 * covers it.
 *
 * @return list<array{bank_code: string, name: string, bic: string|null}>
 */
return [
    ['bank_code' => '03034', 'name' => 'Banca Agricola Commerciale della Repubblica di San Marino', 'bic' => 'BASMSMSM'],
    ['bank_code' => '08540', 'name' => 'Banca di San Marino', 'bic' => 'MAOISMSM'],
    ['bank_code' => '03287', 'name' => 'Banca Sammarinese di Investimento', 'bic' => 'BSDISMSD'],
    ['bank_code' => '06067', 'name' => 'Cassa di Risparmio della Repubblica di San Marino', 'bic' => 'CSSMSMSM'],
];
