# IBAN format review — 28 October 2026

The local registry now recognizes 89 national prefixes. The added prefixes are BI, DJ, FK, HN,
MN, NI, OM, RU, SD, SO and YE. This adds format validation and parsing, not bank-directory coverage,
account existence checks or new national checksum algorithms. None of the additions is marked SEPA.

The [complete country coverage table](country-coverage.md) lists all 89 current prefixes and lengths,
alongside BIC validation, optional national checks and default bank importer availability.

## Method and evidence

Country coverage and the expanded BBAN character classes / lengths were checked against the
[registration authority's release 103 (September 2026)](https://www.swift.com/resource/iban-registry-pdf).
The comparison covered all 78 existing entries: only Brazil differed in accepted character classes.
Adjacent numeric segments were expanded before comparison, so token grouping differences were not
misreported as format changes. Existing parser field offsets were not comprehensively re-audited.

The implementation encodes field facts in the existing CSV schema, combines adjacent character
classes where appropriate, derives zero-based offsets, and generates synthetic examples using
MOD-97. It does not import or redistribute the external registry, its country rows, contact details,
or example-account collection. New examples are test identifiers, not payment instructions.

National documents consulted and their limits:

| Prefix | Evidence |
|---|---|
| BI | [BRB payment-system regulation and bank/branch codes](https://www.brb.bi/sites/default/files/2024-09/REGLEMENT-N%C2%BA1-2022-du14-Avril-2022.pdf), [RIB definitions](https://www.brb.bi/sites/default/files/2024-09/R%C3%A8glement%20N%C2%B0001%20du%2028-02-2020.pdf); international layout corroborated against the registration authority. |
| DJ | International profile corroborated against the registration authority; a separately published national IBAN specification was not located. |
| FK | [Standard Chartered's local bank-code service](https://www.sc.com/fk/help-centre/); detailed international profile corroborated against the registration authority. |
| HN | [BCH circular D-12/2024](https://www.bch.hn/administrativas/JUR/Marco%20Legal%20OM%202/Circular%20No.D-12-2024%20-%20Resoluci%C3%B3n%20No.204-5-2024.pdf): four-letter bank prefix and 20-character account. The national rule allows alphanumeric accounts, whereas the registered international profile restricts those 20 positions to digits; this release validates the registered profile. |
| MN | [Bank of Mongolia announcement](https://www.mongolbank.mn/mn/r/9148): 20-character country/check/bank/account structure; detailed field widths corroborated against the registration authority. |
| NI | [BCN explanation](https://bcn.gob.ni/informacion-sobre-el-iban) and [national standard](https://www.bcn.gob.ni/sites/default/files/normas_disposiciones/Reglamento%20para%20el%20Est%C3%A1ndar%20de%20Cuentas%20Bancarias.pdf): 28 characters, four-letter BIC prefix, 20 account digits. Older 32-character third-party examples are not accepted. |
| OM | [CBO specification and implementation page](https://cbo.gov.om/Pages/InternationalIBANChecker.aspx): 23 characters, three bank digits, 16 account positions. The local illustration labels the account numeric; the registered profile permits alphanumeric account positions, which this release supports. |
| RU | Detailed international profile corroborated against the registration authority: numeric bank and branch fields followed by an alphanumeric account field. A separate current national IBAN specification was not located. Registered format support is not a claim of operational adoption or payment reachability. |
| SD | [Bank of Khartoum's BBAN explanation](https://bankofkhartoum.com/sudan/news-detail/how-to-find-your-bban-for-easy-bank-transfers-in-sudan): 14 local digits and two-digit bank prefix; international profile corroborated against the registration authority. |
| SO | [CBS adoption notice](https://centralbank.gov.so/wp-content/uploads/2024/12/Pressrelease-394-IBAN.pdf) and [national payment-system description](https://centralbank.gov.so/nps/); detailed bank/branch/account widths corroborated against the registration authority. |
| YE | [CBY national IBAN booklet](https://cby-ye.com/files/673353f200706.pdf): 30 characters, four-letter bank prefix, four branch digits and 18 account positions. The registered profile permits alphanumeric account positions. |
| BR | [BCB alphanumeric CNPJ transition](https://www.bcb.gov.br/content/estabilidadefinanceira/sicornoticias/DRN%20-%20CNPJ%20Alfanum%C3%A9rico.pdf), corroborated against the September 2026 registered profile: bank identifier and final account-type/holder characters permit letters and digits. Older numeric-bank examples still validate. |

The previously suggested MIT reference libraries were inspected as cross-check candidates, but
their published coverage did not provide an independent complete reference for all additions.
They were not used as data sources. The registration comparison does not establish redistribution
permission for any external dataset; keep original third-party datasets outside this MIT package.

## Verification

Every added prefix has synthetic component tests, a valid MOD-97 sample, and negative length,
checksum and character-class cases. The existing data-integrity and per-country tests automatically
run for all 89 entries. `php bin/generate-registry.php --dry-run` checks regeneration drift.

Review the supported-prefix list and existing formats when national specifications change, rather
than waiting for a fixed annual release. Re-run the comparisons and record discrepancies here.
