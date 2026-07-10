---
spec: daycry-iban-v1
descripcion: Librería IBAN para CodeIgniter 4 (core cero-deps + resolver opcional) — diseño v1.0
estado: aprobada          # borrador | aprobada | implementada | obsoleta
creado: 2026-07-10
actualizado: 2026-07-10
evaluacion: evaluation.md  # ruta a la evaluación cuando exista
plan: improvement-plan.md  # ruta al plan cuando exista
---

# daycry/iban — Diseño v1.0

> **Evaluación:** [`evaluation.md`](evaluation.md)
> **Plan de implementación:** [`improvement-plan.md`](improvement-plan.md) · [`tasks.md`](tasks.md) — 8 fases, 55 tareas, 157 h base (188 h con margen +20 %), ≈ 9.559 €.
> **Spec fuente (brainstorming):** [`../../superpowers/specs/2026-07-10-daycry-iban-v1-design.md`](../../superpowers/specs/2026-07-10-daycry-iban-v1-design.md) — este documento es el reflejo de la cadena de roadmap; ante cualquier duda de detalle, la fuente manda.

> **Terminología:**
> - **IBAN / BBAN** — número internacional de cuenta (ISO 13616) y su parte nacional.
> - **Registro estructural (registry)** — tabla en código con longitud, gramática de tokens SWIFT y offsets `[posición,longitud]` de banco/sucursal/cuenta/check nacional por país (~80+). Contiene **hechos**, no bytes de la SWIFT Registry.
> - **MOD-97** — dígito de control ISO 7064 sobre el IBAN completo (por ventanas de 9 dígitos, sin `bcmath`).
> - **Resolver / Provider** — capa opcional que superpone metadatos de banco (nombre, BIC, reachability SEPA) al IBAN parseado; intercambiable (`NullProvider` por defecto, `DatabaseProvider` opcional).
> - **SEPA país vs. per-PSP** — `sepaCountry` (ámbito de país, EPC409-09, en el registry) vive en `ParsedIban`; la reachability por entidad (`sepaSct*`) vive en `BankResult` y solo se rellena si un provider tiene datos.

## Contexto y objetivo

`daycry/iban` es la librería de referencia para trabajar con IBAN en el ecosistema CodeIgniter 4 y **también fuera de CI**: no solo "obtener el nombre del banco", sino validar, parsear, formatear, normalizar y resolver metadatos de entidad. Filosofía alineada con `daycry/auth`, `daycry/jobs`, `daycry/doctrine`: sin dependencias innecesarias, core standalone, PSR-4/12/11, tests desde el día uno, documentación completa.

Proyecto **greenfield**: el repositorio está vacío salvo el spec fuente. Todo el esfuerzo es desde cero.

Fuente de requisitos: spec de brainstorming `docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md` (secciones §1–§14).

## Decisiones de diseño

| Decisión | Elección | Motivo |
|---|---|---|
| Alcance de la sesión (§2·1) | **v1.0 completo** | Petición del autor. |
| Persistencia (§2·2) | **Core sin deps (registry en código) + provider CI4 (Model+Migrations+Seeds) opcional** | Honra "sin deps" + "usable fuera de CI"; solo `DatabaseProvider` depende de CI4. |
| Datos del resolver en v1.0 (§2·3) | **BD vacía + degradación elegante**; importadores en v1.1 | Lo más limpio legalmente y el v1.0 más pequeño. |
| Arquitectura (§2·4) | **Enfoque A**: facade sobre dos capas + DTOs + `ValidationResult` | Único que respeta core standalone + provider opcional + BD vacía. |
| Cobertura de países (§2·5) | **Set SWIFT completo (~80+)** | Validador de referencia creíble desde v1.0. |
| Check nacional v1.0 (§2·6) | **Extracción en todos + verificación solo de ES (mod-11)** | Prueba del mecanismo con datos conocidos; evita validadores nacionales mal implementados. |
| Versiones (§2·7) | **PHP `^8.3`, CI4 `^4.6`; matriz CI PHP 8.3/8.4 × CI4 4.6** | Suelo moderno (`readonly class`/enums); menos superficie de test. |

## Configuración / parámetros

`Config\Iban` (publicable, override por env):

| Parámetro | Clave / mecanismo | Default | Notas |
|---|---|---|---|
| Proveedor del resolver | `$provider` | `'null'` | `'null' \| 'database' \| FQCN` |
| Formato por defecto | `$defaultFormat` | `'print'` | `electronic \| print \| anonymized` |
| Check nacional por defecto | `$checkNationalByDefault` | `false` | activa `NationalCheckValidator` |
| Grupo de BD | `$dbGroup` | `'default'` | solo `DatabaseProvider` |
| Tabla de bancos | `$table` | `'banks'` | solo `DatabaseProvider` |

## Arquitectura y componentes

Dependencia en **un solo sentido**: el Core no conoce al Resolver ni a CI4; el Resolver conoce al Core; la integración CI4 (adaptador fino, sin lógica de dominio) conoce a ambos.

```
[ Integración CI4 ]  Config, Services('iban'), iban_helper, spark commands
        │  (adaptador fino)
        ▼
[ Resolver ]  ResolverInterface → ProviderInterface (NullProvider | DatabaseProvider)
        │  produce BankResult (compone ParsedIban + datos de banco nulables)
        ▼
[ Core ]  Registry estructural (en código) → normalize/validate/parse/format
          cero dependencias · usable fuera de CI · produce ParsedIban / ValidationResult
```

**Componentes nuevos (todo greenfield):**

- **Modelo de dominio** — DTOs `final readonly` (`ParsedIban`, `BankResult`, `BankInfo`, `ValidationResult`, `Violation`), enums (`ViolationCode`, `IbanFormat`), excepciones (`IbanException`, `InvalidIbanException`).
- **Contratos** (framework-free) — `ValidatorInterface`, `ParserInterface`, `ProviderInterface`, `ResolverInterface`, `RegistryLoaderInterface`, `NationalCheckValidatorInterface`.
- **Core** — `Normalizer`, `Mod97`, `StructureCompiler` (tokens SWIFT → regex anclada y cacheada), `Validator` (pipeline con corto-circuito), `Parser`, `format()`.
- **Registry** — `CountryStructure`, `Registry`, `PhpRegistryLoader`, y el dato `src/Registry/data/countries.php` (**autoría independiente** de hechos para ~80+ países, cross-check contra libs MIT).
- **`bin/`** — generador de `countries.php` para el refresco ~anual.
- **National** — `SpanishNationalCheckValidator` (mod-11 ponderado).
- **Resolver / Providers** — `Resolver`, `NullProvider` (default), `DatabaseProvider` (`BankModel` + Migration `banks` + Seed vacío).
- **Integración CI4** — `Config\Iban`, `Config\Services::iban()`, `Config\Registrar`, `Helpers/iban_helper.php`, comandos spark (`iban:validate`, `iban:parse`, `iban:resolve`, `iban:update` scaffold no-op).
- **Facade** — `final class Iban implements ValidatorInterface, ParserInterface, ResolverInterface`.

## Flujo (paso a paso)

**Validación** (`Validator`, corto-circuito, primera violación):

1. `Blank / TooShort` → 2. `UnknownCountry` → 3. `IllegalCharacters` → 4. `BadLength` → 5. `MalformedStructure` (regex de tokens) → 6. `ChecksumFailed` (MOD-97) → 7. `NationalCheckFailed` (solo si `checkNational=true`).

**Parseo** — `normalize()` (quita espacios y prefijo `IBAN`, mayúsculas, `[A-Z0-9]`) → validación → trocea por offsets del registry → `ParsedIban`. `parse()` lanza `InvalidIbanException` (con `ValidationResult` dentro); `tryParse()` devuelve `null`.

**Resolución** — `resolve()` construye siempre un `BankResult` desde la estructura (incl. `sepaCountry`); si `provider->supports(cc)`, superpone `BankInfo` (`findByIban` → fallback `findByBankCode`). Con `NullProvider`, campos de banco a `null` (degradación sin condicionales en el llamante).

## Alcance

- **Dentro (v1.0):**
  - Core cero-deps: validación ISO 13616, MOD-97, normalización, formateo (Electronic/Print/Anonymized), parseo.
  - Registry estructural en código para el set SWIFT completo (~80+ países) + script generador en `bin/`.
  - Extracción de `nationalCheckDigit` en todos los países; **verificación** solo de ES (mod-11).
  - Resolver con `NullProvider` (default) y `DatabaseProvider` opcional (Model+Migration+Seed **vacío**; migración no auto-ejecutada, opt-in estricto).
  - Integración CI4: Config, Services, Registrar, helper, comandos spark (`iban:update` como scaffold no-op documentado).
  - Suite de tests desde el día uno, tooling (PHPStan, CS, GitHub Actions matriz 8.3/8.4 × 4.6), documentación.
- **Fuera (roadmap posterior):**
  - Importadores automáticos de listas oficiales por país → **v1.1** (`iban:update` real).
  - Verificación de checks nacionales más allá de ES → **v1.1**.
  - Proveedores externos (ibanapi/IbanComProvider), API REST, CLI independiente → **v2.0**.
  - Empaquetar datos con licencia restrictiva (SWIFT Registry, SwiftRef BIC, registro EPC per-PSP).

## Manejo de errores

| Caso | Comportamiento |
|---|---|
| IBAN vacío / demasiado corto | `ValidationResult` inválido con `Blank` / `TooShort`; `validate/isValid/tryParse` nunca lanzan. |
| País sin entrada en registry | `UnknownCountry`. |
| Caracteres no `[A-Z0-9]` tras normalizar | `IllegalCharacters`. |
| Longitud ≠ la del país | `BadLength` (antes del MOD-97). |
| BBAN no casa con tokens | `MalformedStructure`. |
| MOD-97 ≠ 1 | `ChecksumFailed`. |
| Check nacional ES fallido (`checkNational=true`) | `NationalCheckFailed`; ausencia de impl nacional = skip silencioso. |
| `parse()` sobre basura | Lanza `InvalidIbanException` con el `ValidationResult` dentro. |
| Provider sin datos / BD vacía | `resolve()` degrada: estructura completa, campos de banco `null`. |

## Pruebas

- **PHPUnit** + `CIUnitTestCase`. Tests del Core **sin arrancar CI** (demuestran standalone).
- Fixtures de IBAN válidos/inválidos por país (al menos uno por longitud registrada).
- Un caso por cada `ViolationCode`, incluido el **orden de evaluación** (razón más específica).
- Fixtures del mod-11 español (válidos e inválidos, incl. 10→'1', 11→'0').
- `DatabaseProvider` con SQLite en memoria + `DatabaseTestTrait`; verificar que `NullProvider` no toca BD.
- Tests de comandos spark (incl. `iban:update` no-op y su aviso de licencias).
- Round-trip de formateo (Electronic ↔ Print ↔ Anonymized) y de generación de dígitos de control (`98 − mod`).

## Referencias

- **Spec fuente:** `docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md` (§1–§14: registro de decisiones, arquitectura, modelo de dominio, core, resolver/BD, integración CI4, tests, tooling, roadmap, licencias).
- ISO 13616 / MOD-97-10 (ISO 7064); módulo por ventanas (Apache Commons `IBANCheckDigit`).
- Prior art PHP (fuente de hechos / antipatrones, no de bytes): `jschaedl/iban-validation` (loader intercambiable, RegexConverter), Symfony `IbanValidator`, `globalcitizen/php-iban`, `ixnode/php-iban`, `cmpayments/iban`, `elgigi/iban`.
- Licencias de datos: SWIFT Registry (no-comercial/no-derivados), SwiftRef BIC (propietario), Bundesbank/OeNB/SIX/Betaalvereniging/BdE (v1.1), EPC409-09 (SEPA).

## Decisiones confirmadas (revisión del usuario · 2026-07-10)

1. Enfoque A (facade + dos capas). **Confirmado.**
2. Core cero-deps con registro estructural en código para el set SWIFT completo (~80+). **Confirmado.**
3. Resolver con `NullProvider` por defecto y `DatabaseProvider` opcional (CI4 Model+Migrations+Seeds); **BD vacía** en v1.0. **Confirmado.**
4. Verificación de check nacional solo para España (mod-11); extracción estructural para todos. **Confirmado.**
5. PHP `^8.3` / CI4 `^4.6`; tests desde el día uno. **Confirmado.**
6. `iban:update` va como scaffold no-op documentado en v1.0. **Confirmado.**

## Supuestos

- El fichero `countries.php` se autora de forma **independiente** a partir de hechos (longitudes/offsets no copyrightables), con libs MIT solo como cross-check; ninguna fuente con licencia restrictiva se empaqueta.
- `messageKey`/`message` de las violaciones: el `message` por defecto puede ser inglés en el core standalone; la traducción vía `Language/` de CI4 queda por definir (cuestión abierta §13 de la fuente).
- El esquema exacto de máscara `Anonymized` (código de país + últimos 4 visibles) se documentará en la fase de plan.
