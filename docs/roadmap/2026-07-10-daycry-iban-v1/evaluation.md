# 2026-07-10-daycry-iban-v1

> Presupuesto y viabilidad de implementar **daycry/iban v1.0** (librería IBAN para CodeIgniter 4) desde cero — decisión: aprobar el arranque y priorizar el orden de construcción por capas.

| | |
|---|---|
| **Fecha** | 2026-07-10 |
| **Estado** | borrador |
| **Prioridad global** | Alta 🟠 |
| **Solicitante** | daycry (daycry9@gmail.com) |
| **Spec** | [`spec.md`](spec.md) · fuente: [`2026-07-10-daycry-iban-v1-design.md`](../../superpowers/specs/2026-07-10-daycry-iban-v1-design.md) |
| **Plan** | [`improvement-plan.md`](improvement-plan.md) · [`tasks.md`](tasks.md) — 8 fases, 55 tareas |
| **Características evaluadas** | 10 |

---

## Cuadro de mando

| Métrica | Total estimado | Confianza |
|--------|----------------|-----------|
| Esfuerzo humano | **188 h** (157 h base +20 %) | Media |
| Tiempo IA (ejecución) | **20 h** (+ 5 h supervisión) | Media |
| Coste | **9.559 €** | Media |
| Tokens IA | **≈ 4,68 M** base (in 3,75 M / out 0,93 M) | Baja |
| Multiplicador productividad | **×7,6** | — |
| Características | **10** | — |

> El coste está dominado por el trabajo humano (≈ 9.420 € con margen); los tokens de IA son ≈ 139 € (con margen). El precio de tokens está **⚠️ por verificar** (ver Supuestos económicos).

---

## Resumen ejecutivo

Llega una spec cerrada (enfoque A: facade + dos capas, core cero-deps con registro estructural para ~80+ países, resolver con `NullProvider`/`DatabaseProvider`, integración CI4, tests desde el día uno) para construir **desde cero** una librería de IBAN de referencia. Se presupuestan **10 características** encadenadas por la dependencia de capas **Core → Resolver → Integración CI4**. El total es **≈ 157 h base (188 h con margen +20 %)** y **≈ 9.559 €**, con la **autoría y verificación del registro estructural de ~80+ países (C-04)** como la partida más cara (≈ 36 h / ≈ 1.825 €) y de mayor riesgo, por ser una tarea de datos meticulosa y verificada país a país. Veredicto: **go**, construyendo de abajo arriba y aislando C-04 como hito de datos con tests por país.

---

## Requerimientos recibidos

Mapa de la spec fuente (`docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md`) a las características evaluadas.

| ID | Característica | Requisito origen (ref.) | ¿Claro? |
|----|---------------|-------------------------|---------|
| C-01 | Tooling, `composer.json` y CI | §11, §2·7 | ✅ |
| C-02 | Modelo de dominio y contratos | §4, §5 | ✅ |
| C-03 | Core (normalize/MOD-97/compiler/validator/parser/format) | §6.2–§6.5, §5 | ✅ |
| C-04 | **Registro estructural en código (~80+ países) + infra Registry** | §6.1, §2·5, §9, §13 | 🟡 ambiguo (offsets por país a verificar) |
| C-05 | Script generador del registry (`bin/`) | §2 (defaults), §11 | 🟡 ambiguo (formato de la fuente de hechos) |
| C-06 | Verificación check nacional ES (mod-11) | §6.6, §2·6 | ✅ |
| C-07 | Resolver + Providers + esquema BD | §7 | ✅ |
| C-08 | Integración CI4 (Config/Services/helper/comandos) | §8 | ✅ |
| C-09 | Suite de tests | §10 | ✅ |
| C-10 | Documentación (README, uso, licencias) | §9, §12, §13 | 🟡 ambiguo (claves de mensaje / i18n) |

**Ambigüedades / información que falta (afectan a la estimación):**

- **Offsets banco/sucursal por país (C-04):** los límites banco/sucursal no siempre coinciden con los de token SWIFT; hay que verificarlos país a país contra libs MIT (`cmpayments/iban`, `ixnode/php-iban`). Es el principal foco de incertidumbre y coste (§13). **Supuesto:** ~80–85 países en el set SWIFT; ~15–20 min de autoría+cross-check por país.
- **Formato de la fuente de hechos del generador `bin/` (C-05):** el spec no fija el formato de entrada del generador. **Supuesto:** el generador transforma una fuente tabular de autoría propia (no un fichero con licencia restrictiva) a `countries.php`.
- **Claves de mensaje / i18n (C-10, §13):** por decidir si los `message` se traducen vía `Language/` de CI4 o quedan en inglés en el core. **Supuesto:** v1.0 emite mensajes en inglés en el core; i18n opcional en la capa CI4.
- **Verificación de la máscara `Anonymized`** (código de país + últimos 4) a documentar (§13); no afecta al coste de forma material.

---

## Datos necesarios para una evaluación completa

- [x] **Requerimientos** completos y sin ambigüedades — *spec cerrada; quedan 3 incógnitas menores marcadas arriba.*
- [x] **Alcance** de cada característica acotado (dentro/fuera muy explícito en §1)
- [x] **Criterios de aceptación / éxito** por característica — *derivables de §10 (tests) y de los contratos §5.*
- [x] **Restricciones** (compliance de licencias de datos §9; técnicas: PHP ^8.3 / CI4 ^4.6)
- [x] **Dependencias externas** identificadas — *libs MIT solo como cross-check; ninguna dependencia dura de runtime.*
- [x] **Contexto técnico** del proyecto (greenfield; layout §3; PSR-4)
- [ ] **Tarifa/hora y precio de tokens confirmados** — *se asumen defaults; precio de tokens ⚠️ por verificar.*

---

## Supuestos económicos (ajustables)

**Coste = (horas × tarifa) + coste de tokens de IA.** Importes en **EUR**.

| Parámetro | Valor | Nota |
|-----------|-------|------|
| Tarifa de desarrollo | **50 €/h** | Default del kit; no había tarifa definida en el proyecto. |
| Modelo IA asumido | **claude-opus-4-8** | Base de la previsión de tokens (del spec). |
| Precio input | **≈ 13,80 € / 1M** | ⚠️ **verificar**: asume tarifa clase Opus ≈ 15 USD/1M × 0,92. |
| Precio output | **≈ 69,00 € / 1M** | ⚠️ **verificar**: asume tarifa clase Opus ≈ 75 USD/1M × 0,92. |
| Tipo de cambio | 1 USD = 0,92 € | Aplicado al precio de tokens. |
| Ratio de supervisión | ~25 % de horas IA | Revisión/validación humana del trabajo del agente. |
| Horas por FTE-mes | 160 h | Base del cálculo de FTE equivalentes. |
| Margen de contingencia | **+20 %** | Sobre horas base (humanas **e** IA); coste recalculado desde las horas con margen. |

> Todas las cifras de tokens/coste son **parametrizadas**: si el precio real de tokens difiere, el bloque token es fácil de recalcular sin tocar el resto. El peso humano (≈ 98,5 % del coste) hace que el resultado sea poco sensible al precio de tokens.

---

## Evaluación por característica

### C-01 — Tooling, `composer.json` y CI

- **Requisito origen**: §11, §2·7.
- **Descripción**: `composer.json` (PSR-4 `Daycry\Iban\ → src/`, `php ^8.3`, `codeigniter4/framework ^4.6` en require-dev como peer), config de PHPStan (nivel alto), PHP-CS-Fixer/CodingStandard (PSR-12), workflow GitHub Actions con matriz **PHP 8.3/8.4 × CI4 4.6**, layout de repo y README base.
- **Complejidad**: Media
- **Esfuerzo**: 10 h · confianza Alta
- **Previsión IA**: 0,15 M in / 0,04 M out tok · ≈ 4,83 €
- **Coste**: (10 h × 50 €) + tokens = **≈ 505 €**
- **Impacto / áreas afectadas**: `composer.json`, `.github/workflows/`, `phpstan.neon`, `.php-cs-fixer.php`, raíz del repo
- **Dependencias y prerequisitos**: ninguna (es la base de todo)
- **Riesgos**: fricción de la matriz CI (versiones cruzadas) — bajo
- **Incógnitas**: conjunto exacto de herramientas dev (Rector opcional)

### C-02 — Modelo de dominio y contratos

- **Requisito origen**: §4 (DTOs/enums/excepciones), §5 (interfaces).
- **Descripción**: DTOs `final readonly` (`ParsedIban`, `BankResult`, `BankInfo`, `ValidationResult`, `Violation`), enums (`ViolationCode`, `IbanFormat`), excepciones (`IbanException`, `InvalidIbanException` con `result()`), y las 6 interfaces framework-free. Base contractual de todo el paquete.
- **Complejidad**: Media
- **Esfuerzo**: 10 h · confianza Alta
- **Previsión IA**: 0,20 M in / 0,06 M out tok · ≈ 6,90 €
- **Coste**: (10 h × 50 €) + tokens = **≈ 507 €**
- **Impacto / áreas afectadas**: `src/DTO/`, `src/Enums/`, `src/Exceptions/`, `src/Contracts/`
- **Dependencias y prerequisitos**: C-01 (scaffolding)
- **Riesgos**: cambios de firma tardíos propagan a todo — mitigado congelando contratos pronto
- **Incógnitas**: distinción `null` estructural vs. no-resuelto ya resuelta en el diseño (§4 notas)

### C-03 — Core: normalize / MOD-97 / StructureCompiler / Validator / Parser / format

- **Requisito origen**: §6.2–§6.5, §5.
- **Descripción**: corazón algorítmico. `Normalizer`, `Mod97` (por ventanas de 9 dígitos, sin `bcmath`; + generación `98 − mod`), `StructureCompiler` (gramática de tokens SWIFT → regex anclada y cacheada por país), `Validator` (pipeline de 8 `ViolationCode` con corto-circuito y orden accionable), `Parser` (troceo por offsets), `format()` (Electronic/Print/Anonymized).
- **Complejidad**: Alta
- **Esfuerzo**: 24 h · confianza Media
- **Previsión IA**: 0,50 M in / 0,12 M out tok · ≈ 15,18 €
- **Coste**: (24 h × 50 €) + tokens = **≈ 1.215 €**
- **Impacto / áreas afectadas**: `src/Core/` (`Normalizer`, `Mod97`, `StructureCompiler`, `Validator`, `Parser`)
- **Dependencias y prerequisitos**: C-02 (DTOs/enums); consume el Registry (C-04) para longitudes/tokens/offsets
- **Riesgos**: orden de normalización antes de checks (bug clásico); corrección del MOD-97 en 64 bits; regex de tokens frágil si se escribe a mano (mitigado por el compiler)
- **Incógnitas**: cobertura de la clase de token `c` (alfanumérico mixto) tras mayúsculas

### C-04 — Registro estructural en código (~80+ países) + infraestructura Registry ⭐ más cara

- **Requisito origen**: §6.1, §2·5, §9 (licencias), §13 (verificación por país).
- **Descripción**: infra `CountryStructure` / `Registry` / `PhpRegistryLoader` **y**, sobre todo, la **autoría independiente** de `src/Registry/data/countries.php` para el set SWIFT completo (~80+ países): longitud IBAN, tokens `bbanStructure`, offsets `[posición,longitud]` de banco/sucursal/cuenta/nationalCheck, flag `sepa` (EPC409-09) y ejemplo por país. **Verificación país a país** contra libs MIT (los offsets banco/sucursal no siempre coinciden con los límites de token). Tarea de **datos**, meticulosa y verificada, no de código.
- **Complejidad**: Muy alta
- **Esfuerzo**: 36 h · confianza Media (≈ 6 h infra + ≈ 30 h autoría+verificación de datos)
- **Previsión IA**: 0,90 M in / 0,18 M out tok · ≈ 24,84 €
- **Coste**: (36 h × 50 €) + tokens = **≈ 1.825 €**
- **Impacto / áreas afectadas**: `src/Registry/` (`CountryStructure`, `Registry`, `PhpRegistryLoader`, `data/countries.php`)
- **Dependencias y prerequisitos**: C-02 (tipos); C-01. Es la base de datos de C-03/C-07; conviene hitos incrementales (subconjunto SEPA primero, luego resto)
- **Riesgos**: **el mayor del proyecto** — offsets erróneos por país (validador de referencia poco creíble si falla); riesgo **legal** si se copian bytes de SWIFT/globalcitizen/Wikipedia (obligatorio autorar de hechos, cross-check MIT); envejecimiento silencioso de los datos (mitigado por el generador C-05 y `Registry::VERSION`)
- **Incógnitas**: nº exacto de países del set; países con quirks de sub-estructura; disponibilidad/consistencia de las libs MIT de cross-check

### C-05 — Script generador del registry (`bin/`)

- **Requisito origen**: §2 (defaults menores), §11.
- **Descripción**: script en `bin/` que regenera `countries.php` desde una fuente de hechos de autoría propia, con cross-check contra libs MIT, para el refresco ~anual. Evita el envejecimiento silencioso de los datos estructurales.
- **Complejidad**: Media
- **Esfuerzo**: 8 h · confianza Media
- **Previsión IA**: 0,20 M in / 0,05 M out tok · ≈ 6,21 €
- **Coste**: (8 h × 50 €) + tokens = **≈ 406 €**
- **Impacto / áreas afectadas**: `bin/`, salida a `src/Registry/data/countries.php`
- **Dependencias y prerequisitos**: C-04 (comparte metodología y formato de salida)
- **Riesgos**: acoplamiento al formato de la fuente de hechos; podría diferirse sin bloquear v1.0 (nice-to-have)
- **Incógnitas**: formato de entrada de la fuente de hechos (no fijado en el spec)

### C-06 — Verificación de check nacional ES (mod-11)

- **Requisito origen**: §6.6, §2·6.
- **Descripción**: `SpanishNationalCheckValidator` (mod-11 ponderado, pesos `1,2,4,8,5,10,9,7,3,6`; primer dígito sobre banco+sucursal, segundo sobre la cuenta; resto 10→'1', 11→'0'). Resuelto por código de país; ausencia de impl = skip silencioso; invocado solo con `checkNational=true`.
- **Complejidad**: Media
- **Esfuerzo**: 5 h · confianza Alta
- **Previsión IA**: 0,15 M in / 0,035 M out tok · ≈ 4,49 €
- **Coste**: (5 h × 50 €) + tokens = **≈ 254 €**
- **Impacto / áreas afectadas**: `src/National/` (`NationalCheckValidatorInterface` + impl ES)
- **Dependencias y prerequisitos**: C-02 (interface), C-03 (parseo)
- **Riesgos**: bajo — algoritmo conocido con casos límite bien definidos
- **Incógnitas**: ninguna material

### C-07 — Resolver + Providers + esquema de BD

- **Requisito origen**: §7.
- **Descripción**: `Resolver` (compone `BankResult` desde estructura + `sepaCountry`, superpone `BankInfo` si `supports()`, precedencia `findByIban` → `findByBankCode`), `NullProvider` (default), `DatabaseProvider` (opcional) con `BankModel`, migración `banks` (índices y clave natural) y **Seed vacío**. Migración **no auto-ejecutada** (opt-in estricto).
- **Complejidad**: Alta
- **Esfuerzo**: 14 h · confianza Media
- **Previsión IA**: 0,35 M in / 0,09 M out tok · ≈ 11,04 €
- **Coste**: (14 h × 50 €) + tokens = **≈ 711 €**
- **Impacto / áreas afectadas**: `src/Resolver/`, `src/Providers/`, `src/Models/`, `src/Database/{Migrations,Seeds}/`
- **Dependencias y prerequisitos**: C-03 (Parser), C-02 (DTOs)
- **Riesgos**: fuga de acoplamiento CI4 al core (solo `DatabaseProvider` debe depender de CI4); que el default toque BD (debe instalar/validar sin conexión)
- **Incógnitas**: estrategia de `findByIban` para providers futuros (v1.0 delega en `findByBankCode`)

### C-08 — Integración CodeIgniter 4 (adaptador fino)

- **Requisito origen**: §8.
- **Descripción**: `Config\Iban` (publicable, override por env), `Config\Services::iban()`, `Config\Registrar`, `Helpers/iban_helper.php` (~9 funciones + alias `bank_name()`, `bank_bic()`, `iban_country()`, `iban_valid()`), y 4 comandos spark: `iban:validate` (con `--national`/`--json`, exit 0/1), `iban:parse`, `iban:resolve`, `iban:update` (**scaffold no-op documentado** con aviso de licencias y forma `--source/--country/--dry-run`).
- **Complejidad**: Media
- **Esfuerzo**: 16 h · confianza Media
- **Previsión IA**: 0,45 M in / 0,11 M out tok · ≈ 13,80 €
- **Coste**: (16 h × 50 €) + tokens = **≈ 814 €**
- **Impacto / áreas afectadas**: `src/Config/`, `src/Commands/`, `src/Helpers/iban_helper.php`
- **Dependencias y prerequisitos**: C-07 (resolver/provider), C-03 (core)
- **Riesgos**: superficie de comandos amplia; que `service('iban')` no funcione out-of-the-box con BD vacía (requisito explícito)
- **Incógnitas**: alcance del texto del aviso de licencias en `iban:update`

### C-09 — Suite de tests

- **Requisito origen**: §10.
- **Descripción**: PHPUnit + `CIUnitTestCase`; tests del Core **sin arrancar CI**; fixtures válidos/inválidos por país (≥1 por longitud); un caso por `ViolationCode` (incl. orden de evaluación); fixtures mod-11 ES (incl. 10→'1', 11→'0'); `DatabaseProvider` con SQLite en memoria + `DatabaseTestTrait` (y que `NullProvider` no toque BD); tests de comandos; round-trip de formateo y de generación de dígitos de control.
- **Complejidad**: Alta
- **Esfuerzo**: 24 h · confianza Media
- **Previsión IA**: 0,60 M in / 0,15 M out tok · ≈ 18,63 €
- **Coste**: (24 h × 50 €) + tokens = **≈ 1.219 €**
- **Impacto / áreas afectadas**: `tests/`
- **Dependencias y prerequisitos**: transversal (crece con C-03/C-04/C-06/C-07/C-08); "tests desde el día uno" → se solapa con cada característica
- **Riesgos**: mantener fixtures por país alineados con C-04 (datos y tests deben validarse mutuamente)
- **Incógnitas**: umbral de cobertura objetivo

### C-10 — Documentación (README, uso, licencias)

- **Requisito origen**: §9 (licencias), §12 (roadmap), §13 (cuestiones abiertas).
- **Descripción**: README completo, guía de uso (facade, helper, comandos), documentación del esquema de máscara `Anonymized`, y notas de **licencias/atribución** (por qué no se empaquetan datos SWIFT/SwiftRef/EPC; cross-check MIT).
- **Complejidad**: Baja-Media
- **Esfuerzo**: 10 h · confianza Alta
- **Previsión IA**: 0,25 M in / 0,09 M out tok · ≈ 9,66 €
- **Coste**: (10 h × 50 €) + tokens = **≈ 510 €**
- **Impacto / áreas afectadas**: `docs/`, `README.md`
- **Dependencias y prerequisitos**: se cierra al final (necesita la API estable)
- **Riesgos**: bajo; la precisión del apartado de licencias es importante para la credibilidad
- **Incógnitas**: decisión final sobre i18n de mensajes (§13)

---

## Comparativa

| # | Característica | Complejidad | Horas | Coste € | Tokens | Prioridad | Confianza |
|---|---------------|-------------|-------|---------|--------|-----------|-----------|
| C-01 | Tooling, composer y CI | Media | 10 h | 505 € | 190k | Alta | Alta |
| C-02 | Dominio y contratos | Media | 10 h | 507 € | 260k | Crítica | Alta |
| C-03 | Core (algoritmos) | Alta | 24 h | 1.215 € | 620k | Crítica | Media |
| C-04 | **Registry ~80+ países** ⭐ | Muy alta | 36 h | **1.825 €** | 1,08M | Crítica | Media |
| C-05 | Generador `bin/` | Media | 8 h | 406 € | 250k | Media | Media |
| C-06 | Check nacional ES | Media | 5 h | 254 € | 185k | Media | Alta |
| C-07 | Resolver + Providers + BD | Alta | 14 h | 711 € | 440k | Alta | Media |
| C-08 | Integración CI4 | Media | 16 h | 814 € | 560k | Alta | Media |
| C-09 | Suite de tests | Alta | 24 h | 1.219 € | 750k | Alta | Media |
| C-10 | Documentación | Baja-Media | 10 h | 510 € | 340k | Media | Alta |
| | **Total (base)** | | **157 h** | **≈ 7.966 €** | **≈ 4,68 M** | | |

> Coste de la tabla = base (horas × 50 € + tokens base, **sin** margen). El total con margen +20 % está en el presupuesto siguiente.

---

## Presupuesto total

| Concepto | Cálculo | Importe |
|----------|---------|---------|
| Desarrollo (humano, base) | 157 h × 50 €/h | 7.850 € |
| Margen de contingencia (humano) | +20 % sobre 7.850 € | +1.570 € |
| Tokens IA input (con margen) | 4,50 M tok × 13,80 €/1M | 62,10 € |
| Tokens IA output (con margen) | 1,11 M tok × 69,00 €/1M | 76,59 € |
| **Total estimado (con margen)** | | **≈ 9.559 €** |

> Base sin margen: ≈ 7.966 €. El trabajo humano supone ≈ 98,5 % del coste; el precio de tokens (⚠️ verificar) apenas mueve el total.

---

## Productividad IA (humano vs. IA)

| KPI | Valor |
|-----|-------|
| Horas humanas estimadas | 188 h |
| Horas IA (ejecución) | 20 h |
| Supervisión humana | 5 h |
| **Horas totales (IA + supervisión)** | **25 h** |
| Horas ahorradas | ≈ 164 h |
| **Ahorro** | **≈ 87 %** |
| **Multiplicador de productividad** | **×7,6** |
| FTE equivalentes *(opcional)* | ≈ 1,0 FTE-mes |

> Horas mostradas **con el margen de contingencia (+20 %)** ya aplicado. Base: 157 h humanas · 16,5 h IA · 4,1 h supervisión (≈ 20,6 h totales) → mismo multiplicador ×7,6. "Horas IA" y "supervisión" son estimaciones aproximadas (supervisión ≈ 25 % de las horas IA); la supervisión de C-04 debe ser más intensa por ser tarea de datos verificable.

---

## Recomendación

- **Veredicto**: **go**. Spec sólida, decisiones cerradas y coste moderado para una librería de referencia. Condicionar solo el precio de tokens (irrelevante para el total) y aislar C-04 como hito de datos.
- **Quick wins** (bajo coste, alto valor): **C-06** (check ES, 5 h), **C-01** (tooling, 10 h), **C-02** (dominio/contratos, 10 h) — desbloquean todo con poco esfuerzo.
- **Costosas / a valorar**: **C-04** (registry ~80+ países, 36 h — la más cara y arriesgada), **C-03** (core, 24 h) y **C-09** (tests, 24 h).
- **Orden sugerido** (respeta la dependencia de capas Core → Resolver → Integración CI4):
  **C-01 → C-02 → C-04 → C-03 → C-06 → C-05 → C-07 → C-08 → C-10**, con **C-09 (tests) transversal desde el día uno** ("tests desde el día uno", §10). Motivo: el dominio/contratos (C-02) congelan las firmas; el registry (C-04) es el dato que consume el core (C-03), por eso va antes o en paralelo estrecho; el generador (C-05) se apoya en la metodología de C-04; resolver e integración (C-07/C-08) coronan sobre un core probado; la doc (C-10) cierra con la API estable.
- **Fuera de alcance recomendado**: nada del v1.0 debe recortarse; si hubiera que aligerar, **C-05 (generador `bin/`)** es el único diferible sin romper v1.0 (nice-to-have para el refresco ~anual).

---

## Riesgos transversales

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| Offsets banco/sucursal erróneos por país (C-04) minan la credibilidad del validador | Media | Alto | Verificación país a país con tests dedicados + cross-check contra 2 libs MIT; hitos incrementales (SEPA primero). |
| Contaminación de licencia al empaquetar datos (SWIFT/globalcitizen/Wikipedia/SwiftRef) | Baja | **Muy alto** (legal) | Autoría de **hechos** (no copiar bytes); solo libs MIT como cross-check; `Registry::VERSION` con nota de autoría independiente; documentar en C-10. |
| Fuga de acoplamiento CI4 hacia el Core (rompe "usable fuera de CI") | Media | Alto | Regla arquitectónica de una sola dirección; test del Core **sin arrancar CI**; PHPStan para detectar imports `codeigniter4/*` en `Core`/`Contracts`. |
| Envejecimiento silencioso de los datos estructurales | Media | Medio | Generador en `bin/` (C-05) + `Registry::VERSION` fechada; refresco ~anual documentado. |
| Cambios tardíos en contratos/DTOs propagan a todo el paquete | Media | Medio | Congelar C-02 pronto; tests de contrato antes de construir sobre ellos. |
| Precio de tokens asumido incorrecto | Media | Bajo | Cálculo parametrizado; peso de tokens ≈ 1,5 % del total → impacto económico despreciable. |

---

## Siguiente paso

Para **ejecutar** lo aprobado, genera el plan detallado con el agente **`planner`** (creará `improvement-plan.md` + `tasks.md` en esta misma carpeta y rellenará el campo **Plan** de esta evaluación y el `plan:` de la spec).

**Aprobado para planificar (todo v1.0, orden por capas):**

- **Bloque 1 — Fundaciones:** C-01 (tooling/CI), C-02 (dominio/contratos).
- **Bloque 2 — Datos + Core:** C-04 (registry ~80+ países) ⭐ hito de datos con tests por país, C-03 (core), C-06 (check ES), C-05 (generador `bin/`, diferible).
- **Bloque 3 — Resolver + Integración:** C-07 (resolver/providers/BD), C-08 (integración CI4).
- **Transversal:** C-09 (tests, desde el día uno), C-10 (documentación, cierre).

---

## Changelog

| Fecha | Cambio |
|---|---|
| 2026-07-10 | Evaluación inicial (borrador) a partir de la spec fuente `2026-07-10-daycry-iban-v1-design.md`. 10 características, 157 h base (188 h +20 %), ≈ 9.559 €. |
| 2026-07-10 | Plan generado (`improvement-plan.md` + `tasks.md`): 8 fases, 55 tareas bite-sized (TDD). Presupuesto cuadrado con esta evaluación (157 h base / ≈ 7.966 € base / ≈ 4,68 M tokens). Campo **Plan** enlazado. |
