# Formatting

`daycry/iban` supports three output formats, defined by the `Daycry\Iban\Enums\IbanFormat` enum:
`Electronic`, `Print`, and `Anonymized`. Formatting is implemented by the framework-free
`Daycry\Iban\Core\Formatter` and exposed via `Iban::format()` / `ParsedIban::format()`.

`format()` does **not** require the IBAN to be valid — it normalizes (uppercase, no spaces) and
presents whatever electronic form it's given. Use `validate()`/`isValid()` first if you need a
validity guarantee.

```php
use Daycry\Iban\Iban;
use Daycry\Iban\Enums\IbanFormat;

$iban = new Iban();
$electronic = 'ES9121000418450200051332';

$iban->format($electronic, IbanFormat::Electronic); // 'ES9121000418450200051332'
$iban->format($electronic, IbanFormat::Print);       // 'ES91 2100 0418 4502 0005 1332'
$iban->format($electronic, IbanFormat::Anonymized);  // 'ES******************1332'

// Works from a ParsedIban too, and defaults to IbanFormat::Print:
$parsed = $iban->parse($electronic);
$parsed->format();                      // 'ES91 2100 0418 4502 0005 1332'
$parsed->format(IbanFormat::Anonymized); // 'ES******************1332'
(string) $parsed;                       // 'ES9121000418450200051332' (== ->electronic, via __toString())
```

## `Electronic`

The canonical, machine-readable form: uppercase, no spaces. This is the same value stored in
`ParsedIban::$electronic`.

```
ES9121000418450200051332
```

## `Print`

The human-readable form: the electronic string grouped into chunks of 4 characters, separated by a
single space (`chunk_split($electronic, 4, ' ')`, trimmed).

```
ES91 2100 0418 4502 0005 1332
```

## `Anonymized`

Masks the middle of the IBAN, showing only:
- the **2-character country code** (first 2 characters), and
- the **last 4 characters**;

everything in between is replaced with `*`.

```php
$len = strlen($electronic);            // 24 for ES9121000418450200051332
$masked = $len - 6;                     // 18 stars: 2 (country) + 4 (last block) reserved
```

```
ES9121000418450200051332
ES + (18 x '*') + 1332
= ES******************1332
```

Worked examples for other country lengths:

| Country | Electronic | Anonymized |
|---|---|---|
| ES (24 chars) | `ES9121000418450200051332` | `ES******************1332` |
| DE (22 chars) | `DE89370400440532013000` | `DE****************3000` |
| GB (22 chars) | `GB29NWBK60161331926819` | `GB****************6819` |
| NL (18 chars) | `NL91ABNA0417164300` | `NL************4300` |

**Short-IBAN edge case**: if the (normalized) input is 6 characters or shorter, `Anonymized` returns it
**unchanged** — there isn't enough length to reserve both the 2-character country prefix and the
4-character suffix without overlap, so masking is skipped rather than producing a nonsensical or
negative-length result.

```php
$iban->format('ES9', IbanFormat::Anonymized); // 'ES9' — unchanged (3 chars, <= 6)
```

Note: `Anonymized` operates purely on string length/position — it does not validate the IBAN first.
Running it on garbage input still applies the same masking rule mechanically.
