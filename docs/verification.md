# Local validation and optional external verification

`validate()` / `isValid()` and their BIC equivalents remain local and offline. They check identifier
integrity. The registry recognizes 89 IBAN national formats; BIC country validation uses the full ISO
country set plus XK. Bank-directory coverage is independent of this format coverage. See the
[complete country coverage table](country-coverage.md) for all 89 prefixes, lengths, national checks
and default importer availability.

`verifyIban()` and `verifyBic()` explicitly add an optional external check. In CodeIgniter:

```php
$service = service('iban');
$result = $service->verifyBic('CHASUS33');
echo $result->status->value;
```

Without a provider, valid local inputs return `format_valid`. Configure the existing
`iban.ibanComApiKey` and optional `iban.ibanComTimeout` in the consuming application's environment
to enable the iban.com verifier. Only explicit `verify*()` calls use it for verification; adding the
key does not make ordinary `validate*()` calls remote. The key also retains its existing effect on
bank resolution (`resolve*()` fallback). API access and any charges belong to the consuming app.

Standalone or custom integrations can inject a fourth, trailing facade dependency implementing
the framework-free `VerificationProviderInterface`:

```php
$service = new \Daycry\Iban\Iban(verificationProvider: $myVerifier);
$result = $service->verifyIban($input, checkNational: true);
```

That interface accepts normalized electronic strings and returns `ExternalVerification`. The
facade skips the provider for locally invalid or unsupported inputs and catches provider exceptions.
An unrecognized IBAN prefix remains unsupported; external verification does not bypass the local
registry or silently accept unofficial country formats.

## Outcomes

`VerificationResult` exposes `status` (`VerificationStatus`), `validation` (the complete local
`ValidationResult`), and optional `external` evidence. The existing boolean API is unchanged.

| Status value | Meaning |
|---|---|
| `format_valid` | Local checks passed; no external provider was selected. |
| `format_invalid` | A local format/checksum/national-check rule failed. No external call. |
| `unsupported_country` | The IBAN country format is not in the registry. No external call. |
| `externally_validated` | Local checks passed and the external provider returned a positive verdict. |
| `external_rejected` | Local checks passed but the external provider returned an explicit negative verdict. |
| `verification_unavailable` | Local checks passed but the configured provider could not establish a verdict. |

`ExternalVerification` contains `valid` (`true` / `false` / `null`), `provider`, and nullable
`exists` / `active` flags. Null means unknown, never an implicit positive or negative result.

For iban.com, **IBAN validation never establishes that the customer's account exists**, is open,
can receive payments or belongs to a named person. `exists` and `active` remain null for IBANs.
For BICs, a positive directory result sets `exists=true`; `active` independently reports SWIFT
network connectivity. A registered passive BIC can have `exists=true` and `active=false`.

## Provider semantics

The implementation follows the provider's
[IBAN v4](https://developer.iban.com/validation-api) and
[BIC v2](https://developer.iban.com/bic-validation-api) documentation.

IBAN verification requires all six coded checks. Account-check code 004 (no national algorithm
performed) is not a failure. Presence of `bank_data` alone is not proof of validity. BIC verification
checks the query echo, verdict, error code and matching directory record. Error 803 means the BIC
was not found; account/key errors, transport failures, HTTP errors, malformed JSON or incomplete /
inconsistent response fields yield an unavailable verdict. Network failures never turn a locally
valid identifier into an invalid one.

Requests use POST and the configured timeout. Verification is not cached by the existing bank
resolution cache and makes no automatic retries. A caller that caches BIC results must bound their
age because registration and connectivity can change. No global directory is redistributed.
