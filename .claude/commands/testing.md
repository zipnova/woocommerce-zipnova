---
description: Genera un plan de pruebas manuales completo para una card de Linear (ACs + análisis de código)
---

# Plan de Pruebas Manuales

Generás un plan de pruebas manuales completo y enriquecido para una card de Linear, combinando los criterios de aceptación con el análisis de los cambios reales del código de este repo (plugin de WordPress/WooCommerce para Zipnova).

El output tiene dos componentes:
- **PARTE 1** → casos de prueba listos para pegar en la card de Linear
- **PARTE 2** → guía técnica de ejecución para cada caso (precondiciones, queries de descubrimiento, scripts ejecutables, mini-resumen)

## Parámetros

`$ARGUMENTS` (opcional): ID de la card de Linear (ej: `SHPB-379`). Si se omite, la skill infiere el ticket a partir de la rama git actual.

---

## Paso 1 – Resolver el ID de la card y variables

1. Si `$ARGUMENTS` trae un ID con el patrón `[A-Za-z]+-[0-9]+`, usarlo como `{id}` (en MAYÚSCULAS).
2. Si no viene `$ARGUMENTS`, detectar la rama actual y extraer el ticket:
```bash
BRANCH=$(git branch --show-current)
echo "$BRANCH" | grep -oE '[A-Za-z]+-[0-9]+' | tr '[:lower:]' '[:upper:]'
```
   Si no se encuentra ningún ID en el nombre de la rama, pedírselo explícitamente al usuario.

Definir:
- `{id-slug}` = ID sin guion, primera letra en mayúscula y resto en minúscula (ej: `Shpb379`).
- `{repo-dir}` = raíz de este repo: `git rev-parse --show-toplevel`.
- `{plugin-namespace}` = namespace base del plugin: **`Zippin\Zippin`** (las clases/funciones viven bajo `Zippin\Zippin`, `Zippin\Zippin\Utils` y `Zippin\Zippin\Settings`). No hay `composer.json` ni autoload PSR-4: los archivos se cargan con `require_once` desde `woocommerce-zippin.php`, y WP-CLI (`wp eval`/`wp shell`) los tiene disponibles porque WordPress carga el plugin al bootstrapear.
- `{branch-known}` = la rama detectada en el paso 2 si `$ARGUMENTS` no vino (ya tiene el casing exacto, no hace falta inferirla más adelante).

Salida:
- `{output}` = `{repo-dir}/.claude/local/docs/testing/{id}.md`
```bash
mkdir -p "{repo-dir}/.claude/local/docs/testing/"
```
> `.claude/local/` ya está en el `.gitignore` versionado del repo — no hace falta ni tocar `.git/info/exclude` ni preguntar nada, el archivo nunca se sube.

**Si `{output}` ya existe**, avisar al usuario y preguntar cómo seguir antes de continuar con el resto de los pasos:
- **Sobrescribir** → continuar normalmente, el plan nuevo reemplaza al anterior.
- **Versionar** → usar `{output}` con sufijo incremental (`{id}_2.md`, `{id}_3.md`, ...; el primer sufijo libre que no exista todavía).
- **Cancelar** → detener el flujo sin generar nada.

---

## Paso 2 – Obtener card de Linear

**Validar primero la conexión a Linear.** Antes de intentar obtener la card, verificar que alguna herramienta Linear MCP esté efectivamente disponible en la sesión (`mcp__claude_ai_Linear__get_issue` — conector de claude.ai, preferido — o como alternativas `mcp__linear__get_issue` / `mcp__plugin_linear_linear__get_issue`). Si **ninguna** lo está, detener el flujo con un mensaje claro:

> "No detecto el conector de Linear conectado. Conectalo desde Claude (Conectores → Linear) y volvé a ejecutar la skill."

Si la conexión está OK, usar la herramienta Linear MCP disponible para obtener la card con id `{id}` (pedir `includeRelations: true` para poder detectar ticket padre/relacionados en el mismo llamado).

Extraer:
- `{card-title}` = título de la card
- `{card-url}` = URL de la card
- `{card-assignee}` = nombre del assignee (para la sección de resultado final)
- `{card-description}` = descripción completa del ticket — leerla en su totalidad para entender el contexto funcional, el problema que resuelve, el comportamiento esperado y cualquier detalle técnico o de negocio relevante. Este contenido es la base principal para generar casos de prueba ricos y contextualizados.
- `{criterios}` = criterios de aceptación extraídos de la descripción o campos de la card
- `{labels}` = labels/etiquetas de la card (ej: prioridad, tipo de bug, país — `AR`/`CL`/`MX` si alguna label lo indica). Usar esto para orientar los casos de prueba al país o contexto específico cuando aplique (ej: reglas de negocio o configuraciones que difieren por país — recordar que en CL no se usa zipcode y el manejo de estados difiere).
- `{id-branch}`:
  - Si `{branch-known}` (Paso 1) ya está definida, usar esa directamente (ya tiene el casing real).
  - Si no, usar el campo `gitBranchName` del ticket. Si viene vacío, inferirlo como `feature/{id en minúsculas}` y luego verificar el nombre real en el remoto (ver Paso 3, casing case-sensitive).

**Tickets relacionados (padre / sub-issues):**
- Si la card tiene un ticket **padre** (parent issue), obtenerlo con la misma herramienta Linear MCP y extraer sus ACs también — pueden aplicar al alcance de esta card.
- Si la card tiene **sub-issues**, listarlos con la herramienta Linear MCP equivalente a `list_issues` usando `parentId: {id}` y extraer sus ACs. Esto evita perder cobertura de flujos que están descritos en un ticket hijo en vez de en la card principal.
- Combinar todos los ACs recolectados (card + padre + sub-issues) en `{criterios}`, indicando de qué ticket viene cada uno si no son obvios por contexto.

**Resolución del PR asociado** (`{pr-url}`):
1. Verificar que `gh` esté autenticado antes de usarlo:
```bash
gh auth status 2>/dev/null
```
   Si no está autenticado, omitir este paso y pasar directo al fallback de Linear (no bloquear el flujo por esto).
2. Si `gh` está autenticado, intentar obtener el PR vía GitHub CLI, que es más confiable cuando ya conocemos la rama exacta:
```bash
gh pr view "{id-branch}" --json url -q '.url' 2>/dev/null
```
3. Si no devuelve nada (o `gh` no estaba autenticado), usar como fallback el attachment de Linear (buscar en `attachments` del ticket una URL de `github.com/*/pull/*`).
4. Si ninguna de las dos fuentes tiene resultado, dejarlo vacío.

Si la card no existe o no se puede obtener, informar al usuario y detener el flujo.

---

## Paso 2.5 – Cruzar con los logs de WooCommerce (solo si es un bug fix)

**Determinar si el ticket es un bug fix**: `{labels}` contiene algo como "Bug"/"Bugfix"/"Hotfix", o `{card-title}`/`{card-description}` menciona explícitamente un error, excepción o comportamiento roto. Si no hay indicios de bug, **omitir este paso completo**.

Este plugin **no usa Sentry**: los errores y warnings se registran vía `wc_get_logger()` con source `zippin`, visibles en **WooCommerce > Estado > Registros** (o en `wp-content/uploads/wc-logs/zippin-*.log`). Si parece un bug fix:
1. Buscar en la descripción del ticket el mensaje de error o el fragmento de log adjunto que reportó el problema. Extraer:
   - `{error-summary}` = mensaje/contexto del error (ej. "Falló la creación del envío", "Incomplete destination", código HTTP de la API de Zipnova como 403/404, etc.)
   - `{error-locus}` = función/archivo probable donde ocurre (ej. `ZippinConnector::create_shipment`, `Helper::get_destination_from_order`, `Utils\process_order_status`)
2. Si el ticket no trae el log, pedírselo al usuario o sugerir buscarlo en WooCommerce > Estado > Registros (source `zippin`) filtrando por la fecha/estado del envío afectado. No bloquear el flujo si no está disponible.

**Uso en los casos de prueba (Paso 4):** si se identificó un error concreto, generar al menos un caso de prueba dedicado a reproducir exactamente el escenario que rompía (misma orden/estado/país/config que causaba el error), usando `{error-summary}` como base de la descripción del caso, y verificar en los logs (source `zippin`) que ya no aparezca.

---

## Paso 3 – Analizar cambios de código

En `{repo-dir}`, ejecutar los siguientes comandos para obtener contexto técnico:

```bash
# 1. Traer últimos cambios del remoto
git -C "{repo-dir}" fetch origin 2>/dev/null

# 2. Verificar si existe la rama de la card y capturar su NOMBRE REAL (case-exact)
git -C "{repo-dir}" branch -a | grep -i "{id-branch}" | head -1 | sed 's#.*remotes/origin/##' | xargs
```

> ⚠️ **Casing de la rama:** el `gitBranchName` de Linear suele venir en minúsculas (`feature/shpb-379`) pero la rama remota real puede tener otra capitalización (`feature/SHPB-379`). Como los refs de git son **case-sensitive**, a partir de acá usar el **nombre real** que devuelve el comando de arriba (lo llamamos `{id-branch}` de acá en más), NO el de Linear. Si el comando no devuelve nada, la rama no existe.

**Si la rama NO existe** (el comando no devuelve resultados): informar al usuario que no se encontró la rama remota y generar los casos de prueba basándose únicamente en los ACs del ticket. Omitir el resto del Paso 3.

**Si la rama existe**, resolver primero la **rama base** `{base-branch}` probando en orden `develop` y luego `master`, verificando que existan en el remoto:
```bash
git -C "{repo-dir}" rev-parse --verify origin/develop 2>/dev/null && echo "develop" \
  || { git -C "{repo-dir}" rev-parse --verify origin/master 2>/dev/null && echo "master"; }
```
Si **ninguna** de las dos existe, preguntarle al usuario qué rama base usar (caso raro en este repo) y usar esa.

Con `{base-branch}` resuelta, obtener los cambios:

```bash
# Archivos modificados en la rama de la card respecto a la rama base
git -C "{repo-dir}" diff origin/{base-branch}...origin/{id-branch} --name-only 2>/dev/null

# Resumen estadístico
git -C "{repo-dir}" diff origin/{base-branch}...origin/{id-branch} --stat 2>/dev/null | head -40
```

**Filtrar archivos relevantes** (los `.php`; considerar también `readme.txt`, `css/`, `js/` si el cambio es de UI/checkout):

Para cada archivo relevante modificado:
- Leer su contenido con `git -C "{repo-dir}" show origin/{id-branch}:{ruta/del/archivo}` vía Bash (NO usar `Read` directamente, ya que lee la rama actual del working directory, no la rama de la card)
- Identificar qué se tocó, ubicándolo en la arquitectura del plugin:
  - **cliente API** → `zippin-connector.php` (`ZippinConnector`: `call_api`, `create_shipment`, `quote`, `get_shipment`)
  - **transformación de datos** → `helper.php` (`Helper`: destino/items/paquetes, direcciones, estados/dominios por país)
  - **hooks/flujo** → `utils.php` (`Utils\*`: `process_order_status`, `update_order_meta`, `handle_webhook`, `create_shortcode`, `box_content`, `activate_plugin`)
  - **cotización en checkout** → `zippin-method.php` (`WC_Zippin::calculate_shipping`)
  - **settings** → `zippin-settings.php` (`Settings\*`)
  - **registro de hooks** → `hooks.php`
  - **bootstrap/constantes/versión** → `woocommerce-zippin.php`
- Anotar qué lógica de negocio cambió y en qué país(es) impacta.

**Para casos de regresión**, usar `Grep` y `Glob` para rastrear el impacto del cambio en el resto del codebase:
- Si se modificó una función/método clave, buscar todos los lugares que lo consumen: `Grep pattern="{funcion_o_metodo}" path="{repo-dir}"`
- Si se cambió una opción `zippin_*`, buscar todas sus lecturas: `Grep pattern="zippin_nombre_opcion" path="{repo-dir}"`
- Estos consumidores son candidatos directos a casos de regresión (ej. un cambio en `Helper::get_destination_from_order` afecta tanto `create_shipment` como el detalle en `box_content`).

**Este análisis enriquece los casos de prueba** más allá de lo que dicen los ACs: cubre los flujos reales del código modificado, los puntos de regresión potencial, la compatibilidad HPOS y las diferencias por país.

---

## Paso 4 – Generar casos de prueba

Generar casos de prueba numerados correlativamente (#1, #2, #3...) en una **lista plana y unificada**, sin secciones ni divisiones dentro del documento.

Cubrir los siguientes tipos (mezclados en el orden que tenga sentido narrativo):
- **Happy Path**: cada AC mapea al menos 1 caso exitoso. Incluir casos de los flujos detectados en el código.
- **Negativos**: inputs inválidos, campos vacíos (dirección/telefono/email/documento faltantes), datos fuera de rango, producto sin peso/dimensiones, credenciales inválidas, respuesta de error de la API.
- **Borde**: valores límite, combinaciones inusuales, estados intermedios de la orden, producto virtual/sin envío, pickup points vs entrega a domicilio, envío gratis por umbral.
- **Regresión**: flujos existentes que podrían verse afectados por los cambios — basados en los consumidores detectados con Grep/Glob (solo si aplica).
- **Compatibilidad HPOS**: si el cambio toca meta de órdenes o pantallas de admin, incluir un caso con HPOS **activo** y otro con HPOS **desactivado** (solo si aplica).
- **Específico de país**: si `{labels}` indica un país (AR/CL/MX) o el código modificado tiene lógica condicional por país, incluir al menos un caso que ejercite esa configuración puntual — recordar `use_zipcode`/`zipcode_length` y estados por país (solo si aplica).
- **Integración con la API de Zipnova**: si hay cambios en `ZippinConnector`/payloads, casos que verifiquen el request/response real contra la API (cotización, creación de envío, tracking) (solo si aplica).
- **Reproducción de bug (logs)**: si el Paso 2.5 identificó un error concreto, un caso que reproduzca exactamente ese escenario y confirme en los logs (source `zippin`) que ya no ocurre (solo si aplica).

La **clasificación por tipo** solo se usa al final en el 📊 Resumen, no dentro de los casos.

**Para cada caso, determinar su tipo de ejecución** y asignarlo como tag en el título:
- `[browser]` → requiere interacción con la UI: checkout/carrito, admin de órdenes, página de tracking, página de settings
- `[wp-cli]` → requiere ejecutar código PHP con WordPress cargado (`wp eval '...'`, `wp shell`, `wp eval-file`), instanciar `ZippinConnector`/`Helper`, o leer/escribir opciones (`wp option get/update zippin_*`)
- `[sql]` → solo requiere consultas directas a la DB (`wp_options`, `wp_posts`/`wp_postmeta` en modo legacy, o `wp_wc_orders`/`wp_wc_orders_meta` con HPOS activo)
- `[api]` → prueba el request/response contra la API de Zipnova, o el endpoint de webhook del plugin (`?wc-api=zippin`)
- `[log]` → verificación vía WooCommerce > Estado > Registros (source `zippin`)

---

## Paso 5 – Construir y guardar el .md

> ⚠️ **Sustituir TODOS los marcadores `{...}` por sus valores reales** al escribir el archivo (ej: `{id}` → `SHPB-379`, `{card-title}` → título real, `{id-branch}` → `feature/SHPB-379`). **No debe quedar ningún `{placeholder}` literal** en el `.md`.

Guardar en `{output}` con la siguiente estructura:

---

```
# Testing – {id} – {card-title}
🔗 [{card-url}]({card-url})
{si pr-url existe → "🔀 PR: [{pr-url}]({pr-url})"}

---

## 🔧 Ambiente de pruebas

* **Entorno:** `local` / `staging` / `producción`
* **Rama testeada:** `{id-branch}`
* **País/cuenta Zipnova:** `AR` / `CL` / `MX`  (opción `zippin_domain`)
* **HPOS:** `activo` / `desactivado`
* **Fecha de la prueba:** `DD-MM-AAAA`
* **Tester:** `@nombre_usuario`

---

## ✅ Criterios de aceptación

[Extraídos de la card de Linear]

- [ ] Criterio 1
- [ ] Criterio 2
- [ ] Criterio 3

---

## 🧩 Casos de prueba

### 1️⃣ [Nombre del caso] `[tipo]`

* **Descripción:**
  Se valida que al [acción], el sistema [resultado esperado].

* **Datos utilizados:**
  [Orden, producto, país/cuenta, opciones del plugin, estado inicial necesario]

* **Resultado esperado:**
  [Lo que debería ocurrir]

* **Resultado obtenido:**
  `📝 Completar al ejecutar`

* **Evidencia:**
  `📎 Adjuntar imagen o link a captura`

---

[Repetir para cada caso en lista plana numerada]

---

## 📌 Observaciones

> Completar durante la ejecución: bugs encontrados, comportamientos inesperados, dudas funcionales, sugerencias de mejora.

---

## 📊 Resumen de pruebas
> 💡 *Copiá este bloque en el ticket padre de Linear como referencia de cobertura.*

### ✅ Pruebas Funcionales
- [ ] [Nombre del caso 1]
- [ ] [Nombre del caso 2]

### 🔬 Pruebas No Funcionales
- [ ] [Nombre del caso N]

> **Total:** XX casos de prueba

---

## 🧮 Resultado final

> ✅ **Aprobada**
> ❌ **Rechazada**
> 📬 Se notifica a `@{card-assignee}` para revisión.

---
---

# 🛠️ PARTE 2 – Guía técnica de ejecución

> Esta sección es de uso interno del tester. No se pega en Linear.
> Contiene los pasos técnicos detallados para ejecutar cada caso de la PARTE 1.

---

### Caso 1 – [Nombre del caso] `[tipo]`

#### 📋 Precondiciones
> Qué debe existir antes de ejecutar este caso.
- [Ej: cuenta Zipnova configurada para {país} (opciones zippin_api_key/zippin_api_secret/zippin_domain/zippin_account_id/zippin_origin_id)]
- [Ej: producto con peso y dimensiones definidas]
- [Ej: orden en estado previo al configurado en zippin_shipping_status]

#### 🔍 Descubrimiento de datos
> Query SQL o comando WP-CLI para encontrar registros válidos en el entorno.
```sql
-- Órdenes (HPOS activo)
SELECT id, status, billing_email FROM wp_wc_orders ORDER BY id DESC LIMIT 5;
-- Órdenes (legacy, sin HPOS)
SELECT ID, post_status FROM wp_posts WHERE post_type = 'shop_order' ORDER BY ID DESC LIMIT 5;
-- Opciones del plugin
SELECT option_name, option_value FROM wp_options WHERE option_name LIKE 'zippin_%';
```
```bash
# Alternativa con WP-CLI
wp option get zippin_domain
wp wc shop_order list --user=admin --format=table 2>/dev/null | head
```

#### ⚙️ Script de ejecución
> Comando listo para correr. Reemplazar los valores entre [corchetes].
```bash
# Ejecutar lógica del plugin con WordPress cargado
wp eval '
$order = wc_get_order([ID_ORDEN]);
$connector = new \Zippin\Zippin\ZippinConnector();
$shipment = $connector->create_shipment($order);
var_dump($shipment);
'
```
> O para cotización:
```bash
wp eval '
$connector = new \Zippin\Zippin\ZippinConnector();
$destination = ["city" => "[CIUDAD]", "state" => "[ESTADO]", "zipcode" => "[CP]", "country" => "[AR|CL|MX]"];
$items = [["weight" => 1000, "height" => 10, "width" => 10, "length" => 10, "sku" => "test"]];
var_dump($connector->quote($destination, [], $items, 1000));
'
```
> O si es UI (`[browser]`): describir la navegación exacta (carrito → checkout → completar dirección de {país} → verificar opciones de envío).

#### ✅ Validaciones esperadas
| Check | Valor esperado |
|---|---|
| [campo o llamada] | [valor] |
| Log (source zippin) | [sin errores / mensaje esperado] |

#### 📝 Mini-resumen
> Completar al ejecutar y usar para el campo "Resultado obtenido" de la PARTE 1.

**Datos utilizados:**
- [Completar: orden X, país Y, opciones Z]
**Resultado:**
- [Completar: valor obtenido, comportamiento observado]

---

[Repetir para cada caso]
```

---

## Paso 6 – Confirmar al usuario

Una vez guardado el plan, informar:
- ✅ Plan de pruebas: `{output}`
- 📋 Cantidad de casos generados por tipo (Funcionales: X, No Funcionales: X, Total: X)
- 🌿 Rama detectada: `{id-branch}`
- 🔀 PR asociado: `{pr-url}` (si existe)
- 🏷️ Labels relevantes detectadas: `{labels}` (si aportan contexto, ej. país)
- 🔗 Tickets relacionados incluidos: padre / sub-issues (si los hubo)
- 🐛 Error de logs cruzado: `{error-summary}` (si se identificó uno relacionado)
- 📂 Archivos de código analizados: listar los más relevantes
