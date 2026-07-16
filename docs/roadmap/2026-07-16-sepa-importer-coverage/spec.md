# Spec — completar la cobertura SEPA de importadores (Italia + 17)

- **Fecha:** 2026-07-16
- **Iniciativa:** `2026-07-16-sepa-importer-coverage`
- **Estado:** borrador (pendiente de aprobación del usuario)
- **Evaluación:** [`evaluation.md`](evaluation.md) — presupuesto: **101 h base (121 h +20 %)** · **≈ 6.210 €** · ≈ 4,9 M tokens · 18 características · veredicto **go** por fases (estado: en-revisión 🔍).
- **Plan:** [`improvement-plan.md`](improvement-plan.md) · [`tasks.md`](tasks.md) (borrador · 5 fases · 21 tareas).
- **Base de evidencia:** [`research.md`](research.md) — 18 fichas verificadas en vivo + adversarialmente.
- **Depende de:** marco de importadores v1.2 ([`docs/importers.md`](../../importers.md)),
  disciplina de licencias ([`docs/licensing.md`](../../licensing.md)), metodología de datos factuales
  ([`docs/registry-authoring.md`](../../registry-authoring.md)).

## 1. Objetivo y no-objetivos

**Objetivo:** llevar la cobertura de resolución de bancos de **24/42** a hasta **41/42** países SEPA,
añadiendo importadores para los 18 restantes en la medida en que exista una fuente viable, respetando la
disciplina de "no empaquetar datos / fetch bajo demanda".

**No-objetivos:**
- No se toca la resolución BIC-first ni la validación (v2.1) salvo enriquecimiento opcional.
- No se persigue cobertura a nivel **sucursal** (todas las fuentes son a nivel banco; el fallback
  `findByBankCode(cc, bank, null)` ya lo cubre).
- No se fuerza DK (tier D) ni países no-SEPA (FO/GL comparten el bloqueo de DK).
- No se añade PhpSpreadsheet ni ningún lector pesado (se mantiene la postura del proyecto).

## 2. Qué es construible (de `research.md`)

| Tier | Países | Vía | Resultado de cobertura |
|:----:|--------|-----|------------------------|
| **A** (fetch en vivo) | CY, EE, ME, SE, **FR, MC** | XLSX / HTML / PSV / JSON | 24 → **30/42** |
| **B** (offline `--file`) | AD, MK, PT | PDF / `.xls`-`.docx` / PDF | → **33/42** |
| **C** (salvedades/curación) | AL, IT, LT, RS, SM, VA, IS (FI opcional) | HTML / PDF / curado | → hasta **41/42** |
| **D** (no viable) | DK (+ FO/GL) | — | documentar, no construir |

FR y MC comparten **una sola** fuente (REGAFI): las entidades de Mónaco están en el mismo dataset y
llevan CIB (§5 de `research.md`).

## 3. Decisiones de arquitectura (LO CLAVE — requieren tu visto bueno)

Cada una lleva una **recomendación**; están marcadas como "decisión abierta" en §8.

### D1 — Parser de tablas HTML (nuevo `Import/Support/HtmlTableReader`)
Lo necesitan **CY** (scrape de la landing para la URL con fecha), **EE**, **ME**, **IT** (y opcionalmente
SM). El paquete hoy lee CSV/fixed-width/XML/XLSX/JSON pero **no** HTML.
- **Recomendación: SÍ.** Añadir un helper pequeño y framework-free en `Import/Support/HtmlTableReader`
  basado en `DOMDocument` (`ext-dom`/`ext-libxml`, ya disponibles), con la misma forma que
  `XlsxReader`: devuelve la primera (o n-ésima) `<table>` como rejilla 0-indexada de strings; cada
  importador localiza su cabecera y columna por nombre. Se añade a las carpetas guardadas del test
  `CoreIsFrameworkFreeTest`. Riesgo: frágil ante rediseños web → documentar el CAVEAT por importador
  (igual que hacen los demás).

### D2 — Extracción de texto de PDF (PT, LT, RS, FI, AD)
El paquete evita dependencias pesadas a propósito. Tres opciones:
- (a) añadir un extractor PDF puro-PHP (p. ej. `smalot/pdfparser`);
- (b) `--file` donde el operador aporta el **texto ya extraído** (recetamos `pdftotext -layout`);
- (c) datos **curados** para los conjuntos diminutos.
- **Recomendación:** **no** meter un parser PDF en el core. Para **AD** (4 códigos) → dato curado
  (ver D4). Para **PT/LT/RS** → importadores `--file` que aceptan un **CSV/texto pre-extraído** por el
  operador (patrón NL `betaalvereniging`, que ya consume un CSV exportado a mano), documentando la
  receta `pdftotext -layout`. **FI** queda aparcado (requiere expansión de rangos a medida). Opción de
  futuro: `smalot/pdfparser` como dependencia **opcional** (`suggest`) que el importador use si está
  presente. Esto preserva la postura de "sin lectores pesados".

### D3 — `.xls` legacy / `.docx` (MK)
`XlsxReader` solo lee OOXML. El fichero autoritativo de MK es `.xls` BIFF o `.docx`, y además está tras
Cloudflare (offline sí o sí).
- **Recomendación:** **no** añadir lector BIFF. MK como importador `--file` que acepta un **CSV
  exportado** por el operador desde el `.xls`/`.docx` (patrón NL). Prioridad baja (roster de 2014,
  frescura dudosa → verificar al implementar).

### D4 — Política de datos **curados** (VA, AD, SM, IS, AL)
Estos micro-conjuntos no tienen fuente legible por máquina, pero sí un conjunto diminuto y estable. Choca
con la regla "no empaquetar datos".
- **Recomendación:** permitir **mapas curados, de autoría independiente y factual** para estos casos,
  siguiendo la metodología de [`docs/registry-authoring.md`](../../registry-authoring.md) (hechos, no
  copia de la fuente), con procedencia marcada como `curated`/hardcoded y refresco anual. Es coherente
  con que el **registro de estructuras ya empaqueta datos factuales autoría del proyecto**. Requiere una
  nota nueva en [`docs/licensing.md`](../../licensing.md) acotando esta excepción a micro-jurisdicciones
  sin fuente máquina. **Es un cambio de principio → decisión explícita del usuario.**

### D5 — Capa BIC (EPC) para fuentes name-only (FR, IT)
REGAFI (FR/MC) y AdE (IT) dan nombre pero **no** BIC.
- **Recomendación:** opcional y separada; no bloquea (el nombre ya resuelve). Se puede añadir después
  reutilizando el patrón EPC como enriquecimiento. Prioridad baja.

## 4. Fases propuestas

Cada fase es entregable de forma independiente (tests verdes, PHPStan L8, PSR-12) y suma cobertura.

### Fase 1 — Tier A (fetch en vivo). Cobertura 24 → 30/42
Depende de **D1**. Orden por esfuerzo creciente:
1. **SE** — `SwedenBankInfrastructureImporter`: fetch `source.psv` (PSV, MIT) de
   `raw.githubusercontent.com/Bankinfrastruktur/BankData`; columna `IbanId` (3 díg.) = `bank_code`,
   + BIC + nombre; de-duplicar por `IbanId`; **no** usar columnas de clearing. `--file` como hedge.
2. **FR + MC** — `RegafiImporter` (parametrizado por país, como `EpcRegisterImporter`): fetch JSON de
   la API ODS `prd-jdd-recherche` (o `prd-banque-entites`); **parsear el array `cib`** y expandir una
   fila por código (5 díg., zero-pad/validar); nombre (sin BIC); atribución Licence Ouverte. Registrado
   dos veces (FR, MC) — MC sale del mismo dataset filtrando por país.
3. **CY** — `CentralBankOfCyprusImporter`: GET a la landing IBAN, regex del href
   `CIs_and_EMIs_BICs_updated_*.xlsx`, fetch con **User-Agent de navegador** (WAF), leer con
   `XlsxReader`; columna "Bank identifiers used in IBAN" (3 díg.) → nombre + BIC.
4. **EE** — `EstonianBankingAssociationImporter`: `HtmlTableReader` sobre `pangaliit.ee` bank-codes;
   código 2 díg. → nombre + BIC; manejar dobles (Luminor 96/17) y cero inicial (TBB 00).
5. **ME** — `CentralBankOfMontenegroImporter`: `HtmlTableReader` sobre la página RTGS de CBCG; código
   3 díg. → nombre + BIC; **filtrar** entes públicos (714-931).

### Fase 2 — Tier B (offline `--file`). Cobertura → 33/42
Depende de la decisión **D2**(b)/**D3**.
6. **PT** — `BancoDePortugalImporter`: `--file` con texto/CSV pre-extraído del PDF SICOI; columna 4 díg.
   → nombre + BIC; limpieza de mojibake de acentos.
7. **AD** — `AndorranBankingImporter`: `--file` (texto del PDF) **o** dato curado (D4). 4 díg. → nombre;
   BIC curado aparte. Dado que son 3 bancos, la ruta curada es la recomendada.
8. **MK** — `NbrmImporter`: `--file` CSV exportado del `.xls`/`.docx`; código 3 díg. → nombre + BIC.
   Verificar frescura al implementar (roster 2014).

### Fase 3 — Tier C (salvedades / curación). Cobertura → hasta 41/42
Depende de **D1**, **D2**, **D4**.
9. **IT** — `AgenziaEntrateF24Importer`: `HtmlTableReader` sobre la página F24 `…-xcodice`; `Codice
   ABI` (**zero-pad a 5 díg.**) → nombre; **sin BIC**; parcial (~400 bancos F24). Documentar que el
   ABI/CAB canónico (SIA-Nexi) es de pago.
10. **SM** — `BcsmImporter` **o** dato curado (4 bancos, ABI 5 díg. → nombre + BIC). Curado recomendado.
11. **VA** — dato **curado** de 1 entrada (`001` → IOR / `IOPRVAVX`). Procedencia `curated`.
12. **IS** — dato **curado** a nivel banco (prefijos de "centenas": 01/03/05/07/11/15/22 → banco),
    resuelve banco (no sucursal); BIC curado.
13. **AL** — dato **curado** ~13 bancos (KIB 3 díg. → nombre + BIC) del Reglamento IBAN Anexo 4 +
    cross-check EPC/SWIFT.
14. **LT** — `LietuvosBankasImporter`: `--file` texto/CSV pre-extraído del PDF; 5 díg. → nombre + BIC.
    **Bloqueo de licencia**: confirmar términos con Lietuvos bankas antes de enriquecer (marca "LB
    INTERNAL").
15. **RS** — `NbsSerbiaImporter`: `--file` con los dos PDFs pre-extraídos; zip 19 nombres ↔ 19 códigos
    (layout desalineado); 3 díg. → nombre + BIC. Alternativa: mapa curado (~19).
16. **FI** *(opcional, si se prioriza)* — `FinanceFinlandImporter`: `--file` PDF + **mapeador de rangos
    a medida** (1-4 díg. → clave de 3 díg.). Esfuerzo L. Por defecto **queda aparcado**.

### No construir
- **DK** (+ FO/GL): documentar en `docs/importers.md` como tier D con el motivo (ninguna fuente abierta
  y legible; Finanstilsynet PDF 2011; registreringsnumre.dk de pago y prohíbe copia). Único camino
  teórico: `--file` del operador bajo su propio riesgo — **no** se implementa por defecto.

## 5. Diseño técnico compartido

- **`Import/Support/HtmlTableReader`** (nuevo, framework-free, `ext-dom`): `readTables(string $html): array`
  y/o `readFirstTable()` → rejilla 0-indexada de celdas string; helper `locateHeader()` por-importador
  (igual que XlsxReader). Añadir a `GUARDED_DIRECTORIES`/`GUARDED_FILES` del test de arquitectura.
- **`RegafiImporter`** parametrizado por país (constructor `new RegafiImporter('FR')` / `'MC'`),
  registrado dos veces en `ImporterRegistry::registerDefaults()` (patrón `EpcRegisterImporter`).
- **Importadores `--file`** (PT/MK/LT/RS y AD si no curado): mismo contrato `rows(?string $localFile)`;
  en modo live, o bien no fetchan (documentado) o intentan la URL conocida; el camino soportado es
  `--file`. Documentar la receta de extracción por país.
- **Datos curados** (VA/IS/AL/SM/AD): un importador cuyo `rows()` yield un array constante
  independientemente de `--file`, con `sourceId` propio y `license()` = "curated (factual, non-copyrightable)".
  Fichero de datos en `Import/Importers/data/<cc>.php` (autoría del proyecto, como el registro).
- **Registro:** ampliar `ImporterRegistry::registerDefaults()` con los nuevos importadores; `iban:update`
  los lista/ejecuta sin cambios de comando.
- **Provenance:** sin cambios; `ImportRunner` estampa `source_id`/`source_license`/`source_version` como
  hoy.

## 6. Testing

Siguiendo el patrón existente (`tests/` espeja `src/`, fixtures por importador):
- Un test por importador con un **fixture reducido** del formato real (HTML/PSV/JSON/CSV/texto-PDF),
  verificando el mapeo código→fila esperado, casos borde (zero-pad IT, dobles EE, filtrado de entes ME,
  de-dup SE).
- `HtmlTableReader`: tests unitarios con HTML representativo (tablas anidadas, celdas vacías, `<thead>`).
- Test de arquitectura: confirmar que `HtmlTableReader` y los nuevos importadores framework-free no
  importan `CodeIgniter\`.
- Actualizar los contadores de cobertura en `docs/importers.md` y su matriz.

## 7. Criterios de aceptación

- [ ] Cada importador entregado: test verde con fixture, PHPStan L8 limpio, PSR-12.
- [ ] `iban:update` lista los nuevos importadores; `--country=<cc>` los ejecuta.
- [ ] `resolve()` de una IBAN de ejemplo de cada país cubierto devuelve el banco esperado.
- [ ] `docs/importers.md`: matriz de cobertura y contadores actualizados; DK documentado como tier D.
- [ ] `docs/licensing.md`: nota de la excepción de datos curados (si se aprueba D4).
- [ ] Test de arquitectura sigue verde (nuevos ficheros framework-free correctamente clasificados).
- [ ] `CHANGELOG.md`: entrada de la nueva versión con el salto de cobertura SEPA.

## 8. Decisiones (resueltas 2026-07-16)

Decididas por el usuario tras revisar la investigación:

1. **D1 (parser HTML): APROBADO.** Se añade `Import/Support/HtmlTableReader` (framework-free,
   `ext-dom`). Imprescindible para CY/EE/ME/IT.
2. **D2 (PDF): `--file` con texto pre-extraído.** Sin dependencia nueva; el operador extrae con
   `pdftotext -layout` y pasa texto/CSV (patrón NL `betaalvereniging`). Aplica a PT/LT/RS/FI.
3. **D3 (`.xls`/`.docx` legacy, MK):** sin lector BIFF; MK como `--file` con CSV exportado por el
   operador. (Consecuencia de D2.)
4. **D4 (datos curados): APROBADO.** Se permiten mapas curados factuales para VA/AD/SM/IS/AL
   (autoría independiente, procedencia `curated`), con **nota nueva en `docs/licensing.md`** acotando
   la excepción a micro-jurisdicciones sin fuente legible por máquina.
5. **D5 (capa BIC/EPC para FR/IT):** diferida; opcional y no bloqueante.
6. **Alcance: hasta Fase 3 — TODO lo viable (hasta 41/42).** Incluye Italia y los conjuntos curados;
   **FI incluido** (Fase 3, esfuerzo L, con mapeador de rangos a medida). Único excluido: **DK**
   (+FO/GL), que se documenta como tier D.

### Pendiente (una decisión de proceso)

- **Ruta de planificación:** ¿generar el plan de implementación con el agente **`planner`** del
  proyecto (produce `improvement-plan.md` + `tasks.md` en esta carpeta, con estimación de
  coste/esfuerzo/tokens, coherente con el índice del roadmap), o con la skill **`writing-plans`** de
  superpowers? Recomendado: `planner` (nativo del roadmap). Opcionalmente, pasar antes por el agente
  `evaluator` para el presupuesto formal (`evaluation.md`).

## 9. Enlaces

- Investigación: [`research.md`](research.md)
- Marco de importadores: [`docs/importers.md`](../../importers.md)
- Licencias: [`docs/licensing.md`](../../licensing.md) · Autoría del registro:
  [`docs/registry-authoring.md`](../../registry-authoring.md)
