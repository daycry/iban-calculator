<?php

declare(strict_types=1);

/**
 * CURATED factual map for Andorra (AD): 4-digit "Entitat" bank code -> name +
 * BIC, consumed by {@see \Daycry\Iban\Import\Importers\AndorranBankingImporter}.
 *
 * This is a project-authored factual map, NOT a copy of any source document.
 * Andorra publishes its "Codificació de les oficines bancàries - Format IBAN"
 * only as a rotating-URL PDF, and this package deliberately ships no PDF
 * reader; but the real universe is just THREE banks / FOUR codes (a small,
 * stable set), so the narrow curated-data exception applies -- see
 * `docs/licensing.md` ("Curated micro-jurisdiction bank data") and the
 * facts-vs-compilation methodology in `docs/registry-authoring.md`. The four
 * rows below are independently transcribed public facts (the Entitat code,
 * legal name and BIC), cross-checked against EPC/SWIFT for the BIC, not a
 * redistribution of any single source.
 *
 * `bank_code` is the 4-digit Entitat code, kept as a STRING so its leading
 * zeros survive (every Andorran code starts `000`). MoraBanc holds BOTH `0007`
 * and `0008`, each mapping to the same institution + BIC.
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
    ['bank_code' => '0001', 'name' => 'Andorra Banc Agrícol Reig, S.A. (Andbank)', 'bic' => 'BACAADAD'],
    ['bank_code' => '0003', 'name' => 'Crèdit Andorrà, S.A. (Creand)', 'bic' => 'CRDAADAD'],
    ['bank_code' => '0007', 'name' => 'MoraBanc, S.A.', 'bic' => 'BSAAADAD'],
    ['bank_code' => '0008', 'name' => 'MoraBanc, S.A.', 'bic' => 'BSAAADAD'],
];
