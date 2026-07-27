---
allowed-tools: Bash(gh pr view:*), Bash(gh pr diff:*), Bash(gh api:*), Bash(git log:*), Bash(git diff:*), Bash(git branch:*), Read, Bash(grep:*), Bash(rg:*)
description: Review a pull request
---

## Contexto del proyecto

**Stack:** Plugin de WordPress para **WooCommerce**. PHP 7+ (header `Requires PHP: 7`, `WC requires at least: 4.0.0`, `WC tested up to: 9.3.3`, `Tested up to` WP 6.7.2). **Sin framework, sin composer, sin autoload PSR-4, sin PHPUnit, sin migraciones ni colas.** Los archivos se cargan con `require_once` desde el archivo principal. Usa la HTTP API de WordPress (`wp_remote_get/post/request`), APIs de WooCommerce (`wc_get_order`, `WC()->cart`, `WC()->customer`, `wc_get_logger`, `WC_Shipping_Method`), opciones de WordPress (`get_option`/`update_option`) y meta de órdenes (`get_meta`/`update_meta_data`). Compatible con **HPOS**.

**Qué es este proyecto:** integración de **WooCommerce con Zipnova** (plataforma de logística/envíos multi-transporte en Argentina, Chile y México). Permite que el comprador **cotice** el costo de envío desde el carrito con múltiples transportes, que se **cree el envío en Zipnova automáticamente** cuando la orden alcanza el estado configurado, ver el detalle/etiqueta del envío desde la orden, y ofrece una **página de tracking** vía shortcode.

**Estructura (7 archivos PHP planos en la raíz):**
- `woocommerce-zippin.php` — archivo principal: header del plugin, constantes (`ZIPPIN_VERSION`, `ZIPPIN_APIKEY/SECRETKEY/DOMAIN`, `ZIPPIN_LOGGER_CONTEXT`), declaración de compatibilidad HPOS, `register_activation_hook`, y los `require_once` de los demás archivos
- `hooks.php` — **registro central** de todos los hooks de WordPress/WooCommerce (`add_action`/`add_filter`/`add_shortcode`) que conectan eventos con funciones de `Zippin\Zippin\Utils\*`
- `helper.php` — clase `Zippin\Zippin\Helper`: dominios por país (`get_domains`/`get_current_domain`), estados por país (`get_states`/`get_state_name`), parseo de direcciones (`get_address`/`get_floor_and_apt`) y armado de destino/items/paquetes desde orden o carrito
- `utils.php` — funciones del namespace `Zippin\Zippin\Utils`: registro del método (`add_method`), guardado de meta de orden (`update_order_meta`), procesamiento del cambio de estado y disparo de creación de envío (`process_order_status`), caja lateral en la orden (`add_order_side_box`/`box_content`), botón de acción (`add_action_button`), shortcode de tracking (`create_shortcode`), handler de webhook (`handle_webhook`), activación (`activate_plugin`)
- `zippin-connector.php` — clase `Zippin\Zippin\ZippinConnector`: **cliente de la API de Zipnova** (`call_api` con `wp_remote_*`, Basic auth, endpoints `/v2`), `create_shipment`, `quote`, `get_shipment`, `get_origins`, `get_account`
- `zippin-method.php` — clase `Zippin\Zippin\WC_Zippin` (extiende `WC_Shipping_Method`): cotización en el checkout (`calculate_shipping`) y armado de rates
- `zippin-settings.php` — namespace `Zippin\Zippin\Settings`: página de configuración del plugin

**Patrones y convenciones críticas:**
- Namespaces: `Zippin\Zippin`, `Zippin\Zippin\Utils`, `Zippin\Zippin\Settings`. **Naming legado "zippin"** en todos lados (namespace, method id `'zippin'`, opciones `zippin_*`, meta `zippin_*`) — es histórico; de cara al usuario es **"Zipnova"**. Se soportan ambos shortcodes: `zippin_tracking` y `zipnova_tracking`. No señalar el naming "zippin" como bug.
- **Toda llamada a la API de Zipnova pasa por `ZippinConnector::call_api`** (usa `wp_remote_get/post/request`, NO cURL ni Guzzle directo). La base URL se deriva del dominio del país (`https://api.{dominio-pais}/v2`) salvo override con la constante `ZIPNOVA_API_BASE_URL`.
- La transformación de datos de orden/carrito a payload de la API vive en `Helper`; los hooks (`utils.php`) y el método de envío (`zippin-method.php`) orquestan, no arman el payload a mano.
- Los hooks nuevos se registran en `hooks.php`, no dispersos por otros archivos.
- **Configuración/credenciales en opciones de WordPress** (`get_option('zippin_*')`), NO en variables de entorno. Hay override por constantes solo en la activación (`ZIPPIN_APIKEY`/`ZIPPIN_SECRETKEY`/`ZIPPIN_DOMAIN`).
- **Multi-país:** `Helper::get_domains()` define AR/CL/MX, cada uno con `domain`, `use_zipcode`, `zipcode_length`. Hay lógica condicional por país dispersa (ej. CL no envía `zipcode`; el manejo de estados difiere AR/MX vs CL).
- **Compatibilidad HPOS:** el código verifica `\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()` para resolver screen ids y usa `$order->get_meta()`/`$order->update_meta_data()`+`$order->save()` (HPOS-safe), NUNCA `get_post_meta`/`update_post_meta`. En callbacks de admin la orden puede llegar como `WP_Post` o `WC_Order` (ver `box_content`).
- Los datos del envío se guardan **serializados** en meta de orden: `zippin_shipping_info` (preferencias de carrier/servicio) y `zippin_shipment` (respuesta del envío creado).
- El envío se crea al disparar `woocommerce_order_status_changed` cuando la orden llega al estado configurado en `get_option('zippin_shipping_status')` (ver `process_order_status` y `update_order_meta`).
- **Logging** vía `wc_get_logger()` con contexto `unserialize(ZIPPIN_LOGGER_CONTEXT)` (source `zippin`); visible en WooCommerce > Estado > Registros. **No hay Sentry** en este plugin.
- Todo archivo PHP arranca con `if (!defined('ABSPATH')) { exit; }`.

**Áreas críticas del dominio:** cotización en el checkout (`WC_Zippin::calculate_shipping` → `ZippinConnector::quote`), creación del envío al cambiar el estado de la orden (`Utils\process_order_status`/`update_order_meta` → `ZippinConnector::create_shipment`), armado de destino/paquetes (`Helper::get_destination_from_order`/`get_items_from_order`/`get_address`), handler de webhook (`Utils\handle_webhook`), lógica multi-país (`Helper::get_domains`/`get_state_name`), compatibilidad HPOS, tracking (`Utils\create_shortcode`) y la página de settings (`Settings\*`).

---

## Tu tarea

### Paso 1 — Detectar el PR

Si se pasó un número como argumento usalo. Si no, detectar automáticamente:
```
gh pr view --json number,title,body,headRefOid -q '{number: .number, title: .title, body: .body, commit: .headRefOid}'
```

### Paso 2 — Entrar en modo plan

Todo el análisis es read-only. No ejecutar ningún cambio hasta que el usuario haya decidido qué hacer con cada comentario.

### Paso 3 — Obtener el diff completo

```
gh pr diff {PR}
```

Leer los archivos modificados que sean relevantes para entender el contexto completo.

### Paso 4 — Resumen del PR

Presentar:
- Qué hace y cuál es su propósito
- Archivos principales modificados y qué cambió en cada uno
- **Evaluación de riesgo/impacto:** `bajo` / `medio` / `alto`, justificando si toca áreas críticas (cotización, creación de envío, `ZippinConnector`, armado de destino/paquetes, webhook, lógica multi-país, HPOS)

### Paso 5 — Análisis con checklist

Revisar cada punto. Solo reportar los que tienen findings reales — no mencionar los que están bien.

**Performance**
- No llamar a la API de Zipnova (`ZippinConnector`) dentro de loops de productos/órdenes — cada `call_api` es un request HTTP con timeout de 25s
- Nada de `wc_get_order()`, `get_post_meta()`, `wc_get_product()` ni queries dentro de `foreach` sobre items o resultados de cotización
- `calculate_shipping` corre en cada recálculo del carrito/checkout — evitar trabajo caro o requests redundantes; respetar la limpieza de caché de `clear_cache`
- Respetar el límite conocido de no cotizar más de 1000 items (ya validado en `ZippinConnector::quote`)

**Arquitectura y convenciones**
- Namespace `Zippin\Zippin` (`\Utils`, `\Settings`) en código nuevo
- Llamadas a la API de Zipnova pasan por `ZippinConnector`, no `wp_remote_*`/cURL sueltos en hooks o en el método de envío
- La transformación de datos de orden/carrito a payload vive en `Helper`, no inline en hooks ni en el método
- Hooks nuevos registrados en `hooks.php`
- Configuración vía `get_option('zippin_*')` con default explícito; no hardcodear credenciales ni dominios
- Todo archivo nuevo con `if (!defined('ABSPATH')) { exit; }`

**Compatibilidad HPOS**
- Usar `$order->get_meta()`/`$order->update_meta_data()`+`$order->save()`, NUNCA `get_post_meta`/`update_post_meta`/`get_post`/`WP_Query` sobre `shop_order`
- Screens/hooks de admin deben resolver el screen id con `OrderUtil::custom_orders_table_usage_is_enabled()` (ver `add_order_side_box`/`add_button_css_file`)
- Obtener la orden contemplando que el objeto recibido puede ser `WP_Post` o `WC_Order` (ver `box_content`)

**Multi-país (AR/CL/MX)**
- La lógica condicional por país debe apoyarse en `Helper::get_current_domain()`/`get_domains()`, no en comparaciones hardcodeadas dispersas
- Respetar `use_zipcode`/`zipcode_length` por país (ej. CL no envía `zipcode`)
- Nombres de estados/provincias vía `Helper::get_state_name()`/`get_states()`

**Seguridad (WordPress)**
- Sanitizar toda entrada de `$_REQUEST`/`$_POST`/`$_GET` (`sanitize_text_field`, `sanitize_email`, `filter_var`, etc.) — ver el shortcode de tracking (`create_shortcode`) y el handler de webhook (`handle_webhook`)
- Escapar toda salida a HTML (`esc_html`, `esc_url`, `esc_attr`) — hay `echo` con datos de la orden/envío en `box_content` y en `create_shortcode`; señalar salida sin escapar de datos que provienen de la API o del usuario
- Acciones de admin y formularios que muten estado deben verificar nonce y capability
- No loguear credenciales (`zippin_api_key`/`zippin_api_secret`) en texto plano
- El handler de webhook (`handle_webhook`) **no verifica firma ni autentica el origen** — hoy solo consulta el shipment por `external_id` y actualiza la orden. Señalar solo si un cambio hace confiar en campos del payload para algo más sensible que eso (ej. cambiar estado/monto de la orden directamente desde el payload)

**Órdenes / lógica de envío**
- La meta serializada (`zippin_shipping_info`, `zippin_shipment`) se lee con `unserialize` — verificar el manejo cuando está vacía o vale `b:0;`
- Idempotencia: no crear un envío duplicado si ya existe `zippin_shipment` (ver las guardas en `process_order_status`)
- Manejo de fallos de la API: `create_shipment`/`quote`/`get_shipment` devuelven `false` — el consumidor debe manejarlo (nota de orden, log), no asumir éxito
- Accesos a campos nullables de la orden o de la respuesta protegidos (ej. `carrier`, `destination`, `pickup_points`, `logistic_type`)

**Compatibilidad WordPress / WooCommerce**
- Cambios que usen APIs nuevas de WC/WP deben respetar `WC requires at least` / `Requires PHP` del header del plugin
- Si se agrega una opción nueva (`zippin_*`): definir default en las lecturas `get_option('zippin_x', default)` y contemplar retrocompatibilidad/upgrade para instalaciones existentes
- Si el PR sube la versión del plugin, debe hacerlo en **los 4 lugares** de forma consistente: header `Version:` y `define('ZIPPIN_VERSION', ...)` en `woocommerce-zippin.php`, más `Stable tag:` y la entrada de changelog en `readme.txt`

### Paso 6 — Presentar comentarios propuestos

Por cada finding, mostrar exactamente este formato:

```
---
📍 ARCHIVO: {ruta/al/archivo.php} (líneas X–Y)

CÓDIGO AFECTADO:
{snippet relevante}

🔴/🟡/🔵 SEVERIDAD: critical / warning / suggestion

PROBLEMA: {descripción clara del problema}
IMPACTO: {qué puede pasar si no se corrige}

SOLUCIÓN: {sugerencia concreta con código si aplica}

ACCIÓN RECOMENDADA: resolver local / enviar al PR
```

**Criterio para recomendar acción:**
- **Resolver local**: fix claro y mecánico (falta escapar salida, sanitizar input, variable sin usar, default faltante en `get_option`, fix obvio de HPOS, typo)
- **Enviar al PR**: requiere cambio de enfoque/arquitectura, decisión de negocio, hay múltiples formas válidas, o el autor necesita entender el problema

**Severidades:**
- `critical` 🔴 — puede romper funcionalidad, exponer datos, o crear envíos incorrectos/duplicados
- `warning` 🟡 — problema real; degradación de performance o comportamiento inesperado
- `suggestion` 🔵 — mejora recomendada, no urgente

**Qué NO comentar:**
- Falta de tests (este repo no tiene suite de tests)
- Formato de código / phpcs / estilos
- El naming legado "zippin" (namespace, opciones, method id) — es histórico
- Convenciones cosméticas menores

### Paso 7 — Esperar decisión del usuario

Por cada comentario el usuario puede elegir:
- **Resolver local** → marcar para aplicar fix al salir del plan
- **Enviar al PR** → marcar para crear comentario en review PENDING
- **Preguntar más** → profundizar análisis sin salir del plan
- **Descartar** → ignorar

No salir del modo plan hasta que TODOS los comentarios tengan decisión.

### Paso 8 — Salir del plan y ejecutar

Una vez que todos los comentarios tienen decisión:

1. **Aplicar fixes locales** marcados como "resolver local"

2. **Enviar review al PR** con los comentarios marcados como "enviar al PR":

```bash
# Obtener posición en el diff para cada comentario
# position = número de línea dentro del diff unificado (no número de línea del archivo)
gh pr diff {PR} 2>/dev/null | awk '
  /^diff --git/ { file=$(0); pos=0 }
  /^@@/ { pos++ }
  pos > 0 { pos++ }
  /PATRON/ && /^\+/ { print NR": "file" | " pos="pos" | "$(0) }
'

# Enviar review (sin campo `event` → queda en estado PENDING, no notifica al autor todavía)
gh api repos/zipnova/woocommerce-zipnova/pulls/{PR}/reviews \
  --method POST \
  --field commit_id="{HEAD_COMMIT}" \
  --field body="{RESUMEN}" \
  --field comments="{COMENTARIOS_JSON}"
```

**Formato de cada comentario inline:**
```
🤖 **[Claude Code] SEVERIDAD — Título del problema**

Descripción del problema e impacto potencial.

**Sugerencia:**
`código de solución`
```

**Body general del review:** resumen con conteo por severidad, ejemplo:
`🔴 2 critical · 🟡 1 warning · 🔵 3 suggestions`
