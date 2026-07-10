# Security Audit — daycry/iban v1.0

**Date:** 2026-07-11 · **Scope:** `src/` (41 files), `bin/generate-registry.php`, `composer.json`, `.github/workflows/`
**Method:** 8-dimension SAST audit (OWASP Top 10:2021, CWE Top 25:2024, STRIDE, MITRE ATT&CK), framework-aware false-positive suppression, 4-tier confidence scoring.

## Result

| | |
|---|---|
| **Overall score** | **≈ 95.7 / 100 — Grade A** (excellent security posture) |
| **Critical** | 0 |
| **High** | 0 |
| **Medium** | 0 |
| **Low / Info** | 4 (hardening / defense-in-depth) |

`daycry/iban` is a pure library with a small, well-defended attack surface: **zero runtime dependencies** (only `php: ^8.3`), no authentication/session/network/HTTP/IaC/secrets. Untrusted input is a single well-modelled boundary — the IBAN string — funnelled through a strict, ordered validation pipeline.

### Category scores

| Dimension | Score | Notes |
|---|---|---|
| Vulnerability detection | 95 | No injection/RCE/deserialization. SQL access uses CI4 query-builder bindings; regexes compiled only from the trusted in-repo registry; MOD-97 windowed (no int overflow); CSV→PHP generation escapes string literals. |
| Authorization | 100 | N/A — no auth surface (library); by design. |
| Secret management | 100 | No hardcoded secrets; `.gitignore`/workflows clean; example IBANs are public test vectors. |
| Dependency / supply chain | 90 | Zero runtime deps (major strength). Dev-only deps, all legitimate (no typo/slop-squat). See L-2. |
| Infrastructure / CI-CD | 92 | No Terraform/Docker/K8s. 4 GitHub workflows use least-privilege `permissions`, safe `pull_request` (not `pull_request_target`), no script injection. See L-4. |
| Threat intelligence | 100 | No backdoor/C2/exfil/cryptominer/obfuscation indicators. No `eval`/`exec`/`system`/`unserialize`. |
| AI-code patterns | 95 | No hallucinated imports; comprehensive input validation; strict types throughout. |
| Logic & design | 90 | Robust by design: cheap-before-expensive check ordering, hard trust boundary, never-throws contract on `validate`/`isValid`/`tryParse`. See L-1. |

## Findings (all Low / Info)

- **L-1 — Over-long input handling (FIXED).** `Validator::validate()` scanned the full raw input before any length check → O(n) work on pathologically huge strings (not ReDoS; linear). **Fixed** with an early fail-fast guard (`MAX_INPUT_LENGTH = 64`) that rejects over-long input as `BadLength` before normalization.
- **L-2 — No lock file + `composer update` in CI (by design).** The library convention (no `composer.lock`; CI `composer update`) auto-ingests upstream **dev-dependency** releases. Dev/CI-time only — cannot reach consumers (zero runtime deps). Guarded by `minimum-stability: stable` and the default-Packagist-only resolution. *Backlog:* add a `composer audit` CI step.
- **L-3 — Non-atomic write in `bin/generate-registry.php` (maintainer tool).** A crash mid-write could truncate `countries.php`. Not attacker-reachable (offline, maintainer-run). *Backlog:* write-to-temp + `rename()`.
- **L-4 — CI actions pinned to major tags, not commit SHAs.** Standard practice; least-privilege tokens limit blast radius; `coverallsapp` step is `continue-on-error` with a scoped `GITHUB_TOKEN`. *Backlog (optional):* SHA-pin third-party actions per OpenSSF Scorecard.
- **Info — Config-driven provider FQCN instantiation.** `Config\Iban::$provider` may name a custom `ProviderInterface` class (`new $fqcn()`), guarded by `class_exists()` + `instanceof`. It is developer config, not request input — outside the library's trust boundary.

## Verified-safe (explicitly checked)

- **SQL injection:** `BankModel::findByNaturalKey()` uses CI4 query-builder `where()` bindings exclusively; inputs are pre-validated, fixed-length `[A-Z0-9]` slices — doubly defended.
- **ReDoS:** compiled BBAN regexes are anchored, no nested quantifiers/alternation/backreferences, built only from trusted registry data, and matched against length-bounded (≤34), charset-validated input.
- **Integer overflow:** `Mod97` windowed modulo processes ≤9-digit blocks — safe on 64-bit (and 32-bit) ints; no full-string `(int)` cast.
- **Never-throws:** `validate`/`isValid`/`tryParse` cannot throw on garbage (guarded `Registry::get` behind `has`); `parse` throws only `InvalidIbanException`, whose message never echoes raw input.

> Reference: audit methodology per the project's cybersecurity review; findings ranked by exploitability under the library threat model.
