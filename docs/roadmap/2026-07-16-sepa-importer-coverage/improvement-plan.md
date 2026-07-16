# 2026-07-16-sepa-importer-coverage

> Completar la cobertura SEPA de importadores de resolución de bancos: de **24/42** a hasta **41/42** países (Italia + 17), con un lector de tablas HTML nuevo, importadores en vivo/`--file` y datos curados factuales — respetando la disciplina de «no empaquetar datos».

| | |
|---|---|
| **Fecha** | 2026-07-16 |
| **Estado** | borrador |
| **Tipo** | Nueva Funcionalidad / Infra |
| **Prioridad** | Alta 🟠 |
| **Solicitante** | daycry |
| **Responsable** | por asignar |
| **Spec** | [`spec.md`](spec.md) |
| **Evaluación** | [`evaluation.md`](evaluation.md) · fuentes: [`research.md`](research.md) |

---

## Cuadro de mando

| Métrica | Estimado | Real | Confianza |
|--------|---------|------|-----------|
| Tiempo humano | **121 h** (101 h base +20 %) | 0 h | Media |
| Tiempo IA (ejecución) | **16 h** (+ 5 h supervisión) | 0 h | Media |
| Coste total | **≈ 6.210 €** | 0 € | Media |
| Tokens IA | **≈ 4,9 M** base (in ≈ 3,9 M / out ≈ 1,0 M) | 0 | Baja |
| Multiplicador productividad | **×5,8** | — | — |
| Tareas | **21** | 0 hechas | — |

> Cobertura objetivo: **24 → 30/42** (Fase 1) → **33/42** (Fase 2) → **hasta 41/42** (Fase 3). DK (+FO/GL) queda fuera (tier D, solo documentar). El coste lo domina el trabajo humano (≈ 97,6 %); los tokens (≈ 150 € con margen) apenas mueven el total y su precio está **⚠️ por verificar**.

---

## Estimación por fase

Horas, tokens y coste **base** (sin margen +20 %). El margen se aplica en el presupuesto económico.

| Fase | Estimado (h) | Tokens (in / out) | Coste € |
|------|-------------|-------------------|---------|
| Fase 0 — Infra (`HtmlTableReader`) | 9 | 0,35 M / 0,09 M | ≈ 461 |
| Fase 1 — Tier A (fetch en vivo, → 30/42) | 26 | 1,05 M / 0,27 M | ≈ 1.334 |
| Fase 2 — Tier B (offline `--file`, → 33/42) | 14 | 0,50 M / 0,13 M | ≈ 716 |
| Fase 3 — Tier C (salvedades / curación, → hasta 41/42) | 46 | 1,76 M / 0,445 M | ≈ 2.355 |
| Fase 4 — Transversal (docs / licensing / registro / CHANGELOG) | 6 | 0,25 M / 0,09 M | ≈ 310 |
| **Total** | **101 h** | **≈ 3,91 M / ≈ 1,02 M** | **≈ 5.174 €** |

---

## Presupuesto económico

**Coste = (horas × tarifa) + coste de tokens de IA.** Todos los importes en **EUR**.

### Supuestos (ajustables)

| Parámetro | Valor | Nota |
|-----------|-------|------|
| Tarifa de desarrollo | 50 €/h | Default del kit; sin tarifa definida en el proyecto. |
| Modelo IA asumido | claude-opus-4-8 | Base de la previsión de tokens. |
| Precio input | ≈ 13,80 € / 1M | ⚠️ **verificar**: asume clase Opus ≈ 15 USD/1M × 0,92. |
| Precio output | ≈ 69,00 € / 1M | ⚠️ **verificar**: asume clase Opus ≈ 75 USD/1M × 0,92. |
| Tipo de cambio | 1 USD = 0,92 € | Aplicado al precio de tokens. |
| Ratio de supervisión | ~30 % de horas IA | Elevado sobre el default (25 %) por el peso de verificación de datos/licencias/frescura. |
| Horas por FTE-mes | 160 h | Base del cálculo de FTE. |
| Margen de contingencia | +20 % | Sobre horas base (humanas **e** IA); coste recalculado desde las horas con margen. |

### Desglose

| Concepto | Cálculo | Importe |
|----------|---------|---------|
| Desarrollo (humano, base) | 101 h × 50 €/h | 5.050 € |
| Margen de contingencia (humano) | +20 % sobre 5.050 € | +1.010 € |
| Tokens IA (input, con margen) | ≈ 4,69 M tok × 13,80 €/1M | ≈ 64,72 € |
| Tokens IA (output, con margen) | ≈ 1,24 M tok × 69,00 €/1M | ≈ 85,56 € |
| **Total estimado (con margen)** | | **≈ 6.210 €** |

> Base sin margen: ≈ 5.174 € (5.050 € humano + ≈ 124 € tokens). El trabajo humano supone ≈ 97,6 % del coste; el precio de tokens (⚠️ verificar) apenas mueve el total.

---

## Previsión de tokens (por fase)

Estimación del consumo del modelo por fase. Base: claude-opus-4-8 · precios de la tabla de supuestos (base, sin margen).

| Fase | Input (tok) | Output (tok) | Total (tok) | Coste € |
|------|------------|-------------|-------------|---------|
| Fase 0 — Infra | 0,35 M | 0,09 M | 0,44 M | ≈ 11 |
| Fase 1 — Tier A | 1,05 M | 0,27 M | 1,32 M | ≈ 33 |
| Fase 2 — Tier B | 0,50 M | 0,13 M | 0,63 M | ≈ 16 |
| Fase 3 — Tier C | 1,76 M | 0,445 M | 2,205 M | ≈ 55 |
| Fase 4 — Transversal | 0,25 M | 0,09 M | 0,34 M | ≈ 10 |
| **Total** | **≈ 3,91 M** | **≈ 1,02 M** | **≈ 4,94 M** | **≈ 124 €** |

**Método de estimación:** por importador = lectura del fixture de referencia + la clase plantilla más cercana del catálogo (≈ 85-205 LOC) + generación de la clase, su test con fixture reducido y la línea de `registerDefaults()`; escalado por la fragilidad del formato (PDF `--file` y mapeador de rangos consumen más iteraciones de depuración que un CSV/PSV limpio). Calibrado contra `XlsxReader` (333 LOC) para `HtmlTableReader`.

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

> Horas mostradas **con el margen de contingencia (+20 %)** ya aplicado (base: 101 h humanas · 13 h IA · 4 h supervisión ≈ 17 h totales → mismo ×5,9). El multiplicador es **menor que en iniciativas de código puro** (p. ej. ×7,6 en `daycry-iban-v1`) porque una parte grande del esfuerzo es **verificación de datos curados, confirmación de licencias y frescura, y validación de scrapes/PDFs frágiles** — trabajo que exige criterio humano. Los importadores patronizados (SE, FR+MC, curados) son muy eficientes con IA; FI/LT/RS/AL concentran la carga humana.

---

## Resumen ejecutivo

Se implementa una spec cerrada (decisiones §8 aprobadas) para **completar la cobertura SEPA** de resolución de bancos de **24/42 a hasta 41/42** países, añadiendo importadores para los 17 restantes viables (todos menos DK). El marco de importadores (v1.2: `ImporterInterface`, `ImporterRegistry`, `ImportRunner`, `XlsxReader`) **ya existe** con 30 importadores como plantilla, por lo que el trabajo es **incremental y patronizado**: cada país = una clase framework-free + su test con fixture (TDD) + una línea en `registerDefaults()` + su fila en la matriz de `docs/importers.md`. La única pieza de infraestructura nueva es `Import/Support/HtmlTableReader` (framework-free, `ext-dom`), prerequisito de CY/EE/ME/IT. El plan se estructura en **5 fases** (una infra + 3 por tier de viabilidad + 1 transversal), con **21 tareas**, empezando por los **quick wins de tier A** y dejando **FI el último** (mayor coste/menor confianza) y **LT condicionado** a confirmar su licencia.

### Objetivos

- **Subir la cobertura de resolución SEPA** de 24/42 a **hasta 41/42** países, verificable con `resolve()` sobre una IBAN de ejemplo de cada país.
- **Añadir el lector `HtmlTableReader`** framework-free reutilizable (CY/EE/ME/IT) sin dependencias pesadas nuevas (solo `ext-dom`, ya disponible).
- **Mantener la disciplina del proyecto**: framework-free donde corresponda (test `CoreIsFrameworkFreeTest` verde), TDD, PHPStan L8, PSR-12, y una **excepción de datos curados** acotada y documentada en `docs/licensing.md`.
- **Documentar DK (+FO/GL)** como tier D (no construible) con el motivo, y dejar la matriz de cobertura y el `CHANGELOG` actualizados.

---

## Datos necesarios para un informe completo

- [x] **Requisitos funcionales** confirmados por el solicitante — *spec cerrada (§8) + `research.md` (18 fichas verificadas en vivo).*
- [x] **Alcance** cerrado — *«hasta Fase 3» (17 países); DK fuera (tier D, solo documentar).*
- [x] **Criterios de éxito / métricas** acordados — *spec §7: test verde con fixture, PHPStan L8, PSR-12, `resolve()` de una IBAN de ejemplo devuelve el banco esperado.*
- [x] **Accesos y credenciales** — *no aplica; fuentes públicas por país (research §6); ninguna dependencia dura de runtime nueva.*
- [x] **Entornos** — *local; el patrón `--file` cubre las fuentes con URL rotatoria / WAF / Cloudflare.*
- [x] **Stakeholders** identificados — *daycry (mantenedor).* 
- [x] **Dependencias externas** mapeadas — *fuentes oficiales por país (research §6).* 
- [x] **Restricciones** conocidas — *sin lectores pesados nuevos; disciplina de licencias; patrón repo (framework-free + test).* 
- [ ] **Tarifa/hora y precio de tokens confirmados** — *se asumen defaults; precio de tokens ⚠️ por verificar.*
- [ ] **Licencia de LT confirmada** — *bloqueante para el enriquecimiento de T-16 (no para su entrega como `--file`).*

---

## Análisis de impacto

- **`src/Import/Support/HtmlTableReader.php`** (nuevo) — lector de tablas HTML framework-free (`DOMDocument`, `ext-dom`/`ext-libxml`), forma de `XlsxReader`: `readTables()` / `readFirstTable()` → rejilla 0-indexada de strings + `locateHeader()` por importador.
- **`src/Import/Importers/`** — 13 clases de importador nuevas (SE, `RegafiImporter` FR+MC, CY, EE, ME, PT, MK, IT, VA, SM, IS, AL, LT, RS, FI; los curados VA/SM/IS/AL/AD también aquí). Todas framework-free.
- **`src/Import/Importers/data/`** (nuevo subdirectorio) — mapas curados factuales por país: `ad.php`, `sm.php`, `va.php`, `is.php`, `al.php` (autoría del proyecto, procedencia `curated`).
- **`src/Import/ImporterRegistry.php`** — ampliar `registerDefaults()` (18 registros nuevos: 17 países + MC compartiendo `RegafiImporter`) y su docblock narrativo.
- **`tests/Import/Support/`, `tests/Import/Importers/`** — un test por pieza con fixture reducido (HTML/PSV/JSON/CSV/texto-PDF).
- **`tests/Architecture/CoreIsFrameworkFreeTest.php`** — sin cambios de lista: `Import/Support` e `Import/Importers` **ya** están en `GUARDED_DIRECTORIES` (se escanean recursivamente); solo verificar que sigue verde.
- **`docs/importers.md`, `docs/licensing.md`, `CHANGELOG.md`** — matriz/contadores de cobertura, nota de datos curados (D4), DK tier D, entrada de versión.

---

## Cambios arquitectónicos

- **D1 — `HtmlTableReader` (APROBADO).** Nuevo helper framework-free en `Import/Support/` basado en `DOMDocument`, mismo contrato que `XlsxReader` (rejilla 0-indexada + `locateHeader` por nombre). Al vivir en el subdirectorio ya guardado `Import/Support`, queda cubierto por el test de arquitectura sin tocar `GUARDED_DIRECTORIES`.
- **D2 — PDF vía `--file` (APROBADO).** Sin parser PDF en el core; el operador extrae con `pdftotext -layout` y aporta texto/CSV (patrón `betaalvereniging`). Aplica a PT/LT/RS/FI.
- **D3 — sin lector `.xls`/`.docx` legacy (APROBADO).** MK como `--file` con CSV exportado por el operador (Cloudflare impide fetch).
- **D4 — datos curados (APROBADO).** Mapas curados factuales de autoría independiente para VA/AD/SM/IS/AL (`Import/Importers/data/<cc>.php`), procedencia `curated`, refresco anual, con **nota nueva en `docs/licensing.md`** acotando la excepción a micro-jurisdicciones sin fuente legible por máquina.
- **D5 — capa BIC/EPC para FR/IT (DIFERIDA).** Opcional y no bloqueante; el nombre ya resuelve.
- **`RegafiImporter` parametrizado por país** (patrón `EpcRegisterImporter`): `new RegafiImporter('FR')` / `'MC'`, registrado dos veces; parsea el array JSON `cib` y expande una fila por código (5 díg., zero-pad/validar).

---

## Archivos a crear/modificar

| Archivo | Acción | Propósito |
|---------|--------|-----------|
| `src/Import/Support/HtmlTableReader.php` | Crear | Lector de tablas HTML framework-free (`ext-dom`). |
| `src/Import/Importers/SwedenBankInfrastructureImporter.php` | Crear | SE — PSV (MIT), columna `IbanId`, de-dup. |
| `src/Import/Importers/RegafiImporter.php` | Crear | FR + MC — JSON ODS, array `cib` expandido; parametrizado por país. |
| `src/Import/Importers/CentralBankOfCyprusImporter.php` | Crear | CY — landing scrape + XLSX + User-Agent WAF. |
| `src/Import/Importers/EstonianBankingAssociationImporter.php` | Crear | EE — HTML (2 díg., dobles Luminor, TBB 00). |
| `src/Import/Importers/CentralBankOfMontenegroImporter.php` | Crear | ME — HTML RTGS (3 díg., filtro entes 714-931). |
| `src/Import/Importers/BancoDePortugalImporter.php` | Crear | PT — `--file` texto/CSV del PDF SICOI (4 díg.). |
| `src/Import/Importers/AndorranBankingImporter.php` | Crear | AD — curado (4 códigos) + `data/ad.php`. |
| `src/Import/Importers/NbrmImporter.php` | Crear | MK — `--file` CSV del `.xls`/`.docx` (3 díg.). |
| `src/Import/Importers/AgenziaEntrateF24Importer.php` | Crear | IT — HTML F24, `Codice ABI` zero-pad 5 díg., sin BIC. |
| `src/Import/Importers/BcsmImporter.php` | Crear | SM — curado (4 bancos ABI) + `data/sm.php`. |
| `src/Import/Importers/VaticanImporter.php` | Crear | VA — curado (1 entrada IOR/`IOPRVAVX`) + `data/va.php`. |
| `src/Import/Importers/IcelandBankPrefixImporter.php` | Crear | IS — curado a nivel banco (prefijos centenas) + `data/is.php`. |
| `src/Import/Importers/BankOfAlbaniaImporter.php` | Crear | AL — curado ~13 bancos (KIB) + `data/al.php`. |
| `src/Import/Importers/NbsSerbiaImporter.php` | Crear | RS — `--file` 2 PDFs, zip 19↔19 (3 díg.). |
| `src/Import/Importers/LietuvosBankasImporter.php` | Crear | LT — `--file` PDF (5 díg.) · **bloqueado por licencia**. |
| `src/Import/Importers/FinanceFinlandImporter.php` | Crear | FI — `--file` PDF + mapeador de rangos 1-4→3 díg. |
| `src/Import/Importers/data/{ad,sm,va,is,al}.php` | Crear | Mapas curados factuales (procedencia `curated`). |
| `src/Import/ImporterRegistry.php` | Modificar | `registerDefaults()` (18 registros nuevos) + docblock. |
| `tests/Import/Support/HtmlTableReaderTest.php` | Crear | Tests unitarios del lector HTML. |
| `tests/Import/Importers/*Test.php` | Crear | Un test con fixture reducido por importador. |
| `docs/importers.md` | Modificar | Matriz/contadores de cobertura, recetas `--file`, DK tier D. |
| `docs/licensing.md` | Modificar | Nota de la excepción de datos curados (D4). |
| `CHANGELOG.md` | Modificar | Entrada de versión con el salto de cobertura SEPA. |

> Los nombres de clase son orientativos (siguen el patrón del catálogo); el implementador puede ajustarlos, pero cada uno debe registrarse en `registerDefaults()` con su par `(countryCode, sourceId)`.

---

## Dependencias y prerequisitos

- **`HtmlTableReader` (T-01, Fase 0) es prerequisito** de EE (T-04), ME (T-05), IT (T-13) y del scrape de la landing de CY (T-06, opcional — puede resolverse con regex del href).
- **Datos curados (AD/VA/SM/IS/AL)** dependen de la política **D4** (aprobada) y de la **nota de `licensing.md` (T-18)**; se recomienda **adelantar T-18** al inicio de la Fase 2 para desbloquearlos.
- **`RegafiImporter` (T-03)** cubre FR **y** MC con una sola clase (dos registros) — sin dependencia previa.
- **LT (T-16) está BLOQUEADO** hasta confirmar los términos de licencia con Lietuvos bankas (marca «LB INTERNAL»): se puede desarrollar la clase `--file`, pero **no publicar el enriquecimiento** hasta la confirmación. No bloquea el resto.
- **La matriz de cobertura y el `CHANGELOG` (T-19, T-21)** se cierran al final, con el set de importadores estable.
- Todos los importadores `--file` (PT/MK/LT/RS/FI) reutilizan el patrón existente (`betaalvereniging`) — sin infraestructura nueva.

---

## Criterios de aceptación (global)

- [ ] Cada importador entregado: **test verde con fixture reducido** (TDD), **PHPStan L8 limpio**, **PSR-12**.
- [ ] `resolve()` de una IBAN de ejemplo de **cada país cubierto** devuelve el banco esperado.
- [ ] `iban:update` **lista** los nuevos importadores; `iban:update --country=<cc>` los ejecuta.
- [ ] `HtmlTableReader` con tests unitarios representativos (tablas anidadas, `<thead>`, celdas vacías); framework-free.
- [ ] Test `CoreIsFrameworkFreeTest` **sigue verde** (nuevos ficheros framework-free correctamente clasificados; sin `use CodeIgniter\...` en `Import/Support` ni `Import/Importers`).
- [ ] `docs/importers.md`: matriz y contadores actualizados (24 → 30 → 33 → hasta 41/42); **DK documentado como tier D**.
- [ ] `docs/licensing.md`: **nota de la excepción de datos curados** (D4) acotada a micro-jurisdicciones.
- [ ] `CHANGELOG.md`: entrada de la nueva versión con el salto de cobertura SEPA.
- [ ] La suite completa (`composer test`, `composer analyze`, `composer cs`) sigue verde.

---

## Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| Licencia de LT sin confirmar («LB INTERNAL») bloquea el enriquecimiento (T-16) | Media | Alto | Entregar como `--file` (no fetch por defecto); **confirmar términos** antes de publicar; congelar T-16 si no hay confirmación (no bloquea el resto). |
| Fragilidad de scrapes HTML (CY/EE/ME/IT) ante rediseños web | Media | Medio | `HtmlTableReader` robusto + tests representativos; CAVEAT documentado por importador; `--file` como hedge donde aplique. |
| Frescura de datos (MK roster 2014; curados VA/AD/SM/IS/AL) | Media | Medio | Verificar frescura al implementar (MK, T-09); refresco **anual** documentado; procedencia `curated` marcada. |
| Desviación de la regla «no empaquetar datos» con los curados | Baja | Medio (reputacional) | Argumento «hechos no protegibles» + metodología `registry-authoring.md`; **nota acotada en `licensing.md`** (T-18). |
| Mapeador de rangos de FI incorrecto (1-4 → 3 díg.; 4 díg. no caben) | Media | Medio | Modelar rangos→clave 3 díg. con tests exhaustivos; documentar la pérdida de códigos de 4 díg.; FI el último (T-17). |
| Layout desalineado de RS (zip 19↔19 frágil) | Media | Medio | Cross-check contra mapa curado ~19 como red de seguridad; considerar degradar a curado. |
| Fuga de acoplamiento CI4 en ficheros framework-free | Baja | Alto | `Import/Support` e `Import/Importers` ya en `GUARDED_DIRECTORIES`; `CoreIsFrameworkFreeTest` lo detecta automáticamente. |
| Precio de tokens asumido incorrecto | Media | Bajo | Cálculo parametrizado; tokens ≈ 2,4 % del total → impacto despreciable. |

---

## Métricas de éxito

- **Cobertura SEPA**: de 24/42 a **hasta 41/42** países que resuelven banco vía `resolve()` (medido con una IBAN de ejemplo por país en los tests).
- **Calidad**: suite verde (`composer test`), PHPStan L8 sin baseline nuevo, PSR-12 limpio, `CoreIsFrameworkFreeTest` verde.
- **Trazabilidad**: cada importador con su fixture y su fila en la matriz de `docs/importers.md`; `iban:update` los lista todos.
- **Disciplina de datos**: la nota de `licensing.md` acota la excepción de curados; DK documentado como tier D con su motivo.

---

## Changelog

| Fecha | Cambio |
|---|---|
| 2026-07-16 | Plan inicial (borrador) a partir de `spec.md` (§8 cerrada) + `evaluation.md`. 5 fases · 21 tareas · **121 h (101 h base +20 %)** · **≈ 6.210 €** · ≈ 4,9 M tokens · ×5,8. Orden: infra → Tier A (quick wins) → Tier B → Tier C (FI el último, LT condicionado a licencia) → transversal. |
