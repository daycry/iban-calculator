# 2026-07-10-daycry-iban-v1

> Plan de implementación de **daycry/iban v1.0**: librería IBAN para CodeIgniter 4 con core cero-dependencias (registro estructural en código para ~84 países) + resolver opcional (`NullProvider`/`DatabaseProvider`) e integración CI4, construida desde cero con TDD.

| | |
|---|---|
| **Fecha** | 2026-07-10 |
| **Estado** | 📝 borrador |
| **Tipo** | Nueva Funcionalidad (librería greenfield) |
| **Prioridad** | 🟠 Alta |
| **Solicitante** | daycry (daycry9@gmail.com) |
| **Responsable** | daycry |
| **Spec** | [`spec.md`](spec.md) · fuente: [`2026-07-10-daycry-iban-v1-design.md`](../../superpowers/specs/2026-07-10-daycry-iban-v1-design.md) |
| **Evaluación** | [`evaluation.md`](evaluation.md) |

---

## 🎛️ Cuadro de mando

| Métrica | Estimado | Real | Confianza |
|--------|---------|------|-----------|
| ⏱️ Tiempo | **188 h** (157 h base +20 %) | 0 h | Media |
| 💶 Coste total | **≈ 9.559 €** (base ≈ 7.966 €) | 0 € | Media |
| 🔢 Tokens IA | **≈ 4,68 M** base (in 3,75 M / out 0,93 M) | 0 | Baja |
| 📦 Tareas | **55** (en 8 fases) | 0 hechas | — |

> El coste está dominado por el trabajo humano (≈ 98,5 %); los tokens de IA son ≈ 139 € con margen. El precio de tokens está **⚠️ por verificar** (ver Supuestos económicos). Cifras alineadas con la evaluación (`evaluation.md`): 157 h base / ≈ 9.559 € con margen.

---

## ⏱️ Estimación por fase

Horas y tokens **base** (sin margen). Los C-xx entre corchetes trazan cada fase a la característica evaluada en `evaluation.md`.

| Fase | Estimado (h) | Tokens (in / out) | Coste € |
|------|-------------|-------------------|---------|
| Fase 1 — Fundaciones: tooling/CI + dominio/contratos [C-01, C-02] | 20 | 350k / 100k | 1.012 |
| Fase 2 — Registro estructural + infra Registry [C-04] ⭐ | 36 | 900k / 180k | 1.825 |
| Fase 3 — Core algorítmico [C-03] | 24 | 500k / 120k | 1.215 |
| Fase 4 — Check nacional ES + generador `bin/` [C-06, C-05] | 13 | 350k / 85k | 661 |
| Fase 5 — Resolver + Providers + BD [C-07] | 14 | 350k / 90k | 711 |
| Fase 6 — Integración CodeIgniter 4 [C-08] | 16 | 450k / 110k | 814 |
| Fase 7 — Suite de tests transversal [C-09] | 24 | 600k / 150k | 1.219 |
| Fase 8 — Documentación [C-10] | 10 | 250k / 90k | 510 |
| **Total (base)** | **157 h** | **3,75 M / 0,93 M** | **≈ 7.966 €** |
| **Total (con margen +20 %)** | **188 h** | **4,50 M / 1,11 M** | **≈ 9.559 €** |

---

## 💶 Presupuesto económico

**Coste = (horas × tarifa) + coste de tokens de IA.** Todos los importes en **EUR**.

### Supuestos (ajustables)

| Parámetro | Valor | Nota |
|-----------|-------|------|
| Tarifa de desarrollo | 50 €/h | Default del kit; no había tarifa definida en el proyecto. |
| Modelo IA asumido | claude-opus-4-8 | Base de la previsión de tokens (del spec). |
| Precio input | ≈ 13,80 € / 1M tokens | ⚠️ **verificar**: asume tarifa clase Opus ≈ 15 USD/1M × 0,92. |
| Precio output | ≈ 69,00 € / 1M tokens | ⚠️ **verificar**: asume tarifa clase Opus ≈ 75 USD/1M × 0,92. |
| Tipo de cambio | 1 USD = 0,92 € | Aplicado al precio de tokens. |
| Ratio de supervisión | ~25 % de horas IA | Revisión/validación humana del trabajo del agente. |
| Horas por FTE-mes | 160 h | Base del cálculo de FTE equivalentes. |
| Margen de contingencia | +20 % | Sobre horas base (humanas **e** IA); coste recalculado desde las horas con margen. |

> Cifras de tokens/coste **parametrizadas**: si el precio real difiere, el bloque token se recalcula sin tocar el resto. El peso humano (≈ 98,5 %) hace el total poco sensible al precio de tokens.

### Desglose

| Concepto | Cálculo | Importe |
|----------|---------|---------|
| Desarrollo (humano, base) | 157 h × 50 €/h | 7.850 € |
| Margen de contingencia (humano) | +20 % sobre 7.850 € | +1.570 € |
| Tokens IA input (con margen) | 4,50 M tok × 13,80 €/1M | 62,10 € |
| Tokens IA output (con margen) | 1,11 M tok × 69,00 €/1M | 76,59 € |
| **Total estimado (con margen)** | | **≈ 9.559 €** |

> Base sin margen: ≈ 7.966 € (7.850 € humano + 115,58 € tokens).

---

## ⚡ Productividad IA (humano vs. IA)

| KPI | Valor |
|-----|-------|
| Horas humanas estimadas | 188 h (con margen) |
| Horas IA (ejecución) | 20 h |
| Supervisión humana | 5 h |
| **Horas totales (IA + supervisión)** | **25 h** |
| Horas ahorradas | ≈ 163 h |
| **Ahorro** | **≈ 87 %** |
| **Multiplicador de productividad** | **×7,5** |
| FTE equivalentes *(opcional)* | ≈ 1,0 FTE-mes (163 h / 160 h) |

> Horas con el margen (+20 %) aplicado. Base: 157 h humanas · 16,5 h IA · 4,1 h supervisión (≈ 20,6 h totales). "Horas IA" y "supervisión" son estimaciones aproximadas (supervisión ≈ 25 % de las horas IA); **la supervisión de la Fase 2 (registro ~84 países) debe ser más intensa** por ser tarea de datos verificable país a país.

---

## 🔢 Previsión de tokens (por fase)

Consumo estimado por fase. Base: claude-opus-4-8 · precios de la tabla de supuestos (input 13,80 €/1M · output 69,00 €/1M). Valores **base** (sin margen).

| Fase | Input (tok) | Output (tok) | Total (tok) | Coste € |
|------|------------|-------------|-------------|---------|
| Fase 1 — Fundaciones | 350k | 100k | 450k | 11,76 |
| Fase 2 — Registro estructural | 900k | 180k | 1.080k | 24,84 |
| Fase 3 — Core | 500k | 120k | 620k | 15,18 |
| Fase 4 — Check ES + generador | 350k | 85k | 435k | 10,70 |
| Fase 5 — Resolver + BD | 350k | 90k | 440k | 11,04 |
| Fase 6 — Integración CI4 | 450k | 110k | 560k | 13,80 |
| Fase 7 — Tests transversales | 600k | 150k | 750k | 18,65 |
| Fase 8 — Documentación | 250k | 90k | 340k | 9,67 |
| **Total** | **3,75 M** | **0,93 M** | **4,68 M** | **≈ 115,58 €** |

**Método de estimación:** por tarea = (nº de ficheros de contexto a leer × tamaño medio + spec/prior-art) para *input*; (código + tests + fixtures generados) para *output*. La Fase 2 domina por el volumen de datos (~84 países × autoría+cross-check contra 2 libs MIT). Reutiliza las cifras por característica de `evaluation.md`, redistribuidas a nivel de tarea manteniendo los totales.

---

## 📋 Resumen ejecutivo

Se construye **desde cero** `daycry/iban`, librería de referencia para trabajar con IBAN en CodeIgniter 4 y **también fuera de CI**: validar (ISO 13616 / MOD-97), parsear, normalizar, formatear y resolver metadatos de entidad. La arquitectura es de **dos capas con dependencia unidireccional** (Core cero-deps → Resolver → adaptador fino CI4) más una facade `Iban`. El plan descompone las 10 características aprobadas (C-01..C-10) en **8 fases y 55 tareas bite-sized con TDD estricto**, respetando el orden por capas recomendado por el evaluador y aislando el **registro estructural de ~84 países (Fase 2)** como hito de datos con verificación país a país. v1.0 sale con la **BD vacía** (`NullProvider` por defecto) y degradación elegante; `iban:update` va como scaffold no-op documentado. Presupuesto: **157 h base (188 h con margen), ≈ 9.559 €**.

### 🎯 Objetivos

- Publicar un core **cero-dependencias** (no importa `codeigniter4/*` en `Contracts/` ni `Core/`) que valide, normalice, parsee y formatee IBAN del set SWIFT completo (~84 países) y que **corra sin arrancar CI**.
- Entregar un **registro estructural autoría propia** (hechos: longitudes/tokens/offsets), verificado país a país contra 2 libs MIT, **sin empaquetar bytes** de fuentes con licencia restrictiva.
- Ofrecer un **resolver intercambiable** con `NullProvider` (default, degradación sin condicionales en el llamante) y `DatabaseProvider` opcional (Model + Migration `banks` + Seed vacío, opt-in estricto).
- Integrar en CI4 como **adaptador fino** (Config, Services, Registrar, helper, 4 comandos spark) que funciona out-of-the-box con BD vacía.
- Cobertura de tests desde el día uno (un caso por `ViolationCode`, fixtures por país y longitud, mod-11 ES incl. 10→'1'/11→'0', round-trips) con matriz CI **PHP 8.3/8.4 × CI4 4.6**.

---

## 📥 Datos necesarios para un informe completo

- [x] **Requisitos funcionales** confirmados por el solicitante — *spec cerrada y aprobada; decisiones confirmadas §2·1–7.*
- [x] **Alcance** cerrado (qué entra y qué NO entra) — *§1 dentro/fuera muy explícito; v1.1/v2.0 diferidos.*
- [x] **Criterios de éxito / métricas** acordados — *derivados de §10 (tests) y de los contratos §5.*
- [x] **Accesos y credenciales** — *no aplica: paquete open-source local; no hay APIs de terceros en v1.0.*
- [x] **Entornos** disponibles y datos de prueba — *local + CI GitHub Actions; fixtures autoría propia; SQLite en memoria para el provider.*
- [x] **Stakeholders** identificados — *autor único (daycry).*
- [x] **Dependencias externas** mapeadas — *CI4 como peer (require-dev); libs MIT (`cmpayments/iban`, `ixnode/php-iban`) solo como cross-check, no runtime.*
- [x] **Restricciones** conocidas — *compliance de licencias de datos (§9); técnicas PHP ^8.3 / CI4 ^4.6; regla de dependencia unidireccional.*
- [ ] **Tarifa/hora y precio de tokens confirmados** — *se asumen defaults; **precio de tokens ⚠️ por verificar** (no bloqueante, ≈ 1,5 % del total).*

---

## 🔍 Análisis de impacto

Proyecto **greenfield**: el repo solo contiene `docs/`. Todo es creación nueva bajo `src/`, `tests/`, `bin/`, raíz.

- **`composer.json`, `.github/workflows/`, `phpstan.neon`, `.php-cs-fixer.dist.php`** — tooling, autoload PSR-4 `Daycry\Iban\ → src/`, matriz CI 8.3/8.4 × 4.6. [C-01]
- **`src/Contracts/`** — 6 interfaces framework-free (base contractual congelada pronto). [C-02]
- **`src/DTO/`, `src/Enums/`, `src/Exceptions/`** — modelo de dominio `final readonly`. [C-02]
- **`src/Core/`** — `Normalizer`, `Mod97`, `StructureCompiler`, `Validator`, `Parser`, `Formatter`; **no importa `codeigniter4/*`**. [C-03]
- **`src/Registry/` + `src/Registry/data/countries.php`** — infra + dato estructural de ~84 países (autoría propia). [C-04]
- **`bin/`** — generador de `countries.php` para refresco ~anual. [C-05]
- **`src/National/`** — `SpanishNationalCheckValidator` (mod-11). [C-06]
- **`src/Resolver/`, `src/Providers/`, `src/Models/`, `src/Database/{Migrations,Seeds}/`** — resolver + providers + esquema `banks`. [C-07]
- **`src/Config/`, `src/Commands/`, `src/Helpers/iban_helper.php`, `src/Iban.php`** (facade) — adaptador CI4. [C-08]
- **`tests/`** — suite transversal (unit del core sin CI + integración `CIUnitTestCase`). [C-09]
- **`README.md`, `docs/`** — documentación de uso, máscara Anonymized, licencias/atribución. [C-10]

---

## 🏗️ Cambios arquitectónicos

- **Dos capas + dependencia unidireccional (Enfoque A).** Core (cero-deps) → Resolver (conoce Core) → Integración CI4 (adaptador fino, conoce ambos). Se hace **cumplible con PHPStan**: regla que prohíbe imports `codeigniter4/*` en `Contracts/` y `Core/`, y test del Core que corre sin bootstrap de CI.
- **Facade `final class Iban implements ValidatorInterface, ParserInterface, ResolverInterface`** que compone `Registry` (requerido) + `ProviderInterface $provider = new NullProvider()`; expone sub-servicios `Validator`/`Parser`/`Resolver`. `StructureCompiler`/`Mod97` son colaboradores internos.
- **Dos concerns ortogonales en el registry, nunca derivados uno de otro en runtime:** (a) validación charset+longitud desde los tokens SWIFT compilados a regex; (b) extracción por offsets `[offset,length]` explícitos (los límites banco/sucursal no siempre coinciden con los de token).
- **MOD-97 por ventanas de 9 dígitos** (sin `bcmath`, seguro en 64 bits) + generación `98 − mod`.
- **SEPA en dos niveles:** `sepaCountry` (ámbito de país, EPC409-09, en el registry) en `ParsedIban`; reachability per-PSP (`sepaSct*`) en `BankResult`, solo si un provider tiene datos.
- **`NullProvider` por defecto → degradación sin condicionales:** `resolve()` siempre devuelve `BankResult` con `$iban` completo y campos de banco `null`; el paquete instala y valida **sin conexión a BD**.
- **`DatabaseProvider` opt-in estricto:** migración `banks` existe pero **no se auto-ejecuta**; solo `DatabaseProvider` depende de CI4.

---

## 📁 Archivos a crear/modificar

Todos **Crear** (greenfield). Selección representativa; el detalle exhaustivo por tarea está en [`tasks.md`](tasks.md).

| Archivo | Acción | Propósito |
|---------|--------|-----------|
| `composer.json` | Crear | PSR-4, php ^8.3, CI4 ^4.6 peer (require-dev), scripts test/analyze/cs |
| `phpstan.neon`, `.php-cs-fixer.dist.php` | Crear | Análisis estático (nivel alto) + PSR-12 + regla anti-import CI4 en Core |
| `.github/workflows/ci.yml` | Crear | Matriz PHP 8.3/8.4 × CI4 4.6 (tests + analyze + cs) |
| `src/Contracts/*.php` | Crear | 6 interfaces framework-free |
| `src/DTO/*.php`, `src/Enums/*.php`, `src/Exceptions/*.php` | Crear | Modelo de dominio `final readonly` + enums + excepciones |
| `src/Core/{Normalizer,Mod97,StructureCompiler,Validator,Parser,Formatter}.php` | Crear | Corazón algorítmico cero-deps |
| `src/Registry/{CountryStructure,Registry,PhpRegistryLoader}.php` + `data/countries.php` | Crear | Infra + dato estructural ~84 países |
| `bin/generate-registry.php` | Crear | Generador del registry (refresco ~anual) |
| `src/National/SpanishNationalCheckValidator.php` | Crear | mod-11 ES |
| `src/Resolver/Resolver.php`, `src/Providers/{NullProvider,DatabaseProvider}.php` | Crear | Resolver + providers |
| `src/Models/BankModel.php`, `src/Database/Migrations/*_CreateBanksTable.php`, `src/Database/Seeds/BanksSeeder.php` | Crear | Esquema `banks` + seed vacío (opt-in) |
| `src/Iban.php` | Crear | Facade (3 interfaces) |
| `src/Config/{Iban,Services,Registrar}.php` | Crear | Adaptador CI4 |
| `src/Commands/{Validate,Parse,Resolve,Update}Command.php` | Crear | 4 comandos spark |
| `src/Helpers/iban_helper.php` | Crear | Helper + alias (`bank_name`, `bank_bic`, `iban_country`, `iban_valid`) |
| `tests/**` | Crear | Suite unit (core sin CI) + integración |
| `README.md`, `docs/**`, `CHANGELOG.md`, `LICENSE` | Crear | Documentación + licencia MIT |

---

## 🔗 Dependencias y prerequisitos

- **Orden por capas (del evaluador):** `C-01 → C-02 → C-04 → C-03 → C-06 → C-05 → C-07 → C-08 → C-10`, con **C-09 (tests) transversal desde el día uno** (TDD). El plan lo materializa en 8 fases secuenciales.
- **Fase 3 (Core) depende de Fase 2 (Registry):** el `Validator`/`Parser` consumen `CountryStructure` (longitudes/tokens/offsets). Por eso Registry va antes.
- **Fase 5 (Resolver/Facade) depende de Fase 3:** `resolve()` parsea antes de superponer; la facade se completa aquí (compone Validator+Parser+Resolver).
- **Fase 6 (Integración) depende de Fase 5:** Services construye la facade; comandos/helper delegan en `service('iban')`.
- **Congelar contratos (C-02) pronto:** cambios de firma tardíos propagan a todo el paquete.
- **Sin dependencias de runtime externas:** CI4 es peer (require-dev); libs MIT solo cross-check en Fases 2 y 4.
- **C-05 (generador `bin/`) es lo único diferible** sin romper v1.0 (nice-to-have para el refresco ~anual).

---

## ✅ Criterios de aceptación (global)

- [ ] `composer install` y la suite pasan en la matriz **PHP 8.3/8.4 × CI4 4.6**; PHPStan (nivel alto) y CS (PSR-12) en verde.
- [ ] **PHPStan/regla arquitectónica** confirma que `Contracts/` y `Core/` no importan `codeigniter4/*`; existe un test del Core que corre **sin arrancar CI**.
- [ ] `Validator` reporta **la primera** violación según el orden `Blank/TooShort → UnknownCountry → IllegalCharacters → BadLength → MalformedStructure → ChecksumFailed → NationalCheckFailed`, con un test por cada `ViolationCode` incluido el orden.
- [ ] `Registry` cubre el **set SWIFT completo (~84 países)**; para cada país hay fixture válido (MOD-97 == 1) y su ejemplo estructural casa con los offsets; datos de **autoría propia** (sin bytes de fuentes restrictivas).
- [ ] `parse()` lanza `InvalidIbanException` (con `ValidationResult` dentro) sobre basura; `tryParse()` devuelve `null`; `validate()`/`isValid()` nunca lanzan.
- [ ] Round-trip de formateo `Electronic ↔ Print ↔ Anonymized` y generación de dígitos de control `98 − mod` verificados.
- [ ] `SpanishNationalCheckValidator` pasa fixtures mod-11 (incl. 10→'1', 11→'0'); solo se invoca con `checkNational=true`; ausencia de impl nacional = skip silencioso.
- [ ] `service('iban')` funciona **out-of-the-box con BD vacía**; `NullProvider` **no toca BD**; `resolve()` degrada (estructura llena, banco `null`).
- [ ] `DatabaseProvider` resuelve contra SQLite en memoria (`DatabaseTestTrait`); la migración `banks` **no se auto-ejecuta**.
- [ ] Los 4 comandos existen en `spark list`; `iban:validate` devuelve exit 0/1; `iban:update` es no-op documentado con aviso de licencias.
- [ ] README y docs cubren uso (facade/helper/comandos), esquema de máscara `Anonymized` y notas de licencias/atribución.
- [ ] **Cobertura**: ≥ 90 % líneas en `src/Core/` y `src/Registry/`; ≥ 80 % global (umbral asumido, ver Supuestos).

---

## ⚠️ Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| Offsets banco/sucursal erróneos por país (Fase 2) minan la credibilidad del validador | Media | Alto | Verificación país a país con tests dedicados + cross-check contra 2 libs MIT; hitos incrementales (SEPA primero, luego resto); supervisión reforzada. |
| Contaminación de licencia al empaquetar datos (SWIFT/globalcitizen/Wikipedia/SwiftRef) | Baja | **Muy alto** (legal) | Autoría de **hechos** (no copiar bytes); solo libs MIT como cross-check; `Registry::VERSION` con nota de autoría independiente; documentar en Fase 8. |
| Fuga de acoplamiento CI4 hacia el Core (rompe "usable fuera de CI") | Media | Alto | Regla arquitectónica de una sola dirección; test del Core sin arrancar CI; PHPStan detecta imports `codeigniter4/*` en `Core`/`Contracts`. |
| Cambios tardíos en contratos/DTOs (C-02) propagan a todo el paquete | Media | Medio | Congelar C-02 al final de la Fase 1; tests de contrato antes de construir sobre ellos. |
| Envejecimiento silencioso de los datos estructurales | Media | Medio | Generador en `bin/` (Fase 4) + `Registry::VERSION` fechada; refresco ~anual documentado. |
| Orden de normalización mal aplicado (bug clásico: check antes de mayúsculas) | Media | Medio | `Normalizer` como primer paso obligatorio de validate/parse/format; test dedicado del orden. |
| MOD-97 incorrecto en 64 bits (overflow) | Baja | Alto | Algoritmo por ventanas de 9 dígitos sin `bcmath`; fixtures de IBAN largos (Malta/Rusia). |
| Precio de tokens asumido incorrecto | Media | Bajo | Cálculo parametrizado; peso de tokens ≈ 1,5 % del total → impacto despreciable. |

---

## 📊 Métricas de éxito

- **Corrección**: 100 % de los fixtures por país (≥ 1 por longitud registrada) validan; 0 falsos positivos/negativos en la matriz de `ViolationCode`.
- **Aislamiento**: 0 imports `codeigniter4/*` en `Contracts/`+`Core/` (verificado por PHPStan); los tests del Core corren sin bootstrap CI.
- **Cobertura**: ≥ 90 % en Core/Registry, ≥ 80 % global.
- **CI verde** en las 2 combinaciones de la matriz (8.3/8.4 × 4.6) para tests + análisis + estilo.
- **Compliance**: revisión documental confirma que ningún byte de fuente restrictiva se empaqueta; `countries.php` declara autoría independiente.
- **DX**: `composer require daycry/iban` + `service('iban')` operativo sin configurar BD; 4 comandos visibles en `spark list`.

---

## ⏱️ Agregación de tiempo

- 2026-07-10: Creación del plan (`Tiempo consumido`: 0 h)

---

## 📝 Changelog

- 2026-07-10: Creación del plan (borrador) a partir de la cadena spec→evaluación. 8 fases, 55 tareas, 157 h base (188 h con margen +20 %), ≈ 9.559 €, ≈ 4,68 M tokens. Cobertura completa de C-01..C-10. Supuestos nuevos declarados: ~84 países en el set SWIFT (split 22/22/40), máscara `Anonymized` con `*`, PHPStan nivel 8, umbrales de cobertura, formato de la fuente de hechos del generador; facade ubicada en la Fase 5.
