# Registry Authoring Methodology

## Purpose

The IBAN structural registry lives in `src/Registry/data/countries.php` as a raw PHP array — a zero-dependency, embedded data source that requires no network access, database, or external files. This design choice serves three goals:

1. **Framework freedom**: The core module has zero runtime dependencies, making it usable in any environment (CLI, microservice, serverless) without external configuration.
2. **Transparency and auditability**: The complete source of truth for IBAN structure is a single, readable PHP array in the repository, subject to version control and code review.
3. **Performance**: IBAN validation and parsing can proceed immediately on class instantiation without waiting for data loading.

The registry is loaded by `PhpRegistryLoader` at runtime and hydrated into `CountryStructure` DTOs by `Registry`, which caches the result for the lifetime of the object.

---

## Raw Array Schema

Each entry in `src/Registry/data/countries.php` is keyed by ISO 3166-1 alpha-2 country code (uppercase, e.g., `'ES'`, `'FR'`, `'GB'`) and contains the following required keys:

| Key | Type | Description |
|-----|------|-------------|
| `iban_length` | `int` | The fixed IBAN length for this country (including the 2-letter country code and 2-digit check). For Spain (ES), this is 24. |
| `bban_structure` | `string` | The SWIFT format token string describing the BBAN (the part of the IBAN after the country code and check digits). For ES, this is `'4!n4!n2!n10!n'`. See "SWIFT Token Grammar" below. |
| `bank` | `[int, int]` | A two-element array: `[offset, length]` of the bank identifier field within the normalized IBAN. Offset 4 is the start of the BBAN. For ES: `[4, 4]`. |
| `branch` | `[int, int] \| null` | A two-element array: `[offset, length]` of the branch identifier, or `null` if the country's BBAN has no branch field. For ES: `[8, 4]`. |
| `account` | `[int, int]` | A two-element array: `[offset, length]` of the account number field. For ES: `[14, 10]`. |
| `national_check` | `[int, int] \| null` | A two-element array: `[offset, length]` of a national-level check digit (distinct from the IBAN check), or `null` if unused. For ES: `[12, 2]` (the Spanish national check digit). |
| `sepa` | `bool` | Whether this country is part of the Single Euro Payments Area (SEPA), as defined by EPC409-09. For ES: `true`. |
| `example` | `string` | A valid IBAN example in electronic format (no spaces), used for validation testing and documentation. For ES: `'ES9121000418450200051332'`. |

**Offset convention**: All offsets are relative to the normalized IBAN (lowercase, no spaces). The IBAN check digits are at positions 2–3; the BBAN begins at offset 4.

---

## SWIFT Token Grammar

The BBAN structure is expressed using SWIFT's IBAN Registry format, a simple grammar:

```
<token> ::= <length>[!]<class>
<length> ::= 1..∞ (decimal digits, no leading zeros; e.g., '4' or '10')
             [!]    (optional) means fixed length; if omitted, length is maximum
<class>  ::= 'n' | 'a' | 'c' | 'e'
             'n'  = numeric digits (0–9)
             'a'  = alphabetic uppercase (A–Z)
             'c'  = alphanumeric (0–9, A–Z, after uppercasing input)
             'e'  = space (encoding error check; rarely used)
```

**Example**: `'4!n4!n2!n10!n'` for Spain means:
- 4 fixed numeric digits (bank)
- 4 fixed numeric digits (branch)
- 2 fixed numeric digits (national check)
- 10 fixed numeric digits (account)
- Total BBAN: 20 characters; Total IBAN: 20 + 4 (country + check) = 24 characters.

The exclamation mark (`!`) indicates that the length is invariant; if omitted, the length would be a maximum. Most modern IBANs use fixed-length fields (with `!`).

---

## Independent Authorship Methodology

Authoring the registry requires collecting **facts** (IBAN lengths, field offsets, BBAN tokens) from independent, public sources — never copying bytes from restricted sources:

### What IS copyrightable
- The SWIFT IBAN Registry file (as a compilation, with a non-commercial, no-derivative license)
- `registry.txt` from [globalcitizen/IBAN](https://github.com/globalcitizen/IBAN/) (LGPL-3.0 copyleft)
- Wikipedia's IBAN table (CC BY-SA copyleft; derivative works must credit and keep the same license)

### What IS NOT copyrightable
- Individual facts: "Spain's IBAN is 24 characters long," "the bank field is 4 numeric digits at offset 4"
- Field offsets and lengths: these are derived from publicly documented IBAN specifications
- BBAN token grammar: the SWIFT `n`, `a`, `c`, `e` notation is a standard format, not original expression

### Authoring workflow
1. **Consult independent, authoritative sources**: IBAN specification documents (ISO 13616 / ECB publications), central bank websites, payment standards bodies (EPC, SEPA).
2. **Record the facts**: Write down the IBAN length, BBAN structure, field offsets.
3. **Cross-check** (see below) against MIT-licensed reference libraries to verify no transcription errors.
4. **Never copy-paste** from SWIFT Registry files, globalcitizen registry, or Wikipedia article tables.
5. **Document the sources** in commit messages or comments if unclear.

This ensures the registry is independently authored, free to license under any terms (MIT), and usable in any commercial or academic context without license conflicts.

---

## Cross-Check: MIT Reference Libraries

Once an entry is authored, validate it against independent, MIT-licensed reference implementations:

- **[`cmpayments/iban`](https://github.com/cmpayments/iban)** — A PHP IBAN library with MIT license; includes a registry of country structures.
- **[`ixnode/php-iban`](https://github.com/ixnode/php-iban)** — Another PHP IBAN validator; MIT license; includes IBAN structure definitions.

**Cross-check procedure**:
1. Retrieve the same country's structure from each library.
2. Compare: `iban_length`, `bban_structure`, field offsets.
3. Document any discrepancies in a comment or PR description.
4. If a discrepancy is found, investigate the source:
   - Check the official IBAN specification for that country.
   - Verify the example IBAN with MOD-97 checksum validation (see below).
   - Update the registry entry or note the reason if the reference library is incorrect.

This process ensures the registry is consistent with other open-source, permissively licensed sources, reducing the risk of transcription errors or outdated data.

---

## Per-Entry Verification Rules

Each entry in the registry must satisfy the following structural and algorithmic constraints, verified by the test suite (Task T-19):

### Structural validation
1. **Example length** — The `example` IBAN (normalized: no spaces, lowercase) must have exactly `iban_length` characters.
2. **Token sum** — The tokens in `bban_structure` must sum to exactly `iban_length - 4` characters (the BBAN length).
3. **Offset coverage**:
   - All offsets (`bank`, `branch`, `account`, `national_check`) must fall within `[4, iban_length)`.
   - No two fields may overlap.
   - Full BBAN coverage is NOT required (some countries have unassigned BBAN segments, e.g. currency/reserved digits).

### Algorithmic validation
4. **MOD-97 checksum** — The `example` IBAN must satisfy the IBAN checksum algorithm (ISO 13616):
   - Rearrange: Move the country code and check digits to the end (e.g., `ES9121000418450200051332` → `21000418450200051332ES91`).
   - Replace letters with numbers (A=10, B=11, …, Z=35).
   - Compute `mod 97` of the resulting number.
   - **Result must equal 1**.

For Spain's example (`ES9121000418450200051332`), MOD-97 validation passes if:
```
21000418450200051332ES91
21000418450200051332142891 (after letter substitution)
mod 97 ≡ 1 (verified)
```

These validations are parameterized over all countries in the registry, so as new countries are added, their entries are automatically tested.

---

## Annual Refresh and Registry Generator

Every year or after a structural IBAN update, the registry may be regenerated using the bin-level generator (planned for Tasks T-28/T-29, not yet implemented):

- **Source**: A dedicated fact-gathering document (e.g., a CSV or YAML file) containing the independently authored structural data for all countries.
- **Process**: `bin/generate-registry.php` transforms this source into `src/Registry/data/countries.php`, ensuring consistency and reducing manual transcription errors.
- **Versioning**: After regeneration, `Registry::VERSION` is updated to reflect the new release date, e.g., `'2027-07 (independent authorship; not derived from the SWIFT IBAN Registry)'`.

This design allows the registry to evolve without manually editing the PHP array, while maintaining an auditable trail of changes.

---

## Registry::VERSION — Legal Note

The `Registry` class defines a class constant:

```php
public const string VERSION = '2026-07 (independent authorship; not derived from the SWIFT IBAN Registry)';
```

This declaration serves a legal and technical purpose:

1. **Legal clarity**: It signals to consumers and auditors that the registry is independently authored, not derived from the SWIFT IBAN Registry file or other copyleft sources, and is therefore free to license under any terms (MIT).
2. **Traceability**: The version string embeds the authorship year-month, helping identify which generation of the registry is in use.
3. **Integrity**: The phrasing "not derived from the SWIFT IBAN Registry" protects against accidental violations of SWIFT's non-commercial license if the code is used in a for-profit context.

When the registry is regenerated (see above), `VERSION` must be updated to the new release date. The note "independent authorship" must remain constant to preserve the legal basis.

---

## Summary

Authoring and maintaining the IBAN registry requires:
- **Independent research** from non-copyrighted sources (central banks, ISO specs, EPC standards).
- **Cross-checking** against MIT-licensed reference libraries (cmpayments/iban, ixnode/php-iban).
- **Structural and algorithmic validation** of each entry (length, offsets, MOD-97 checksum).
- **Versioning and legal transparency** via `Registry::VERSION` and explicit licensing notes in the code.

This ensures the registry is reliable, legally sound, and ready for distribution under the MIT license.
