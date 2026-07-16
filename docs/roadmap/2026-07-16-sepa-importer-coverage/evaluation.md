# 2026-07-16-sepa-importer-coverage

> Presupuesto y viabilidad de **completar la cobertura SEPA de importadores** (Italia + 17 países) hasta **41/42** — decisión: aprobar el alcance «hasta Fase 3» y fijar el orden de construcción (quick wins de tier A primero) antes del handoff a `planner`.

| | |
|---|---|
| **Fecha** | 2026-07-16 |
| **Estado** | en-revisión 🔍 |
| **Prioridad global** | Alta 🟠 |
| **Solicitante** | daycry |
| **Spec** | [`spec.md`](spec.md) · fuente de evidencia: [`research.md`](research.md) (18 fichas verificadas en vivo) |
| **Plan** | [`improvement-plan.md`](improvement-plan.md) · [`tasks.md`](tasks.md) (borrador · 5 fases · 21 tareas) |
| **Características evaluadas** | 18 (1 infra + 16 importadores/países + 1 doc) |

---

## Cuadro de mando

| Métrica | Total estimado | Confianza |
|--------|----------------|-----------|
| Esfuerzo humano | **121 h** (101 h base +20 %) | Media |
| Tiempo IA (ejecución) | **16 h** (+ 5 h supervisión) | Media |
| Coste | **≈ 6.210 €** | Media |
| Tokens IA | **≈ 4,9 M** base (in ≈ 3,9 M / out ≈ 1,0 M) | Baja |
| Multiplicador productividad | **×5,8** | — |
| Características | **18** (17 países, DK excluido) | — |

> El coste lo domina el trabajo humano (≈ 6.060 € con margen); los tokens de IA son ≈ 150 € (con margen). El precio de tokens está **⚠️ por verificar** (ver Supuestos económicos). El multiplicador (×5,8) es menor que en iniciativas de código puro porque buena parte del esfuerzo es **verificación de datos, licencias y frescura** —trabajo que exige supervisión humana, no lo resuelve el agente solo.

---

## Resumen ejecutivo

Llega una spec cerrada (decisiones §8 ya tomadas por el usuario) para **completar la cobertura SEPA** de resolución de bancos de **24/42 a hasta 41/42** países, añadiendo importadores para los 17 restantes viables (todos menos DK). El marco de importadores (v1.2: `ImporterInterface`, `ImporterRegistry`, `ImportRunner`, `XlsxReader`) **ya existe** y hay 30 importadores como plantilla; el trabajo es **incremental y muy patronizado**: cada país = una clase framework-free + su test con fixture (TDD), una línea en `registerDefaults()`, y actualización de la matriz de `docs/importers.md`. Se presupuestan **18 características** agrupadas en **infraestructura compartida + 3 fases** por tier de viabilidad. El total es **≈ 101 h base (121 h con margen +20 %)** y **≈ 6.210 €**. Las partidas más caras y arriesgadas son **FI** (mapeador de rangos a medida, ≈ 10 h) y los importadores **`--file` de PDF** (LT/RS), no por volumen de código sino por fragilidad de parseo, licencias sin confirmar (LT/EE/IT) y frescura dudosa (MK 2014). Veredicto: **go** por fases, empezando por los **quick wins de tier A** (SE, FR+MC, EE) que suben la cobertura a 30/42 con bajo riesgo, dejando **FI el último** por ser el de mayor coste y menor confianza.

---

## Requerimientos recibidos

Mapa de la spec (`docs/roadmap/2026-07-16-sepa-importer-coverage/spec.md`) y su evidencia (`research.md`) a las características evaluadas. Una característica por importador/país (FR+MC comparten importador), más la infraestructura compartida y la documentación transversal.

| ID | Característica | Requisito origen (ref.) | ¿Claro? |
|----|---------------|-------------------------|---------|
| C-01 | **`HtmlTableReader`** (infra compartida, `ext-dom`) | spec §3·D1, §5; research §4·1 | ✅ (decidido D1) |
| C-02 | SE — `SwedenBankInfrastructureImporter` (PSV, MIT) | spec §4·F1·1; research §6·SE | ✅ |
| C-03 | FR+MC — `RegafiImporter` (JSON `cib`, parametrizado) | spec §4·F1·2, §5; research §5, §6·FR/MC | 🟡 licencia Licence Ouverte (atribución) |
| C-04 | CY — `CentralBankOfCyprusImporter` (landing+XLSX+WAF) | spec §4·F1·3; research §6·CY | 🟡 URL con fecha rotatoria + WAF |
| C-05 | EE — `EstonianBankingAssociationImporter` (HTML) | spec §4·F1·4; research §6·EE | 🟡 sin licencia explícita (factual) |
| C-06 | ME — `CentralBankOfMontenegroImporter` (HTML) | spec §4·F1·5; research §6·ME | ✅ (filtrar entes 714-931) |
| C-07 | PT — `BancoDePortugalImporter` (`--file`, PDF→texto) | spec §4·F2·6; research §6·PT | ✅ (receta `pdftotext -layout`) |
| C-08 | AD — `AndorranBankingImporter` (curado, D4) | spec §4·F2·7; research §6·AD | ✅ (curado recomendado) |
| C-09 | MK — `NbrmImporter` (`--file` CSV del `.xls`/`.docx`) | spec §4·F2·8; research §6·MK | 🟡 frescura roster 2014 |
| C-10 | IT — `AgenziaEntrateF24Importer` (HTML, zero-pad) | spec §4·F3·9; research §6·IT | 🟡 parcial (~400), sin BIC, licencia |
| C-11 | SM — `BcsmImporter` o curado (4 bancos) | spec §4·F3·10; research §6·SM | ✅ (curado recomendado) |
| C-12 | VA — dato curado (1 entrada, IOR/`IOPRVAVX`) | spec §4·F3·11; research §6·VA | ✅ |
| C-13 | IS — dato curado a nivel banco (prefijos) | spec §4·F3·12; research §6·IS | 🟡 resuelve banco, no sucursal |
| C-14 | AL — dato curado ~13 bancos (Anexo 4 + EPC) | spec §4·F3·13; research §6·AL | 🟡 sin fuente máquina; curación |
| C-15 | LT — `LietuvosBankasImporter` (`--file`, PDF→texto) | spec §4·F3·14; research §6·LT | 🔴 licencia sin confirmar («LB INTERNAL») |
| C-16 | RS — `NbsSerbiaImporter` (`--file`, 2 PDFs) | spec §4·F3·15; research §6·RS | 🟡 layout desalineado (zip 19↔19) |
| C-17 | FI — `FinanceFinlandImporter` (`--file` + rangos) | spec §4·F3·16; research §6·FI | 🔴 mapeador de rangos 1-4→3 díg. a medida |
| C-18 | Docs + matriz cobertura + `licensing.md` + DK tier D + `CHANGELOG` | spec §6, §7; research §7 | ✅ |

**Ambigüedades / información que falta (afectan a la estimación):**

- **Licencia de LT (C-15):** el PDF descargable lleva marca *"LB VIDAUS (ECB INTERNAL)"* pese a no requerir login; la landing de términos da 403. **Supuesto:** el importador se entrega como `--file` (no fetch por defecto) y **el enriquecimiento queda condicionado a confirmar términos con Lietuvos bankas** — riesgo de tener que congelar C-15 hasta esa confirmación.
- **Mapeador de rangos de FI (C-17):** el `Rahalaitostunnus` es de longitud variable (1-4 díg.), dado como valores sueltos y **rangos** (POP `470-479`, cajas de ahorro con listas largas) mientras el `bank_code` del paquete es fijo de 3 díg.; desde 2024 los códigos `72-78` son de 4 díg. (no caben en 3). **Supuesto:** se construye un mapeador de rangos → clave de 3 díg. a medida, con pérdida controlada de los códigos de 4 díg. → confianza Baja.
- **Frescura del roster de MK (C-09):** el único contenido confirmable es v1.15 (2014); puede predatar fusiones/liquidaciones (Eurostandard/370 liquidado 2020). **Supuesto:** se verifica la frescura al implementar; si el roster está obsoleto, se reduce alcance a los bancos vigentes.
- **Licencias factuales sin declarar (C-05 EE, C-10 IT y las fuentes curadas):** cubiertas por la disciplina «fetch bajo demanda, no se empaqueta el dato» (EE/IT) o por el argumento «hechos no protegibles» + nota en `licensing.md` (curados). **Supuesto:** D4 aprobado (§8·4) cubre los curados.
- **Fragilidad de los scrapes HTML (C-04/C-05/C-06/C-10):** dependen de la estructura de la web de origen; se documenta el CAVEAT por importador (igual que el resto del catálogo).

> Todas las **decisiones de arquitectura (D1-D5) y el alcance «hasta Fase 3» están cerradas por el usuario** (spec §8) y se presupuestan como fijas, no se cuestionan.

---

## Datos necesarios para una evaluación completa

- [x] **Requerimientos** completos y sin ambigüedades — *spec cerrada + `research.md` con 18 fichas verificadas en vivo; quedan las incógnitas de licencia/frescura marcadas arriba.*
- [x] **Alcance** de cada característica acotado — *muy explícito: qué país, qué fuente, qué formato, qué salvedad; DK fuera (tier D, solo documentar).*
- [x] **Criterios de aceptación / éxito** por característica — *spec §7: test verde con fixture, PHPStan L8, PSR-12, `resolve()` de una IBAN de ejemplo devuelve el banco esperado.*
- [x] **Restricciones** — *sin dependencias pesadas nuevas (solo `ext-dom`, ya disponible); disciplina de licencias; patrón de repo (framework-free + test).*
- [x] **Dependencias externas** identificadas — *fuentes oficiales por país (research §6); ninguna dependencia dura de runtime nueva.*
- [x] **Contexto técnico** — *marco de importadores v1.2 ya existe; 30 importadores de plantilla; test de arquitectura guarda `Import/Importers` e `Import/Support`.*
- [ ] **Tarifa/hora y precio de tokens confirmados** — *se asumen defaults; precio de tokens ⚠️ por verificar.*
- [ ] **Licencia de LT confirmada** — *bloqueante para el enriquecimiento de C-15 (no para su entrega como `--file`).*

---

## Supuestos económicos (ajustables)

**Coste = (horas × tarifa) + coste de tokens de IA.** Importes en **EUR**.

| Parámetro | Valor | Nota |
|-----------|-------|------|
| Tarifa de desarrollo | **50 €/h** | Default del kit; sin tarifa definida en el proyecto. |
| Modelo IA asumido | **claude-opus-4-8** | Base de la previsión de tokens. |
| Precio input | **≈ 13,80 € / 1M** | ⚠️ **verificar**: asume tarifa clase Opus ≈ 15 USD/1M × 0,92. |
| Precio output | **≈ 69,00 € / 1M** | ⚠️ **verificar**: asume tarifa clase Opus ≈ 75 USD/1M × 0,92. |
| Tipo de cambio | 1 USD = 0,92 € | Aplicado al precio de tokens. |
| Ratio de supervisión | **~30 % de horas IA** | Elevado sobre el default (25 %) por el peso de verificación de datos/licencias/frescura. |
| Horas por FTE-mes | 160 h | Base del cálculo de FTE equivalentes. |
| Margen de contingencia | **+20 %** | Sobre horas base (humanas **e** IA); coste recalculado desde las horas con margen. |

> Las cifras de tokens/coste son **parametrizadas**: el peso humano (≈ 97,6 % del coste) hace el resultado poco sensible al precio real de tokens. Todas las horas del cuadro de mando y del bloque de productividad se muestran **con margen +20 %** ya aplicado.

---

## Evaluación por característica

> Base de calibración: en el repo, un importador es una clase framework-free de ≈ 85-205 LOC + un test con fixture de ≈ 120-180 LOC + una línea en `registerDefaults()`. `XlsxReader` (lector compartido comparable a `HtmlTableReader`) son 333 LOC. Las horas son **humanas base** (sin margen); incluyen investigación de formato, código, test/fixture, PHPStan L8, PSR-12 y la fila de la matriz de cobertura.

### C-01 — `HtmlTableReader` (infraestructura compartida) ⭐ prerequisito

- **Requisito origen**: spec §3·D1 (APROBADO), §5; research §4·1.
- **Descripción**: nuevo helper framework-free en `Import/Support/HtmlTableReader` basado en `DOMDocument` (`ext-dom`/`ext-libxml`, ya disponibles), con la misma forma que `XlsxReader`: `readTables()` / `readFirstTable()` → rejilla 0-indexada de celdas string, + `locateHeader()` por-importador. Lo consumen CY, EE, ME e IT.
- **Complejidad**: Media
- **Esfuerzo**: 9 h · confianza Media
- **Previsión IA**: 0,35 M in / 0,09 M out tok · ≈ 11 €
- **Coste**: (9 h × 50 €) + tokens = **≈ 461 €**
- **Impacto / áreas afectadas**: `src/Import/Support/HtmlTableReader.php`, `tests/Import/Support/`, `tests/Architecture/CoreIsFrameworkFreeTest.php` (ya cubierto por el directorio guardado `Import/Support`).
- **Dependencias y prerequisitos**: ninguna; **habilita** C-04/C-05/C-06/C-10.
- **Riesgos**: quirks de `libxml` con HTML malformado; tablas anidadas / `<thead>` / celdas vacías. Mitigable con tests representativos (spec §6).
- **Incógnitas**: si algún origen exige `colspan`/`rowspan` (no visto en las fichas; asumido no).

### C-02 — SE · `SwedenBankInfrastructureImporter`

- **Requisito origen**: spec §4·F1·1; research §6·SE.
- **Descripción**: fetch `Data/source.psv` (PSV, licencia **MIT**) del mirror `Bankinfrastruktur/BankData`; columna `IbanId` (3 díg.) = `bank_code` + BIC + nombre; **de-duplicar por `IbanId`** (Nordea=300 en muchos rangos); **no** usar columnas de clearing. `--file` como hedge.
- **Complejidad**: Baja-Media
- **Esfuerzo**: 4 h · confianza Alta
- **Previsión IA**: 0,15 M in / 0,04 M out tok · ≈ 4,8 €
- **Coste**: (4 h × 50 €) + tokens = **≈ 205 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, `tests/Import/Importers/`, `ImporterRegistry::registerDefaults()`.
- **Dependencias y prerequisitos**: ninguna (PSV se lee con `fgetcsv`, ya soportado).
- **Riesgos**: mirror comunitario (no fuente oficial); si `raw.githubusercontent.com` se bloquea → `--file`. Bajo.
- **Incógnitas**: estabilidad del layout del PSV comunitario.

### C-03 — FR + MC · `RegafiImporter` (parametrizado por país)

- **Requisito origen**: spec §4·F1·2, §5; research §5, §6·FR/MC.
- **Descripción**: importador parametrizado (patrón `EpcRegisterImporter`, `new RegafiImporter('FR')`/`'MC'`), registrado **dos veces**. Fetch JSON de la API ODS REGAFI; **parsear el array serializado `cib`** (`["24659"]`, `[{"code":...}]`, `"[]"`) y **expandir una fila por código** (5 díg., zero-pad/validar); nombre (sin BIC); atribución Licence Ouverte. MC sale del mismo dataset filtrando por país.
- **Complejidad**: Media
- **Esfuerzo**: 7 h · confianza Media
- **Previsión IA**: 0,30 M in / 0,08 M out tok · ≈ 9,7 €
- **Coste**: (7 h × 50 €) + tokens = **≈ 360 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/RegafiImporter.php`, tests (FR **y** MC), `registerDefaults()` (2 registros).
- **Dependencias y prerequisitos**: ninguna.
- **Riesgos**: el `cib` es array JSON, no escalar → parseo/expansión no trivial (fuente principal de las 7 h); una entidad con varios CIB. Cobre 2 países con un importador → **alta eficiencia**.
- **Incógnitas**: elegir dataset (`prd-jdd-recherche` simple vs. `prd-banque-entites` autoritativo); ambos verificados en vivo.

### C-04 — CY · `CentralBankOfCyprusImporter`

- **Requisito origen**: spec §4·F1·3; research §6·CY.
- **Descripción**: GET a la landing IBAN estable, **regex del href** `CIs_and_EMIs_BICs_updated_*.xlsx` (nombre con fecha rotatoria), fetch con **User-Agent de navegador** (WAF), leer con `XlsxReader`; columna *"Bank identifiers used in IBAN"* (3 díg.) → nombre + BIC.
- **Complejidad**: Media
- **Esfuerzo**: 6 h · confianza Media
- **Previsión IA**: 0,25 M in / 0,06 M out tok · ≈ 7,6 €
- **Coste**: (6 h × 50 €) + tokens = **≈ 308 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, tests + fixture XLSX, `registerDefaults()`.
- **Dependencias y prerequisitos**: `XlsxReader` (existe). No usa `HtmlTableReader` (solo regex del href), aunque el scrape de la landing se beneficia de la misma disciplina.
- **Riesgos**: WAF exige UA de navegador (403 en su ausencia); URL con fecha rotatoria → el regex debe ser robusto. La edición `.xls` (BIFF) más nueva **no** es legible → fijar la `.xlsx`.
- **Incógnitas**: cadencia de rotación del nombre de fichero.

### C-05 — EE · `EstonianBankingAssociationImporter`

- **Requisito origen**: spec §4·F1·4; research §6·EE.
- **Descripción**: `HtmlTableReader` sobre `pangaliit.ee/.../bank-codes`; código de 2 díg. → nombre + BIC; **manejar dobles** (Luminor 96/17) y **cero inicial** (TBB 00).
- **Complejidad**: Baja-Media
- **Esfuerzo**: 4 h · confianza Media
- **Previsión IA**: 0,15 M in / 0,04 M out tok · ≈ 4,8 €
- **Coste**: (4 h × 50 €) + tokens = **≈ 205 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, tests + fixture HTML, `registerDefaults()`.
- **Dependencias y prerequisitos**: **C-01** (`HtmlTableReader`).
- **Riesgos**: primer consumidor del scraping HTML → valida C-01; sin licencia explícita (lista factual, cubierta por «fetch bajo demanda»).
- **Incógnitas**: preservar el cero inicial (código como string, no int).

### C-06 — ME · `CentralBankOfMontenegroImporter`

- **Requisito origen**: spec §4·F1·5; research §6·ME.
- **Descripción**: `HtmlTableReader` sobre la página RTGS de CBCG; código de 3 díg. → nombre + BIC; **filtrar** entes públicos (rango 714-931), dejando solo bancos comerciales.
- **Complejidad**: Media
- **Esfuerzo**: 5 h · confianza Media
- **Previsión IA**: 0,20 M in / 0,05 M out tok · ≈ 6,2 €
- **Coste**: (5 h × 50 €) + tokens = **≈ 256 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, tests + fixture HTML, `registerDefaults()`.
- **Dependencias y prerequisitos**: **C-01** (`HtmlTableReader`).
- **Riesgos**: la tabla mezcla bancos y entes públicos → el filtro por rango debe ser correcto (falsos positivos/negativos).
- **Incógnitas**: estabilidad del rango 714-931 en el tiempo.

### C-07 — PT · `BancoDePortugalImporter` (`--file`)

- **Requisito origen**: spec §4·F2·6; research §6·PT.
- **Descripción**: importador `--file` que consume el **texto/CSV pre-extraído** del PDF SICOI (receta `pdftotext -layout`, patrón NL `betaalvereniging`); columna de 4 díg. → nombre + BIC; **limpieza de mojibake** de acentos.
- **Complejidad**: Media
- **Esfuerzo**: 6 h · confianza Media
- **Previsión IA**: 0,22 M in / 0,055 M out tok · ≈ 6,8 €
- **Coste**: (6 h × 50 €) + tokens = **≈ 307 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, tests + fixture (texto PDF), `registerDefaults()`, `docs/importers.md` (receta de extracción).
- **Dependencias y prerequisitos**: patrón `--file` (existe).
- **Riesgos**: fragilidad del parseo posicional del texto extraído; mojibake de acentos; landing bloquea bots (por eso `--file`).
- **Incógnitas**: estabilidad del layout de columnas fijas entre ediciones (hay edición 2026).

### C-08 — AD · `AndorranBankingImporter` (dato curado)

- **Requisito origen**: spec §4·F2·7; research §6·AD.
- **Descripción**: dado que son **3 bancos / 4 códigos**, ruta **curada** (D4): `rows()` yield un array constante `Entitat` (4 díg.) → nombre; BIC curado (BACAADAD, CRDAADAD, BSAAADAD). Fichero en `Import/Importers/data/ad.php`, procedencia `curated`.
- **Complejidad**: Baja
- **Esfuerzo**: 3 h · confianza Media
- **Previsión IA**: 0,10 M in / 0,03 M out tok · ≈ 3,5 €
- **Coste**: (3 h × 50 €) + tokens = **≈ 153 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/AndorranBankingImporter.php`, `data/ad.php`, test, `registerDefaults()`, nota en `licensing.md`.
- **Dependencias y prerequisitos**: **D4** (política curados, aprobada); C-18 (nota licensing).
- **Riesgos**: dato factual que envejece → refresco anual documentado. Bajo (conjunto diminuto y estable).
- **Incógnitas**: mapeo exacto 0007/0008 de MoraBanc.

### C-09 — MK · `NbrmImporter` (`--file`)

- **Requisito origen**: spec §4·F2·8; research §6·MK.
- **Descripción**: importador `--file` que acepta un **CSV exportado** por el operador desde el `.xls` BIFF / `.docx` (NBRM está tras Cloudflare → offline sí o sí; sin lector BIFF, D3); código de 3 díg. → nombre + BIC.
- **Complejidad**: Media
- **Esfuerzo**: 5 h · confianza **Baja** (frescura)
- **Previsión IA**: 0,18 M in / 0,045 M out tok · ≈ 5,6 €
- **Coste**: (5 h × 50 €) + tokens = **≈ 256 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, tests + fixture CSV, `registerDefaults()`, `docs/importers.md`.
- **Dependencias y prerequisitos**: patrón `--file` (existe).
- **Riesgos**: **frescura del roster 2014** (puede predatar fusiones/liquidaciones, p. ej. Eurostandard/370 liquidado 2020) → verificar al implementar; Cloudflare impide fetch automático.
- **Incógnitas**: si NBRM publica una edición más reciente que la v1.15 (2014).

### C-10 — IT · `AgenziaEntrateF24Importer`

- **Requisito origen**: spec §4·F3·9; research §6·IT.
- **Descripción**: `HtmlTableReader` sobre la página F24 `…-xcodice`; `Codice ABI` **zero-pad a 5 díg.** → nombre; **sin BIC**; **parcial** (~400 bancos adheridos a F24). Documentar que el ABI/CAB canónico (SIA-Nexi) es de pago (tier D).
- **Complejidad**: Media
- **Esfuerzo**: 6 h · confianza Media
- **Previsión IA**: 0,24 M in / 0,06 M out tok · ≈ 7,5 €
- **Coste**: (6 h × 50 €) + tokens = **≈ 307 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, tests + fixture HTML, `registerDefaults()`, `docs/importers.md` (caveat de parcialidad).
- **Dependencias y prerequisitos**: **C-01** (`HtmlTableReader`).
- **Riesgos**: cobertura **parcial** (solo bancos F24) → documentar claramente; ABI mostrado sin ceros → zero-pad obligatorio; sin licencia de reutilización explícita (cubierto por «fetch bajo demanda»).
- **Incógnitas**: proporción real de bancos italianos cubiertos por F24 vs. el universo ABI.

### C-11 — SM · `BcsmImporter` o dato curado

- **Requisito origen**: spec §4·F3·10; research §6·SM.
- **Descripción**: **4 bancos**, ABI de 5 díg. → nombre + BIC. Ruta **curada** recomendada (más robusta que scrapear 4 filas), `data/sm.php`, procedencia `curated`. Los directorios ABI italianos **no** listan bancos sammarinesi.
- **Complejidad**: Baja
- **Esfuerzo**: 3 h · confianza Alta
- **Previsión IA**: 0,10 M in / 0,03 M out tok · ≈ 3,5 €
- **Coste**: (3 h × 50 €) + tokens = **≈ 153 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, `data/sm.php`, test, `registerDefaults()`, nota `licensing.md`.
- **Dependencias y prerequisitos**: **D4**; C-18.
- **Riesgos**: envejecimiento (4 entidades) → refresco anual. Bajo.
- **Incógnitas**: ninguna material (4 bancos verificados con ABI+BIC).

### C-12 — VA · dato curado (1 entrada)

- **Requisito origen**: spec §4·F3·11; research §6·VA.
- **Descripción**: **1 entrada** curada `001 → Istituto per le Opere di Religione (IOR)`, BIC `IOPRVAVX`. El universo real es un banco. Procedencia `curated`/hardcoded.
- **Complejidad**: Baja
- **Esfuerzo**: 2 h · confianza Alta
- **Previsión IA**: 0,08 M in / 0,02 M out tok · ≈ 2,5 €
- **Coste**: (2 h × 50 €) + tokens = **≈ 102 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, `data/va.php`, test, `registerDefaults()`, nota `licensing.md`.
- **Dependencias y prerequisitos**: **D4**; C-18.
- **Riesgos**: mínimo (hecho único no protegible).
- **Incógnitas**: ninguna (2 IBAN oficiales confirman `001`/IOPRVAVX).

### C-13 — IS · dato curado a nivel banco

- **Requisito origen**: spec §4·F3·12; research §6·IS.
- **Descripción**: **mapa curado a nivel banco** por prefijos de «centenas» (01/03/05/07/11/15/22 → banco); resuelve **banco, no sucursal** (sin directorio abierto de sucursal); BIC curado. Procedencia `curated`.
- **Complejidad**: Media
- **Esfuerzo**: 5 h · confianza Media
- **Previsión IA**: 0,18 M in / 0,045 M out tok · ≈ 5,6 €
- **Coste**: (5 h × 50 €) + tokens = **≈ 256 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, `data/is.php`, test, `registerDefaults()`, nota `licensing.md`.
- **Dependencias y prerequisitos**: **D4**; C-18.
- **Riesgos**: mapeo por prefijo (no código exacto de 4 díg.) → documentar que resuelve a nivel banco; el fallback `findByBankCode(cc, bank, null)` ya lo soporta.
- **Incógnitas**: exhaustividad del esquema de prefijos (¿cubre el 100 % de bancos activos?).

### C-14 — AL · dato curado (~13 bancos)

- **Requisito origen**: spec §4·F3·13; research §6·AL.
- **Descripción**: mapa **curado** de ~13 bancos (KIB 3 díg. → nombre + BIC) autorado del **Reglamento IBAN nº 42, Anexo 4** + **cross-check EPC/SWIFT** (dato factual, no redistribución del reglamento). Salvedad semántica: el «KIB» del BoA es de 8 díg.; el `bank_code` son los **3 primeros**.
- **Complejidad**: Media
- **Esfuerzo**: 6 h · confianza Media
- **Previsión IA**: 0,22 M in / 0,055 M out tok · ≈ 6,8 €
- **Coste**: (6 h × 50 €) + tokens = **≈ 307 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, `data/al.php`, test, `registerDefaults()`, nota `licensing.md`.
- **Dependencias y prerequisitos**: **D4**; C-18.
- **Riesgos**: autoría factual meticulosa (dominio BoA bloquea fetch); necesita cross-check con EPC/SWIFT para BIC.
- **Incógnitas**: si BoA publica un dataset abierto en 6-12 meses (revisar); nº exacto de bancos activos.

### C-15 — LT · `LietuvosBankasImporter` (`--file`) 🔴 bloqueo de licencia

- **Requisito origen**: spec §4·F3·14; research §6·LT.
- **Descripción**: importador `--file` que consume **texto/CSV pre-extraído** del PDF del directorio FI de Lietuvos bankas (221 filas, 5 díg. → nombre + BIC). **Bloqueo:** confirmar términos de licencia (marca «LB INTERNAL») antes de enriquecer.
- **Complejidad**: Alta
- **Esfuerzo**: 8 h · confianza **Baja** (licencia + parseo)
- **Previsión IA**: 0,30 M in / 0,075 M out tok · ≈ 9,3 €
- **Coste**: (8 h × 50 €) + tokens = **≈ 409 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, tests + fixture (texto PDF), `registerDefaults()`, `docs/importers.md`.
- **Dependencias y prerequisitos**: patrón `--file`; **confirmación de licencia** (bloqueante para publicar el enriquecimiento).
- **Riesgos**: **licencia sin confirmar** («LB VIDAUS / ECB INTERNAL») → posible congelación; parseo posicional frágil (sub-columnas sucursal/ciudad entrelazadas); URL rotatoria + índice bloqueado por Cloudflare.
- **Incógnitas**: respuesta de Lietuvos bankas sobre reutilización.

### C-16 — RS · `NbsSerbiaImporter` (`--file`)

- **Requisito origen**: spec §4·F3·15; research §6·RS.
- **Descripción**: importador `--file` con **dos PDFs pre-extraídos** (`pregled_racuna` código→BIC + `id_brojevi` código→nombre); **zip 19 nombres ↔ 19 códigos** (layout de dos columnas desalineado); 3 díg. → nombre + BIC. Alternativa: mapa curado (~19).
- **Complejidad**: Media-Alta
- **Esfuerzo**: 6 h · confianza **Baja** (alineación)
- **Previsión IA**: 0,24 M in / 0,06 M out tok · ≈ 7,5 €
- **Coste**: (6 h × 50 €) + tokens = **≈ 307 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, tests + fixture (2 textos PDF), `registerDefaults()`, `docs/importers.md`.
- **Dependencias y prerequisitos**: patrón `--file`.
- **Riesgos**: **layout desalineado** → la estrategia «zip 19↔19» es frágil ante cualquier fila extra/faltante; conviene cross-check contra un mapa curado (~19) como red de seguridad.
- **Incógnitas**: si conviene degradar directamente a mapa curado (podría bajar el coste y subir la robustez).

### C-17 — FI · `FinanceFinlandImporter` (`--file` + mapeador de rangos) ⭐ más cara/menor confianza

- **Requisito origen**: spec §4·F3·16; research §6·FI.
- **Descripción**: importador `--file` (texto PDF) + **mapeador de rangos a medida** que convierte el `Rahalaitostunnus` de longitud variable (1-4 díg., valores sueltos y **rangos**: Nordea «1 ja 2», POP `470-479`, cajas de ahorro con listas largas) a la clave **fija de 3 díg.** del paquete, gestionando los códigos de 4 díg. (`72-78`, desde 2024) que no caben.
- **Complejidad**: **Alta**
- **Esfuerzo**: 10 h · confianza **Baja**
- **Previsión IA**: 0,40 M in / 0,10 M out tok · ≈ 12,4 €
- **Coste**: (10 h × 50 €) + tokens = **≈ 512 €**
- **Impacto / áreas afectadas**: `src/Import/Importers/`, tests + fixture (texto PDF con rangos), `registerDefaults()`, `docs/importers.md`.
- **Dependencias y prerequisitos**: patrón `--file`. **Recomendado el último** (mayor coste/menor confianza).
- **Riesgos**: el **join exacto falla** para todos los grandes bancos → el mapeador de rangos es la parte cara y con riesgo de correctitud; pérdida controlada de códigos de 4 díg.; sin licencia explícita.
- **Incógnitas**: cómo modelar limpiamente el solapamiento rango→clave de 3 díg. sin colisiones; cobertura resultante real.

### C-18 — Documentación, matriz de cobertura y `CHANGELOG` (transversal)

- **Requisito origen**: spec §6, §7; research §7.
- **Descripción**: actualizar `docs/importers.md` (matriz de cobertura + contadores 24 → 30 → 33 → hasta 41/42, recetas `--file` por país), **nota nueva en `docs/licensing.md`** acotando la excepción de datos curados a micro-jurisdicciones (D4), **documentar DK como tier D** (motivo: sin fuente abierta legible; `registreringsnumre.dk` de pago y prohíbe copia), y entrada en `CHANGELOG.md` con el salto de cobertura SEPA. Confirmar que el test de arquitectura sigue verde.
- **Complejidad**: Baja-Media
- **Esfuerzo**: 6 h · confianza Alta
- **Previsión IA**: 0,25 M in / 0,09 M out tok · ≈ 9,7 €
- **Coste**: (6 h × 50 €) + tokens = **≈ 310 €**
- **Impacto / áreas afectadas**: `docs/importers.md`, `docs/licensing.md`, `CHANGELOG.md`.
- **Dependencias y prerequisitos**: se cierra al final (necesita el set de importadores estable); la nota de `licensing.md` (D4) puede adelantarse para desbloquear los curados.
- **Riesgos**: precisión de la nota de licencias (curados + DK) es importante para la credibilidad del proyecto.
- **Incógnitas**: redacción exacta de la excepción de datos curados en `licensing.md`.

---

## Comparativa

Agrupada por fase (una fila por característica). Horas y coste son **base** (sin margen +20 %).

| # | Característica | Fase | Complejidad | Horas | Coste € | Tokens | Prioridad | Confianza |
|---|---------------|:----:|-------------|:-----:|:-------:|:------:|-----------|-----------|
| C-01 | `HtmlTableReader` (infra) ⭐ | Infra | Media | 9 h | 461 € | 0,44 M | Alta | Media |
| C-02 | SE — PSV (MIT) | F1 | Baja-Media | 4 h | 205 € | 0,19 M | Alta | Alta |
| C-03 | FR + MC — REGAFI (2 países) | F1 | Media | 7 h | 360 € | 0,38 M | Alta | Media |
| C-04 | CY — landing + XLSX + WAF | F1 | Media | 6 h | 308 € | 0,31 M | Alta | Media |
| C-05 | EE — HTML | F1 | Baja-Media | 4 h | 205 € | 0,19 M | Alta | Media |
| C-06 | ME — HTML + filtro | F1 | Media | 5 h | 256 € | 0,25 M | Alta | Media |
| C-07 | PT — `--file` PDF | F2 | Media | 6 h | 307 € | 0,275 M | Media | Media |
| C-08 | AD — curado | F2 | Baja | 3 h | 153 € | 0,13 M | Media | Media |
| C-09 | MK — `--file` CSV | F2 | Media | 5 h | 256 € | 0,225 M | Baja | Baja |
| C-10 | IT — HTML + zero-pad | F3 | Media | 6 h | 307 € | 0,30 M | Media | Media |
| C-11 | SM — curado | F3 | Baja | 3 h | 153 € | 0,13 M | Baja | Alta |
| C-12 | VA — curado (1) | F3 | Baja | 2 h | 102 € | 0,10 M | Baja | Alta |
| C-13 | IS — curado (prefijos) | F3 | Media | 5 h | 256 € | 0,225 M | Baja | Media |
| C-14 | AL — curado (~13) | F3 | Media | 6 h | 307 € | 0,275 M | Baja | Media |
| C-15 | LT — `--file` PDF 🔴 licencia | F3 | Alta | 8 h | 409 € | 0,375 M | Baja | Baja |
| C-16 | RS — `--file` 2 PDFs | F3 | Media-Alta | 6 h | 307 € | 0,30 M | Baja | Baja |
| C-17 | FI — `--file` + rangos ⭐ | F3 | Alta | 10 h | 512 € | 0,50 M | Baja | Baja |
| C-18 | Docs + matriz + licensing + DK | Trans. | Baja-Media | 6 h | 310 € | 0,34 M | Media | Alta |
| | **Total (base)** | | | **101 h** | **≈ 5.174 €** | **≈ 4,94 M** | | |

### Resumen por fase

| Fase | Alcance | Cobertura SEPA | Horas base | Coste base € | Veredicto |
|------|---------|:--------------:|:----------:|:------------:|-----------|
| **Infra** | `HtmlTableReader` (habilita CY/EE/ME/IT) | — | 9 h | ≈ 461 € | **go** (prerequisito) |
| **Fase 1 — Tier A** | SE, FR+MC, CY, EE, ME (5 importadores / 6 países) | 24 → **30/42** | 26 h | ≈ 1.334 € | **go** (quick wins) |
| **Fase 2 — Tier B** | PT, AD, MK (`--file`/curado) | 30 → **33/42** | 14 h | ≈ 716 € | **go** |
| **Fase 3 — Tier C** | IT, SM, VA, IS, AL, LT, RS, FI (8 países) | 33 → **hasta 41/42** | 46 h | ≈ 2.353 € | **go con condiciones** |
| **Transversal** | Docs + matriz + `licensing.md` + DK tier D + `CHANGELOG` | — | 6 h | ≈ 310 € | **go** |
| | **Total** | **24 → hasta 41/42** | **101 h** | **≈ 5.174 €** | |

> Fase 3 «con condiciones»: **LT** condicionado a confirmar licencia; **FI** el último por coste/riesgo; **RS/MK** con cross-check por frescura/alineación. Ninguna condición bloquea las Fases 1-2.

---

## Presupuesto total

| Concepto | Cálculo | Importe |
|----------|---------|---------|
| Desarrollo (humano, base) | 101 h × 50 €/h | 5.050 € |
| Margen de contingencia (humano) | +20 % sobre 5.050 € | +1.010 € |
| Tokens IA input (con margen) | ≈ 4,69 M tok × 13,80 €/1M | ≈ 64,72 € |
| Tokens IA output (con margen) | ≈ 1,24 M tok × 69,00 €/1M | ≈ 85,28 € |
| **Total estimado (con margen)** | | **≈ 6.210 €** |

> Base sin margen: ≈ 5.174 € (5.050 € humano + ≈ 124 € tokens). El trabajo humano supone ≈ 97,6 % del coste; el precio de tokens (⚠️ verificar) apenas mueve el total.

---

## Productividad IA (humano vs. IA)

| KPI | Valor |
|-----|-------|
| Horas humanas estimadas | 121 h |
| Horas IA (ejecución) | 16 h |
| Supervisión humana | 5 h |
| **Horas totales (IA + supervisión)** | **21 h** |
| Horas ahorradas | ≈ 100 h |
| **Ahorro** | **≈ 83 %** |
| **Multiplicador de productividad** | **×5,8** |
| FTE equivalentes *(opcional)* | ≈ 0,6 FTE-mes |

> Horas mostradas **con el margen de contingencia (+20 %)** ya aplicado. Base: 101 h humanas · 13 h IA · 4 h supervisión (≈ 17 h totales) → mismo multiplicador ×5,9. El multiplicador es **menor que en iniciativas de código puro** (p. ej. ×7,6 en `daycry-iban-v1`) porque una parte grande del esfuerzo es **verificación de datos curados, confirmación de licencias y frescura, y validación de scrapes frágiles** — trabajo que requiere criterio y supervisión humana (por eso la supervisión se sube a ~30 % de las horas IA), no que el agente cierra solo. Los importadores patronizados (SE, FR+MC, curados) sí son muy eficientes con IA; FI/LT/RS/AL concentran la carga humana.

---

## Recomendación

- **Veredicto global**: **go**, por fases y con orden. El alcance («hasta Fase 3», 17 países, DK fuera) está **cerrado por el usuario** (spec §8) y se presupuesta como fijo. El coste (≈ 6.210 €) es moderado para pasar de 24/42 a **hasta 41/42** países SEPA, y el marco de importadores ya existe (bajo riesgo estructural).
- **Quick wins** (bajo coste, alto valor, alta confianza): **C-02 SE** (4 h, PSV/MIT), **C-03 FR+MC** (7 h, dos países con un importador), **C-05 EE** (4 h) — junto con **C-01 (`HtmlTableReader`)** como habilitador. Cierran la Fase 1 (24 → 30/42) con ≈ 35 h y bajo riesgo.
- **Costosas / a valorar**: **C-17 FI** (10 h, mapeador de rangos, confianza Baja — la más cara), **C-15 LT** (8 h, bloqueo de licencia), **C-16 RS** (6 h, layout desalineado). Concentran el coste y el riesgo de la Fase 3.
- **Orden sugerido**:
  **C-01 (infra) → Fase 1: C-02 SE → C-03 FR+MC → C-05 EE → C-06 ME → C-04 CY** → **Fase 2: C-08 AD → C-07 PT → C-09 MK** → **Fase 3: C-12 VA → C-11 SM → C-13 IS → C-10 IT → C-14 AL → C-16 RS → C-15 LT → C-17 FI** → **C-18 docs (cierre)**.
  Motivo: `HtmlTableReader` primero (habilita CY/EE/ME/IT); dentro de cada fase, de **esfuerzo/riesgo creciente** (curados y patrones limpios antes que los `--file` de PDF); **FI el último** (mayor coste/menor confianza); **LT tras confirmar licencia**; la doc/matriz/licensing al final (con la nota de `licensing.md` adelantable para desbloquear los curados).
- **Fuera de alcance (confirmado)**: **DK (+FO/GL)** — solo se documenta como tier D en `docs/importers.md` (parte de C-18), no se construye. Si hubiera que aligerar el presupuesto, los diferibles de menor valor son **C-16 RS** (degradable a mapa curado ~19, más barato/robusto) y **C-17 FI** (aparcable sin romper el resto: 40/42 sin FI).

---

## Riesgos transversales

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| **Licencia de LT sin confirmar** («LB INTERNAL») bloquea el enriquecimiento (C-15) | Media | Alto | Entregar como `--file` (no fetch por defecto); **confirmar términos con Lietuvos bankas** antes de publicar; congelar C-15 si no hay confirmación (no bloquea Fases 1-2). |
| **Fragilidad de scrapes HTML** (CY/EE/ME/IT) ante rediseños web | Media | Medio | `HtmlTableReader` robusto + tests representativos; CAVEAT documentado por importador; `--file` como hedge donde aplique. |
| **Frescura de datos** (MK roster 2014; curados VA/AD/SM/IS/AL) | Media | Medio | Verificar frescura al implementar (MK); refresco **anual** documentado de los curados; procedencia `curated` marcada. |
| **Desviación de la regla «no empaquetar datos»** con los curados | Baja | Medio (reputacional) | Argumento «hechos no protegibles» + metodología `registry-authoring.md`; **nota acotada en `licensing.md`** (D4) limitada a micro-jurisdicciones sin fuente máquina. |
| **Mapeador de rangos de FI incorrecto** (join exacto falla; 4 díg. no caben) | Media | Medio | Modelar rangos→clave 3 díg. con tests exhaustivos; documentar la pérdida de códigos de 4 díg.; FI el último para aprender de los demás. |
| **Layout desalineado de RS** (zip 19↔19 frágil) | Media | Medio | Cross-check contra mapa curado ~19 como red de seguridad; considerar degradar a curado. |
| **Fuga de acoplamiento CI4** en los nuevos ficheros framework-free | Baja | Alto | `Import/Support` e `Import/Importers` ya están en `GUARDED_DIRECTORIES`; el test `CoreIsFrameworkFreeTest` lo detecta automáticamente. |
| **Precio de tokens asumido incorrecto** | Media | Bajo | Cálculo parametrizado; tokens ≈ 2,4 % del total → impacto económico despreciable. |

---

## Siguiente paso

Para **ejecutar** lo aprobado, genera el plan detallado con el agente **`planner`** (creará `improvement-plan.md` + `tasks.md` en esta misma carpeta y rellenará el campo **Plan** de esta evaluación y el `plan:` de la spec). No se genera aquí el plan de ejecución.

**Aprobado para planificar (alcance «hasta Fase 3», DK excluido):**

- **Infra:** C-01 `HtmlTableReader` (prerequisito de CY/EE/ME/IT).
- **Fase 1 — Tier A (quick wins, 24 → 30/42):** C-02 SE, C-03 FR+MC, C-04 CY, C-05 EE, C-06 ME.
- **Fase 2 — Tier B (`--file`/curado, → 33/42):** C-07 PT, C-08 AD, C-09 MK.
- **Fase 3 — Tier C (salvedades/curación, → hasta 41/42):** C-10 IT, C-11 SM, C-12 VA, C-13 IS, C-14 AL, C-15 LT *(condicionado a licencia)*, C-16 RS, C-17 FI *(el último)*.
- **Transversal:** C-18 docs + matriz de cobertura + nota `licensing.md` (D4) + DK tier D + `CHANGELOG`.

---

## Changelog

| Fecha | Cambio |
|---|---|
| 2026-07-16 | Evaluación inicial (en-revisión) a partir de `spec.md` + `research.md`. 18 características (1 infra + 16 importadores/países + 1 doc), alcance «hasta Fase 3» (17 países, DK excluido). **101 h base (121 h +20 %)**, **≈ 6.210 €**, **≈ 4,9 M tokens**, multiplicador ×5,8. Veredicto **go** por fases; quick wins de tier A primero, FI el último. |
