# daycry/iban — Diseño v1.0

- **Fecha:** 2026-07-10
- **Estado:** Aprobado en brainstorming (pendiente de revisión final del spec)
- **Paquete:** `daycry/iban` — librería de IBAN para CodeIgniter 4, usable también fuera de CI.
- **Filosofía (alineada con daycry/auth, daycry/jobs, daycry/doctrine):** sin dependencias innecesarias, core standalone, PSR-4/12/11, tests desde el día uno, documentación completa.

---

## 1. Objetivo y alcance

`daycry/iban` es la referencia para trabajar con IBAN en el ecosistema CodeIgniter 4: no solo "obtener el nombre del banco", sino validar, parsear, formatear, normalizar y resolver metadatos de entidad.

**Alcance de v1.0 (este spec):**
- **Core (cero dependencias):** validación ISO 13616, MOD-97, normalización, formateo, parseo (país / banco / sucursal / cuenta / dígito de control nacional) sobre un registro estructural en código para el set SWIFT completo (~80+ países).
- **Resolver (opcional):** `ResolverInterface` sobre `ProviderInterface` intercambiable. `NullProvider` por defecto; `DatabaseProvider` opcional (Model + Migration + Seed de CI4).
- **Integración CI4:** `Config\Iban`, `service('iban')`, `iban_helper`, comandos spark (`iban:validate`, `iban:parse`, `iban:resolve`, `iban:update` como scaffold).
- **Datos del resolver:** v1.0 sale con la BD **vacía** (`NullProvider` default). `resolve()` degrada con gracia: rellena todo lo estructural (incluido SEPA de país) y deja los campos de banco a `null`.
- **Check nacional:** extracción estructural de `nationalCheckDigit` en todos los países; **verificación** solo de España (mod-11 ponderado) como prueba del mecanismo.

**No-objetivos de v1.0 (roadmap posterior):**
- Importadores automáticos de listas oficiales por país → **v1.1** (`iban:update` va como scaffold no-op en v1.0).
- Verificación de dígitos de control nacionales más allá de ES → **v1.1**.
- Proveedores externos (p. ej. ibanapi/IbanComProvider), API REST, CLI independiente → **v2.0**.

---

## 2. Registro de decisiones

| # | Decisión | Elección | Motivo |
|---|---|---|---|
| 1 | Alcance de la sesión | v1.0 completo | Petición del autor. |
| 2 | Persistencia | Core sin deps (registro en código) + provider del resolver CI4-nativo (Model+Migrations+Seeds), **opcional** | Honra "sin deps" + "usable fuera de CI"; solo el `DatabaseProvider` depende de CI4. |
| 3 | Datos del resolver en v1.0 | BD vacía + degradación elegante; importadores en v1.1 | Lo más limpio legalmente y el v1.0 más pequeño. |
| 4 | Arquitectura | **Enfoque A**: facade sobre dos capas + dos DTOs + `ValidationResult` | Único que respeta core standalone + provider opcional + BD vacía; coincide con el estado del arte. |
| 5 | Cobertura de países | Set SWIFT completo (~80+) | Validador de referencia creíble desde v1.0. |
| 6 | Check nacional v1.0 | Extracción en todos + verificación de **ES** | Prueba del mecanismo con datos conocidos; evita el riesgo de validadores nacionales mal implementados. |
| 7 | Versiones | PHP `^8.3`, CI4 `^4.6`; matriz CI PHP 8.3/8.4 × CI4 4.6 | Suelo moderno; `readonly class`/enums; menos superficie de test. |

**Defaults menores fijados:** facade única + sub-servicios (`Validator`/`Parser`/`Resolver`) expuestos; `ParsedIban` minimalista (estructura + `sepaCountry`, sin moneda/banco central); `IbanFormat::Anonymized` = visible código de país + últimos 4, resto enmascarado; migración de `banks` **no** auto-ejecutada (opt-in estricto al elegir `DatabaseProvider`); script generador del registry incluido en `bin/` en v1.0.

---

## 3. Arquitectura

Dependencia en **un solo sentido**. El Core no conoce al Resolver ni a CI4. El Resolver conoce al Core. La integración CI4 (adaptador fino, sin lógica de dominio) conoce a ambos.

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

**Layout del repositorio:**

```
daycry/iban
├── src/
│   ├── Iban.php                      # Facade: implementa las 3 interfaces
│   ├── Contracts/                    # ValidatorInterface, ParserInterface, ResolverInterface,
│   │                                 #   ProviderInterface, RegistryLoaderInterface,
│   │                                 #   NationalCheckValidatorInterface
│   ├── DTO/                          # ParsedIban, BankResult, BankInfo, ValidationResult, Violation
│   ├── Enums/                        # ViolationCode, IbanFormat
│   ├── Exceptions/                   # IbanException (base), InvalidIbanException
│   ├── Core/                         # Normalizer, Validator, Parser, Mod97, StructureCompiler
│   ├── Registry/                     # Registry, PhpRegistryLoader, CountryStructure, data/countries.php
│   ├── Resolver/                     # Resolver
│   ├── Providers/                    # NullProvider, DatabaseProvider
│   ├── National/                     # NationalCheckValidatorInterface impls (v1.0: SpanishNationalCheckValidator)
│   ├── Config/                       # Iban (config), Services, Registrar
│   ├── Commands/                     # ValidateCommand, ParseCommand, ResolveCommand, UpdateCommand
│   ├── Models/                       # BankModel (solo DatabaseProvider)
│   ├── Database/{Migrations,Seeds}/  # crea tabla banks + seed VACÍO
│   └── Helpers/iban_helper.php
├── bin/                              # generador de src/Registry/data/countries.php (refresco ~anual)
├── tests/
├── docs/
└── composer.json                    # Daycry\Iban\ → src/ ; php ^8.3 ; codeigniter4/framework en require-dev
```

`Contracts/` y `Core/` no importan nada de `codeigniter4/*`. Solo `Config/`, `Commands/`, `Models/`, `Database/`, `Providers/DatabaseProvider` y el helper dependen de CI4.

---

## 4. Modelo de dominio (DTOs, enums, excepciones)

Todos los DTOs son `final readonly` (PHP 8.3).

```php
// Producido por Parser::parse y por Validator cuando el IBAN es válido.
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

// Producido SOLO por Resolver::resolve.
final readonly class BankResult {
    public function __construct(
        public ParsedIban $iban,            // composición: la estructura siempre presente
        public ?string $bankName,   public ?string $shortName,
        public ?string $bic,        public ?string $city,   public ?string $address,
        public ?bool $sepaSct,      public ?bool $sepaSctInst,
        public ?bool $sepaSddCore,  public ?bool $sepaSddB2b, // reachability per-PSP (solo si hay datos)
        public ?string $sourceId,   public ?string $sourceVersion, public ?string $sourceLicense,
    ) {}
    public function isResolved(): bool;     // true si algún campo de banco no es null
}

// DTO interno: lo que devuelve ProviderInterface (desacopla el provider de la salida del resolver).
final readonly class BankInfo {
    public function __construct(
        public ?string $bankName, public ?string $shortName,
        public ?string $bic, public ?string $city, public ?string $address,
        public ?bool $sepaSct, public ?bool $sepaSctInst, public ?bool $sepaSddCore, public ?bool $sepaSddB2b,
        public ?string $sourceId, public ?string $sourceVersion, public ?string $sourceLicense,
    ) {}
}

final readonly class ValidationResult {
    public function __construct(public bool $valid, public array $violations) {} // Violation[]
    public function isValid(): bool;
    public function violations(): array;
    public function firstViolation(): ?Violation;
}
final readonly class Violation {
    public function __construct(public ViolationCode $code, public string $messageKey, public string $message) {}
}

enum ViolationCode: string {
    case Blank = 'blank';
    case TooShort = 'too_short';
    case UnknownCountry = 'unknown_country';        // sin entrada en registry / prefijo no ISO
    case IllegalCharacters = 'illegal_characters';  // no [A-Z0-9] tras normalizar
    case BadLength = 'bad_length';                  // len != longitud del país
    case MalformedStructure = 'malformed_structure';// BBAN no casa con los tokens
    case ChecksumFailed = 'checksum_failed';        // MOD-97 != 1
    case NationalCheckFailed = 'national_check_failed'; // solo si checkNational=true
}
enum IbanFormat { case Electronic; case Print; case Anonymized; }

class IbanException extends \RuntimeException {}
final class InvalidIbanException extends IbanException {
    // transporta el ValidationResult que provocó el fallo del parseo estricto
    public function result(): ValidationResult;
}
```

**Notas de diseño:**
- El `?string` en `branchIdentifier`/`nationalCheckDigit` distingue "el país no tiene ese campo" (null estructural) de "lookup sin resolver" (campos null en `BankResult`) — arregla el `''` ambiguo de globalcitizen.
- Con `NullProvider`, `resolve()` devuelve un `BankResult` con `$iban` completo y todos los campos de banco `null` → degradación sin condicionales en el llamante.
- El SEPA se modela en dos niveles: `sepaCountry` (ámbito de país, EPC409-09, en el registry en código) vive en `ParsedIban`; la reachability per-PSP (`sepaSct*`) vive en `BankResult` y solo se rellena si un provider tiene datos.

---

## 5. Contratos (interfaces, framework-free)

Namespace `Daycry\Iban\Contracts`.

```php
interface ValidatorInterface {
    public function validate(string|ParsedIban $iban, bool $checkNational = false): ValidationResult; // NUNCA lanza
    public function isValid(string|ParsedIban $iban): bool;
}
interface ParserInterface {
    public function normalize(string $iban): string;    // quita ws + 'IBAN' inicial, mayúsculas, asegura [A-Z0-9]
    public function parse(string $iban): ParsedIban;     // LANZA InvalidIbanException si es imposible
    public function tryParse(string $iban): ?ParsedIban; // laxo, null si falla
    public function format(string|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string;
}
interface ProviderInterface {   // capa de datos tras el resolver
    public function supports(string $countryCode): bool;
    public function findByIban(ParsedIban $iban): ?BankInfo;
    public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo;
}
interface ResolverInterface {
    public function resolve(string|ParsedIban $iban): BankResult; // siempre devuelve; superpone provider si supports()
}
interface RegistryLoaderInterface {
    public function load(): array; // country => datos crudos; default: PhpRegistryLoader
}
interface NationalCheckValidatorInterface {
    public function supports(string $countryCode): bool;
    public function verify(ParsedIban $iban): bool; // ausencia de impl = skip, nunca fallo
}
```

**Facade:** `final class Iban implements ValidatorInterface, ParserInterface, ResolverInterface`, compone `Registry` (requerido) + `ProviderInterface $provider = new NullProvider()`. Se exponen también los sub-servicios `Validator` / `Parser` / `Resolver`. `StructureCompiler` y `Mod97` son colaboradores internos, no interfaces públicas.

---

## 6. Core interno

### 6.1 Registry estructural

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

- `PhpRegistryLoader implements RegistryLoaderInterface` carga `src/Registry/data/countries.php` (array crudo). Intercambiable; default en código, cero deps.
- `Registry`: `has(cc): bool`, `get(cc): CountryStructure`, `all(): array`. Constante `Registry::VERSION` con fecha/revisión y nota "autoría independiente, no derivado del fichero SWIFT".
- **Dos concerns ortogonales, ambos almacenados, nunca derivados uno de otro en runtime:** (a) validación charset+longitud desde los tokens SWIFT; (b) extracción por offsets `[offset,length]` explícitos (los límites banco/sucursal no siempre coinciden con los de token).

### 6.2 Gramática de tokens SWIFT y StructureCompiler

`<longitud>[!]<clase>` — `!` = longitud fija. Clases: `n` = dígitos 0-9, `a` = A-Z mayúsculas, `c` = alfanumérico mixto (validar tras mayúsculas), `e` = espacio. `StructureCompiler` convierte el string de tokens en una **regex anclada una sola vez** por país y la cachea (patrón `RegexConverter` de jschaedl); evita mantener regex a mano (frágil, error de Symfony).

### 6.3 Normalizer

Paso común de validate/parse/format: quita espacios y prefijo `IBAN` inicial, pasa a mayúsculas, asegura `[A-Z0-9]`. Se normaliza **antes** de cualquier check (el token `c` es mixto pero MOD-97 exige mayúsculas — orden inverso es bug clásico).

### 6.4 Validator (pipeline)

Chequeos de lo barato/específico a lo caro, con corto-circuito, devolviendo la razón más accionable:

```
Blank/TooShort → UnknownCountry → IllegalCharacters → BadLength
→ MalformedStructure (regex de tokens) → ChecksumFailed (MOD-97) → [NationalCheckFailed si checkNational]
```

v1.0 recoge la **primera** violación. Recolección multi-violación queda como posible mejora futura. `validate()`/`isValid()`/`tryParse()` nunca lanzan; `parse()` lanza `InvalidIbanException` (con el `ValidationResult` dentro) porque no se puede devolver un objeto estructurado a partir de basura.

### 6.5 Mod97

Módulo por **ventanas de 9 dígitos** arrastrando el resto (sin bigint, seguro en 64 bits, sin `bcmath`). Reordena (4 primeros al final), letras `A=10..Z=35`. Válido si `mod 97 == 1`. Generación de dígitos de control: `98 - (mod de CC+'00'+BBAN)`.

### 6.6 Check nacional (ES en v1.0)

`SpanishNationalCheckValidator implements NationalCheckValidatorInterface`: mod-11 ponderado (pesos `1,2,4,8,5,10,9,7,3,6`; primer dígito sobre banco+sucursal (8), segundo sobre la cuenta (10); resto 10→'1', 11→'0'). Resuelto por código de país; ausencia de impl = skip silencioso. Invocado solo con `checkNational=true`.

---

## 7. Resolver, Providers y esquema de BD

```php
final class Resolver implements ResolverInterface {
    public function resolve(string|ParsedIban $iban): BankResult {
        $parsed = /* $iban es string ? $this->parser->parse($iban) : $iban */;
        // siempre construye BankResult desde $parsed (estructura + sepaCountry)
        // si $this->provider->supports($parsed->countryCode): superpone BankInfo
    }
}
```

**Precedencia de lookup:** cuando `supports()` es `true`, el resolver llama primero a `findByIban($parsed)` (permite matching más fino) y, si devuelve `null`, cae a `findByBankCode($cc, $bankIdentifier, $branchIdentifier)`. El `DatabaseProvider` implementa `findByIban()` delegando en `findByBankCode()` con los campos ya parseados; otros providers pueden usar estrategias distintas.

- **`NullProvider`** (default v1.0): `supports()` → `false`; `findBy*()` → `null`. `resolve()` degrada con gracia.
- **`DatabaseProvider`** (opcional, `Config::$provider='database'`): usa `BankModel` (Model CI4) sobre la tabla `banks`. **Nunca** se instancia con el default → el paquete instala y valida sin conexión a BD ni migraciones.

**Migración `banks` (v1.0, seed VACÍO):**

| columna | tipo | notas |
|---|---|---|
| `id` | PK | |
| `country_code` | CHAR(2) NOT NULL | |
| `bank_code` | VARCHAR(35) NOT NULL | |
| `branch_code` | VARCHAR(35) NULL | |
| `bic` | VARCHAR(11) NULL | |
| `name` / `short_name` | VARCHAR(255)/(140) NULL | |
| `city` / `address` | VARCHAR(140)/(255) NULL | |
| `sepa_sct` / `sepa_sct_inst` / `sepa_sdd_core` / `sepa_sdd_b2b` | TINYINT NULL | reachability per-PSP |
| `source_id` / `source_version` / `source_license` | VARCHAR NULL | atribución (Bundesbank/OeNB) |
| `updated_at` | DATETIME | |

Índices: `UNIQUE(country_code, bank_code, branch_code)` (clave natural); `INDEX(country_code)`; `INDEX(bic)`. La migración existe en v1.0 pero **no se auto-ejecuta**: solo se corre si el usuario elige `DatabaseProvider` (opt-in estricto).

---

## 8. Integración CodeIgniter 4 (adaptador fino)

- **`Config\Iban`** (publicable, override por env): `$provider = 'null'` (`'null'|'database'|FQCN`), `$defaultFormat = 'print'`, `$checkNationalByDefault = false`, `$dbGroup = 'default'`, `$table = 'banks'`.
- **`Config\Services::iban(bool $getShared = true): Iban`** — construye `Registry` (en código), instancia el provider configurado (Null por defecto), devuelve la facade. `service('iban')` funciona out-of-the-box con BD vacía.
- **`Config\Registrar`** — registro/discovery de Services/Commands/helper vía PSR-4.
- **`Helpers/iban_helper.php`** (vía `helper('iban')`): `iban_validate()`, `iban_is_valid()`, `iban_parse()`, `iban_format()`, `iban_resolve()`, más alias afines a la visión (`bank_name()`, `bank_bic()`, `iban_country()`, `iban_valid()`) — todos delegan en `service('iban')`.
- **Comandos spark** (grupo `IBAN`, thin wrappers sobre `service('iban')`):
  - `iban:validate <iban> [--national] [--json]` → válido/invalido + `ViolationCode` + mensaje; exit 0/1 para scripting.
  - `iban:parse <iban> [--json]` → tabla del `ParsedIban`.
  - `iban:resolve <iban> [--json]` → `BankResult`; con BD vacía muestra estructura llena + nota "sin datos de provider".
  - `iban:update` → **scaffold documentado / no-op** en v1.0: existe y es visible en `spark list`; imprime el aviso de licencias (SWIFT registry no-comercial; BIC SwiftRef propietario; listas nacionales con atribución); enumera importadores registrados (cero en v1.0) vía un futuro `ImporterInterface`; acepta ya la forma `--source= --country= --dry-run`; termina como no-op claro "sin importadores empaquetados — diferido a v1.1".

---

## 9. Datos y licencias (restricciones críticas)

- **NO empaquetar** el fichero de la SWIFT IBAN Registry (licencia no-comercial, no-derivados + derechos de BD), ni `registry.txt` de globalcitizen (LGPL-3.0), ni la tabla de Wikipedia (CC BY-SA copyleft). Los **hechos** (longitudes/offsets) no son copyrightables; la compilación de SWIFT sí. → El `countries.php` se **autora de forma independiente** a partir de hechos, con libs MIT (`cmpayments/iban`, `ixnode/php-iban`) solo como cross-check.
- **NO empaquetar** ningún dato derivado del SWIFT BIC Directory (SwiftRef, propietario/de pago).
- **SEPA:** hardcodear en código solo la lista de ámbito de país (EPC409-09, corta y estable) para `sepaCountry`. El registro per-PSP (EPC Register of Participants) es grande/volátil → nunca se empaqueta.
- **Roadmap de fuentes para v1.1** (prioridad por limpieza de licencia): Austria/OeNB (CC BY 4.0) y Suiza/SIX ("uso libre") primero; Alemania/Bundesbank (libre + atribución "Source: Deutsche Bundesbank", **no alterar**) después; Países Bajos/Betaalvereniging (Excel libre, marcar incompleto); España/BdE al final (sin licencia abierta clara, export manual).

---

## 10. Estrategia de tests

- **PHPUnit** + `CIUnitTestCase`. Tests del Core **sin arrancar CI** (demuestran standalone).
- Fixtures de IBAN válidos/ inválidos por país (al menos uno por longitud registrada).
- Un caso por cada `ViolationCode` (incluye orden de evaluación: que reporte la razón más específica).
- Fixtures del mod-11 español (válidos e inválidos, incluido manejo de 10→'1', 11→'0').
- `DatabaseProvider` con SQLite en memoria + `DatabaseTestTrait`; verificar que `NullProvider` no toca BD.
- Tests de comandos spark (incl. `iban:update` no-op y su aviso de licencias).
- Round-trip de formateo (Electronic ↔ Print ↔ Anonymized) y de generación de dígitos de control (`98 - mod`).

---

## 11. Tooling, composer y CI

- **`composer.json`:** `require: { "php": "^8.3" }`; `require-dev: { "codeigniter4/framework": "^4.6", "phpunit/phpunit", "phpstan/phpstan", "..." }` (CI4 como peer, no dependencia dura). Autoload PSR-4 `Daycry\\Iban\\ → src/`; `autoload-dev` para tests; scripts `test`/`analyze`/`cs`.
- **Calidad:** PHPStan (nivel alto), PHP-CS-Fixer / CodeIgniter CodingStandard (PSR-12), Rector opcional.
- **CI (GitHub Actions):** matriz **PHP 8.3/8.4 × CI4 4.6**; jobs de tests + análisis estático + estilo.
- **`bin/` generador del registry:** script para regenerar `src/Registry/data/countries.php` desde una fuente de hechos de autoría propia (cross-check contra libs MIT), para el refresco ~anual; evita que los datos estructurales envejezcan en silencio.

---

## 12. Roadmap posterior (contexto, fuera de alcance v1.0)

- **v1.1:** `ImporterInterface { countryCode(); sourceId(); license(); fetch(): iterable; import(): ImportReport }` registrado por `countryCode+sourceId`; `iban:update` pasa de scaffold a descargador real; actualización incremental; caché; verificadores nacionales adicionales (BE, FR, IT, ...).
- **v2.0:** proveedores externos opcionales (p. ej. `IbanComProvider`/ibanapi), API REST, CLI independiente.

---

## 13. Cuestiones abiertas

Ninguna bloqueante. Notas para la fase de plan/implementación:
- Confirmar el esquema exacto de máscara de `Anonymized` en docs (fijado: código de país + últimos 4 visibles).
- Verificar offsets banco/sucursal por país contra libs MIT al autorar `countries.php` (tarea de datos, con tests por país).
- Definir claves de mensaje (`messageKey`) y si se traducen vía `Language/` de CI4 (el `message` por defecto puede ser inglés en el core standalone).

---

## 14. Referencias de investigación

- ISO 13616 / MOD-97-10 (ISO 7064); técnica de módulo por ventanas (Apache Commons `IBANCheckDigit`).
- SWIFT IBAN Registry (estructura y gramática de tokens MT) — **fuente de hechos, no de bytes**.
- Prior art PHP: `jschaedl/iban-validation` (loader intercambiable, RegexConverter), Symfony `IbanValidator` (códigos de error), `globalcitizen/php-iban` (formatos, antipatrones a evitar), `ixnode/php-iban`, `elgigi/iban`, `cmpayments/iban`.
- Licencias de datos: SWIFT (no-comercial/no-derivados), SwiftRef BIC (propietario), Bundesbank/OeNB/SIX/Betaalvereniging/BdE (para v1.1), EPC409-09 (SEPA).
