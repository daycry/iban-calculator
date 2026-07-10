# Checklist de Tareas — daycry/iban v1.0

| | |
|---|---|
| **Estado** | 📝 borrador |
| **Fecha** | 2026-07-10 |
| **Plan** | [`improvement-plan.md`](./improvement-plan.md) |

> **Método:** TDD estricto y bite-sized por tarea — test que falla → verificar fallo → implementación mínima → verificar que pasa → commit. Cada tarea termina en un entregable **testeable independiente**. Las firmas de código son **verbatim del spec** (`spec.md` / fuente `2026-07-10-daycry-iban-v1-design.md`).
> **Trazabilidad C-xx → fase:** F1 [C-01,C-02] · F2 [C-04] · F3 [C-03] · F4 [C-06,C-05] · F5 [C-07] · F6 [C-08] · F7 [C-09] · F8 [C-10]. C-09 es transversal: cada tarea de las Fases 1–6 escribe sus tests unitarios (TDD); la Fase 7 consolida fixtures e infraestructura de test cross-cutting.

---

## 📊 Resumen de progreso

| Fase | Completadas | Total | Progreso | Horas (real/est) | Tokens (real/est) |
|------|------------|-------|----------|------------------|-------------------|
| Fase 1 — Fundaciones [C-01,C-02] | 0 | 12 | 0% | 0 / 20h | 0 / 450k |
| Fase 2 — Registro estructural [C-04] | 0 | 7 | 0% | 0 / 36h | 0 / 1.080k |
| Fase 3 — Core algorítmico [C-03] | 0 | 6 | 0% | 0 / 24h | 0 / 620k |
| Fase 4 — Check ES + generador [C-06,C-05] | 0 | 5 | 0% | 0 / 13h | 0 / 435k |
| Fase 5 — Resolver + BD [C-07] | 0 | 6 | 0% | 0 / 14h | 0 / 440k |
| Fase 6 — Integración CI4 [C-08] | 0 | 7 | 0% | 0 / 16h | 0 / 560k |
| Fase 7 — Tests transversales [C-09] | 0 | 7 | 0% | 0 / 24h | 0 / 750k |
| Fase 8 — Documentación [C-10] | 0 | 5 | 0% | 0 / 10h | 0 / 340k |
| **TOTAL** | **0** | **55** | **0%** | **0 / 157h** | **0 / 4,68M** |

---

## 🏗️ Fase 1 — Fundaciones: tooling/CI + dominio/contratos [C-01, C-02]

**Estado**: 📝 borrador · **Estimado**: 20h · **Real**: — · **Coste est.**: 1.012 € · **Tokens est.**: 450k

### T-01 — `composer.json` y autoload PSR-4

- **Descripción**: Crear el `composer.json` del paquete `daycry/iban`: `require { "php": "^8.3" }`, `require-dev { "codeigniter4/framework": "^4.6", "phpunit/phpunit", "phpstan/phpstan", "friendsofphp/php-cs-fixer" }` (CI4 como **peer**, no dependencia dura). Autoload PSR-4 `Daycry\\Iban\\ → src/`, `autoload-dev` `Tests\\ → tests/`, scripts `test`/`analyze`/`cs`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 30k in / 8k out tok · 0,97 €
- **Dependencias**: ninguna
- **Archivos**: `composer.json`, `LICENSE`

**Criterios de aceptación**
- [ ] `composer validate` pasa sin warnings.
- [ ] `composer install` resuelve con PHP 8.3 y CI4 4.6 en require-dev.
- [ ] Autoload PSR-4 `Daycry\Iban\ → src/` activo; `LICENSE` MIT presente.

**Subtareas**
- [ ] Escribir `composer.json` con require/require-dev/autoload/scripts.
- [ ] Añadir `LICENSE` (MIT) con titularidad del autor.
- [ ] `composer validate` + `composer install` y commit.

**Notas**: CI4 en require-dev honra "usable fuera de CI" (§3). Rector es opcional (incógnita C-01), se difiere.

<!-- ==================================================================== -->

### T-02 — Layout del repositorio y skeleton PSR-4

- **Descripción**: Crear la estructura de directorios de `src/` (Contracts, DTO, Enums, Exceptions, Core, Registry/data, Resolver, Providers, National, Config, Commands, Models, Database/{Migrations,Seeds}, Helpers), `tests/`, `bin/`, `docs/`, con `.gitignore`, `.gitkeep` donde haga falta y `README.md` stub.
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 22k in / 6k out tok · 0,72 €
- **Dependencias**: T-01
- **Archivos**: `src/**` (dirs), `tests/`, `bin/`, `.gitignore`, `README.md`

**Criterios de aceptación**
- [ ] El árbol coincide con el layout del spec §3.
- [ ] `.gitignore` excluye `vendor/`, `.phpunit.cache`, `build/`.
- [ ] `README.md` stub con nombre, badges placeholder e instalación mínima.

**Subtareas**
- [ ] Crear directorios y `.gitkeep`.
- [ ] Escribir `.gitignore` y `README.md` stub.
- [ ] Commit del skeleton.

**Notas**: —

<!-- ==================================================================== -->

### T-03 — PHPStan (nivel alto) + regla de dependencia unidireccional

- **Descripción**: Configurar `phpstan.neon` a **nivel 8** con `phpstan/phpstan` y añadir una regla/comprobación que **prohíba imports `codeigniter4/*` en `src/Contracts/` y `src/Core/`** (regla de arquitectura, §3). Baseline vacía inicial.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 30k in / 8k out tok · 0,97 €
- **Dependencias**: T-02
- **Archivos**: `phpstan.neon`, `tests/Architecture/CoreIsFrameworkFreeTest.php`

**Criterios de aceptación**
- [ ] `composer analyze` (PHPStan nivel 8) pasa sobre el skeleton.
- [ ] Un test/regla falla si se introduce un `use CodeIgniter\...` en `Core/` o `Contracts/`.

**Subtareas**
- [ ] Escribir `phpstan.neon` (nivel 8, paths `src`).
- [ ] Añadir test de arquitectura que escanea `use codeigniter4` en Core/Contracts.
- [ ] Verificar rojo→verde con un import de prueba y commit.

**Notas**: Cumple la mitigación "fuga de acoplamiento CI4 hacia el Core".

<!-- ==================================================================== -->

### T-04 — PHP-CS-Fixer / CodingStandard (PSR-12)

- **Descripción**: Configurar `.php-cs-fixer.dist.php` con reglas PSR-12 (o CodeIgniter CodingStandard) y el script `cs`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 23k in / 6k out tok · 0,73 €
- **Dependencias**: T-02
- **Archivos**: `.php-cs-fixer.dist.php`

**Criterios de aceptación**
- [ ] `composer cs` (dry-run) pasa sin diferencias sobre el skeleton.
- [ ] Reglas PSR-12 aplicadas a `src/` y `tests/`.

**Subtareas**
- [ ] Escribir `.php-cs-fixer.dist.php`.
- [ ] Ejecutar `cs` y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-05 — GitHub Actions: matriz PHP 8.3/8.4 × CI4 4.6

- **Descripción**: Workflow `ci.yml` con matriz **PHP 8.3/8.4 × CI4 4.6**; jobs de tests (PHPUnit), análisis estático (PHPStan) y estilo (CS). Cache de Composer.
- **Estado**: 📝 borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 45k in / 12k out tok · 1,45 €
- **Dependencias**: T-01, T-03, T-04
- **Archivos**: `.github/workflows/ci.yml`

**Criterios de aceptación**
- [ ] El workflow define matriz `php: [8.3, 8.4]` con CI4 `^4.6`.
- [ ] Corre `test`, `analyze` y `cs` en cada combinación.
- [ ] Verde en un push de prueba (con un test trivial).

**Subtareas**
- [ ] Escribir `ci.yml` (setup-php, composer cache, matriz).
- [ ] Añadir test trivial para validar el pipeline.
- [ ] Verificar CI verde y commit.

**Notas**: Riesgo bajo de fricción por versiones cruzadas (evaluación C-01).

<!-- ==================================================================== -->

### T-06 — Enums `ViolationCode` e `IbanFormat`

- **Descripción**: Crear los enums del dominio con las firmas exactas del spec §4.
- **Estado**: 📝 borrador
- **Tiempo**: est. 1h · real —
- **Previsión IA**: 20k in / 6k out tok · 0,69 €
- **Dependencias**: T-02
- **Archivos**: `src/Enums/ViolationCode.php`, `src/Enums/IbanFormat.php`

**Firmas (verbatim spec §4):**

```php
enum ViolationCode: string {
    case Blank = 'blank';
    case TooShort = 'too_short';
    case UnknownCountry = 'unknown_country';
    case IllegalCharacters = 'illegal_characters';
    case BadLength = 'bad_length';
    case MalformedStructure = 'malformed_structure';
    case ChecksumFailed = 'checksum_failed';
    case NationalCheckFailed = 'national_check_failed';
}
enum IbanFormat { case Electronic; case Print; case Anonymized; }
```

**Criterios de aceptación**
- [ ] `ViolationCode` es backed-enum `string` con los 8 casos y valores exactos.
- [ ] `IbanFormat` es enum puro con `Electronic`/`Print`/`Anonymized`.
- [ ] Test que confirma `ViolationCode::from('too_short')` y el conteo de casos.

**Subtareas**
- [ ] Test de casos y valores (rojo).
- [ ] Implementar enums (verde) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-07 — Excepciones `IbanException` e `InvalidIbanException`

- **Descripción**: Jerarquía de excepciones; `InvalidIbanException` transporta el `ValidationResult` que provocó el fallo de parseo estricto.
- **Estado**: 📝 borrador
- **Tiempo**: est. 1h · real —
- **Previsión IA**: 20k in / 6k out tok · 0,69 €
- **Dependencias**: T-08 (usa `ValidationResult`)
- **Archivos**: `src/Exceptions/IbanException.php`, `src/Exceptions/InvalidIbanException.php`

**Firmas (verbatim spec §4):**

```php
class IbanException extends \RuntimeException {}
final class InvalidIbanException extends IbanException {
    public function result(): ValidationResult;
}
```

**Criterios de aceptación**
- [ ] `IbanException extends \RuntimeException`.
- [ ] `InvalidIbanException` es `final`, guarda y devuelve el `ValidationResult` vía `result()`.
- [ ] Test: `new InvalidIbanException(...)->result()` devuelve el `ValidationResult` inyectado.

**Subtareas**
- [ ] Test de `result()` (rojo).
- [ ] Implementar excepciones (verde) y commit.

**Notas**: Puede solaparse con T-08 en el mismo commit si conviene; se lista aparte para trazabilidad.

<!-- ==================================================================== -->

### T-08 — DTOs `Violation` y `ValidationResult`

- **Descripción**: DTOs `final readonly` del resultado de validación.
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 30k in / 9k out tok · 1,04 €
- **Dependencias**: T-06
- **Archivos**: `src/DTO/Violation.php`, `src/DTO/ValidationResult.php`

**Firmas (verbatim spec §4):**

```php
final readonly class ValidationResult {
    public function __construct(public bool $valid, public array $violations) {} // Violation[]
    public function isValid(): bool;
    public function violations(): array;
    public function firstViolation(): ?Violation;
}
final readonly class Violation {
    public function __construct(public ViolationCode $code, public string $messageKey, public string $message) {}
}
```

**Criterios de aceptación**
- [ ] `ValidationResult::isValid()`, `violations()`, `firstViolation()` con semántica del spec.
- [ ] `firstViolation()` devuelve `null` cuando `violations` está vacío.
- [ ] Ambos son `final readonly`.

**Subtareas**
- [ ] Tests de `isValid`/`firstViolation` (rojo).
- [ ] Implementar DTOs (verde) y commit.

**Notas**: `messageKey` en inglés en el core (supuesto §Supuestos); i18n opcional en la capa CI4.

<!-- ==================================================================== -->

### T-09 — DTO `ParsedIban`

- **Descripción**: DTO estructural producido por `Parser::parse` y por `Validator` cuando el IBAN es válido. `__toString()` devuelve `$electronic`; `format()` se **cablea en la Fase 3** (T-25) delegando en `Formatter`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 40k in / 12k out tok · 1,38 €
- **Dependencias**: T-06
- **Archivos**: `src/DTO/ParsedIban.php`

**Firmas (verbatim spec §4):**

```php
final readonly class ParsedIban {
    public function __construct(
        public string  $countryCode,        // 'ES'
        public string  $checkDigits,        // '91'
        public string  $bban,               // BBAN normalizado
        public string  $bankIdentifier,     // troceado por offsets del registry
        public ?string $branchIdentifier,   // null donde el país no tiene (DE/NL/BE)
        public string  $accountNumber,
        public ?string $nationalCheckDigit, // solo extracción estructural en v1.0
        public bool    $sepaCountry,        // del registry en código; útil con BD vacía
        public string  $electronic,         // forma canónica normalizada
    ) {}
    public function format(IbanFormat $f = IbanFormat::Print): string;
    public function __toString(): string;   // devuelve $electronic
}
```

**Criterios de aceptación**
- [ ] 9 propiedades con tipos y nulabilidad exactos; `?string` en `branchIdentifier`/`nationalCheckDigit`.
- [ ] `__toString()` devuelve `$electronic`.
- [ ] Test de construcción y `(string) $parsed === $electronic`.

**Subtareas**
- [ ] Test de props y `__toString` (rojo).
- [ ] Implementar DTO; `format()` documentado como pendiente de T-25 (verde) y commit.

**Notas**: El `?string` distingue "el país no tiene ese campo" (null estructural) de "lookup sin resolver" (§4 notas).

<!-- ==================================================================== -->

### T-10 — DTOs `BankInfo` y `BankResult`

- **Descripción**: DTO interno `BankInfo` (salida de `ProviderInterface`) y `BankResult` (salida de `Resolver::resolve`, compone `ParsedIban` + datos de banco nulables).
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 30k in / 9k out tok · 1,04 €
- **Dependencias**: T-09
- **Archivos**: `src/DTO/BankInfo.php`, `src/DTO/BankResult.php`

**Firmas (verbatim spec §4):**

```php
final readonly class BankResult {
    public function __construct(
        public ParsedIban $iban,
        public ?string $bankName,   public ?string $shortName,
        public ?string $bic,        public ?string $city,   public ?string $address,
        public ?bool $sepaSct,      public ?bool $sepaSctInst,
        public ?bool $sepaSddCore,  public ?bool $sepaSddB2b,
        public ?string $sourceId,   public ?string $sourceVersion, public ?string $sourceLicense,
    ) {}
    public function isResolved(): bool; // true si algún campo de banco no es null
}
final readonly class BankInfo {
    public function __construct(
        public ?string $bankName, public ?string $shortName,
        public ?string $bic, public ?string $city, public ?string $address,
        public ?bool $sepaSct, public ?bool $sepaSctInst, public ?bool $sepaSddCore, public ?bool $sepaSddB2b,
        public ?string $sourceId, public ?string $sourceVersion, public ?string $sourceLicense,
    ) {}
}
```

**Criterios de aceptación**
- [ ] `BankResult` compone `ParsedIban $iban` + campos de banco nulables.
- [ ] `isResolved()` = `true` sii algún campo de banco no es `null`; `false` con todos `null`.
- [ ] `BankInfo` con las 12 propiedades nulables exactas.

**Subtareas**
- [ ] Test de `isResolved()` (todos null → false; uno no-null → true) (rojo).
- [ ] Implementar DTOs (verde) y commit.

**Notas**: `sepaSct*` es reachability per-PSP, solo se rellena si un provider tiene datos.

<!-- ==================================================================== -->

### T-11 — Contratos `ValidatorInterface` y `ParserInterface`

- **Descripción**: Interfaces framework-free del núcleo de validación/parseo (namespace `Daycry\Iban\Contracts`).
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 30k in / 9k out tok · 1,04 €
- **Dependencias**: T-09
- **Archivos**: `src/Contracts/ValidatorInterface.php`, `src/Contracts/ParserInterface.php`

**Firmas (verbatim spec §5):**

```php
interface ValidatorInterface {
    public function validate(string|ParsedIban $iban, bool $checkNational = false): ValidationResult; // NUNCA lanza
    public function isValid(string|ParsedIban $iban): bool;
}
interface ParserInterface {
    public function normalize(string $iban): string;
    public function parse(string $iban): ParsedIban;     // LANZA InvalidIbanException si es imposible
    public function tryParse(string $iban): ?ParsedIban; // laxo, null si falla
    public function format(string|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string;
}
```

**Criterios de aceptación**
- [ ] Ambas interfaces con firmas exactas (tipos union, defaults).
- [ ] Namespace `Daycry\Iban\Contracts`; sin imports de `codeigniter4/*`.
- [ ] PHPStan verde.

**Subtareas**
- [ ] Escribir las interfaces.
- [ ] Verificar con el test de arquitectura (T-03) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-12 — Contratos `Provider`/`Resolver`/`RegistryLoader`/`NationalCheckValidator`

- **Descripción**: Las 4 interfaces restantes framework-free.
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 30k in / 9k out tok · 1,04 €
- **Dependencias**: T-10
- **Archivos**: `src/Contracts/ProviderInterface.php`, `ResolverInterface.php`, `RegistryLoaderInterface.php`, `NationalCheckValidatorInterface.php`

**Firmas (verbatim spec §5):**

```php
interface ProviderInterface {
    public function supports(string $countryCode): bool;
    public function findByIban(ParsedIban $iban): ?BankInfo;
    public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo;
}
interface ResolverInterface {
    public function resolve(string|ParsedIban $iban): BankResult;
}
interface RegistryLoaderInterface {
    public function load(): array; // country => datos crudos
}
interface NationalCheckValidatorInterface {
    public function supports(string $countryCode): bool;
    public function verify(ParsedIban $iban): bool; // ausencia de impl = skip, nunca fallo
}
```

**Criterios de aceptación**
- [ ] Las 4 interfaces con firmas exactas.
- [ ] Contratos **congelados** al cierre de la Fase 1 (base contractual de todo el paquete).
- [ ] PHPStan verde; sin imports CI4.

**Subtareas**
- [ ] Escribir las 4 interfaces.
- [ ] Marcar contratos como congelados en `docs/` (nota) y commit.

**Notas**: Congelar aquí mitiga el riesgo de cambios de firma tardíos (C-02).

---

## 🗂️ Fase 2 — Registro estructural + infraestructura Registry [C-04] ⭐

**Estado**: 📝 borrador · **Estimado**: 36h · **Real**: — · **Coste est.**: 1.825 € · **Tokens est.**: 1.080k

> Hito de **datos**, la partida más cara y arriesgada. Autoría **independiente** de hechos (longitudes/offsets), cross-check contra `cmpayments/iban` e `ixnode/php-iban` (MIT); **nunca** copiar bytes de SWIFT/globalcitizen/Wikipedia/SwiftRef. Se construye por hitos incrementales (SEPA primero).

### T-13 — DTO `CountryStructure`

- **Descripción**: DTO `final readonly` que modela la estructura de un país en el registry.
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 30k in / 6k out tok · 0,83 €
- **Dependencias**: T-12
- **Archivos**: `src/Registry/CountryStructure.php`

**Firmas (verbatim spec §6.1):**

```php
final readonly class CountryStructure {
    public function __construct(
        public string  $countryCode,
        public int     $ibanLength,          // longitud FIJA; rechazar len != esto ANTES del MOD-97
        public string  $bbanStructure,       // tokens SWIFT, p.ej. '4!n4!n2!n10!n' (ES)
        public array   $bank,                // [offset, length] sobre el IBAN normalizado (idx 4 = inicio BBAN)
        public ?array  $branch,              // [offset, length] | null
        public array   $account,             // [offset, length]
        public ?array  $nationalCheck,       // [offset, length] | null
        public bool    $sepa,                // ámbito SEPA de país (EPC409-09)
        public string  $ibanExampleElectronic,
    ) {}
}
```

**Criterios de aceptación**
- [ ] 9 propiedades con tipos/nulabilidad exactos.
- [ ] Test de construcción con datos de ES (`ibanLength=24`, `bbanStructure='4!n4!n2!n10!n'`).

**Subtareas**
- [ ] Test de construcción (rojo).
- [ ] Implementar DTO (verde) y commit.

**Notas**: `bank`/`account` no-nulables; `branch`/`nationalCheck` nulables (país sin ese campo).

<!-- ==================================================================== -->

### T-14 — `PhpRegistryLoader` + `Registry` (has/get/all + VERSION)

- **Descripción**: `PhpRegistryLoader implements RegistryLoaderInterface` carga `data/countries.php` (array crudo → `CountryStructure[]`); `Registry` expone `has(cc)`, `get(cc)`, `all()` y la constante `Registry::VERSION` (fecha/revisión + nota "autoría independiente, no derivado del fichero SWIFT").
- **Estado**: 📝 borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 70k in / 14k out tok · 1,93 €
- **Dependencias**: T-13
- **Archivos**: `src/Registry/PhpRegistryLoader.php`, `src/Registry/Registry.php`

**Firmas (spec §6.1):** `Registry`: `has(cc): bool`, `get(cc): CountryStructure`, `all(): array`; `const VERSION`. `RegistryLoaderInterface::load(): array`.

**Criterios de aceptación**
- [ ] `PhpRegistryLoader::load()` devuelve el array crudo desde `data/countries.php`.
- [ ] `Registry::get('ES')` devuelve un `CountryStructure`; `has('ZZ')` → `false`; `get('ZZ')` maneja el caso (excepción o null documentado).
- [ ] `Registry::VERSION` incluye fecha y la nota de autoría independiente.
- [ ] `all()` devuelve el mapa completo.

**Subtareas**
- [ ] Test con un `data/countries.php` mínimo (solo ES) (rojo).
- [ ] Implementar loader + registry + VERSION (verde) y commit.

**Notas**: Loader intercambiable (patrón jschaedl); default en código, cero deps.

<!-- ==================================================================== -->

### T-15 — Formato de `data/countries.php` + metodología de autoría

- **Descripción**: Definir el **formato del array crudo** de `data/countries.php` (claves por país: `iban_length`, `bban_structure`, `bank`, `branch`, `account`, `national_check`, `sepa`, `example`) y documentar la metodología de autoría independiente + cross-check MIT. Sembrar solo **ES** como referencia.
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 40k in / 8k out tok · 1,10 €
- **Dependencias**: T-14
- **Archivos**: `src/Registry/data/countries.php`, `docs/registry-authoring.md`

**Criterios de aceptación**
- [ ] `data/countries.php` con la entrada ES completa y verificada (MOD-97 del ejemplo == 1, offsets suman el BBAN).
- [ ] `docs/registry-authoring.md` describe la fuente de hechos, el cross-check y la prohibición de copiar bytes restringidos.
- [ ] Test que valida la coherencia estructural de la entrada ES.

**Subtareas**
- [ ] Escribir el schema y la entrada ES.
- [ ] Documentar metodología y commit.

**Notas**: Este formato es también la salida objetivo del generador `bin/` (T-28/T-29).

<!-- ==================================================================== -->

### T-16 — Datos: SEPA core (~22 países)

- **Descripción**: Autorar las entradas de ~22 países SEPA de la eurozona/núcleo (ES, DE, FR, IT, NL, BE, PT, AT, IE, FI, LU, GR, SK, SI, EE, LV, LT, CY, MT, MC, SM, AD…): longitud, tokens, offsets banco/sucursal/cuenta/nationalCheck, flag `sepa`, ejemplo. Verificación país a país contra 2 libs MIT.
- **Estado**: 📝 borrador
- **Tiempo**: est. 9h · real —
- **Previsión IA**: 230k in / 46k out tok · 6,35 €
- **Dependencias**: T-15
- **Archivos**: `src/Registry/data/countries.php`, `tests/Registry/fixtures/`

**Criterios de aceptación**
- [ ] ~22 entradas SEPA con offsets verificados (los límites banco/sucursal pueden no coincidir con los de token).
- [ ] Para cada país: ejemplo con MOD-97 == 1 y offsets que cubren exactamente el BBAN.
- [ ] Fixture válido por país; cross-check documentado contra `cmpayments/iban` e `ixnode/php-iban`.

**Subtareas**
- [ ] Autorar entradas por lotes; anotar discrepancias del cross-check.
- [ ] Añadir fixtures y correr los tests estructurales (T-19).
- [ ] Commit por lotes pequeños.

**Notas**: Supervisión reforzada (tarea de datos). Empezar por SEPA cumple el hito incremental del evaluador.

<!-- ==================================================================== -->

### T-17 — Datos: resto EU/EEA + SEPA no-euro (~22 países)

- **Descripción**: Autorar ~22 países EU/EEA y SEPA no-euro (GB, CH, LI, NO, SE, DK, IS, PL, CZ, HU, RO, BG, HR, GI, JE, GG, IM, FO, GL…) con la misma metodología y verificación.
- **Estado**: 📝 borrador
- **Tiempo**: est. 9h · real —
- **Previsión IA**: 230k in / 46k out tok · 6,35 €
- **Dependencias**: T-16
- **Archivos**: `src/Registry/data/countries.php`, `tests/Registry/fixtures/`

**Criterios de aceptación**
- [ ] ~22 entradas verificadas con fixture válido por país.
- [ ] Flag `sepa` correcto (país SEPA vs. no).
- [ ] Cross-check documentado; discrepancias resueltas o anotadas.

**Subtareas**
- [ ] Autorar entradas por lotes.
- [ ] Fixtures + tests estructurales.
- [ ] Commit por lotes.

**Notas**: Casos con quirks de sub-estructura (p. ej. GB con sort code) documentados en `registry-authoring.md`.

<!-- ==================================================================== -->

### T-18 — Datos: resto del set SWIFT no-SEPA (~40 países)

- **Descripción**: Completar el set SWIFT con ~40 países no-SEPA (TR, IL, SA, AE, QA, KW, BH, JO, LB, EG, TN, MA, MR, PK, XK, MK, RS, ME, BA, AL, MD, UA, GE, AZ, KZ, VG, MU, SC, DO, GT, CR, SV, BR, TL, IQ, PS, LY, ST…) hasta cubrir el set completo (~84).
- **Estado**: 📝 borrador
- **Tiempo**: est. 10h · real —
- **Previsión IA**: 250k in / 50k out tok · 6,90 €
- **Dependencias**: T-17
- **Archivos**: `src/Registry/data/countries.php`, `tests/Registry/fixtures/`

**Criterios de aceptación**
- [ ] El registry cubre el **set SWIFT completo (~84 países)**.
- [ ] Fixture válido por país; todos con MOD-97 == 1.
- [ ] `Registry::all()` devuelve ~84 entradas; `Registry::VERSION` actualizada.

**Subtareas**
- [ ] Autorar entradas por lotes.
- [ ] Fixtures + tests estructurales completos.
- [ ] Commit por lotes; cerrar el conteo final de países.

**Notas**: Nº exacto de países es incógnita (~80–85); se fija el conteo real al cerrar. IBAN largos (MT/RU si aplica) validan el MOD-97 por ventanas.

<!-- ==================================================================== -->

### T-19 — Tests estructurales parametrizados del registry

- **Descripción**: Test data-driven que, por cada país, verifica coherencia: longitud del ejemplo == `ibanLength`; los tokens `bbanStructure` cubren la longitud del BBAN; los offsets `bank/branch/account/nationalCheck` caen dentro del rango y no se solapan indebidamente; el ejemplo pasa MOD-97 (una vez exista `Mod97`, T-21).
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 50k in / 10k out tok · 1,38 €
- **Dependencias**: T-16 (crece con T-17/T-18)
- **Archivos**: `tests/Registry/CountriesDataIntegrityTest.php`

**Criterios de aceptación**
- [ ] Un caso por país (data provider desde `Registry::all()`).
- [ ] Falla si un offset excede `ibanLength` o si la suma de tokens ≠ longitud del BBAN.
- [ ] Verificación MOD-97 del ejemplo integrada tras T-21 (o marcada skip hasta entonces).

**Subtareas**
- [ ] Escribir el test parametrizado (rojo mientras faltan países).
- [ ] Integrar MOD-97 tras T-21 y commit.

**Notas**: Este test es la red de seguridad de la calidad de datos (mitiga "offsets erróneos").

---

## ⚙️ Fase 3 — Core algorítmico [C-03]

**Estado**: 📝 borrador · **Estimado**: 24h · **Real**: — · **Coste est.**: 1.215 € · **Tokens est.**: 620k

### T-20 — `Normalizer`

- **Descripción**: `Normalizer::normalize(string): string` — quita espacios y prefijo `IBAN` inicial, pasa a mayúsculas y asegura `[A-Z0-9]`. Paso común de validate/parse/format; se aplica **antes** de cualquier check.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2,5h · real —
- **Previsión IA**: 50k in / 12k out tok · 1,52 €
- **Dependencias**: T-11
- **Archivos**: `src/Core/Normalizer.php`, `tests/Core/NormalizerTest.php`

**Criterios de aceptación**
- [ ] `'  iban es91 2100...' → 'ES91...'` (mayúsculas, sin ws, sin prefijo).
- [ ] Elimina el prefijo `IBAN` solo al inicio, no en medio.
- [ ] No altera caracteres `[A-Z0-9]` ya válidos; los inválidos se conservan para que el `Validator` reporte `IllegalCharacters` (el normalizer no valida, solo canoniza charset a mayúsculas).

**Subtareas**
- [ ] Tests de casos (ws, prefijo, minúsculas, mixto `c`) (rojo).
- [ ] Implementar `Normalizer` (verde) y commit.

**Notas**: Orden inverso (check antes de mayúsculas) es el bug clásico que se evita explícitamente (§6.3).

<!-- ==================================================================== -->

### T-21 — `Mod97` (ventanas de 9 dígitos + generación 98−mod)

- **Descripción**: `Mod97` por **ventanas de 9 dígitos** arrastrando el resto (sin `bcmath`, seguro en 64 bits). Reordena (4 primeros al final), letras `A=10..Z=35`; válido si `mod 97 == 1`. Método de generación de dígitos de control: `98 - mod(CC+'00'+BBAN)`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 4h · real —
- **Previsión IA**: 85k in / 20k out tok · 2,55 €
- **Dependencias**: T-20
- **Archivos**: `src/Core/Mod97.php`, `tests/Core/Mod97Test.php`

**Criterios de aceptación**
- [ ] `Mod97::isValid('ES91...')` == `true` para IBAN válidos; `false` si se altera un dígito.
- [ ] `Mod97::checkDigits('ES', bban)` devuelve los 2 dígitos tales que el IBAN resultante valida.
- [ ] Correcto con IBAN de longitud máxima del registry (sin overflow en 64 bits).

**Subtareas**
- [ ] Tests con IBAN cortos y largos + generación (rojo).
- [ ] Implementar ventanas de 9 dígitos + `checkDigits` (verde) y commit.
- [ ] Integrar en el test de integridad del registry (T-19).

**Notas**: Técnica de módulo por ventanas (Apache Commons `IBANCheckDigit`).

<!-- ==================================================================== -->

### T-22 — `StructureCompiler` (tokens SWIFT → regex anclada y cacheada)

- **Descripción**: Convierte `bbanStructure` (`<longitud>[!]<clase>`, clases `n`/`a`/`c`/`e`) en una **regex anclada una sola vez por país y cacheada** (patrón `RegexConverter`). Evita mantener regex a mano.
- **Estado**: 📝 borrador
- **Tiempo**: est. 5h · real —
- **Previsión IA**: 105k in / 25k out tok · 3,17 €
- **Dependencias**: T-21
- **Archivos**: `src/Core/StructureCompiler.php`, `tests/Core/StructureCompilerTest.php`

**Criterios de aceptación**
- [ ] `'4!n4!n2!n10!n'` → regex que casa exactamente 20 dígitos del BBAN de ES.
- [ ] Clases `n` (0-9), `a` (A-Z), `c` (alfanum tras mayúsculas), `e` (espacio) mapeadas correctamente; `!` = longitud fija.
- [ ] La regex se **cachea** por país (no se recompila en cada validación).

**Subtareas**
- [ ] Tests por clase de token y por `!` fijo/variable (rojo).
- [ ] Implementar compiler + caché (verde) y commit.

**Notas**: Incógnita cubierta: la clase `c` se valida tras mayúsculas (coherente con el `Normalizer`).

<!-- ==================================================================== -->

### T-23 — `Validator` (pipeline de 8 `ViolationCode` con corto-circuito)

- **Descripción**: `Validator implements ValidatorInterface`. Pipeline en orden de lo barato/específico a lo caro, **primera violación** (corto-circuito): `Blank/TooShort → UnknownCountry → IllegalCharacters → BadLength → MalformedStructure → ChecksumFailed → [NationalCheckFailed si checkNational]`. Nunca lanza.
- **Estado**: 📝 borrador
- **Tiempo**: est. 6h · real —
- **Previsión IA**: 125k in / 30k out tok · 3,80 €
- **Dependencias**: T-22, T-14 (Registry), T-08 (ValidationResult)
- **Archivos**: `src/Core/Validator.php`, `tests/Core/ValidatorTest.php`

**Criterios de aceptación**
- [ ] Un test por cada `ViolationCode` (7 en v1.0 sin national; el 8º en Fase 4).
- [ ] El **orden de evaluación** se verifica: un IBAN con varios fallos reporta la razón más específica según el pipeline.
- [ ] `validate()`/`isValid()` **nunca lanzan**; devuelven `ValidationResult`.
- [ ] Acepta `string|ParsedIban` y `bool $checkNational = false`.

**Subtareas**
- [ ] Tests por code + orden (rojo).
- [ ] Implementar pipeline con corto-circuito (verde) y commit.

**Notas**: `NationalCheckFailed` se cablea en T-27 (Fase 4). Recolección multi-violación queda para futuro.

<!-- ==================================================================== -->

### T-24 — `Parser` (parse / tryParse / normalize)

- **Descripción**: `Parser implements ParserInterface`. `normalize()` → `validate()` → trocea por offsets del registry → `ParsedIban`. `parse()` lanza `InvalidIbanException` (con el `ValidationResult` dentro) sobre entrada imposible; `tryParse()` devuelve `null`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 4h · real —
- **Previsión IA**: 85k in / 21k out tok · 2,62 €
- **Dependencias**: T-23
- **Archivos**: `src/Core/Parser.php`, `tests/Core/ParserTest.php`

**Criterios de aceptación**
- [ ] `parse('ES91...')` devuelve `ParsedIban` con banco/sucursal/cuenta/nationalCheck troceados por offsets; `branchIdentifier=null` en DE/NL/BE.
- [ ] `parse('basura')` lanza `InvalidIbanException`; `$e->result()->firstViolation()` da el code correcto.
- [ ] `tryParse('basura')` devuelve `null`; `tryParse('ES91...')` devuelve `ParsedIban`.
- [ ] `sepaCountry` se rellena desde el registry.

**Subtareas**
- [ ] Tests de parse/tryParse/excepción (rojo).
- [ ] Implementar troceo por offsets (verde) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-25 — `Formatter` (Electronic/Print/Anonymized) + `ParsedIban::format`

- **Descripción**: `Formatter::format(string|ParsedIban, IbanFormat): string` con las 3 formas y cableado de `ParsedIban::format()` para delegar en él. **Anonymized** = visible código de país + últimos 4, resto enmascarado con `*` (supuesto declarado). Round-trip Electronic ↔ Print.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2,5h · real —
- **Previsión IA**: 50k in / 12k out tok · 1,52 €
- **Dependencias**: T-24
- **Archivos**: `src/Core/Formatter.php`, `src/DTO/ParsedIban.php` (wiring), `tests/Core/FormatterTest.php`

**Criterios de aceptación**
- [ ] `Electronic` = forma canónica sin espacios; `Print` = grupos de 4 separados por espacio; `Anonymized` = `ES**...**1234` (país + últimos 4 visibles, resto `*`).
- [ ] `ParsedIban::format(IbanFormat::Print)` delega en `Formatter` y funciona.
- [ ] Round-trip Electronic ↔ Print reversible.

**Subtareas**
- [ ] Tests de las 3 formas + round-trip (rojo).
- [ ] Implementar `Formatter` + wiring de `ParsedIban::format` (verde) y commit.

**Notas**: Esquema exacto de máscara documentado en Fase 8 (T-53).

---

## 🇪🇸 Fase 4 — Check nacional ES (mod-11) + generador `bin/` [C-06, C-05]

**Estado**: 📝 borrador · **Estimado**: 13h · **Real**: — · **Coste est.**: 661 € · **Tokens est.**: 435k

### T-26 — `SpanishNationalCheckValidator` (mod-11)

- **Descripción**: `SpanishNationalCheckValidator implements NationalCheckValidatorInterface`: mod-11 ponderado (pesos `1,2,4,8,5,10,9,7,3,6`; primer dígito sobre banco+sucursal (8), segundo sobre la cuenta (10); resto 10→'1', 11→'0'). `supports('ES')`; ausencia de impl = skip silencioso.
- **Estado**: 📝 borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 90k in / 21k out tok · 2,69 €
- **Dependencias**: T-24 (ParsedIban parseado), T-12 (interface)
- **Archivos**: `src/National/SpanishNationalCheckValidator.php`, `tests/National/SpanishNationalCheckValidatorTest.php`

**Criterios de aceptación**
- [ ] `supports('ES')` → `true`; `supports('DE')` → `false`.
- [ ] `verify()` valida los 2 dígitos de control (DC) sobre banco+sucursal y cuenta.
- [ ] Casos límite `10→'1'` y `11→'0'` cubiertos por fixtures.

**Subtareas**
- [ ] Fixtures mod-11 ES válidos/inválidos (incl. edge) (rojo).
- [ ] Implementar el algoritmo ponderado (verde) y commit.

**Notas**: Algoritmo conocido, riesgo bajo (evaluación C-06).

<!-- ==================================================================== -->

### T-27 — Cablear el check nacional en el `Validator`

- **Descripción**: Integrar la resolución por país del `NationalCheckValidatorInterface` en el pipeline: con `checkNational=true`, tras `ChecksumFailed`, invocar el validador nacional si existe para el país; fallo → `NationalCheckFailed`; ausencia de impl = **skip silencioso** (no fallo).
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 60k in / 14k out tok · 1,79 €
- **Dependencias**: T-26, T-23
- **Archivos**: `src/Core/Validator.php`, `tests/Core/ValidatorNationalTest.php`

**Criterios de aceptación**
- [ ] `validate($esIbanConDcMalo, checkNational: true)` → `NationalCheckFailed`.
- [ ] `validate($esIbanConDcMalo)` (sin flag) → válido (no se invoca el national).
- [ ] País sin impl nacional + `checkNational=true` → válido (skip silencioso).

**Subtareas**
- [ ] Registro de validadores nacionales por país (map `ES` → impl).
- [ ] Tests de flag on/off y skip (rojo→verde) y commit.

**Notas**: Completa el 8º `ViolationCode` del pipeline (T-23).

<!-- ==================================================================== -->

### T-28 — Generador `bin/`: fuente de hechos → parseo

- **Descripción**: Scaffolding del generador en `bin/generate-registry.php`: lee una **fuente de hechos de autoría propia** (tabla CSV/PHP con longitud/tokens/offsets/sepa/ejemplo por país) y la parsea a una estructura intermedia. Formato de entrada = autoría propia (supuesto declarado; el spec no lo fija).
- **Estado**: 📝 borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 75k in / 19k out tok · 2,35 €
- **Dependencias**: T-15 (formato de salida)
- **Archivos**: `bin/generate-registry.php`, `bin/data/registry-facts.csv`

**Criterios de aceptación**
- [ ] El generador lee la fuente de hechos y produce una estructura intermedia por país.
- [ ] Falla con mensaje claro si la fuente tiene filas malformadas.
- [ ] La fuente de hechos es de autoría propia (sin licencia restrictiva).

**Subtareas**
- [ ] Definir el formato de `registry-facts.csv`.
- [ ] Implementar el parseo (rojo→verde) y commit.

**Notas**: Diferible sin romper v1.0 (nice-to-have, refresco ~anual).

<!-- ==================================================================== -->

### T-29 — Generador `bin/`: emitir `countries.php` + cross-check + `--dry-run`

- **Descripción**: El generador emite `src/Registry/data/countries.php` (determinista, idéntico al autoría manual), con paso de **cross-check contra libs MIT** y flag `--dry-run` (imprime diff sin escribir).
- **Estado**: 📝 borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 75k in / 19k out tok · 2,35 €
- **Dependencias**: T-28
- **Archivos**: `bin/generate-registry.php`

**Criterios de aceptación**
- [ ] `php bin/generate-registry.php` produce un `countries.php` byte-idéntico al versionado (salida determinista).
- [ ] `--dry-run` muestra el diff y **no** escribe.
- [ ] El cross-check reporta discrepancias con las libs MIT (si están instaladas en dev).

**Subtareas**
- [ ] Emisión determinista (orden estable de países/claves).
- [ ] Cross-check + `--dry-run` (rojo→verde) y commit.

**Notas**: `Registry::VERSION` se refresca al regenerar.

<!-- ==================================================================== -->

### T-30 — Tests del generador (regeneración == fichero versionado)

- **Descripción**: Test que ejecuta el generador en modo dry-run y verifica que **regenerar == el `countries.php` commiteado** (detecta drift entre la fuente de hechos y el dato versionado).
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 50k in / 12k out tok · 1,52 €
- **Dependencias**: T-29
- **Archivos**: `tests/Bin/GenerateRegistryTest.php`

**Criterios de aceptación**
- [ ] El test falla si el `countries.php` versionado difiere de la salida del generador.
- [ ] El test corre en CI (parte de la matriz).

**Subtareas**
- [ ] Escribir el test de igualdad regeneración↔versionado (rojo→verde).
- [ ] Commit.

**Notas**: Mitiga el envejecimiento silencioso de los datos.

---

## 🔌 Fase 5 — Resolver + Providers + esquema BD [C-07]

**Estado**: 📝 borrador · **Estimado**: 14h · **Real**: — · **Coste est.**: 711 € · **Tokens est.**: 440k

### T-31 — `NullProvider` (default)

- **Descripción**: `NullProvider implements ProviderInterface`: `supports()` → `false`; `findByIban()`/`findByBankCode()` → `null`. Es el default de v1.0; garantiza degradación con gracia sin tocar BD.
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 35k in / 9k out tok · 1,10 €
- **Dependencias**: T-12
- **Archivos**: `src/Providers/NullProvider.php`, `tests/Providers/NullProviderTest.php`

**Criterios de aceptación**
- [ ] `supports(cualquier cc)` → `false`; `findBy*()` → `null`.
- [ ] No importa `codeigniter4/*` (framework-free).

**Subtareas**
- [ ] Test (rojo→verde) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-32 — `Resolver::resolve` (compone `BankResult`)

- **Descripción**: `Resolver implements ResolverInterface`. Si `$iban` es string, parsea; **siempre** construye `BankResult` desde `$parsed` (estructura + `sepaCountry`). Si `provider->supports(cc)`: superpone `BankInfo` con precedencia `findByIban($parsed)` → fallback `findByBankCode($cc, $bank, $branch)`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 4h · real —
- **Previsión IA**: 100k in / 26k out tok · 3,17 €
- **Dependencias**: T-31, T-24
- **Archivos**: `src/Resolver/Resolver.php`, `tests/Resolver/ResolverTest.php`

**Criterios de aceptación**
- [ ] Con `NullProvider`: `resolve()` devuelve `BankResult` con `$iban` completo (incl. `sepaCountry`) y todos los campos de banco `null`; `isResolved()` → `false`.
- [ ] Con un provider fake que `supports()`: se superpone el `BankInfo`; `isResolved()` → `true`.
- [ ] Precedencia `findByIban` → `findByBankCode` verificada (findByIban null → cae a findByBankCode).

**Subtareas**
- [ ] Tests con NullProvider y provider fake (rojo).
- [ ] Implementar composición + precedencia (verde) y commit.

**Notas**: Degradación sin condicionales en el llamante (§4 notas).

<!-- ==================================================================== -->

### T-33 — Facade `Iban` (3 interfaces + sub-servicios)

- **Descripción**: `final class Iban implements ValidatorInterface, ParserInterface, ResolverInterface`. Compone `Registry` (requerido) + `ProviderInterface $provider = new NullProvider()`; delega en `Validator`/`Parser`/`Resolver` y los expone como sub-servicios.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 50k in / 13k out tok · 1,59 €
- **Dependencias**: T-32, T-25
- **Archivos**: `src/Iban.php`, `tests/IbanFacadeTest.php`

**Criterios de aceptación**
- [ ] Implementa los 3 contratos; construible con solo `Registry` (provider default Null).
- [ ] `validate/isValid/normalize/parse/tryParse/format/resolve` delegan correctamente.
- [ ] Expone sub-servicios `validator()`/`parser()`/`resolver()` (o equivalentes).
- [ ] Framework-free (sin imports `codeigniter4/*`).

**Subtareas**
- [ ] Tests de delegación end-to-end (rojo).
- [ ] Implementar facade (verde) y commit.

**Notas**: `StructureCompiler`/`Mod97` permanecen internos, no en la API pública.

<!-- ==================================================================== -->

### T-34 — `BankModel` (CI4 Model sobre `banks`)

- **Descripción**: `BankModel` (Model CI4) sobre la tabla `banks`, con `$dbGroup` configurable y un método de consulta por clave natural para `findByBankCode`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2,5h · real —
- **Previsión IA**: 65k in / 16k out tok · 2,00 €
- **Dependencias**: T-10
- **Archivos**: `src/Models/BankModel.php`, `tests/Models/BankModelTest.php`

**Criterios de aceptación**
- [ ] `BankModel` mapea la tabla `banks` y usa `$dbGroup` de config.
- [ ] Consulta por `(country_code, bank_code, branch_code)` devuelve la fila o `null`.
- [ ] Depende de CI4 (permitido: está fuera de Core/Contracts).

**Subtareas**
- [ ] Test con SQLite en memoria (rojo).
- [ ] Implementar Model (verde) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-35 — Migración `banks` + Seed vacío (opt-in, no auto-run)

- **Descripción**: Migración que crea la tabla `banks` con el esquema exacto del spec §7 y los índices; Seed **vacío**. La migración **no se auto-ejecuta**: solo se corre si el usuario elige `DatabaseProvider`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 50k in / 13k out tok · 1,59 €
- **Dependencias**: T-34
- **Archivos**: `src/Database/Migrations/2026-07-10-000001_CreateBanksTable.php`, `src/Database/Seeds/BanksSeeder.php`

**Esquema (verbatim spec §7):** columnas `id` (PK), `country_code CHAR(2) NOT NULL`, `bank_code VARCHAR(35) NOT NULL`, `branch_code VARCHAR(35) NULL`, `bic VARCHAR(11) NULL`, `name VARCHAR(255) NULL`, `short_name VARCHAR(140) NULL`, `city VARCHAR(140) NULL`, `address VARCHAR(255) NULL`, `sepa_sct`/`sepa_sct_inst`/`sepa_sdd_core`/`sepa_sdd_b2b TINYINT NULL`, `source_id`/`source_version`/`source_license VARCHAR NULL`, `updated_at DATETIME`. Índices: `UNIQUE(country_code, bank_code, branch_code)`, `INDEX(country_code)`, `INDEX(bic)`.

**Criterios de aceptación**
- [ ] La migración crea la tabla con todas las columnas y los 3 índices.
- [ ] El Seed es vacío (no inserta filas).
- [ ] La migración **no** se ejecuta con el default (`NullProvider`); solo al optar por `DatabaseProvider`.

**Subtareas**
- [ ] Escribir migración + seed vacío.
- [ ] Test que aplica la migración en SQLite y verifica el esquema/índices (rojo→verde) y commit.

**Notas**: Opt-in estricto (§7).

<!-- ==================================================================== -->

### T-36 — `DatabaseProvider` (opcional)

- **Descripción**: `DatabaseProvider implements ProviderInterface`: `supports()` según config/tabla; `findByBankCode()` consulta `BankModel`; `findByIban()` delega en `findByBankCode()` con los campos ya parseados. Nunca se instancia con el default.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 50k in / 13k out tok · 1,59 €
- **Dependencias**: T-34, T-35
- **Archivos**: `src/Providers/DatabaseProvider.php`, `tests/Providers/DatabaseProviderTest.php`

**Criterios de aceptación**
- [ ] `findByBankCode()` devuelve `BankInfo` desde una fila `banks`; `null` si no existe.
- [ ] `findByIban($parsed)` delega en `findByBankCode($cc, $bankIdentifier, $branchIdentifier)`.
- [ ] Con `Resolver` + `DatabaseProvider` y una fila sembrada, `resolve()` produce `BankResult` con banco resuelto (`isResolved()` → `true`).

**Subtareas**
- [ ] Tests con SQLite en memoria + `DatabaseTestTrait` (rojo).
- [ ] Implementar provider (verde) y commit.

**Notas**: Único provider que depende de CI4 (§3).

---

## 🧩 Fase 6 — Integración CodeIgniter 4 (adaptador fino) [C-08]

**Estado**: 📝 borrador · **Estimado**: 16h · **Real**: — · **Coste est.**: 814 € · **Tokens est.**: 560k

### T-37 — `Config\Iban`

- **Descripción**: Config publicable con override por env: `$provider = 'null'` (`'null'|'database'|FQCN`), `$defaultFormat = 'print'`, `$checkNationalByDefault = false`, `$dbGroup = 'default'`, `$table = 'banks'`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 55k in / 13k out tok · 1,66 €
- **Dependencias**: T-33
- **Archivos**: `src/Config/Iban.php`, `tests/Config/IbanConfigTest.php`

**Criterios de aceptación**
- [ ] Los 5 parámetros con sus defaults exactos.
- [ ] Override por variable de entorno funciona.
- [ ] Config publicable (extiende `BaseConfig`).

**Subtareas**
- [ ] Escribir la config + test de defaults/override (rojo→verde) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-38 — `Config\Services::iban()`

- **Descripción**: `Services::iban(bool $getShared = true): Iban` — construye `Registry` (en código), instancia el provider configurado (Null por defecto), devuelve la facade. **Funciona out-of-the-box con BD vacía.**
- **Estado**: 📝 borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 85k in / 21k out tok · 2,62 €
- **Dependencias**: T-37
- **Archivos**: `src/Config/Services.php`, `tests/Config/ServicesTest.php`

**Criterios de aceptación**
- [ ] `service('iban')` devuelve una `Iban` operativa **sin configurar BD**.
- [ ] Con `$provider='database'` instancia `DatabaseProvider`; con `'null'`/default, `NullProvider`.
- [ ] Soporta FQCN de provider custom.

**Subtareas**
- [ ] Tests de instanciación con cada provider (rojo).
- [ ] Implementar el factory (verde) y commit.

**Notas**: Requisito explícito: el paquete instala/valida sin conexión a BD.

<!-- ==================================================================== -->

### T-39 — `Config\Registrar` (discovery PSR-4)

- **Descripción**: Registro/discovery de Services, Commands y helper vía PSR-4.
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 40k in / 10k out tok · 1,24 €
- **Dependencias**: T-38
- **Archivos**: `src/Config/Registrar.php`

**Criterios de aceptación**
- [ ] Los comandos aparecen en `spark list` al instalar el paquete.
- [ ] El helper `iban` es descubrible vía `helper('iban')`.

**Subtareas**
- [ ] Escribir el Registrar y verificar discovery en un test de integración (rojo→verde) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-40 — Helper `iban_helper.php` + alias

- **Descripción**: `Helpers/iban_helper.php` (vía `helper('iban')`): `iban_validate()`, `iban_is_valid()`, `iban_parse()`, `iban_format()`, `iban_resolve()`, más alias `bank_name()`, `bank_bic()`, `iban_country()`, `iban_valid()`. Todos delegan en `service('iban')`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 85k in / 21k out tok · 2,62 €
- **Dependencias**: T-38
- **Archivos**: `src/Helpers/iban_helper.php`, `tests/Helpers/IbanHelperTest.php`

**Criterios de aceptación**
- [ ] Las 5 funciones base + 4 alias existen y delegan en `service('iban')`.
- [ ] `bank_name($iban)` devuelve `null` con BD vacía (degradación); `iban_valid($iban)` devuelve `bool`.
- [ ] Sin redefinición si ya están declaradas (guardas `function_exists`).

**Subtareas**
- [ ] Tests de cada función/alias (rojo).
- [ ] Implementar helper (verde) y commit.

**Notas**: Alias afines a la visión "obtener el nombre del banco" sin perder el resto de capacidades.

<!-- ==================================================================== -->

### T-41 — Comando `iban:validate`

- **Descripción**: `ValidateCommand` (grupo `IBAN`): `iban:validate <iban> [--national] [--json]` → válido/inválido + `ViolationCode` + mensaje; **exit 0/1** para scripting. Thin wrapper sobre `service('iban')`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 55k in / 13k out tok · 1,66 €
- **Dependencias**: T-40
- **Archivos**: `src/Commands/ValidateCommand.php`, `tests/Commands/ValidateCommandTest.php`

**Criterios de aceptación**
- [ ] IBAN válido → salida OK + exit 0; inválido → `ViolationCode`/mensaje + exit 1.
- [ ] `--national` activa el check nacional; `--json` emite salida JSON.

**Subtareas**
- [ ] Tests de exit codes y `--json`/`--national` (rojo).
- [ ] Implementar comando (verde) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-42 — Comandos `iban:parse` y `iban:resolve`

- **Descripción**: `ParseCommand` (`iban:parse <iban> [--json]` → tabla del `ParsedIban`) y `ResolveCommand` (`iban:resolve <iban> [--json]` → `BankResult`; con BD vacía muestra estructura llena + nota "sin datos de provider").
- **Estado**: 📝 borrador
- **Tiempo**: est. 2,5h · real —
- **Previsión IA**: 70k in / 17k out tok · 2,14 €
- **Dependencias**: T-40
- **Archivos**: `src/Commands/ParseCommand.php`, `src/Commands/ResolveCommand.php`, `tests/Commands/ParseResolveCommandTest.php`

**Criterios de aceptación**
- [ ] `iban:parse` imprime la tabla del `ParsedIban`; `--json` emite JSON.
- [ ] `iban:resolve` con BD vacía muestra la estructura completa + la nota de "sin datos de provider".
- [ ] Ambos comandos delegan en `service('iban')`.

**Subtareas**
- [ ] Tests de salida tabla/JSON y degradación (rojo).
- [ ] Implementar comandos (verde) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-43 — Comando `iban:update` (scaffold no-op)

- **Descripción**: `UpdateCommand`: **scaffold documentado / no-op** en v1.0. Existe y es visible en `spark list`; imprime el **aviso de licencias** (SWIFT no-comercial; BIC SwiftRef propietario; listas nacionales con atribución); enumera importadores registrados (cero en v1.0); acepta ya la forma `--source= --country= --dry-run`; termina como no-op claro "sin importadores empaquetados — diferido a v1.1".
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 60k in / 15k out tok · 1,86 €
- **Dependencias**: T-40
- **Archivos**: `src/Commands/UpdateCommand.php`, `tests/Commands/UpdateCommandTest.php`

**Criterios de aceptación**
- [ ] Visible en `spark list`; acepta `--source/--country/--dry-run` sin fallar.
- [ ] Imprime el aviso de licencias y enumera 0 importadores.
- [ ] Termina como no-op explícito (mensaje "diferido a v1.1"), exit 0.

**Subtareas**
- [ ] Test del no-op + aviso de licencias (rojo).
- [ ] Implementar comando (verde) y commit.

**Notas**: Prepara el terreno para el `ImporterInterface` de v1.1 (§12).

---

## 🧪 Fase 7 — Suite de tests transversal [C-09]

**Estado**: 📝 borrador · **Estimado**: 24h · **Real**: — · **Coste est.**: 1.219 € · **Tokens est.**: 750k

> Transversal: los tests unitarios TDD viven en sus tareas (Fases 1–6). Esta fase consolida infraestructura y fixtures **cross-cutting**, y fija los umbrales de cobertura.

### T-44 — Bootstrap de tests + PHPUnit + cobertura

- **Descripción**: `phpunit.xml.dist`, bootstrap que permite correr **tests del Core sin arrancar CI** y `CIUnitTestCase` para integración; configuración de cobertura y umbrales (≥ 90 % Core/Registry, ≥ 80 % global — supuesto).
- **Estado**: 📝 borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 70k in / 18k out tok · 2,21 €
- **Dependencias**: T-05
- **Archivos**: `phpunit.xml.dist`, `tests/_bootstrap.php`, `tests/Core/**`

**Criterios de aceptación**
- [ ] `composer test` corre unit (core, sin CI) e integración (CI4) por separado.
- [ ] Reporte de cobertura generado; umbrales configurados.
- [ ] Un test demuestra que el Core se instancia sin bootstrap de CI.

**Subtareas**
- [ ] Escribir `phpunit.xml.dist` + bootstrap.
- [ ] Configurar cobertura y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-45 — Fixtures IBAN válidos/inválidos por país

- **Descripción**: Dataset de fixtures con **al menos un IBAN válido por longitud registrada** y variantes inválidas por país; data providers reutilizables por los tests de `Validator`/`Parser`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 6h · real —
- **Previsión IA**: 150k in / 38k out tok · 4,69 €
- **Dependencias**: T-18, T-24
- **Archivos**: `tests/fixtures/ibans_valid.php`, `tests/fixtures/ibans_invalid.php`, `tests/Core/CountryValidationTest.php`

**Criterios de aceptación**
- [ ] ≥ 1 fixture válido por longitud registrada (todos con MOD-97 == 1).
- [ ] Fixtures inválidos que disparan cada tipo de fallo (charset, longitud, checksum...).
- [ ] Los fixtures y los datos del registry se validan mutuamente (data ↔ tests).

**Subtareas**
- [ ] Construir los datasets (autoría propia, coherentes con el registry).
- [ ] Test parametrizado por país (rojo→verde) y commit.

**Notas**: Mantener alineados con la Fase 2 (riesgo de drift datos↔tests).

<!-- ==================================================================== -->

### T-46 — Matriz de `ViolationCode` + orden de evaluación

- **Descripción**: Test que cubre **un caso por cada `ViolationCode`** e incluye el **orden de evaluación** (un IBAN con múltiples fallos reporta la razón más específica según el pipeline).
- **Estado**: 📝 borrador
- **Tiempo**: est. 4h · real —
- **Previsión IA**: 100k in / 25k out tok · 3,11 €
- **Dependencias**: T-23, T-27
- **Archivos**: `tests/Core/ViolationMatrixTest.php`

**Criterios de aceptación**
- [ ] Los 8 `ViolationCode` tienen un caso positivo.
- [ ] El orden `Blank/TooShort → UnknownCountry → IllegalCharacters → BadLength → MalformedStructure → ChecksumFailed → NationalCheckFailed` se verifica con casos de fallo combinado.

**Subtareas**
- [ ] Construir la matriz de casos (rojo→verde).
- [ ] Commit.

**Notas**: —

<!-- ==================================================================== -->

### T-47 — Fixtures mod-11 ES consolidados

- **Descripción**: Consolidar los fixtures del check nacional ES (válidos e inválidos, incluido el manejo de `10→'1'` y `11→'0'`) en un dataset reutilizable.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 55k in / 13k out tok · 1,66 €
- **Dependencias**: T-26
- **Archivos**: `tests/fixtures/es_mod11.php`, `tests/National/SpanishFixturesTest.php`

**Criterios de aceptación**
- [ ] Fixtures con casos válidos e inválidos, incluidos los edge `10→'1'`/`11→'0'`.
- [ ] El test cubre ambos dígitos de control (banco+sucursal y cuenta).

**Subtareas**
- [ ] Consolidar fixtures.
- [ ] Test parametrizado (rojo→verde) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-48 — Tests de `DatabaseProvider` (SQLite en memoria)

- **Descripción**: Suite de integración del `DatabaseProvider` con **SQLite en memoria + `DatabaseTestTrait`**, incluyendo la verificación de que **`NullProvider` no toca BD**.
- **Estado**: 📝 borrador
- **Tiempo**: est. 4h · real —
- **Previsión IA**: 100k in / 25k out tok · 3,11 €
- **Dependencias**: T-36
- **Archivos**: `tests/Providers/DatabaseProviderIntegrationTest.php`

**Criterios de aceptación**
- [ ] Con filas sembradas, `resolve()` devuelve banco resuelto; sin filas, degrada.
- [ ] Un test confirma que el flujo con `NullProvider` **no abre conexión a BD**.
- [ ] La migración `banks` se aplica correctamente en SQLite.

**Subtareas**
- [ ] Configurar `DatabaseTestTrait` + migración.
- [ ] Tests de resolución y de "no toca BD" (rojo→verde) y commit.

**Notas**: —

<!-- ==================================================================== -->

### T-49 — Tests de comandos spark

- **Descripción**: Tests de los 4 comandos (`validate`/`parse`/`resolve`/`update`), incluido el **no-op de `iban:update`** y su **aviso de licencias**, y los exit codes de `iban:validate`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 75k in / 19k out tok · 2,35 €
- **Dependencias**: T-41, T-42, T-43
- **Archivos**: `tests/Commands/CommandsSmokeTest.php`

**Criterios de aceptación**
- [ ] Los 4 comandos se ejecutan vía runner de tests sin error.
- [ ] `iban:validate` devuelve exit 0/1 correcto; `iban:update` imprime el aviso de licencias y termina no-op.

**Subtareas**
- [ ] Tests de cada comando (rojo→verde).
- [ ] Commit.

**Notas**: —

<!-- ==================================================================== -->

### T-50 — Round-trips de formateo y generación de dígitos de control

- **Descripción**: Tests de round-trip `Electronic ↔ Print ↔ Anonymized` y de generación de dígitos de control (`98 − mod`), y verificación final del umbral de cobertura.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 50k in / 12k out tok · 1,52 €
- **Dependencias**: T-25, T-21
- **Archivos**: `tests/Core/RoundTripTest.php`

**Criterios de aceptación**
- [ ] `Electronic → Print → Electronic` es idempotente; `Anonymized` respeta país + últimos 4.
- [ ] `checkDigits()` regenera dígitos que hacen validar el IBAN (round-trip `98 − mod`).
- [ ] La cobertura global cumple el umbral configurado.

**Subtareas**
- [ ] Tests de round-trip (rojo→verde).
- [ ] Verificar cobertura y commit.

**Notas**: —

---

## 📚 Fase 8 — Documentación [C-10]

**Estado**: 📝 borrador · **Estimado**: 10h · **Real**: — · **Coste est.**: 510 € · **Tokens est.**: 340k

### T-51 — README completo

- **Descripción**: README con instalación (`composer require daycry/iban`), quickstart (facade/helper/comandos), matriz de versiones (PHP 8.3/8.4 × CI4 4.6), badges de CI.
- **Estado**: 📝 borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 75k in / 27k out tok · 2,90 €
- **Dependencias**: T-40, T-41
- **Archivos**: `README.md`

**Criterios de aceptación**
- [ ] Instalación + quickstart ejecutable (validar/parsear/formatear/resolver).
- [ ] Sección de comandos spark y de helper.
- [ ] Matriz de compatibilidad y badges.

**Subtareas**
- [ ] Redactar README y verificar ejemplos.
- [ ] Commit.

**Notas**: —

<!-- ==================================================================== -->

### T-52 — Guía de uso detallada

- **Descripción**: Documento de uso: API de la facade, cada `ViolationCode` con su significado, ejemplos de degradación con BD vacía, uso del `DatabaseProvider`.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2,5h · real —
- **Previsión IA**: 65k in / 23k out tok · 2,48 €
- **Dependencias**: T-51
- **Archivos**: `docs/usage.md`

**Criterios de aceptación**
- [ ] Tabla de `ViolationCode` con descripción y ejemplo.
- [ ] Ejemplos de `resolve()` con Null y Database providers.
- [ ] Guía de configuración (`Config\Iban`).

**Subtareas**
- [ ] Redactar la guía.
- [ ] Commit.

**Notas**: —

<!-- ==================================================================== -->

### T-53 — Doc de máscara `Anonymized` + i18n de mensajes

- **Descripción**: Documentar el esquema exacto de máscara `Anonymized` (código de país + últimos 4 visibles, resto `*`) y la decisión sobre `messageKey`/i18n (mensajes en inglés en el core; traducción opcional vía `Language/` de CI4).
- **Estado**: 📝 borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 40k in / 14k out tok · 1,52 €
- **Dependencias**: T-25
- **Archivos**: `docs/formatting.md`, `docs/i18n.md`

**Criterios de aceptación**
- [ ] Esquema de máscara documentado con ejemplos.
- [ ] Decisión de i18n registrada (§13 resuelto: inglés en core, i18n opcional CI4).

**Subtareas**
- [ ] Redactar ambos docs.
- [ ] Commit.

**Notas**: Cierra las cuestiones abiertas §13 del spec.

<!-- ==================================================================== -->

### T-54 — Doc de licencias y atribución

- **Descripción**: Nota de licencias: por qué **no** se empaquetan datos SWIFT/globalcitizen/Wikipedia/SwiftRef/EPC per-PSP; autoría independiente de hechos; cross-check MIT; `Registry::VERSION`; roadmap de fuentes v1.1 con sus licencias.
- **Estado**: 📝 borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 45k in / 16k out tok · 1,73 €
- **Dependencias**: T-15
- **Archivos**: `docs/licensing.md`

**Criterios de aceptación**
- [ ] Explica la distinción hechos (no copyrightables) vs. compilación SWIFT.
- [ ] Enumera las fuentes prohibidas y las de v1.1 con su licencia/atribución.
- [ ] Referencia `Registry::VERSION` y la metodología de autoría (`docs/registry-authoring.md`).

**Subtareas**
- [ ] Redactar la nota de licencias.
- [ ] Commit.

**Notas**: Precisión clave para la credibilidad legal (riesgo Muy alto).

<!-- ==================================================================== -->

### T-55 — CHANGELOG + roadmap + release notes

- **Descripción**: `CHANGELOG.md` (v1.0), notas de release y el roadmap v1.1/v2.0 (importadores, `iban:update` real, verificadores nacionales adicionales, proveedores externos).
- **Estado**: 📝 borrador
- **Tiempo**: est. 1h · real —
- **Previsión IA**: 25k in / 10k out tok · 1,04 €
- **Dependencias**: T-51
- **Archivos**: `CHANGELOG.md`, `docs/roadmap.md`

**Criterios de aceptación**
- [ ] `CHANGELOG.md` con la entrada v1.0 (features principales).
- [ ] Roadmap v1.1/v2.0 documentado (coherente con §12).

**Subtareas**
- [ ] Redactar changelog y roadmap.
- [ ] Commit y tag propuesto `v1.0.0`.

**Notas**: —

---

## 📝 Notas de implementación

_A completar durante la ejecución. Registra decisiones, desvíos de la estimación y aprendizajes._

- **Supuestos nuevos del plan** (no fijados por el spec): (1) set SWIFT ≈ **84 países**, dividido en lotes 22/22/40 (T-16/17/18) — el conteo real se cierra en T-18; (2) máscara `Anonymized` usa `*` sobre la forma electronic, país + últimos 4 visibles; (3) **PHPStan nivel 8**; (4) umbrales de cobertura ≥ 90 % Core/Registry y ≥ 80 % global; (5) formato de la fuente de hechos del generador = CSV de autoría propia (`bin/data/registry-facts.csv`); (6) la **facade** se completa en la Fase 5 (T-33), tras existir `Resolver`, exponiéndose vía Services en la Fase 6.
- **Trazabilidad C-xx**: cada característica del spec queda cubierta — C-01 (T-01..05), C-02 (T-06..12), C-03 (T-20..25), C-04 (T-13..19), C-05 (T-28..30), C-06 (T-26..27), C-07 (T-31..36), C-08 (T-37..43), C-09 (T-44..50 + tests TDD embebidos), C-10 (T-51..55).
