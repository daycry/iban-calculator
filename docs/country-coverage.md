# Country coverage: IBAN and SWIFT/BIC

The bundled registry supports **89 national IBAN prefixes**. No registered prefix is missing from
the comparison with [SWIFT's IBAN Registry release 103, September 2026](https://www.swift.com/resource/iban-registry-pdf),
reviewed on 28 October 2026. This page lists the package's own runtime registry facts, not an imported
third-party directory. See the [format review and source limitations](registry-refresh-2026-10.md).

## What the columns mean

- **IBAN**: local validation of country prefix, total length, BBAN character structure and MOD-97
  check digits. All 89 bundled example IBANs pass these checks.
- **SWIFT/BIC**: local validation of an 8- or 11-character BIC's structure and country code. All
  89 prefixes below are accepted. This does not confirm that an institution or BIC exists.
- **National check**: an additional domestic checksum validator is bundled for nine countries and
  runs only with `checkNational: true`. A dash means this extra algorithm is not bundled; ordinary
  IBAN validation still applies. Requesting national checks skips countries without a validator.
- **Default bank importer**: at least one default importer registration exists for this prefix
  (44 in total). A dash means no default importer; it does not indicate missing IBAN/BIC validation.
  Importing data is a separate step and does not guarantee full bank or branch coverage.

## Complete IBAN coverage table

Sorted by country code. Country names come from the package's ISO country registry, with `XK`
handled explicitly for Kosovo. Length includes the country prefix and both IBAN check digits.

| Country / territory | Code | IBAN length | IBAN | SWIFT/BIC | National check | Default bank importer |
|---|---|---:|:---:|:---:|:---:|:---:|
| Andorra | AD | 24 | Yes | Yes | - | Yes |
| United Arab Emirates | AE | 23 | Yes | Yes | - | - |
| Albania | AL | 28 | Yes | Yes | - | - |
| Austria | AT | 20 | Yes | Yes | - | Yes |
| Azerbaijan | AZ | 28 | Yes | Yes | - | Yes |
| Bosnia and Herzegovina | BA | 20 | Yes | Yes | - | - |
| Belgium | BE | 16 | Yes | Yes | Opt-in | Yes |
| Bulgaria | BG | 22 | Yes | Yes | - | Yes |
| Bahrain | BH | 22 | Yes | Yes | - | - |
| Burundi | BI | 27 | Yes | Yes | - | - |
| Brazil | BR | 29 | Yes | Yes | - | Yes |
| Belarus | BY | 28 | Yes | Yes | - | - |
| Switzerland | CH | 21 | Yes | Yes | - | Yes |
| Costa Rica | CR | 22 | Yes | Yes | - | - |
| Cyprus | CY | 28 | Yes | Yes | - | Yes |
| Czechia | CZ | 24 | Yes | Yes | - | Yes |
| Germany | DE | 22 | Yes | Yes | - | Yes |
| Djibouti | DJ | 27 | Yes | Yes | - | - |
| Denmark | DK | 18 | Yes | Yes | - | - |
| Dominican Republic | DO | 28 | Yes | Yes | - | - |
| Estonia | EE | 20 | Yes | Yes | - | Yes |
| Egypt | EG | 29 | Yes | Yes | - | - |
| Spain | ES | 24 | Yes | Yes | Opt-in | Yes |
| Finland | FI | 18 | Yes | Yes | Opt-in | Yes |
| Falkland Islands (Malvinas) | FK | 18 | Yes | Yes | - | - |
| Faroe Islands | FO | 18 | Yes | Yes | - | - |
| France | FR | 27 | Yes | Yes | Opt-in | Yes |
| United Kingdom of Great Britain and Northern Ireland | GB | 22 | Yes | Yes | - | Yes |
| Georgia | GE | 22 | Yes | Yes | - | Yes |
| Gibraltar | GI | 23 | Yes | Yes | - | Yes |
| Greenland | GL | 18 | Yes | Yes | - | - |
| Greece | GR | 27 | Yes | Yes | - | Yes |
| Guatemala | GT | 28 | Yes | Yes | - | - |
| Honduras | HN | 28 | Yes | Yes | - | - |
| Croatia | HR | 21 | Yes | Yes | - | Yes |
| Hungary | HU | 28 | Yes | Yes | - | Yes |
| Ireland | IE | 22 | Yes | Yes | - | Yes |
| Israel | IL | 23 | Yes | Yes | - | Yes |
| Iraq | IQ | 23 | Yes | Yes | - | - |
| Iceland | IS | 26 | Yes | Yes | - | - |
| Italy | IT | 27 | Yes | Yes | Opt-in | Yes |
| Jordan | JO | 30 | Yes | Yes | - | - |
| Kuwait | KW | 30 | Yes | Yes | - | - |
| Kazakhstan | KZ | 20 | Yes | Yes | - | Yes |
| Lebanon | LB | 28 | Yes | Yes | - | - |
| Saint Lucia | LC | 32 | Yes | Yes | - | - |
| Liechtenstein | LI | 21 | Yes | Yes | - | Yes |
| Lithuania | LT | 20 | Yes | Yes | - | - |
| Luxembourg | LU | 20 | Yes | Yes | - | Yes |
| Latvia | LV | 21 | Yes | Yes | - | Yes |
| Libya | LY | 25 | Yes | Yes | - | - |
| Monaco | MC | 27 | Yes | Yes | Opt-in | Yes |
| Moldova, Republic of | MD | 24 | Yes | Yes | - | Yes |
| Montenegro | ME | 22 | Yes | Yes | - | Yes |
| North Macedonia | MK | 19 | Yes | Yes | - | Yes |
| Mongolia | MN | 20 | Yes | Yes | - | - |
| Mauritania | MR | 27 | Yes | Yes | - | - |
| Malta | MT | 31 | Yes | Yes | - | Yes |
| Mauritius | MU | 30 | Yes | Yes | - | - |
| Nicaragua | NI | 28 | Yes | Yes | - | - |
| Netherlands | NL | 18 | Yes | Yes | - | Yes |
| Norway | NO | 15 | Yes | Yes | - | Yes |
| Oman | OM | 23 | Yes | Yes | - | - |
| Pakistan | PK | 24 | Yes | Yes | - | - |
| Poland | PL | 28 | Yes | Yes | - | Yes |
| Palestine, State of | PS | 29 | Yes | Yes | - | - |
| Portugal | PT | 25 | Yes | Yes | Opt-in | Yes |
| Qatar | QA | 29 | Yes | Yes | - | - |
| Romania | RO | 24 | Yes | Yes | - | Yes |
| Serbia | RS | 22 | Yes | Yes | - | Yes |
| Russian Federation | RU | 33 | Yes | Yes | - | - |
| Saudi Arabia | SA | 24 | Yes | Yes | - | - |
| Seychelles | SC | 31 | Yes | Yes | - | - |
| Sudan | SD | 18 | Yes | Yes | - | - |
| Sweden | SE | 24 | Yes | Yes | - | Yes |
| Slovenia | SI | 19 | Yes | Yes | Opt-in | Yes |
| Slovakia | SK | 24 | Yes | Yes | - | Yes |
| San Marino | SM | 27 | Yes | Yes | Opt-in | Yes |
| Somalia | SO | 23 | Yes | Yes | - | - |
| Sao Tome and Principe | ST | 25 | Yes | Yes | - | - |
| El Salvador | SV | 28 | Yes | Yes | - | - |
| Timor-Leste | TL | 23 | Yes | Yes | - | - |
| Tunisia | TN | 24 | Yes | Yes | - | - |
| Türkiye | TR | 26 | Yes | Yes | - | - |
| Ukraine | UA | 29 | Yes | Yes | - | Yes |
| Holy See | VA | 22 | Yes | Yes | - | Yes |
| Virgin Islands (British) | VG | 24 | Yes | Yes | - | - |
| Kosovo | XK | 20 | Yes | Yes | - | - |
| Yemen | YE | 30 | Yes | Yes | - | - |

## Countries without their own IBAN prefix

BIC country validation uses all **249 officially assigned ISO 3166-1 alpha-2 codes plus `XK`**,
independently of IBAN coverage. For example, US, CA, JP and CN BIC country codes are supported even
though these countries do not have a registered national IBAN format. An IBAN using one of these
prefixes returns `unknown_country` locally (`unsupported_country` through `verifyIban()`).

Some territories use another country's IBAN prefix, so the number of IBAN prefixes is not a list
of every territory where IBANs can be used. Registered format support also does not establish
payment reachability or operational adoption.

## Bank data and external verification

Local format checks need no database or network. Bank names, BIC directory presence and network
connectivity require separate evidence:

- See [bank-data importers](importers.md#coverage-matrix) for source availability and limitations.
  The opt-in [reference CSV importer](importers.md#local-bank-reference-csv) can add operator-supplied
  licensed data beyond the default registrations, without redistributing the CSV in this package.
- See [external verification](verification.md) for `verifyIban()` / `verifyBic()`, provider setup and
  the distinct valid, rejected, unsupported and unavailable outcomes. Verification does not prove
  that a customer's account exists or belongs to a named person.
- See [national check-digit validators](usage.md#national-check-digit-validators) for the nine
  optional algorithms; [IBAN/BIC cross-checks](api-reference.md#ibanbiccrosschecker) are separate
  coherence checks, not directory lookups.

When updating the registry or importer registrations, refresh the rows from `Registry::all()`,
`IsoCountryRegistry` and `ImporterRegistry::forCountry()`, and recheck the national-validator map in
`Core\Validator`. Country additions and length changes must also pass the registry tests and
`php bin/generate-registry.php --dry-run`.
