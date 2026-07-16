# Checklist de Tareas — Cobertura SEPA de importadores (Italia + 17)

| | |
|---|---|
| **Estado** | en-progreso |
| **Fecha** | 2026-07-16 |
| **Plan** | [`improvement-plan.md`](./improvement-plan.md) |

> **⚠️ Ledger canónico de progreso.** Este fichero es la **fuente única de verdad** del avance del plan. **Cualquier** implementador —el agente `implementer`, el chat principal, o un orquestador externo (p. ej. *superpowers SDD*)— **debe** marcar aquí cada tarea (checkbox + estado) al completarla y actualizar el resumen. Los ledgers propios de otras herramientas son **espejo**, no fuente.

---

## Resumen de progreso

Horas y tokens **base** (sin margen +20 %).

| Fase | Completadas | Total | Progreso | Horas (real/est) | Tokens (real/est) |
|------|------------|-------|----------|------------------|-------------------|
| Fase 0 — Infra | 1 | 1 | 100% | — / 9h | — / 0,44 M |
| Fase 1 — Tier A (fetch en vivo) | 5 | 5 | 100% | — / 26h | — / 1,32 M |
| Fase 2 — Tier B (offline `--file`) | 0 | 3 | 0% | 0 / 14h | 0 / 0,63 M |
| Fase 3 — Tier C (salvedades / curación) | 0 | 8 | 0% | 0 / 46h | 0 / 2,21 M |
| Fase 4 — Transversal | 0 | 4 | 0% | 0 / 6h | 0 / 0,34 M |
| **TOTAL** | **6** | **21** | **29%** | **— / 101h** | **— / ≈ 4,94 M** |

> Cobertura objetivo por fase: Fase 1 → **30/42**, Fase 2 → **33/42**, Fase 3 → **hasta 41/42**. DK (+FO/GL) fuera (tier D). Tareas condicionadas: **T-16 (LT) bloqueada por licencia**; **T-09 (MK) condicionada a frescura**; **T-15 (RS) con cross-check por alineación**; **T-17 (FI) el último por coste/riesgo**.

---

## Fase 0 — Infra (`HtmlTableReader`)

**Estado**: completado · **Estimado**: 9h · **Real**: — · **Coste est.**: ≈ 461 € · **Tokens est.**: 0,44 M

### T-01 — `Import/Support/HtmlTableReader` (lector de tablas HTML, framework-free)

- **Descripción**: nuevo helper framework-free basado en `DOMDocument` (`ext-dom`/`ext-libxml`, ya disponibles), con la misma forma que `XlsxReader`: `readTables()` / `readFirstTable()` → rejilla 0-indexada de celdas string, + `locateHeader()` por importador (localiza cabecera y columna por nombre). Prerequisito de EE/ME/IT y del scrape de la landing de CY.
- **Estado**: completado
- **Tiempo**: est. 9h · real —
- **Previsión IA**: 0,35 M in / 0,09 M out tok · ≈ 11 €
- **Dependencias**: ninguna · **habilita** T-04, T-05, T-06, T-13
- **Archivos**: `src/Import/Support/HtmlTableReader.php`, `tests/Import/Support/HtmlTableReaderTest.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [x] `readFirstTable()`/`readTables()` devuelven rejilla 0-indexada `list<list<string>>` desde HTML representativo (tablas anidadas, `<thead>`/`<tbody>`, celdas vacías).
- [x] `locateHeader()` localiza la fila de cabecera y el índice de columna por nombre.
- [x] Tests unitarios verdes con fixtures HTML representativos (TDD: fixture reducido primero).
- [x] PHPStan L8 limpio, PSR-12.
- [x] Framework-free: `CoreIsFrameworkFreeTest` verde (vive en el directorio ya guardado `Import/Support`; **no** requiere editar `GUARDED_DIRECTORIES`, solo verificar que sigue cubierto).

**Subtareas**
- [x] Test con HTML de referencia (fixture reducido) primero.
- [x] Implementar `readTables()`/`readFirstTable()` con `DOMDocument` (tolerante a HTML malformado vía `libxml_use_internal_errors`).
- [x] Implementar `locateHeader()` por nombre de columna.
- [x] Verificar `CoreIsFrameworkFreeTest`, PHPStan L8 y PSR-12.

**Notas**: sin `colspan`/`rowspan` (no visto en las fichas; asumido no). Documentar el CAVEAT de fragilidad ante rediseños web, como el resto del catálogo. **Impl.**: `readTables()` devuelve `list<list<list<string>>>` (una rejilla por `<table>`, en orden de documento; las tablas anidadas afloran como rejilla propia y no contaminan la exterior). UTF-8 preservado vía `mb_encode_numericentity` antes de `loadHTML`. `locateHeader()` es estático en `HtmlTableReader` (case-insensitive, normaliza espacios). Añadido `ext-dom` a `composer.json` (igual que `ext-zip` respalda a `XlsxReader`). 10 tests unitarios verdes.

---

## Fase 1 — Tier A (fetch en vivo) · Cobertura 24 → 30/42

**Estado**: completado · **Estimado**: 26h · **Real**: — · **Coste est.**: ≈ 1.334 € · **Tokens est.**: 1,32 M

### T-02 — SE · `SwedenBankInfrastructureImporter` (PSV / MIT)

- **Descripción**: fetch `Data/source.psv` (PSV, licencia MIT) del mirror `Bankinfrastruktur/BankData`; columna `IbanId` (3 díg.) = `bank_code` + BIC + nombre; **de-duplicar por `IbanId`** (Nordea=300 en varios rangos); **no** usar columnas de clearing (4-5 díg.). `--file` como hedge.
- **Estado**: completado
- **Tiempo**: est. 4h · real —
- **Previsión IA**: 0,15 M in / 0,04 M out tok · ≈ 4,8 €
- **Dependencias**: ninguna (PSV se lee con `fgetcsv`, ya soportado)
- **Archivos**: `src/Import/Importers/SwedenBankInfrastructureImporter.php`, `tests/Import/Importers/SwedenBankInfrastructureImporterTest.php`, `src/Import/ImporterRegistry.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [x] `rows()` mapea `IbanId` (3 díg.) → nombre + BIC, de-duplicado por `IbanId`; ignora columnas de clearing.
- [x] Test verde con fixture PSV reducido (incluye caso de de-dup, p. ej. Nordea 300); PHPStan L8, PSR-12.
- [x] Registrado en `registerDefaults()` y listado por `iban:update`.
- [x] `resolve()` de una IBAN SE de ejemplo (`SE45 5000…` → 500 = SEB) devuelve el banco esperado.

**Subtareas**
- [x] Test con fixture PSV reducido primero.
- [x] Implementar `rows()` (columna `IbanId`, de-dup, sin clearing) + soporte `--file` como hedge.
- [x] Registrar en `registerDefaults()`.

**Notas**: mirror comunitario (no fuente oficial BSAB, que es PDF/DOCX = tier D). Si `raw.githubusercontent.com` se bloquea → `--file`. **Impl.**: `SwedenBankInfrastructureImporter` (sourceId `bankinfrastruktur`), usa `ReadsCsvSource` con delimitador `|`; columnas por nombre; `IbanId` exigido `^\d{3}$`. Fixture DB `tests/Fixtures/import/se_sample.psv`; resolve() verde con `SE4550000000058398257466` → SEB.

### T-03 — FR + MC · `RegafiImporter` (JSON `cib`, parametrizado por país)

- **Descripción**: importador parametrizado (patrón `EpcRegisterImporter`, `new RegafiImporter('FR')` / `'MC'`), registrado **dos veces**. Fetch JSON de la API ODS REGAFI; **parsear el array serializado `cib`** (`["24659"]`, `[{"code":…}]`, `"[]"`) y **expandir una fila por código** (5 díg., zero-pad/validar); nombre (sin BIC); atribución Licence Ouverte. MC sale del mismo dataset filtrando por país (lleva CIB).
- **Estado**: completado
- **Tiempo**: est. 7h · real —
- **Previsión IA**: 0,30 M in / 0,08 M out tok · ≈ 9,7 €
- **Dependencias**: ninguna
- **Archivos**: `src/Import/Importers/RegafiImporter.php`, `tests/Import/Importers/RegafiImporterTest.php` (FR **y** MC), `src/Import/ImporterRegistry.php` (2 registros)
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [x] `cib` (array JSON) parseado y expandido a una fila por código (5 díg., zero-pad/validar); una entidad con varios CIB genera varias filas.
- [x] Constructor por país (`'FR'`/`'MC'`); registrado dos veces; atribución Licence Ouverte en `license()`.
- [x] Test verde con fixture JSON reducido cubriendo FR **y** MC (Mónaco filtrado del mismo dataset); PHPStan L8, PSR-12.
- [x] `resolve()` de una IBAN FR (CIB 5 díg.) **y** una IBAN MC (p. ej. CIB 12739 CFM Indosuez) devuelven el banco esperado.

**Subtareas**
- [x] Test con fixture JSON reducido (FR + MC) primero.
- [x] Implementar parseo/expansión del array `cib` y filtrado por país.
- [x] Registrar `RegafiImporter('FR')` y `RegafiImporter('MC')` en `registerDefaults()`.

**Notas**: elegir dataset (`prd-jdd-recherche` simple vs. `prd-banque-entites` autoritativo); ambos verificados en vivo. Sin BIC (capa EPC diferida, D5). **Impl.**: `RegafiImporter($country)` sobre `prd-banque-entites` `exports/json`; `cib` decodificado (string serializado o array nativo; elementos string u objeto `{code}`); códigos `^\d{1,5}$` → zero-pad a 5. **DESVIACIÓN/asunción a validar en vivo**: el filtro país usa el primer campo presente entre `pays`/`code_pays`/`pays_localisation`/`localisation` con valor `FRANCE|FR` / `MONACO|MC`; un registro sin campo país reconocible se descarta (no se asume FR), para que FR/MC no se mezclen. El nombre de campo país exacto de REGAFI no está confirmado en `research.md` → documentado como CAVEAT en el docblock; la ruta viva no se testea (patrón del repo). Fixture DB `tests/Fixtures/import/regafi_sample.json`; resolve() verde para FR (30003→Société Générale) y MC (12739→CFM Indosuez).

### T-04 — EE · `EstonianBankingAssociationImporter` (HTML)

- **Descripción**: `HtmlTableReader` sobre `pangaliit.ee/.../bank-codes`; código de **2 díg.** → nombre + BIC; **manejar dobles** (Luminor 96/17) y **cero inicial** (TBB 00, código como string, no int). Primer consumidor del scraping HTML → valida T-01.
- **Estado**: completado
- **Tiempo**: est. 4h · real —
- **Previsión IA**: 0,15 M in / 0,04 M out tok · ≈ 4,8 €
- **Dependencias**: **T-01** (`HtmlTableReader`)
- **Archivos**: `src/Import/Importers/EstonianBankingAssociationImporter.php`, `tests/Import/Importers/EstonianBankingAssociationImporterTest.php` (+ fixture HTML), `src/Import/ImporterRegistry.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [x] `rows()` mapea el código de 2 díg. → nombre + BIC; preserva el cero inicial (TBB 00) y emite los dos códigos de Luminor (96 y 17).
- [x] Test verde con fixture HTML reducido (incluye 00 y 96/17); PHPStan L8, PSR-12; framework-free.
- [x] Registrado en `registerDefaults()` y listado por `iban:update`.
- [x] `resolve()` de una IBAN EE de ejemplo devuelve el banco esperado.

**Subtareas**
- [x] Test con fixture HTML reducido primero.
- [x] Implementar `rows()` con `HtmlTableReader` + `locateHeader()`; tratar el código como string.
- [x] Registrar en `registerDefaults()`.

**Notas**: sin licencia explícita (lista factual, cubierta por «fetch bajo demanda»). Contrastar código con la definición legal del FSA (dígitos 5.º-6.º de la IBAN). **Impl.**: `EstonianBankingAssociationImporter` (sourceId `pangaliit`) usa `HtmlTableReader::readTables()` + `locateHeader()`. **ASUNCIÓN a validar en vivo**: cabeceras `Bank`/`Bank code`/`BIC` (documentado como CAVEAT; ruta viva no testeada). Códigos extraídos por regex `(?<!\d)\d{2}(?!\d)` → soporta multi-código (Luminor `96, 17` → 2 filas) y cero inicial (`00`). Fixture DB `tests/Fixtures/import/ee_sample.html`; resolve() verde con `EE382200221020145685` → Swedbank.

### T-05 — ME · `CentralBankOfMontenegroImporter` (HTML + filtro)

- **Descripción**: `HtmlTableReader` sobre la página RTGS de CBCG; código de **3 díg.** → nombre + BIC; **filtrar** entes públicos (rango 714-931), dejando solo bancos comerciales.
- **Estado**: completado
- **Tiempo**: est. 5h · real —
- **Previsión IA**: 0,20 M in / 0,05 M out tok · ≈ 6,2 €
- **Dependencias**: **T-01** (`HtmlTableReader`)
- **Archivos**: `src/Import/Importers/CentralBankOfMontenegroImporter.php`, `tests/Import/Importers/CentralBankOfMontenegroImporterTest.php` (+ fixture HTML), `src/Import/ImporterRegistry.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [x] `rows()` mapea el código de 3 díg. → nombre + BIC y **filtra** el rango 714-931 (entes públicos).
- [x] Test verde con fixture HTML reducido (incluye una fila de ente público que debe filtrarse); PHPStan L8, PSR-12; framework-free.
- [x] Registrado en `registerDefaults()` y listado por `iban:update`.
- [x] `resolve()` de una IBAN ME de ejemplo (p. ej. `ME25 510…` → CKB, `520…` → Hipotekarna) devuelve el banco esperado.

**Subtareas**
- [x] Test con fixture HTML reducido primero.
- [x] Implementar `rows()` con `HtmlTableReader` + filtro de rango 714-931.
- [x] Registrar en `registerDefaults()`.

**Notas**: la tabla mezcla bancos y entes públicos → el filtro por rango debe ser correcto (evitar falsos positivos/negativos). Documentar el CAVEAT de estabilidad del rango. **Impl.**: `CentralBankOfMontenegroImporter` (sourceId `cbcg`) usa `HtmlTableReader` + `locateHeader`. **ASUNCIÓN a validar en vivo**: cabeceras `Code`/`Name`/`BIC` (CAVEAT en docblock). Filtro numérico `714 ≤ code ≤ 931` → excluye Tesoro/banco central. Fixture DB `tests/Fixtures/import/me_sample.html`; resolve() verde con `ME56510123456789012300` → CKB.

### T-06 — CY · `CentralBankOfCyprusImporter` (landing + XLSX + WAF)

- **Descripción**: GET a la landing IBAN estable, **regex del href** `CIs_and_EMIs_BICs_updated_*.xlsx` (nombre con fecha rotatoria), fetch con **User-Agent de navegador** (WAF devuelve 403 sin él), leer con `XlsxReader`; columna *"Bank identifiers used in IBAN"* (3 díg.) → nombre + BIC.
- **Estado**: completado
- **Tiempo**: est. 6h · real —
- **Previsión IA**: 0,25 M in / 0,06 M out tok · ≈ 7,6 €
- **Dependencias**: `XlsxReader` (existe); **T-01** (`HtmlTableReader`) recomendado para el scrape de la landing (alternativa: regex del href sin `HtmlTableReader`, según evaluación C-04)
- **Archivos**: `src/Import/Importers/CentralBankOfCyprusImporter.php`, `tests/Import/Importers/CentralBankOfCyprusImporterTest.php` (+ fixture XLSX), `src/Import/ImporterRegistry.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [x] Resuelve la URL con fecha vía regex del href y fetch con User-Agent de navegador; lee la `.xlsx` con `XlsxReader` (no la `.xls` BIFF).
- [x] `rows()` mapea la columna de 3 díg. → nombre + BIC (instituciones 001-129, EMIs 901-912).
- [x] Test verde con fixture XLSX reducido; PHPStan L8, PSR-12.
- [x] Registrado en `registerDefaults()`; `resolve()` de una IBAN CY de ejemplo devuelve el banco esperado.

**Subtareas**
- [x] Test con fixture XLSX reducido primero.
- [x] Implementar resolución del href (regex) + fetch con User-Agent + lectura `XlsxReader`.
- [x] Registrar en `registerDefaults()`.

**Notas**: la edición `.xls` (BIFF) más nueva **no** es legible → fijar la `.xlsx`. Regex robusto ante la rotación del nombre de fichero. **Impl.**: `CentralBankOfCyprusImporter` (sourceId `cbc`). Vía viva (no testeada): landing con User-Agent de navegador → regex `href=…CIs_and_EMIs_BICs_updated_*.xlsx` → descarga con User-Agent → `XlsxReader`. Columna código anclada por el literal *"Bank identifiers used in IBAN"*; BIC/nombre por subcadena `bic`/`name|institution`. Zero-pad a 3 díg. Fixture DB generado con `XlsxFixtureFactory` en `setUp`; resolve() verde con `CY17002001280000001200527600` → Bank of Cyprus.

---

## Fase 2 — Tier B (offline `--file`) · Cobertura → 33/42

**Estado**: borrador · **Estimado**: 14h · **Real**: — · **Coste est.**: ≈ 716 € · **Tokens est.**: 0,63 M

### T-07 — AD · `AndorranBankingImporter` (dato curado)

- **Descripción**: dado que son **3 bancos / 4 códigos**, ruta **curada** (D4): `rows()` yield un array constante `Entitat` (4 díg.) → nombre; BIC curado (BACAADAD, CRDAADAD, BSAAADAD). Fichero `Import/Importers/data/ad.php`, procedencia `curated`.
- **Estado**: borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 0,10 M in / 0,03 M out tok · ≈ 3,5 €
- **Dependencias**: **D4** (aprobada) · **T-18** (nota `licensing.md`, recomendada adelantar)
- **Archivos**: `src/Import/Importers/AndorranBankingImporter.php`, `src/Import/Importers/data/ad.php`, `tests/Import/Importers/AndorranBankingImporterTest.php`, `src/Import/ImporterRegistry.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `rows()` yield el mapa curado (4 díg. → nombre + BIC); `license()` = "curated (factual, non-copyrightable)"; procedencia `curated`.
- [ ] Test verde con el dato curado; PHPStan L8, PSR-12; framework-free.
- [ ] Registrado en `registerDefaults()`; `resolve()` de una IBAN AD de ejemplo (0001 Andbank, 0003 Creand) devuelve el banco esperado.

**Subtareas**
- [ ] Autorar `data/ad.php` (hechos, no copia de la fuente; metodología `registry-authoring.md`).
- [ ] Implementar importador + test; registrar en `registerDefaults()`.

**Notas**: verificar el mapeo 0007/0008 de MoraBanc. Refresco anual documentado.

### T-08 — PT · `BancoDePortugalImporter` (`--file`, texto del PDF SICOI)

- **Descripción**: importador `--file` que consume el **texto/CSV pre-extraído** del PDF SICOI (receta `pdftotext -layout`, patrón `betaalvereniging`); columna de **4 díg.** → nombre + BIC; **limpieza de mojibake** de acentos.
- **Estado**: borrador
- **Tiempo**: est. 6h · real —
- **Previsión IA**: 0,22 M in / 0,055 M out tok · ≈ 6,8 €
- **Dependencias**: patrón `--file` (existe)
- **Archivos**: `src/Import/Importers/BancoDePortugalImporter.php`, `tests/Import/Importers/BancoDePortugalImporterTest.php` (+ fixture texto-PDF), `src/Import/ImporterRegistry.php`, `docs/importers.md` (receta)
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `rows(--file)` parsea el texto pre-extraído (columnas de ancho fijo), mapea 4 díg. → nombre + BIC y **limpia el mojibake** de acentos.
- [ ] En modo live no rompe (documentar que la landing bloquea bots → `--file`).
- [ ] Test verde con fixture de texto-PDF reducido; PHPStan L8, PSR-12.
- [ ] Registrado en `registerDefaults()`; `resolve()` de una IBAN PT de ejemplo (0034 CGD, 0033 BNP) devuelve el banco esperado.

**Subtareas**
- [ ] Test con fixture de texto pre-extraído reducido primero.
- [ ] Implementar parseo posicional + limpieza de mojibake.
- [ ] Documentar la receta `pdftotext -layout` en `docs/importers.md`; registrar en `registerDefaults()`.

**Notas**: verificar estabilidad del layout entre ediciones (existe edición 2026). `listaimeipdsp2.xlsx` es pista falsa (registro PSD2 sin códigos).

### T-09 — MK · `NbrmImporter` (`--file` CSV del `.xls`/`.docx`) · condicionada a frescura

- **Descripción**: importador `--file` que acepta un **CSV exportado** por el operador desde el `.xls` BIFF / `.docx` (NBRM está tras Cloudflare → offline sí o sí; sin lector BIFF, D3); código de **3 díg.** → nombre + BIC. **Verificar frescura al implementar** (roster 2014).
- **Estado**: borrador
- **Tiempo**: est. 5h · real —
- **Previsión IA**: 0,18 M in / 0,045 M out tok · ≈ 5,6 €
- **Dependencias**: patrón `--file` (existe)
- **Archivos**: `src/Import/Importers/NbrmImporter.php`, `tests/Import/Importers/NbrmImporterTest.php` (+ fixture CSV), `src/Import/ImporterRegistry.php`, `docs/importers.md`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `rows(--file)` mapea el código de 3 díg. → nombre + BIC desde el CSV exportado.
- [ ] Test verde con fixture CSV reducido; PHPStan L8, PSR-12.
- [ ] Registrado en `registerDefaults()`; `resolve()` de una IBAN MK de ejemplo (200 Stopanska, 300 Komercijalna) devuelve el banco esperado.
- [ ] **Frescura verificada**: si el roster 2014 está obsoleto (p. ej. Eurostandard/370 liquidado 2020), se reduce el alcance a los bancos vigentes y se documenta.

**Subtareas**
- [ ] Verificar frescura del roster antes de fijar el fixture.
- [ ] Test con fixture CSV reducido primero.
- [ ] Implementar `rows(--file)` + documentar la receta de export; registrar en `registerDefaults()`.

**Notas**: **CONDICIONADA** — Cloudflare impide fetch automático (solo `--file`); frescura dudosa (verificar y, si procede, recortar a bancos vigentes).

---

## Fase 3 — Tier C (salvedades / curación) · Cobertura → hasta 41/42

**Estado**: borrador · **Estimado**: 46h · **Real**: — · **Coste est.**: ≈ 2.355 € · **Tokens est.**: 2,21 M

### T-10 — VA · dato curado (1 entrada)

- **Descripción**: **1 entrada** curada `001 → Istituto per le Opere di Religione (IOR)`, BIC `IOPRVAVX`. Procedencia `curated`/hardcoded. Fichero `data/va.php`.
- **Estado**: borrador
- **Tiempo**: est. 2h · real —
- **Previsión IA**: 0,08 M in / 0,02 M out tok · ≈ 2,5 €
- **Dependencias**: **D4** (aprobada) · **T-18** (nota `licensing.md`, recomendada adelantar)
- **Archivos**: `src/Import/Importers/VaticanImporter.php`, `src/Import/Importers/data/va.php`, `tests/Import/Importers/VaticanImporterTest.php`, `src/Import/ImporterRegistry.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `rows()` yield la entrada curada (`001` → IOR / `IOPRVAVX`); procedencia `curated`.
- [ ] Test verde; PHPStan L8, PSR-12; framework-free.
- [ ] Registrado en `registerDefaults()`; `resolve()` de una IBAN VA de ejemplo (`VA…001…`) devuelve IOR.

**Subtareas**
- [ ] Autorar `data/va.php`; implementar importador + test; registrar en `registerDefaults()`.

**Notas**: universo real = 1 banco (IOR). Hecho único no protegible.

### T-11 — SM · `BcsmImporter` (dato curado, 4 bancos)

- **Descripción**: **4 bancos**, ABI de 5 díg. → nombre + BIC. Ruta **curada** recomendada (más robusta que scrapear 4 filas), `data/sm.php`, procedencia `curated`.
- **Estado**: borrador
- **Tiempo**: est. 3h · real —
- **Previsión IA**: 0,10 M in / 0,03 M out tok · ≈ 3,5 €
- **Dependencias**: **D4** (aprobada) · **T-18** (nota `licensing.md`)
- **Archivos**: `src/Import/Importers/BcsmImporter.php`, `src/Import/Importers/data/sm.php`, `tests/Import/Importers/BcsmImporterTest.php`, `src/Import/ImporterRegistry.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `rows()` yield los 4 bancos (ABI 5 díg. → nombre + BIC); procedencia `curated`.
- [ ] Test verde; PHPStan L8, PSR-12; framework-free.
- [ ] Registrado en `registerDefaults()`; `resolve()` de una IBAN SM de ejemplo (03034 Banca Agricola Commerciale) devuelve el banco esperado.

**Subtareas**
- [ ] Autorar `data/sm.php` (4 bancos verificados con ABI+BIC); implementar importador + test; registrar.

**Notas**: los directorios ABI italianos **no** listan bancos sammarinesi → fuente propia. Refresco anual.

### T-12 — IS · dato curado a nivel banco (prefijos centenas)

- **Descripción**: **mapa curado a nivel banco** por prefijos de «centenas» (01/03/05/07/11/15/22 → banco); resuelve **banco, no sucursal** (el fallback `findByBankCode(cc, bank, null)` ya lo soporta); BIC curado. `data/is.php`, procedencia `curated`.
- **Estado**: borrador
- **Tiempo**: est. 5h · real —
- **Previsión IA**: 0,18 M in / 0,045 M out tok · ≈ 5,6 €
- **Dependencias**: **D4** (aprobada) · **T-18** (nota `licensing.md`)
- **Archivos**: `src/Import/Importers/IcelandBankPrefixImporter.php`, `src/Import/Importers/data/is.php`, `tests/Import/Importers/IcelandBankPrefixImporterTest.php`, `src/Import/ImporterRegistry.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `rows()` yield el mapa curado por prefijo → banco + BIC; documenta que resuelve a nivel banco (no sucursal).
- [ ] Test verde; PHPStan L8, PSR-12; framework-free.
- [ ] Registrado en `registerDefaults()`; `resolve()` de una IBAN IS de ejemplo (05xx Íslandsbanki, 03xx Arion) devuelve el banco esperado.

**Subtareas**
- [ ] Autorar `data/is.php` (esquema de prefijos «de centenas»); implementar importador + test; registrar.

**Notas**: sin directorio abierto de sucursal → resolución a nivel banco. Verificar exhaustividad del esquema de prefijos.

### T-13 — IT · `AgenziaEntrateF24Importer` (HTML + zero-pad)

- **Descripción**: `HtmlTableReader` sobre la página F24 `…-xcodice`; `Codice ABI` **zero-pad a 5 díg.** → nombre; **sin BIC**; **parcial** (~400 bancos adheridos a F24). Documentar que el ABI/CAB canónico (SIA-Nexi) es de pago (tier D).
- **Estado**: borrador
- **Tiempo**: est. 6h · real —
- **Previsión IA**: 0,24 M in / 0,06 M out tok · ≈ 7,5 €
- **Dependencias**: **T-01** (`HtmlTableReader`)
- **Archivos**: `src/Import/Importers/AgenziaEntrateF24Importer.php`, `tests/Import/Importers/AgenziaEntrateF24ImporterTest.php` (+ fixture HTML), `src/Import/ImporterRegistry.php`, `docs/importers.md` (caveat de parcialidad)
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `rows()` mapea `Codice ABI` con **zero-pad a 5 díg.** → nombre; sin BIC.
- [ ] Test verde con fixture HTML reducido (incluye un ABI sin ceros para validar el zero-pad); PHPStan L8, PSR-12; framework-free.
- [ ] Registrado en `registerDefaults()`; `resolve()` de una IBAN IT de ejemplo (ABI 5 díg.) devuelve el banco esperado.
- [ ] `docs/importers.md` documenta la **parcialidad** (~400 bancos F24) y que la fuente ABI/CAB canónica es de pago (tier D).

**Subtareas**
- [ ] Test con fixture HTML reducido primero.
- [ ] Implementar `rows()` con `HtmlTableReader` + zero-pad a 5 díg.
- [ ] Registrar en `registerDefaults()`; documentar caveat de parcialidad.

**Notas**: sin licencia de reutilización explícita (cubierto por «fetch bajo demanda»). Capa EPC para BIC diferida (D5).

### T-14 — AL · dato curado (~13 bancos, KIB)

- **Descripción**: mapa **curado** de ~13 bancos (KIB 3 díg. → nombre + BIC) autorado del **Reglamento IBAN nº 42, Anexo 4** + **cross-check EPC/SWIFT** (dato factual, no redistribución del reglamento). Salvedad: el «KIB» del BoA es de 8 díg.; el `bank_code` son los **3 primeros**. `data/al.php`, procedencia `curated`.
- **Estado**: borrador
- **Tiempo**: est. 6h · real —
- **Previsión IA**: 0,22 M in / 0,055 M out tok · ≈ 6,8 €
- **Dependencias**: **D4** (aprobada) · **T-18** (nota `licensing.md`)
- **Archivos**: `src/Import/Importers/BankOfAlbaniaImporter.php`, `src/Import/Importers/data/al.php`, `tests/Import/Importers/BankOfAlbaniaImporterTest.php`, `src/Import/ImporterRegistry.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `rows()` yield ~13 bancos (KIB 3 primeros díg. → nombre + BIC); procedencia `curated`.
- [ ] Datos cross-checkeados con EPC/SWIFT para el BIC; documentada la semántica KIB 8 díg. → 3 primeros.
- [ ] Test verde; PHPStan L8, PSR-12; framework-free.
- [ ] Registrado en `registerDefaults()`; `resolve()` de una IBAN AL de ejemplo devuelve el banco esperado.

**Subtareas**
- [ ] Autorar `data/al.php` (Anexo 4 + cross-check EPC/SWIFT, hechos no redistribución).
- [ ] Implementar importador + test; registrar en `registerDefaults()`.

**Notas**: dominio BoA bloquea fetch → curación a mano. Revisar en 6-12 meses por si BoA publica dataset abierto.

### T-15 — RS · `NbsSerbiaImporter` (`--file`, 2 PDFs) · con cross-check

- **Descripción**: importador `--file` con **dos PDFs pre-extraídos** (`pregled_racuna` código→BIC + `id_brojevi` código→nombre); **zip 19 nombres ↔ 19 códigos** (layout de dos columnas desalineado); 3 díg. → nombre + BIC. Alternativa/red de seguridad: mapa curado (~19).
- **Estado**: borrador
- **Tiempo**: est. 6h · real —
- **Previsión IA**: 0,24 M in / 0,06 M out tok · ≈ 7,5 €
- **Dependencias**: patrón `--file` (existe)
- **Archivos**: `src/Import/Importers/NbsSerbiaImporter.php`, `tests/Import/Importers/NbsSerbiaImporterTest.php` (+ 2 fixtures texto-PDF), `src/Import/ImporterRegistry.php`, `docs/importers.md`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `rows(--file)` une los dos textos pre-extraídos (código→BIC y código→nombre) → 3 díg. → nombre + BIC.
- [ ] Cross-check de la alineación (zip 19↔19) contra un mapa curado de referencia; el test cubre el caso desalineado.
- [ ] Test verde con fixtures reducidos de los 2 PDFs; PHPStan L8, PSR-12.
- [ ] Registrado en `registerDefaults()`; `resolve()` de una IBAN RS de ejemplo (`RS35 105…` → AIK) devuelve el banco esperado.

**Subtareas**
- [ ] Test con 2 fixtures de texto reducido primero.
- [ ] Implementar el zip 19↔19 + cross-check; evaluar degradar a mapa curado si la alineación es frágil.
- [ ] Registrar en `registerDefaults()`; documentar receta `--file`.

**Notas**: **CONDICIONADA** — layout de dos columnas desalineado; si el zip resulta frágil, degradar a mapa curado (~19), más barato y robusto.

### T-16 — LT · `LietuvosBankasImporter` (`--file`, PDF) · 🔴 BLOQUEADA por licencia

- **Descripción**: importador `--file` que consume **texto/CSV pre-extraído** del PDF del directorio FI de Lietuvos bankas (221 filas, **5 díg.** → nombre + BIC). **Bloqueo:** confirmar términos de licencia (marca «LB INTERNAL» / «LB VIDAUS ECB INTERNAL») antes de publicar el enriquecimiento.
- **Estado**: borrador
- **Tiempo**: est. 8h · real —
- **Previsión IA**: 0,30 M in / 0,075 M out tok · ≈ 9,3 €
- **Dependencias**: patrón `--file` · **confirmación de licencia** (bloqueante para publicar el enriquecimiento)
- **Archivos**: `src/Import/Importers/LietuvosBankasImporter.php`, `tests/Import/Importers/LietuvosBankasImporterTest.php` (+ fixture texto-PDF), `src/Import/ImporterRegistry.php`, `docs/importers.md`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `rows(--file)` parsea el texto pre-extraído (sub-columnas sucursal/ciudad entrelazadas) → 5 díg. → nombre + BIC.
- [ ] Test verde con fixture de texto-PDF reducido; PHPStan L8, PSR-12.
- [ ] **Licencia confirmada** con Lietuvos bankas **antes** de registrar/publicar el enriquecimiento; si no se confirma, la tarea se **congela** (clase presente pero no registrada por defecto) y se documenta.
- [ ] Si se desbloquea: registrado en `registerDefaults()`; `resolve()` de una IBAN LT de ejemplo (73000 Swedbank, 70440 SEB) devuelve el banco esperado.

**Subtareas**
- [ ] **Confirmar términos de licencia con Lietuvos bankas** (paso previo bloqueante para publicar).
- [ ] Test con fixture de texto reducido primero; implementar parseo posicional.
- [ ] Registrar en `registerDefaults()` **solo** si la licencia lo permite; documentar receta `--file`.

**Notas**: **BLOQUEADA** hasta confirmar licencia. No bloquea el resto del plan. URL rotatoria + índice bloqueado por Cloudflare → `--file`.

### T-17 — FI · `FinanceFinlandImporter` (`--file` + mapeador de rangos) · el último

- **Descripción**: importador `--file` (texto PDF) + **mapeador de rangos a medida** que convierte el `Rahalaitostunnus` de longitud variable (1-4 díg., valores sueltos y **rangos**: Nordea «1 ja 2», POP `470-479`, cajas de ahorro con listas largas) a la clave **fija de 3 díg.** del paquete, gestionando los códigos de 4 díg. (`72-78`, desde 2024) que no caben (pérdida controlada, documentada).
- **Estado**: borrador
- **Tiempo**: est. 10h · real —
- **Previsión IA**: 0,40 M in / 0,10 M out tok · ≈ 12,4 €
- **Dependencias**: patrón `--file` · **recomendado el último** (mayor coste/menor confianza)
- **Archivos**: `src/Import/Importers/FinanceFinlandImporter.php`, `tests/Import/Importers/FinanceFinlandImporterTest.php` (+ fixture texto-PDF con rangos), `src/Import/ImporterRegistry.php`, `docs/importers.md`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] El mapeador expande valores sueltos, listas y rangos (`470-479`, «1 ja 2») a claves de 3 díg. sin colisiones.
- [ ] Documenta y gestiona la **pérdida controlada** de códigos de 4 díg. (`72-78`).
- [ ] Test verde con fixture que cubra los tres tipos (suelto/lista/rango) y un caso de 4 díg.; PHPStan L8, PSR-12.
- [ ] Registrado en `registerDefaults()`; `resolve()` de una IBAN FI de ejemplo (código 5 Nordea/OP) devuelve el banco esperado.

**Subtareas**
- [ ] Test con fixture de texto-PDF con rangos primero.
- [ ] Diseñar el mapeador de rangos → clave de 3 díg. (modelar solapamientos sin colisión).
- [ ] Implementar + documentar la pérdida de 4 díg.; registrar en `registerDefaults()`.

**Notas**: **el más caro / menor confianza → el último** (aprender de RS/LT). El join exacto falla para los grandes bancos → el mapeador es la parte cara y con riesgo de correctitud.

---

## Fase 4 — Transversal (docs / licensing / registro / CHANGELOG)

**Estado**: borrador · **Estimado**: 6h · **Real**: — · **Coste est.**: ≈ 310 € · **Tokens est.**: 0,34 M

### T-18 — `docs/licensing.md`: nota de la excepción de datos curados (D4)

- **Descripción**: nota nueva acotando la excepción de **datos curados** (VA/AD/SM/IS/AL) a micro-jurisdicciones sin fuente legible por máquina, con el argumento «hechos no protegibles» + metodología `registry-authoring.md` y procedencia `curated`. **Recomendada adelantar** para desbloquear los importadores curados de las Fases 2-3.
- **Estado**: borrador
- **Tiempo**: est. 1,5h · real —
- **Previsión IA**: 0,05 M in / 0,02 M out tok · ≈ 2,1 €
- **Dependencias**: **D4** (aprobada)
- **Archivos**: `docs/licensing.md`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] La nota acota la excepción a micro-jurisdicciones sin fuente máquina y referencia la metodología factual.
- [ ] Coherente con la disciplina «no empaquetar datos» del resto del catálogo.

**Subtareas**
- [ ] Redactar la nota y enlazarla desde los importadores curados.

**Notas**: adelantar al inicio de la Fase 2 (desbloquea AD/VA/SM/IS/AL). Precisión importante para la credibilidad del proyecto.

### T-19 — `docs/importers.md`: matriz/contadores de cobertura + DK tier D

- **Descripción**: actualizar la matriz de cobertura y los contadores (24 → 30 → 33 → hasta 41/42), añadir las recetas `--file` por país (PT/MK/LT/RS/FI) y **documentar DK (+FO/GL) como tier D** (sin fuente abierta legible; `registreringsnumre.dk` de pago y prohíbe copia; Finanstilsynet PDF 2011).
- **Estado**: borrador
- **Tiempo**: est. 2,5h · real —
- **Previsión IA**: 0,12 M in / 0,04 M out tok · ≈ 4,4 €
- **Dependencias**: set de importadores estable (Fases 0-3)
- **Archivos**: `docs/importers.md`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] Matriz y contadores de cobertura actualizados (hasta 41/42); una fila por importador nuevo.
- [ ] Recetas `--file` documentadas (PT/MK/LT/RS/FI, incl. `pdftotext -layout`).
- [ ] DK (+FO/GL) documentado como **tier D** con el motivo.

**Subtareas**
- [ ] Actualizar la matriz y contadores; añadir recetas `--file`; añadir la fila/sección DK tier D.

**Notas**: se cierra con el set de importadores estable (tras Fases 1-3).

### T-20 — `ImporterRegistry`: consolidar registro y docblock

- **Descripción**: verificar que los **18 registros nuevos** (17 países + `RegafiImporter('MC')`) están en `registerDefaults()` y actualizar el **docblock narrativo** de `ImporterRegistry` describiendo el lote SEPA-coverage. Confirmar que `iban:update` los lista todos y que el conteo del catálogo cuadra.
- **Estado**: borrador
- **Tiempo**: est. 1h · real —
- **Previsión IA**: 0,03 M in / 0,01 M out tok · ≈ 1,1 €
- **Dependencias**: todas las tareas de importador (T-02…T-17); **excluye T-16 si su licencia no se confirma**
- **Archivos**: `src/Import/ImporterRegistry.php`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] `registerDefaults()` incluye todos los importadores entregados (T-16 solo si su licencia se confirmó).
- [ ] Docblock de `ImporterRegistry` actualizado con el lote SEPA-coverage.
- [ ] `iban:update` lista todos los importadores; el conteo del catálogo cuadra; suite verde.

**Subtareas**
- [ ] Consolidar registros + `use` ordenados alfabéticamente (PSR-12).
- [ ] Actualizar el docblock narrativo; verificar `iban:update` y `composer test`.

**Notas**: cada importador ya se registra en su propia tarea; esta tarea consolida y documenta el lote (evita divergencias).

### T-21 — `CHANGELOG.md`: entrada de versión (salto de cobertura SEPA)

- **Descripción**: entrada nueva en `CHANGELOG.md` (Keep a Changelog) con el salto de cobertura SEPA (24 → hasta 41/42), el nuevo `HtmlTableReader`, los importadores por país y la nota de datos curados. Cierre del plan.
- **Estado**: borrador
- **Tiempo**: est. 1h · real —
- **Previsión IA**: 0,05 M in / 0,02 M out tok · ≈ 2,1 €
- **Dependencias**: T-18, T-19, T-20
- **Archivos**: `CHANGELOG.md`
- **Cubre (tests)**: — (sin UI)

**Criterios de aceptación**
- [ ] Entrada de versión con Added/Changed acorde al formato Keep a Changelog.
- [ ] Refleja el salto de cobertura, `HtmlTableReader`, los importadores y la excepción de datos curados.

**Subtareas**
- [ ] Redactar la entrada; enumerar países cubiertos y salvedades (DK tier D, LT condicionado).

**Notas**: se escribe al final, con el alcance real entregado (ajustar si T-16 LT queda congelada).

---

## Notas de implementación

_A completar durante la ejecución. Registra decisiones, desvíos de la estimación y aprendizajes._

- **Orden recomendado**: T-01 → T-02 SE → T-03 FR+MC → T-04 EE → T-05 ME → T-06 CY → (T-18 licensing, adelantada) → T-07 AD → T-08 PT → T-09 MK → T-10 VA → T-11 SM → T-12 IS → T-13 IT → T-14 AL → T-15 RS → T-16 LT (si licencia) → T-17 FI → T-19 docs → T-20 registro → T-21 CHANGELOG.
- **Tareas condicionadas/bloqueadas**: **T-16 (LT)** bloqueada por confirmación de licencia; **T-09 (MK)** condicionada a frescura del roster 2014; **T-15 (RS)** con cross-check por alineación (posible degradación a curado); **T-17 (FI)** el último por coste/riesgo.
- **Convenciones del repo** (aplican a todas las tareas de código): TDD (fixture reducido primero), PHPStan L8 limpio, PSR-12, framework-free en `Import/Support` e `Import/Importers` (`CoreIsFrameworkFreeTest`), y `resolve()` de una IBAN de ejemplo del país devuelve el banco esperado.
