---
allowed-tools: Bash(gh pr list:*), Bash(gh pr view:*), Bash(gh pr diff:*), Bash(gh api:*), Bash(git log:*), Bash(git diff:*), Bash(git branch:*), Bash(git show:*), Bash(grep:*), Bash(rg:*), Bash(find:*), Bash(ls:*), Read
description: Revisión de una rama release antes de publicar la nueva versión del plugin — bump de versión, opciones nuevas sin default, regresiones HPOS/multi-país, conflictos entre tickets.
---

## Contexto del proyecto

**Stack:** Plugin de WordPress para **WooCommerce**. PHP 7+ (`Requires PHP: 7`, `WC requires at least: 4.0.0`, `WC tested up to: 9.3.3`, WP `Tested up to: 6.7.2`). **Sin framework, sin composer, sin autoload PSR-4, sin PHPUnit, sin migraciones, sin colas, sin `.env`.** Los archivos se cargan con `require_once` desde el archivo principal. Usa la HTTP API de WordPress (`wp_remote_*`), APIs de WooCommerce (`wc_get_order`, `WC()->cart`, `WC()->customer`, `wc_get_logger`, `WC_Shipping_Method`), opciones de WordPress (`get_option`/`update_option`) y meta de órdenes (`get_meta`/`update_meta_data`). Compatible con **HPOS**.

**Qué es este proyecto:** integración de **WooCommerce con Zipnova** (logística/envíos multi-transporte en Argentina, Chile y México): cotización en el carrito, creación automática del envío al alcanzar el estado configurado, detalle/etiqueta del envío en la orden, y página de tracking vía shortcode.

**Estructura (7 archivos PHP planos en la raíz):**
- `woocommerce-zippin.php` — archivo principal: header, constantes (`ZIPPIN_VERSION`, `ZIPPIN_APIKEY/SECRETKEY/DOMAIN`, `ZIPPIN_LOGGER_CONTEXT`), declaración HPOS, `register_activation_hook`, `require_once`
- `hooks.php` — registro central de hooks (`add_action`/`add_filter`/`add_shortcode`) → funciones de `Zippin\Zippin\Utils\*`
- `helper.php` — clase `Zippin\Zippin\Helper`: dominios/estados por país, parseo de direcciones, armado de destino/items/paquetes
- `utils.php` — namespace `Zippin\Zippin\Utils`: método, meta de orden, `process_order_status` (disparo de creación de envío), caja de orden, botón, tracking, webhook, `activate_plugin`
- `zippin-connector.php` — clase `Zippin\Zippin\ZippinConnector`: cliente de la API de Zipnova (`call_api`, `create_shipment`, `quote`, `get_shipment`, ...)
- `zippin-method.php` — clase `Zippin\Zippin\WC_Zippin` (extiende `WC_Shipping_Method`): `calculate_shipping`
- `zippin-settings.php` — namespace `Zippin\Zippin\Settings`: página de configuración

**Patrones y convenciones críticas:**
- Naming legado **"zippin"** (namespace, method id `'zippin'`, opciones/meta `zippin_*`) — histórico; de cara al usuario es **"Zipnova"**. Shortcodes soportados: `zippin_tracking` y `zipnova_tracking`.
- Toda llamada a la API de Zipnova pasa por `ZippinConnector::call_api` (`wp_remote_*`, Basic auth, `/v2`). Base URL derivada del dominio del país salvo override con la constante `ZIPNOVA_API_BASE_URL`.
- Configuración/credenciales en opciones de WordPress (`get_option('zippin_*')`), NO en variables de entorno. Override por constantes solo en activación.
- **Multi-país:** `Helper::get_domains()` → AR/CL/MX (`domain`, `use_zipcode`, `zipcode_length`). Lógica condicional por país dispersa (ej. CL sin zipcode; estados AR/MX vs CL).
- **HPOS:** `$order->get_meta()`/`update_meta_data()`+`save()`, nunca `get_post_meta`/`update_post_meta`; screen ids resueltos con `OrderUtil::custom_orders_table_usage_is_enabled()`.
- Datos del envío en meta serializada: `zippin_shipping_info` y `zippin_shipment`. Envío creado en `woocommerce_order_status_changed` al alcanzar `get_option('zippin_shipping_status')`.
- Logging vía `wc_get_logger()` (source `zippin`), visible en WooCommerce > Estado > Registros. **No hay Sentry.**

**Versión del plugin — se declara en 4 lugares que deben ir sincronizados:**
1. `woocommerce-zippin.php` header → `* Version: X.Y.Z`
2. `woocommerce-zippin.php` → `define('ZIPPIN_VERSION', 'X.Y.Z');`
3. `readme.txt` → `Stable tag: X.Y.Z`
4. `readme.txt` → entrada `= X.Y.Z (YYYY-MM-DD) =` al tope del `== Changelog ==`

**Convención de release:** git-flow con tags `vX.Y.Z`. Ramas `master` (producción) y `develop`. Repo GitHub `zipnova/woocommerce-zipnova`. Prefijo de tickets: `SHPB` (ramas tipo `feature/SHPB-379`). No hay CI/deploy automático de infra: "publicar" = crear el tag `vX.Y.Z` (y, si aplica, distribuir el `.zip` del plugin / actualizar el repositorio de WordPress.org).

---

## Tu tarea

Sos el revisor técnico de una rama release antes de publicar la nueva versión del plugin. La release agrupa múltiples PRs/tickets. Tu objetivo es detectar riesgos sistémicos que en la revisión de cada PR individual no son visibles: conflictos entre features, regresiones de HPOS o multi-país, opciones nuevas sin default o sin upgrade, bump de versión inconsistente, y pasos que hay que ejecutar al publicar/actualizar.

---

### Paso 1 — Identificar la rama y los commits incluidos

Si se pasa el nombre de rama como argumento, usarlo. Si no, usar la rama actual:
```bash
git branch --show-current
git log --oneline origin/develop..HEAD
git log --oneline origin/develop..HEAD | grep -oE "SHPB-[0-9]+" | sort -u
git diff --name-only origin/develop...HEAD
```

### Paso 2 — Entrar en modo plan

Todo el análisis es read-only. No ejecutar ningún cambio hasta que el usuario haya decidido qué hacer con cada hallazgo.

### Paso 3 — Resumen ejecutivo

Presentar:
- Versión/nombre de la release y rama
- Tickets incluidos agrupados por área: `cotización` (checkout/`WC_Zippin`) · `creación de envío` (`process_order_status`/`ZippinConnector`) · `armado de destino/paquetes` (`Helper`) · `webhook` · `multi-país` (AR/CL/MX) · `HPOS` · `settings` · `tracking`
- **Evaluación de riesgo global:** `bajo` / `medio` / `alto`, con justificación de las áreas más sensibles

---

### Paso 4 — Bump de versión y changelog

Verificar que la versión esté subida y **sincronizada en los 4 lugares**:
```bash
git diff origin/develop...HEAD -- woocommerce-zippin.php readme.txt | grep -E "^\+.*(Version:|ZIPPIN_VERSION|Stable tag:|^\+= [0-9])"
grep -nE "Version:|ZIPPIN_VERSION" woocommerce-zippin.php
grep -nE "Stable tag:" readme.txt
```
Hallazgos a detectar:
- Versión subida en unos lugares pero no en otros → `[FIX LOCAL]` crítico (WordPress usa `Stable tag`/header; el código usa `ZIPPIN_VERSION` como `source` en la API)
- Falta la entrada de changelog `= X.Y.Z (fecha) =` en `readme.txt`, o la fecha es inconsistente
- El tag que se va a crear no coincide con la versión del código (recordar: tag `vX.Y.Z`, código `X.Y.Z`)

---

### Paso 5 — Opciones, configuración y dependencias implícitas

Este plugin no tiene `.env` ni migraciones: sus "dependencias implícitas" son **opciones `zippin_*` nuevas** y **campos/endpoints que espera de la API de Zipnova**.

```bash
# Opciones zippin_* nuevas leídas/escritas por la release
git diff origin/develop...HEAD | grep -E "^\+[^\+].*(get_option|update_option)\('zippin_" | sort -u

# Constantes nuevas (ZIPPIN_*, ZIPNOVA_*) referenciadas
git diff origin/develop...HEAD | grep -E "^\+[^\+].*(ZIPPIN_|ZIPNOVA_)" | sort -u

# Endpoints/campos nuevos de la API de Zipnova
git diff origin/develop...HEAD | grep -E "^\+[^\+].*call_api\(" | sort -u
```

**Para cada opción `zippin_*` nueva leída con `get_option`:**
- ¿Tiene default en la propia llamada (`get_option('zippin_x', default)`)? Si no → en instalaciones existentes devolverá `false`/`null` silenciosamente → `[POST-DEPLOY]`/`[FIX LOCAL]`
- ¿Se setea en algún lado (settings, activación)? Si depende de que el admin la configure para no romper, marcarlo como riesgo de upgrade

**Para cada campo/endpoint nuevo de la API de Zipnova:**
- Confirmar que la API de Zipnova ya lo soporta en el/los países afectados (preguntar al usuario si no se puede verificar desde el repo) → si no, `[PRE-DEPLOY]`: coordinar con backend antes de publicar

---

### Paso 6 — Conflictos entre features

Como el plugin es plano (pocos archivos compartidos), es **muy probable** que varios tickets toquen el mismo archivo. Detectar solapamientos sobre los archivos del dominio crítico:

```bash
git diff --name-only origin/develop...HEAD | grep -E "\.php$"
```

**Paso 6a — Mapa de solapamiento**

Para cada archivo `.php` modificado, identificar qué tickets lo tocaron (`git log --oneline origin/develop..HEAD -- {archivo}`). Construir una tabla:

| Archivo | Tickets que lo tocan |
|---------|----------------------|

Solo profundizar en archivos tocados por 2+ tickets (especialmente `helper.php`, `utils.php`, `zippin-connector.php`, `zippin-method.php`).

**Paso 6b — Análisis de compatibilidad** (solo para solapamientos detectados)

Para cada solapamiento, leer los diffs de ambos tickets sobre ese archivo y evaluar:
- ¿Modifican la misma función con semántica distinta (ej. dos cambios distintos a `create_shipment`, `quote`, `calculate_shipping`, `get_destination_from_order`)?
- ¿Un cambio deja un campo del payload/meta en `null`/vacío que el otro asume presente?
- ¿Cambian la firma de un método que el otro también llama (ej. parámetros de `ZippinConnector::quote`)?
- ¿Registran el mismo hook en `hooks.php` con handlers distintos, o pisan el mismo `add_filter`/`add_action`?
- ¿Introducen lógica por país contradictoria en `Helper`?

Ignorar solapamientos en: CSS/JS, imágenes, textos de `readme.txt` (salvo el changelog/versión).

---

### Paso 7 — HPOS, activación y compatibilidad

```bash
# Uso de APIs incompatibles con HPOS introducidas por la release
git diff origin/develop...HEAD | grep -E "^\+[^\+].*(get_post_meta|update_post_meta|delete_post_meta|WP_Query|get_posts)\(" | grep -v test

# Cambios en la rutina de activación o en el header de compatibilidad
git diff origin/develop...HEAD -- woocommerce-zippin.php | grep -E "^\+.*(register_activation_hook|Requires PHP|WC requires at least|WC tested up to|Tested up to|declare_compatibility)"
```

Detectar:
- Uso nuevo de `get_post_meta`/`update_post_meta`/`WP_Query` sobre órdenes → **rompe HPOS** → crítico
- Cambios en `activate_plugin` (`utils.php`) que asuman estado nuevo → verificar retrocompatibilidad para sitios ya instalados que actualizan (no re-ejecuta la lógica de primera instalación)
- Bajar `Requires PHP`/`WC requires at least` o subir mínimos sin justificación → riesgo de compatibilidad con sitios existentes

---

### Paso 8 — Presentar hallazgos

Por cada hallazgo, mostrar exactamente este formato:

```
---
📍 ÁREA: {Versión & Changelog / Opciones & Config / Conflicto entre tickets / HPOS & Activación / API Zipnova / Multi-país}
🎫 TICKETS: {SHPB-XXX, SHPB-YYY}
ARCHIVO(S): {ruta/al/archivo.php} (líneas X–Y si aplica)

CÓDIGO AFECTADO:
{snippet relevante si aplica}

🔴/🟡/🔵 SEVERIDAD: critical / warning / suggestion

PROBLEMA: {descripción clara}
IMPACTO: {qué puede pasar en producción / en los sitios que actualicen}

ACCIÓN RECOMENDADA: {runbook concreto o fix específico}
TAG DE DEPLOY: [PRE-DEPLOY] / [AUTO] / [POST-DEPLOY] / [FIX LOCAL]
```

**Severidades:**
- `critical` 🔴 — rompe la cotización/creación de envíos, expone datos, rompe HPOS, o deja la versión inconsistente
- `warning` 🟡 — problema real; degradación o comportamiento inesperado en algún país/escenario
- `suggestion` 🔵 — mejora recomendada, bajo riesgo

**Qué NO reportar:**
- Falta de tests
- Formato de código / phpcs / estilos
- El naming legado "zippin"
- Convenciones cosméticas

---

### Paso 9 — Esperar decisión del usuario

Por cada hallazgo el usuario puede:
- **Confirmar** → agregar al checklist final de release
- **Descartar** → ignorar
- **Fix local** → marcar para aplicar el cambio ahora mismo
- **Preguntar más** → profundizar sin salir del plan

No salir del modo plan hasta que todos los hallazgos tengan decisión o el usuario lo indique.

---

### Paso 10 — Checklist de release

Una vez cerrados todos los hallazgos, generar el checklist consolidado a partir de los tags asignados:

```
## Checklist release {vX.Y.Z}

### Antes de publicar
- [ ] Versión sincronizada en los 4 lugares (header, ZIPPIN_VERSION, Stable tag, changelog)
- [ ] {hallazgos [PRE-DEPLOY]: campos/endpoints de la API de Zipnova disponibles en AR/CL/MX}
- [ ] {fixes locales pendientes}
- [ ] {opciones zippin_* nuevas con default u onboarding resuelto}

### Publicación
- [ ] git flow release finish (merge a master + develop, tag vX.Y.Z)
- [ ] {si aplica: generar .zip del plugin / subir a WordPress.org SVN}

### Después de publicar
- [ ] {hallazgos [POST-DEPLOY]}
- [ ] Verificar en un sitio real: cotización en el carrito por país (AR/CL/MX)
- [ ] Verificar creación de envío al pasar una orden al estado configurado
- [ ] Verificar detalle/etiqueta del envío en la orden (con HPOS activo y sin HPOS)
- [ ] Revisar WooCommerce > Estado > Registros (source zippin) por errores nuevos
```

---

### Paso 11 — Mensaje Slack para el equipo

Una vez confirmados todos los hallazgos y generado el checklist, producir el siguiente mensaje listo para pegar en Slack. Sin links, sin separadores, solo referencias a los tickets. Íconos únicamente en los títulos de sección.

Formato exacto:

```
:rocket: RELEASE — {vX.Y.Z del plugin WooCommerce} @channel

:ballot_box_with_check: *Qué se sube*
• {SHPB-XXX} — {descripción en una línea, sin backticks ni markdown}
• {sin ticket} — {descripción en una línea}
...

:eyes: *Post release*
• {verificación 1}
• {verificación 2}
...
```

Reglas:
- Cada ítem de "Qué se sube": empezar con el ID del ticket si existe, luego ` — ` y la descripción en lenguaje llano. Si no tiene ticket, empezar directo con la descripción.
- "Post release": solo verificaciones concretas (cotización por país, creación de envío, HPOS, logs source zippin) más los puntos a confirmar con autores si los hay.
- Sin bloques de código, sin separadores ━━━, sin iconos en los bullets.
