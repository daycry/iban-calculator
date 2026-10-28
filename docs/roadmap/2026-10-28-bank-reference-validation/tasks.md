# Bank reference data and validation improvements

Requested scope: import operator-supplied country/bank reference data, accept the documented
Martinique IBAN/BIC exception, and normalize Unicode spacing in IBANs and BICs.

## T-01 — Unicode normalization

- Status: completed.
- RED: `NormalizerTest::testUnicodeSeparatorsAreRemovedAndTheIbanValidates` failed for all 16 Unicode separators because they survived normalization · 2026-10-28.
- RED: `BicValidatorTest::testUnicodeSeparatorsAreRemovedAndTheBicValidates` failed for all 16 Unicode separators because they survived normalization · 2026-10-28.
- Acceptance: both entry points remove the documented separators; malformed UTF-8 and other illegal characters remain rejected.
- GREEN (T-01/T-02): focused core tests: 81 tests, 140 assertions, all passing.

## T-02 — Martinique exception

- Status: completed.
- RED: `IbanBicCrossCheckerTest::testDocumentedMartiniqueBankAcceptsItsEightAndElevenCharacterBic` failed despite individually valid inputs (`FR` bank `13088`, BIC `BNPAMQMX`/`BNPAMQMXXXX`) · 2026-10-28.
- Acceptance: only the documented bank/BIC pair bypasses the country mismatch; other banks and BICs still fail.

## T-03 — Import additional bank reference data

- Status: completed.
- RED: `CommandsTest::testUpdateImportsReferenceCsvForAllCountriesWithOriginalProvenance` failed: 0 rows imported instead of 5 · 2026-10-28.
- RED: `CommandsTest::testUpdateReferenceCsvCanPreviewAndSelectOneCountry` failed: command reported no importer instead of a dry-run count · 2026-10-28.
- RED: `CommandsTest::testUpdateReferenceCsvEnrichesWithoutErasingExistingBankDetails` failed: the BIC remained null · 2026-10-28.
- RED: `CommandsTest::testUpdateReferenceCsvRequiresAFileAndRefusesPruning` failed: missing file returned success · 2026-10-28.
- RED: `ImportRunnerTest::testPartialUpdatesKeepOmittedFieldsAndExplicitNullStillClearsThem` failed: omitted BIC/city/reachability were cleared on update · 2026-10-28.
- RED: `CommandsTest::testUpdateReferenceCsvCannotAccidentallyRunLiveImportersWithAll` and `testUpdateReferenceCsvReportsDatabaseFailuresAsErrors` returned success instead of an error · 2026-10-28.
- Design: an opt-in `iban:update --source=reference-csv --file=...` import of annotated, multi-country CSVs. Keep externally compiled data in the consuming application; retain row-level source version/license, merge without destroying unrelated bank attributes, validate the complete file before writing, and support dry-run and country selection.
- Acceptance: leading zeros, row provenance and BICs survive an idempotent import; malformed input fails without partial writes; no writes to external application databases.
- GREEN: focused tests passed (155 tests / 487 assertions); malformed and duplicate input, country selection, dry-run, original row attribution, omitted attributes, explicit nulls and CLI error exits covered.
- Full suite: `php vendor/bin/phpunit --no-coverage` — 1,429 tests / 8,326 assertions, all passing.
- Final checks: PHPStan level 8 — no errors; PHP-CS-Fixer dry-run — 0/224 files need fixes; `git diff --check` — clean. After removing a redundant CLI condition flagged by PHPStan, the eight reference-CSV command tests passed again (25 assertions).
- TDD n/a: documentation and changelog prose.

## T-04 — Global IBAN format coverage

- Status: completed.
- Authorized follow-up: complete the 89 registered national IBAN formats, review existing formats, and add the optional external verification layer with distinct outcomes.
- RED: `GlobalIbanCoverageTest::testAdditionalCountriesValidateAndParseTheirComponents` failed for BI/DJ/FK/HN/MN/NI/OM/RU/SD/SO/YE with `unknown_country` · 2026-10-28.
- RED: `GenerateRegistryTest::testRegistryFactsCsvHas89DataRows` expected 90 lines including the header, got 79 · 2026-10-28.
- RED: `GlobalIbanCoverageTest::testBrazilAcceptsTheAlphanumericBankAndAccountTypeIntroducedIn2026` rejected a checksum-valid synthetic Brazilian IBAN with letters in its bank code · 2026-10-28.
- Audit: compared the expanded character classes and lengths of all 78 existing entries against the published national profiles in registration-authority release 103 (September 2026). Only Brazil differed; numeric-token group boundaries were normalized for comparison. This was a comparison, not an import of registry rows. New examples are synthetic and fields are independently encoded.
- Acceptance: synthetic examples and parsed components for all additions; rejection of bad structure/length/checksum; current Brazilian alphanumeric profile and old numeric examples both accepted; generated registry stays reproducible.
- GREEN: focused registry/country checks passed (502 tests / 2,178 assertions); full-suite and regeneration checks below also pass. Source comparison and limitations are in `docs/registry-refresh-2026-10.md`.

## T-05 — Optional verification and explicit outcomes

- Status: completed.
- API design: additive `Iban::verifyIban()` / `verifyBic()` with an optional fourth constructor dependency `VerificationProviderInterface`. `VerificationResult` preserves local validation and reports format-valid, format-invalid, unsupported-country, externally-validated, external-rejected or unavailable. `ExternalVerification` carries a nullable external verdict, source and optional directory existence/connectivity; IBAN validation never claims account existence.
- RED: `VerificationTest::testOfflineVerificationDistinguishesValidInvalidAndUnsupported` failed because `verifyIban` did not exist · 2026-10-28.
- RED: the three new provider verification tests failed because `IbanComProvider::verifyIban` / `verifyBic` did not exist · 2026-10-28.
- RED: `ServicesTest::testConfiguredIbanComKeyEnablesExplicitVerificationButKeepsValidationOffline` returned `format_valid` instead of the configured provider's directory result · 2026-10-28.
- Acceptance: existing boolean validators remain offline; only the new explicit methods may contact the configured provider; invalid local inputs never contact it; access/network/protocol failures remain distinct from rejected identifiers; BIC directory presence and connectivity stay separate; no live paid API calls during development.
- GREEN: verification/provider/service tests passed (38 tests / 139 assertions), including empty credentials, explicit access errors, rate limits, network exceptions, malformed replies, wrong-BIC echoes/records, and positive/inactive BIC directory records.
- Final verification: `php vendor/bin/phpunit --no-coverage` — **1,505 tests / 8,572 assertions**, all passing; PHPStan level 8 — no errors; PHP-CS-Fixer dry-run — **0/230** files need fixes; `php bin/generate-registry.php --dry-run` — no drift; `git diff --check` — clean.
- All external verification tests use canned responses. No credential was added and no paid validation endpoint was called. Provider credentials are configured only by the consuming application.
- TDD n/a: documentation, source notes and changelog prose.

## Publication hygiene

- Only generic package documentation and synthetic reference CSV fixtures are published.
- External application files, private bank-reference datasets and local agent configuration are excluded.
- Removed internal application identifiers and development-source descriptions before publication.

## T-06 - Compatibility with updated static-analysis dependencies

- Status: completed.
- RED: PHPStan 2.3.3 with codeigniter/phpstan-codeigniter 2.2.0 reproduced all six CI errors: constant config alias inference, query-result union types and repeated cached-provider assertions inferred as pure calls (2026-10-28).
- Preserve app-config alias priority and fallback while resolving candidates in one loop; require ResultInterface before reading query rows in tests; explicitly mark stateful cache lookup methods impure so repeated-call regression assertions remain meaningful.
- No baseline entries, ignored diagnostics, inferred-type overrides or reduced analysis level.
- GREEN: PHPStan level 8 passed with PHPStan 2.3.3 and codeigniter/phpstan-codeigniter 2.2.0; focused tests passed (90 tests / 613 assertions); full suite passed (1,505 tests / 8,572 assertions); style dry-run and registry regeneration clean.
- PHP compatibility: full suite passed independently on PHP 8.3.30, 8.4.18 and 8.5.12 (1,505 tests / 8,572 assertions per version). PHP 8.5 added to CI; minimum PHP remains 8.3. Static analysis also passed on PHP 8.5.12.
