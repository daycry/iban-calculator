# Investigación de fuentes — importadores SEPA restantes (Italia + 17)

- **Fecha:** 2026-07-16
- **Iniciativa:** `2026-07-16-sepa-importer-coverage`
- **Tipo:** informe de descubrimiento de fuentes (previo al spec). No implementa código.
- **Alcance:** los 18 países SEPA que aún **no** resuelven vía un importador incluido (24/42 ya cubiertos).

## 1. Contexto y objetivo

El paquete resuelve hoy 24 de los 42 países del esquema SEPA. Los 18 que faltan son
**Italia + los 17 restantes**, y son exactamente los que la investigación de v1.2 aparcó a
propósito (buckets *paywalled* / *PDF-only* / *portal-HTML* / *sin directorio* en
[`docs/importers.md`](../../importers.md#coverage-matrix)).

Objetivo de esta pasada: para **cada** país, determinar si hoy (2026-07) existe una fuente oficial
que permita mapear el `bank_code` numérico de la IBAN → banco (nombre y/o BIC), con qué formato,
licencia y viabilidad real, verificándolo **en vivo** (descarga/inspección del fichero real) y con
**verificación adversarial** de cada hallazgo. El resultado alimenta el `spec.md` que decide qué se
construye.

### Hallazgo estructural que enmarca todo

Los 18 países restantes tienen **todos** un `bank_code` **numérico nacional** (no un prefijo BIC):

| Longitud | Países |
|---|---|
| 2 díg. | EE |
| 3 díg. | AL, CY, FI, ME, MK, RS, SE, VA |
| 4 díg. | AD, DK, IS, PT |
| 5 díg. | FR, IT, LT, MC, SM |

Consecuencia: **no queda ninguna "victoria fácil" vía el importador EPC**. Los 5 países que resolvió
el EPC (GB/GI/IE/LV/RO) eran los únicos cuyo `bank_code` *es* el prefijo de 4 letras del BIC. Para
todos estos 18, el registro EPC solo aporta BIC + alcanzabilidad SEPA, nunca el código numérico
nacional, así que cada uno necesita un **directorio nacional** propio. Confirmado país por país abajo.

## 2. Metodología

Workflow de 36 agentes (18 investigación + 18 verificación adversarial), en modo exhaustivo:

1. **Investigación por país** — localizar la fuente oficial (banco central / asociación bancaria /
   regulador / portal de datos abiertos) e **intentar descargar/inspeccionar el fichero real** para
   confirmar formato, columnas, coincidencia del código con las posiciones/longitud del `bank_code`,
   licencia, frescura y alcanzabilidad hoy.
2. **Verificación adversarial** — un segundo agente intenta **refutar** cada afirmación de
   "construible" (¿fichero realmente alcanzable sin login/API-key/pago? ¿la columna de código *es* el
   `bank_code` de la IBAN? ¿la licencia permite fetch+enriquecimiento? ¿está mantenida?), o **buscar
   una fuente** que se le escapara a los marcados "no viable".

Datos crudos completos (18 fichas + 36 veredictos): `research-raw.json` (adjunto al workflow, no
versionado). Métricas del run: 36 agentes, 460 llamadas a herramientas, ~1,76 M tokens.

### Tiers de viabilidad

- **A — Construible ahora (fetch en vivo):** fuente abierta, legible por máquina, código casa,
  licencia OK, alcanzable en vivo.
- **B — Construible offline (`--file`):** fuente válida pero URL rotatoria / bloqueo de bots / formato
  que exige descarga manual (patrón LU/UA/KZ ya existente).
- **C — Construible con salvedades:** requiere scrape HTML / extracción PDF / conjunto curado a mano /
  herencia de sistema, o solo aporta BIC+reachability.
- **D — No viable:** de pago real, PDF sin tabla extraíble, o sin directorio. Se documenta el motivo.

## 3. Matriz-resumen (veredicto tras verificación adversarial)

| País | `bank_code` | Tier | Habilita | Esf. | Fuente elegida | Formato | Licencia | Salvedad principal |
|------|-------------|:----:|----------|:----:|----------------|---------|----------|--------------------|
| 🇨🇾 CY | 3 díg. | **A** | nombre+BIC | M | Central Bank of Cyprus `CIs_and_EMIs_BICs_*.xlsx` | XLSX (XlsxReader ✓) | Atribución (OK) | URL con fecha rotatoria + WAF exige User-Agent |
| 🇪🇪 EE | 2 díg. | **A** | nombre+BIC | S | Eesti Pangaliit `bank-codes` (+ FSA) | HTML-tabla | Sin licencia explícita (factual) | Nuevo patrón: scraping HTML |
| 🇲🇪 ME | 3 díg. | **A** | nombre+BIC | M | CBCG página RTGS (tabla participantes) | HTML-tabla | Factual banco central (OK) | Scraping HTML; mezcla bancos y entes públicos |
| 🇸🇪 SE | 3 díg. | **A** | nombre+BIC | S | `Bankinfrastruktur/BankData` `source.psv` | PSV | **MIT** (OK) | Mirror comunitario (oficial BSAB es PDF/DOCX, tier D) |
| 🇫🇷 FR | 5 díg. | **A** | nombre+LEI | M | REGAFI (ACPR/BdF) API Opendatasoft | CSV/JSON | Licence Ouverte/CRPA (atribución) | Sin BIC (capa EPC aparte); **cubre también MC** |
| 🇦🇩 AD | 4 díg. | **B** | nombre | S | Andorran Banking "Codificació…IBAN" | PDF fixed-width | Sin licencia explícita | 3 bancos/4 códigos → mejor set curado; URL rotatoria |
| 🇲🇰 MK | 3 díg. | **B** | nombre+BIC | M | NBRM "Lista vodečki broevi" | `.xls`/`.docx` | Público, sin licencia explícita | Cloudflare bloquea (→ `--file`); `.xls` legacy (no XlsxReader); roster 2014 |
| 🇵🇹 PT | 4 díg. | **B** | nombre+BIC | M | Banco de Portugal SICOI "BIC…IBAN" | PDF (capa texto) | Atribución (OK) | Landing bloquea bots + URL rotatoria (→ `--file`); requiere extracción PDF |
| 🇦🇱 AL | 3 díg. | **C** | nombre+BIC | M | BoA Reglamento IBAN Anexo 4 + EPC | PDF legal / — | Sin licencia; dominio bloquea bots | Sin fuente máquina; **curar ~13 bancos** a mano |
| 🇫🇮 FI | 3 díg. | **C** | nombre+BIC | L | Finanssiala "rahalaitostunnukset ja BIC" | PDF | Sin licencia explícita | Código longitud variable 1-4 díg. ≠ 3 díg. fijo → expansión de rangos a medida |
| 🇮🇸 IS | 4 díg. | **C** | nombre (BIC) | M | (RB no publica) → prefijos por banco | — | — | Sin directorio abierto; **mapa curado a nivel banco** (no sucursal) |
| 🇮🇹 IT | 5 díg. (ABI) | **C** | nombre | M | Agenzia delle Entrate F24 "banche convenzionate" | HTML-tabla | Sin licencia explícita | Scrape HTML; parcial (~400 bancos F24); sin BIC; ABI sin ceros→zero-pad; canónica ABI/CAB de pago (D) |
| 🇱🇹 LT | 5 díg. | **C** | nombre+BIC | L | Lietuvos bankas directorio FI | PDF | Marca "LB INTERNAL"; licencia sin confirmar | Extracción PDF; URL rotatoria + índice bloqueado (→ `--file`) |
| 🇲🇨 MC | 5 díg. (CIB) | **A** | nombre (REGAFI) / +BIC (AMAF) | S | **REGAFI** (compartida con FR; MC incluido) · AMAF para BIC | CSV/JSON (+HTML) | Licence Ouverte/CRPA · AMAF sin licencia | Resuelve desde el importador FR (Mónaco lleva CIB en REGAFI); AMAF/EPC solo para BIC |
| 🇷🇸 RS | 3 díg. | **C** | nombre+BIC | M | NBS 2 PDFs (`pregled_racuna` + `id_brojevi`) | PDF | Público, sin licencia explícita | Extracción PDF; layout desalineado (zip 19 nombres↔códigos) |
| 🇸🇲 SM | 5 díg. (ABI) | **C** | nombre+BIC | S | BCSM "Operating Banks" | HTML-tabla | Factual banco central | Solo 4 bancos → **set curado**; directorios ABI italianos NO cubren SM |
| 🇻🇦 VA | 3 díg. | **C** | nombre+BIC | S | (sin directorio) → **1 entrada curada** | — | Hecho único (OK) | Universo real = 1 banco (IOR, `001`/IOPRVAVX) |
| 🇩🇰 DK | 4 díg. | **D** | — | L | (ninguna abierta y legible) | — | Todas cerradas/de pago/sin licencia | Finanstilsynet PDF 2011; registreringsnumre.dk (Mastercard, de pago + prohíbe copia); afecta también FO/GL |

**Distribución:** 6×A · 3×B · 8×C · 1×D. Muy por encima de lo que sugerían los buckets "no viable" de
v1.2 (que daban por perdidos casi todos). FR y MC confirmados vía una única fuente (REGAFI), ver §5.

## 4. Hallazgos transversales (para el spec)

Estos cortan a través de varios países y son las decisiones de arquitectura clave:

1. **Capacidad nueva: parseo de tablas HTML.** La necesitan CY (scrape de la landing para la URL con
   fecha), EE, ME, IT, MC, SM. El paquete hoy solo lee CSV/fixed-width/XML/XLSX/JSON — no hay parser
   HTML. Es una capacidad compartida nueva y significativa (DOMDocument), estructuralmente frágil ante
   rediseños de web.
2. **Capacidad nueva: extracción de texto de PDF.** La necesitan PT, LT, RS, FI (y AD). El paquete
   **deliberadamente no** incluye lector PDF. Decisión de fondo: (a) añadir una dependencia ligera
   PDF→texto, (b) exigir `--file` con texto pre-extraído por el operador, o (c) sets curados para los
   países diminutos. La investigación se inclina por `--file` offline + extracción del operador, o
   sets curados donde el conjunto es pequeño.
3. **Capacidad nueva: lectura de `.xls` legacy (BIFF).** La necesita MK (fichero autoritativo es `.xls`
   OLE2 / `.docx`), y la edición más nueva de CY también es `.xls` (por eso CY usa la `.xlsx`).
   `XlsxReader` solo lee OOXML (`.xlsx`), no BIFF.
4. **Conjuntos micro curados vs. la regla "no empaquetar datos".** VA (1 entrada), AD (~4 códigos),
   SM (4 bancos), IS (nivel banco ~7 prefijos) y AL (~13 bancos) no tienen fuente legible por máquina
   pero sí un conjunto diminuto y estable. Se pueden **curar a mano** como datos factuales
   (metodología ya usada por el registro de estructuras, ver
   [`docs/registry-authoring.md`](../../registry-authoring.md)). **Tensión de diseño:** el paquete
   presume de "no empaquetar ningún dato bancario"; curar nombres/BIC sería una desviación (aunque
   defendible: son hechos no protegibles por copyright). El spec debe decidir esta política
   explícitamente.
5. **Herencia de sistema.** MC usa el CIB francés → **si FR (REGAFI) es tier A, MC resuelve desde la
   misma fuente** (REGAFI cubre "établissements situés en France ou à Monaco"): un solo importador
   sirve a ambos. SM usa el ABI italiano, **pero** los directorios ABI italianos **no** listan bancos
   sammarinesi → SM necesita su propia fuente (BCSM), no reutilizar IT.
6. **Salvedades de licencia.** Muchas fuentes no declaran licencia abierta (EE, IT, MC, LT, AD, SM,
   RS, MK, VA, AL, FI). Para importadores de *fetch bajo demanda* esto lo cubre la disciplina del
   paquete (no se empaqueta el dato). Para los conjuntos curados (VA/AD/SM/IS/AL) hay que apoyarse en
   el argumento "hechos no protegibles" y documentarlo.
7. **DK bloquea FO/GL.** El sistema `registreringsnummer` danés lo comparten Islas Feroe (FO) y
   Groenlandia (GL); al no ser DK viable, esos dos tampoco (y además son no-SEPA).

## 5. FR + MC: una sola fuente (REGAFI) — confirmado

El resultado más importante **anula el veredicto "paywalled"** de FR: el portal REGAFI
(ACPR/Banque de France) se reconstruyó sobre un backend Opendatasoft cuya API Explore v2.1 expone el
mapeo CIB→banco de forma abierta y sin autenticación. El `FIB` (fichero canónico CIB+BIC de la Banque
de France) sigue siendo de suscripción (tier D), pero ya no es la única vía.

La verificación **adversarial independiente** (relanzada aparte tras fallar 1 de 36 agentes del
workflow) **confirmó tier A** fetcheando la API ella misma:
- Alcanzable sin login/clave/pago; catálogo con 17 datasets; export CSV `cib;denomination;lei;categorie`.
- Cifras exactas confirmadas: `records_count = 25.442`, `data_processed = 2026-07-14`; filtro
  `where=cib like "code"` → `total_count = 822`. CIBs de ejemplo verificados (15208 Crédit municipal de
  Paris, 24659 Banque Chabrières, 16607 Banque populaire du Sud).
- Licencia: `license=null` en metadatos ODS, pero REGAFI se publica en data.gouv.fr bajo **Licence
  Ouverte** (reutilizable citando fuente + fecha) → atribución obligatoria. `licenseOkConfirmed=true`.
- **Bonus decisivo:** **Mónaco está en el MISMO dataset y sus entidades llevan CIB** (Crédit mobilier
  de Monaco 10160, Barclays Monaco 12448). **Una sola fuente resuelve FR y MC** → MC sube a tier A y
  comparte importador con FR.

Matiz de formato (no cambia el tier): el campo `cib` es un **array JSON serializado**, no un escalar —
`[{"code":"15208","date":"…"}]` en `prd-banque-entites`, `["24659"]` en `prd-jdd-recherche`, `"[]"`
si vacío; una entidad puede tener varios CIB. El importador debe **parsear el JSON y expandir** una
fila por código (validar/zero-pad a 5 díg.), no leerlo como número.

## 6. Fichas por país

Cada ficha: `bank_code` (del registro) · tier (revisado) · qué habilita · esfuerzo · fuente(s)
verificadas · resumen del veredicto adversarial · citas.

### 🇨🇾 CY — Chipre — Tier A
- `bank_code`: 3 díg. numéricos (IBAN pos. 5-7). Habilita nombre+BIC. Esfuerzo M.
- **Fuente:** Central Bank of Cyprus, `CIs_and_EMIs_BICs_updated_April_2025.xlsx`. Columna literal
  *"Bank identifiers used in IBAN"* (001-129 instituciones de crédito, 901-912 EMIs) → nombre + BIC de
  8 car. **Legible por `XlsxReader`** (OOXML). Licencia CBC Terms of Use: uso libre con atribución (OK).
- **Verificado (confirmado A):** descargado vía curl (HTTP 200); WebFetch daba 403 (WAF) → el importador
  debe enviar User-Agent de navegador. `sharedStrings.xml` leído directamente confirma el header y los
  códigos de 3 díg. + BIC. La edición `.xls` de mayo-2026 es más nueva pero BIFF legacy (XlsxReader no
  la lee) → preferir la `.xlsx`. La `BIC from IBAN.pdf` (2014) del hallazgo previo "PDF-only" queda
  **superada**.
- **Implementación:** GET a la landing estable y regex del href `CIs_and_EMIs_BICs_updated_*.xlsx`
  (nombre con fecha rotatoria) + User-Agent.
- Citas: `centralbank.cy/.../CIs_and_EMIs_BICs_updated_April_2025.xlsx`,
  `centralbank.cy/en/financial-market-infrastructures-payments/international-bank-account-number-iban`.

### 🇪🇪 EE — Estonia — Tier A
- `bank_code`: 2 díg. numéricos (IBAN pos. 5-6). Habilita nombre+BIC. Esfuerzo S.
- **Fuente:** Eesti Pangaliit (asociación bancaria) `settlements-and-standards/bank-codes` — tabla HTML
  con código + nombre + BIC juntos (Swedbank 22/HABAEE2X, SEB 10/EEUHEE2X, LHV 77/LHVBEE22,
  Luminor 96 y 17/RIKOEE22, TBB 00/TABUEE22…). Contrastable con el registro **autoritativo** del FSA
  (Finantsinspektsioon, fi.ee, editado 25/08/2025) que define el código como "los dígitos 5º y 6º de la
  IBAN".
- **Verificado (confirmado A):** ambas fuentes fetchadas en vivo sin login. Coincidencia de código
  **definitiva** (definición legal del FSA). Salvedades: solo HTML (no hay CSV/XML/JSON en ningún
  sitio; nuevo patrón de scraping); sin licencia explícita (lista factual); manejar códigos dobles
  (Luminor 96/17) y con cero a la izquierda (TBB 00). Conjunto ~11 bancos + PIs, pequeño y estable.
- Citas: `pangaliit.ee/settlements-and-standards/bank-codes`,
  `fi.ee/en/banking-and-credit/applying-activity-licences/identity-codes-international-account-numbers-credit-institutions`.

### 🇲🇪 ME — Montenegro — Tier A
- `bank_code`: 3 díg. numéricos (IBAN pos. 5-7). Habilita nombre+BIC. Esfuerzo M.
- **Fuente:** Central Bank of Montenegro (CBCG), página del sistema RTGS — tabla HTML inline que mapea
  el código de 3 díg. a nombre + BIC (907 CBCG, 510 CKB, 520 Hipotekarna, 530 NLB, 540 Erste,
  555 Addiko…).
- **Verificado (confirmado A):** fetch en vivo sin login. Code-match confirmado incluso contra un intento
  de refutación (Wise reporta "505" para CKB, pero 505 es el IBAN de ejemplo genérico del registro SWIFT;
  el PDF de instrucciones de la propia CKB da `ME25510…` y una IBAN real de Hipotekarna `ME25520…`
  confirman 510/520). Licencia: dato factual de banco central (OK). Salvedad: scraping HTML; la tabla
  mezcla bancos comerciales con entes públicos (714-931) que hay que filtrar.
- Citas: `cbcg.me/en/core-functions/payment-system/cbcg-payment-system/rtgs-system`,
  `ckb.me/upload/documents/o_nama/Instrukcija.pdf`.

### 🇸🇪 SE — Suecia — Tier A
- `bank_code`: 3 díg. numéricos (IBAN pos. 5-7) — un "IBAN ID" a nivel banco (300=Nordea, 500=SEB,
  600=Handelsbanken, 800=Swedbank), **distinto** del clearing number de 4-5 díg. Habilita nombre+BIC.
  Esfuerzo S.
- **Fuente:** repo comunitario `github.com/Bankinfrastruktur/BankData`, fichero `Data/source.psv`
  (pipe-separated; columnas `#ClrStart|ClrEnd|IbanId|BIC|BankName|…`). La columna `IbanId` **es** el
  código de 3 díg. de la IBAN. **Licencia MIT** (permite fetch, redistribución, uso comercial).
- **Verificado (confirmado A):** `source.psv` fetchado vía `raw.githubusercontent.com` sin auth;
  `IbanId` cross-check contra IBAN real (SE45 5000… → 500 = SEB/ESSESESS) y contra el propio registro
  del repo. **No usar** las columnas de clearing (4-5 díg. = fallo de longitud/semántica);
  de-duplicar por `IbanId` (Nordea=300 aparece en muchos rangos). El publicador oficial (BSAB,
  bankinfrastruktur.se) es PDF/DOCX sin licencia abierta → tier D por sí solo; el mirror MIT es lo que
  hace SE construible. Considerar `--file` como hedge si `raw.githubusercontent.com` está bloqueado.
- Citas: `raw.githubusercontent.com/Bankinfrastruktur/BankData/main/Data/source.psv`,
  `bankinfrastruktur.se/framtidens-betalningsinfrastruktur/konto-och-clearingnummer`.

### 🇫🇷 FR — Francia — Tier A (confirmado)
- `bank_code`: 5 díg. numéricos (code banque / CIB), primeros 5 car. del BBAN. Habilita nombre+LEI (sin
  BIC). Esfuerzo M. **Cubre también Mónaco** (una sola fuente, §5).
- **Fuente:** REGAFI (ACPR / Banque de France), API Opendatasoft Explore v2.1. Datasets:
  `prd-jdd-recherche` (`cib` = array de strings de 5 díg. — el más simple) o `prd-banque-entites`
  (autoritativo; `cib` anidado `{code,date}` + LEI/categoría). Export CSV/JSON/XLSX abierto, sin clave.
- **Verificado (confirmado A, doble):** verificado en vivo por investigación **y** por verificación
  adversarial independiente (§5): 25.442 registros, 822 con CIB, `data_processed` 2026-07-14; CIBs
  reales 15208/24659/16607. Anula el "paywalled": el `FIB` sigue de pago (tier D) pero REGAFI expone el
  CIB abiertamente. Licencia Licence Ouverte / CRPA (atribución obligatoria, `licenseOkConfirmed=true`).
  Sin BIC → capa EPC aparte si se quiere BIC. El campo `cib` es array JSON → parsear y expandir una
  fila por código.
- Citas: `regafi.fr/api/explore/v2.1/catalog/datasets/prd-banque-entites/exports/csv`,
  `regafi.fr/api/explore/v2.1/catalog/datasets/prd-jdd-recherche/exports/json`,
  `banque-france.fr/.../fichier-implantations-bancaires-fib` (FIB, de pago).

### 🇦🇩 AD — Andorra — Tier B
- `bank_code`: 4 díg. numéricos (código "Entitat"). Habilita nombre. Esfuerzo S.
- **Fuente:** Andorran Banking (ABA), PDF *"Codificació de les oficines bancàries d'Andorra - Format
  IBAN"* — columna `Entitat` de 4 díg. = `bank_code` (0001 Andbank, 0003 Creand/Crèdit Andorrà,
  0007/0008 MoraBanc). Solo 3 bancos / 4 códigos.
- **Verificado (confirmado B):** descargado en vivo (HTTP 200, 113.742 bytes) y extraído con
  `pdftotext -layout`. El registro del regulador AFA usa números "EB NN/YY" (no el código de 4 díg.) →
  inservible para resolución. Tier B (no A) porque es PDF (sin lector en el paquete) y la URL
  wp-content rota por fecha. Recomendado: importador `--file` **o** — dado el conjunto diminuto y
  estable — **mapa hardcodeado** refrescado anualmente. Enriquecer BIC aparte (BACAADAD, CRDAADAD,
  BSAAADAD).
- Citas: `andorranbanking.ad/wp-content/uploads/2024/10/Codificacio-oficines-bancaries-CAT-2024-1.pdf`,
  `afa.ad/en/entitats-supervisades/...`.

### 🇲🇰 MK — Macedonia del Norte — Tier B
- `bank_code`: 3 díg. numéricos ("Водечки број" / leading number). Habilita nombre+BIC. Esfuerzo M.
- **Fuente:** NBRM (banco central), *"Листа на доделени водечки броеви на банките"* — columnas
  `Р.бр | SWIFT BIC | Назив на банка | Водечки број`. Código de 3 díg. → nombre + BIC (200 Stopanska,
  210 NLB, 300 Komercijalna, 320 CKB…).
- **Verificado (confirmado B):** todo `nbrm.mk` está tras un reto Cloudflare (403 a todo fetch
  automático, reproducido) → **`--file` manual** (patrón LU/UA/KZ). El fichero es `.xls` legacy (BIFF8,
  cp1251) o `.docx` → **no** legible por `XlsxReader`: hace falta parser de `.xls` legacy o de tabla
  DOCX. **Frescura incierta:** el único contenido confirmable es v1.15 (2014); puede predatar
  fusiones/liquidaciones (Eurostandard/370 liquidado 2020) → verificar al descargar. EPC solo como
  enriquecimiento BIC.
- Citas: `nbrm.mk/WBStorage/Files/Platni sistemi_ListaVodeckiBroeviBanki.xls`,
  `nbrm.mk/lista_na_vodiechki_broievi_...nspx`.

### 🇵🇹 PT — Portugal — Tier B
- `bank_code`: 4 díg. numéricos (IBAN pos. 5-8 / primeros 4 del NIB). Habilita nombre+BIC. Esfuerzo M.
- **Fuente:** Banco de Portugal, SICOI *"BIC associated with IBANs of PSPs participating in SICOI"* —
  PDF con capa de texto; columnas `IBAN Bank Identifier` (4 díg.) | nombre PSP | BIC (0034 = Caixa
  Geral de Depósitos/CGDIPTPL, 0033 BNP Paribas, 0035 Montepio…).
- **Verificado (confirmado B):** PDF descargado (~465 KB) y extraído con `pdftotext -layout` → tabla de
  columnas fijas limpia (los acentos salen mojibake → limpieza). La landing `bportugal.pt/*/page/sicoi`
  bloquea bots (403) y las URLs con fecha rotan → **`--file`** (patrón LU/UA/KZ) + paso de extracción
  PDF. Licencia: disclaimer del Banco de Portugal permite reutilización con atribución (OK). Existe una
  edición 2026 (mantenimiento activo). **No** es tier D: el mapeo es completo y extraíble. El
  `listaimeipdsp2.xlsx` del sitio es el registro de passporting PSD2 (sin códigos) — pista falsa.
- Citas: `bportugal.pt/sites/default/files/anexos/.../bic_linked_with_ibans_..._21.10.2022.pdf`,
  `bportugal.pt/en/page/sicoi`.

### 🇦🇱 AL — Albania — Tier C
- `bank_code`: 3 díg. numéricos (KIB, Kodi i Identifikimit Bankar). Habilita nombre+BIC (vía curación).
  Esfuerzo M.
- **Fuente:** no existe directorio KIB legible por máquina. El mapeo autoritativo KIB→banco vive solo en
  el Reglamento IBAN nº 42 del Bank of Albania (Anexo 4), PDF en texto legal, y el dominio
  `bankofalbania.org` da 403 a fetch automático. La página HTML de bancos licenciados no tiene columna
  de código.
- **Verificado (confirmado C):** todos los WebFetch (BoA PDF, EPC, sct_inst.csv, bank.codes) dieron 403
  → "blocked" confirmado. El EPC da BIC + reachability (AL operativo en SEPA oct-2025) pero **sin**
  columna KIB → no resuelve por código. **No reutilizar `EpcRegisterImporter`** (asume bank_code =
  prefijo BIC). Salvedad semántica: el BoA llama "KIB" al identificador de 8 díg. (banco+sucursal); el
  `bank_code` de la IBAN son los **3 primeros**. Ruta pragmática: **curar** un mapa ~13 bancos
  KIB→{nombre,BIC} del Anexo 4 + cross-check EPC/SWIFT (dato factual, no redistribución del reglamento).
  Revisar en 6-12 meses por si BoA publica dataset abierto.
- Citas: `bankofalbania.org/rc/doc/Regulation_no_42_...IBAN_..._6127.pdf`,
  `bankofalbania.org/Supervision/Licensed_institutions/Banks/`.

### 🇫🇮 FI — Finlandia — Tier C (mantener aparcado salvo priorización)
- `bank_code`: 3 díg. numéricos (IBAN pos. 5-7). Habilita nombre+BIC. Esfuerzo L.
- **Fuente:** Finanssiala ry (Finance Finland), *"Suomalaiset rahalaitostunnukset ja BIC-koodit"* —
  **fresca** (ed. 16.3.2026; corrige el "stale 2022"). PDF con nombre + BIC por institución.
- **Verificado (confirmado C, code-match FALSE):** descargado y extraído en vivo. **Bloqueo decisivo
  confirmado:** el `Rahalaitostunnus` es un código **de longitud variable 1-4 díg.**, dado como valores
  sueltos, múltiples y **rangos** (Nordea="1 ja 2", OP="5", Danske="8 ja 34", POP="470-479", cajas de
  ahorro= larga lista de rangos), mientras el `bank_code` del paquete es un campo **fijo de 3 díg.**
  → un join exacto falla para todos los grandes bancos. Matiz que el hallazgo no vio: desde 2024 los
  códigos que empiezan por 72-78 son de **4 díg.**, insuficientes para una clave de 3. Solo XLSX = la
  ed. obsoleta de 2022; todas las 2023-2026 son PDF. Sin licencia. Si se prioriza: importador
  `--file` PDF con **mapeador de rangos a medida** al código de 3 díg. Si no, dejar aparcado.
- Citas: `finanssiala.fi/wp-content/uploads/2026/04/suomalaiset-rahalaitostunnukset-ja-bic-16032026.pdf`,
  `finanssiala.fi/julkaisut/suomalaiset-rahalaitostunnukset-ja-bic-koodit/`.

### 🇮🇸 IS — Islandia — Tier C
- `bank_code`: 4 díg. numéricos (banki 2 + útibú 2, p. ej. 0515). Habilita nombre a nivel banco (BIC).
  Esfuerzo M.
- **Fuente:** no hay directorio abierto legible por máquina. RB (Reiknistofa bankanna), el mantenedor
  autoritativo, no publica abiertamente. El código completo de 4 díg. solo aparece en manuales PDF de
  bancos individuales (no redistribuibles, no extraíbles).
- **Verificado (confirmado C):** `rb.is` fetchado (HTTP 200) pero sin dataset descargable. El esquema
  estable de prefijos "de centenas" (10/100→Landsbankinn, 30/300→Arion, 50/500→Íslandsbanki,
  70/700→Kvika, 11/1100→cajas, 15/1500→banco central, 22→indó) permite un **mapa curado a nivel banco**
  (no sucursal). EPC = solo BIC (IS es SEPA vía EEE) y **no** reutiliza el patrón GB/GI/… (código
  numérico, no prefijo BIC). Confirmado el "sin directorio oficial" previo.
- Citas: `rb.is/`, `sedlabanki.is/peningastefna/markadsvidskipti/greidslukerfi`.

### 🇮🇹 IT — Italia — Tier C
- `bank_code`: 5 díg. numéricos (ABI; IBAN pos. 5-9). Habilita nombre (sin BIC). Esfuerzo M.
- **Fuente:** Agenzia delle Entrate, página F24 *"elenco banche convenzionate"* (`…-xcodice`) — tabla
  HTML `Denominazione + Codice ABI`, ~400+ bancos, actualizada 2026-05-04.
- **Verificado (confirmado C):** fetch en vivo sin login. `Codice ABI` = `bank_code` (pos. 5-9), **pero
  mostrado sin ceros a la izquierda** → el importador debe hacer zero-pad a 5. Salvedades que lo dejan
  en C (no A): scrape HTML (sin CSV/XLS); **parcial** (solo bancos adheridos a F24); **sin BIC**; sin
  licencia de reutilización explícita. Intentos de subir a A fallaron: Banca d'Italia GIAVA está tras
  OAuth (302) y usa "matricola" no ABI; el ABI/CAB/BIC canónico (CIPA/ABI vía SIA-Nexi) sigue de
  suscripción (**tier D**). Capa EPC para BIC. **Reutilizar filas IT para San Marino** (mismo sistema
  ABI) — aunque SM tiene su propia fuente (BCSM), no está en las listas italianas.
- Citas: `agenziaentrate.gov.it/portale/schede/pagamenti/f24/elenco-banche-convenzionate-f24/elenco-banche-f24-xcodice`,
  `infostat.bancaditalia.it/GIAVAInquiry-public/ng/`, `sia.eu/.../abi-cab-bic-e-apiba-...`.

### 🇱🇹 LT — Lituania — Tier C
- `bank_code`: 5 díg. numéricos. Habilita nombre+BIC. Esfuerzo L.
- **Fuente:** Lietuvos bankas (banco central), directorio de instituciones financieras (ed. EN
  `LT-20251118-en.pdf`, LT `FIK_kodai_202507-lt.pdf`). Columna `National ID` (5 díg.) | BIC | nombre
  (73000 Swedbank/HABALT22, 70440 SEB/CBVILT2X, 32500 Revolut/REVOLT21…).
- **Verificado (confirmado C):** PDF descargado (348.748 bytes) y extraído; 221 filas de código, todas
  de 5 díg., triple código→BIC→nombre limpio → **resolución completa**. Salvedades: **PDF** (sin
  CSV/XML), parseo posicional frágil (sub-columnas sucursal/ciudad se entrelazan), URL con fecha
  rotatoria e índice HTML bloqueado por Cloudflare (403) → **`--file`**. **Licencia sin confirmar:** la
  landing (donde estarían los términos) da 403 y el PDF lleva marca de agua *"LB VIDAUS (ECB
  INTERNAL)"* pese a ser descargable sin login → confirmar con Lietuvos bankas antes de enriquecer.
- Citas: `lb.lt/uploads/documents/files/LT-20251118-en.pdf`, `lb.lt/en/iban-and-financial-institution-codes`.

### 🇲🇨 MC — Mónaco — Tier A (vía REGAFI, compartida con FR)
- `bank_code`: 5 díg. numéricos (CIB francés; IBAN pos. 5-9). Habilita nombre (REGAFI) / +BIC (AMAF).
  Esfuerzo S (comparte importador con FR).
- **Fuente primaria (confirmada, §5):** **REGAFI** — las entidades de Mónaco están en el **mismo**
  dataset que Francia y **llevan CIB** (Crédit mobilier de Monaco 10160, Barclays Monaco 12448). Un
  solo importador FR+MC resuelve ambos (nombre+LEI, sin BIC).
- **Fuente alternativa para BIC:** AMAF (Association Monégasque des Activités Financières), `bic-iban` —
  tabla HTML (+PDF) de ~51 establecimientos / 64 códigos CIB con nombre + **BIC**, `Code Pays = MC`,
  actualizado dic-2025 (CFM Indosuez CIB 12739 = IBAN MC11 12739…). Verificado en vivo (tier C por sí
  sola: scrape, sin licencia declarada — Mentions Légales da 404). Usar solo si se quiere el BIC que
  REGAFI no trae, o capa EPC.
- **Nota:** el `FIB` de la Banque de France (cubre FR+MC, con BIC) sigue de suscripción (**tier D**).
- Citas: `regafi.fr/api/explore/v2.1/catalog/datasets/prd-banque-entites/exports/csv` (MC incluido),
  `amaf.mc/fr/bic-iban`, `banque-france.fr/.../fichier-implantations-bancaires-fib`.

### 🇷🇸 RS — Serbia — Tier C
- `bank_code`: 3 díg. numéricos. Habilita nombre+BIC. Esfuerzo M.
- **Fuente:** National Bank of Serbia (NBS), dos PDFs 2025: `pregled_racuna_banka.pdf` (código→BIC vía
  columna de cuenta de liquidación `908-Xnn01-kk`) + `pu_jedinstveni_id_brojevi.pdf` (código→nombre,
  ~19 bancos). 105 AIK/AIKBRS22, 160 Banca Intesa/DBDBRSBG, 170 UniCredit/BACXRSBG…
- **Verificado (confirmado C):** ambos PDF descargados (HTTP 200, sin login) y extraídos; code-match
  confirmado contra BICs reales y una IBAN real `RS35105…`=AIK. Salvedades: **solo PDF**, layout de dos
  columnas **desalineado** (nombres vs código/BIC) → estrategia "zip 19 nombres ↔ 19 códigos", frágil;
  sin dependencia PDF en el paquete. Conjunto pequeño (~19) → alternativa `--file` o **mapa curado**
  cross-check contra los PDF. Sin licencia explícita (documentos regulatorios públicos). EPC solo BIC.
- Citas: `nbs.rs/documents/platni-sistem/pregled_racuna_banka.pdf`,
  `nbs.rs/export/sites/NBS_site/documents/propisi/propisi-ps/pu_jedinstveni_id_brojevi.pdf`.

### 🇸🇲 SM — San Marino — Tier C
- `bank_code`: 5 díg. numéricos (ABI, sistema italiano; IBAN pos. 5-9 tras el check nacional).
  Habilita nombre+BIC. Esfuerzo S.
- **Fuente:** BCSM (Banca Centrale della Repubblica di San Marino), página *"Operating Banks"* — 4
  bancos, cada uno con nombre + ABI de 5 díg. + BIC (03034 Banca Agricola Commerciale/BASMSMSM,
  08540 Banca di San Marino/MAOISMSM, 03287 BSI/BSDISMSD, 06067 Cassa di Risparmio/CSSMSMSM).
- **Verificado (confirmado C):** fetch en vivo sin login; columnas literales `ABI Code` y `SWIFT BIC`.
  **Los directorios ABI italianos NO listan bancos sammarinesi** (pista falsa de "herencia") → SM
  necesita esta fuente propia. Tier C: solo HTML, 4 filas. Dado el conjunto diminuto y estable →
  **set curado** (patrón Vaticano/Andorra) igual de válido y más robusto que scrapear 4 filas. Licencia:
  solo "© Central Bank" (dato factual, OK con salvedad documentada).
- Citas: `bcsm.sm/en/functions/statutory-functions/payment-system/operating-banks`,
  `bcsm.sm/en/functions/other-functions/guarantee-fund-for-depositors/participating-banks`.

### 🇻🇦 VA — Ciudad del Vaticano — Tier C
- `bank_code`: 3 díg. numéricos (IBAN pos. 5-7). Habilita nombre+BIC. Esfuerzo S.
- **Fuente:** no hay directorio legible por máquina. ASIF (regulador) publica solo una página HTML de
  entidades supervisadas que nombra únicamente al IOR. **Mejor artefacto: mapa curado de 1 entrada**
  `001 → Istituto per le Opere di Religione (IOR)`, BIC `IOPRVAVX`.
- **Verificado (confirmado C):** dos IBAN oficiales VA distintas confirman `001`/IOPRVAVX (Basílica de
  San Pedro `VA90001000000011626001`; Dicasterio para la Comunicación `VA96001000000046371002`). El
  universo real es efectivamente un banco (IOR). Marcar procedencia como curada/hardcodeada (no import
  de fuente oficial). Licencia: hechos únicos no protegibles (OK). Correcciones de la ficha: la página
  de Óbolo hoy muestra una IBAN italiana de FinecoBank; `VA96…` es del Dicasterio de Comunicación, no
  APSA (no afecta la conclusión).
- Citas: `asif.va/ITA/EntiVigilati.aspx`, `basilicasanpietro.va/en/donate`.

### 🇩🇰 DK — Dinamarca — Tier D (no viable)
- `bank_code`: 4 díg. numéricos (registreringsnummer; IBAN pos. 5-8). **También FO y GL.** Habilita
  nada. Esfuerzo L.
- **Fuente:** ninguna abierta y legible por máquina. El registro autoritativo Finanstilsynet
  (*"Kapitel5_1PI_Register"*) es PDF y data de ~2011 (metadatos `D:20110610`). Finans Danmark solo
  publica estadísticas agregadas.
- **Verificado (confirmado D):** el PDF de 2011 es alcanzable pero obsoleto y sin licencia. El
  verificador **encontró** una fuente que la ficha omitió: `registreringsnumre.dk` (Mastercard Payment
  Services Denmark, ex-Nets) — el registro **vivo y autoritativo**, pero solo caja de búsqueda (sin
  bulk/CSV/API), el bulk es **suscripción de pago** y su licencia **prohíbe expresamente la
  reproducción y el uso comercial**. El CSV `x2q/danish-bank-codes` de GitHub es no oficial, sin
  licencia (all-rights-reserved) y abandonado desde 2014. EPC no resuelve (keyed por BIC). Único camino
  si DK fuese obligatorio: `--file` aportado por el operador **bajo su propio riesgo** de licencia/datos.
  Revisar anualmente por si data.gov.dk / Datafordeler publica un dataset abierto.
- Citas: `finanstilsynet.dk/media/44154/Kapitel5_1PI_Register.pdf`, `registreringsnumre.dk/`,
  `github.com/x2q/danish-bank-codes`.

## 7. Implicaciones para el spec (resumen)

- **Construibles ya (fetch en vivo) — 6:** CY, EE, ME, SE, **FR, MC** (FR+MC comparten el importador
  REGAFI). Requieren scraping HTML (CY/EE/ME), parseo PSV (SE) o JSON (FR/MC). Ganancia inmediata:
  **+6 países SEPA** → cobertura SEPA pasaría de 24/42 a **30/42**.
- **Construibles offline (`--file`) — 3:** AD, MK, PT. Requieren PDF (AD/PT) o `.xls`/`.docx` legacy
  (MK) y el patrón `--file` ya existente. → 33/42.
- **Construibles con salvedades / curación — 8:** AL, IT, LT, RS, SM, VA, IS (+ FI si se prioriza).
  Mezcla de scrape HTML (IT/SM), extracción PDF (LT/RS/FI) y **conjuntos curados** (VA/AD/SM/IS/AL). →
  hasta 41/42.
- **No viable — 1:** DK (y por extensión FO/GL). Documentar; no construir. Techo realista: **41/42**.
- **Decisiones de infraestructura a tomar en el spec:** (1) ¿se añade parser HTML compartido? (2) ¿se
  añade extracción PDF, o se limita a `--file`/curación? (3) ¿lector `.xls` legacy para MK? (4)
  **política de datos curados** (VA/AD/SM/IS/AL) frente a la regla "no empaquetar datos"; (5) capa EPC
  opcional para BIC en las fuentes name-only (FR, IT).

## 8. Enlaces

- Spec derivado: [`spec.md`](spec.md)
- Marco de importadores: [`docs/importers.md`](../../importers.md)
- Disciplina de licencias: [`docs/licensing.md`](../../licensing.md)
- Autoría del registro (metodología de datos factuales): [`docs/registry-authoring.md`](../../registry-authoring.md)
