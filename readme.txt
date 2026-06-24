=== Envios con Zipnova en Woocommerce ===
Contributors: zippindevelopers
Donate link: https://www.zipnova.com/
Tags: envios, zipnova, zippin, shipping, woocommerce
Requires at least: 4.6
Tested up to: 6.7.2
Requires PHP: 7
Stable tag: 2.6
Language: Spanish
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Conecta tu tienda Woocommerce con Zipnova para realizar envíos en Argentina, Chile y México.

== Description ==

Conecta tu tienda Woocommerce con Zipnova para realizar envíos a todo el país.
Zipnova es una solución para realizar envíos de productos de cualquier tamaño con múltiples operadores logísticos, desde una única plataforma donde gestionarlos.
Este plugin para Woocommerce te permitirá crear envíos en múltiples transportes con una sola integración, a través de Zipnova.

La integración te permitirá:
* Que tus compradores puedan cotizar el costo de envío desde el carrito, con resultados de múltiples transportes, y elegir el de su conveniencia.
* Cuando se cree una orden, se creará en Zipnova un envío en forma automática (cuando la orden alcance el estado que definas).
* Podrás verificar el estado del envío y descargar la etiqueta y la guía del envío desde el detalle de la orden.
* Habilita una página de tracking para que tus compradores sepan el estado del envío.

== Installation ==

Revisa nuestra guía para instrucciones más detalladas: https://ayuda.zipnova.com/instalaci%C3%B3n-y-uso-del-plugin-para-woocommerce

1. Instalar el plugin directamente a través de WordPress. Alternativamente, se pueden subir todos los archivos del plugin a la carpeta `/wp-content/plugins/woocommerce-zippin`.
2. Activar el plugin en WordPress desde la sección Plugins.
3. Configurar el plugin desde Ajustes > Configuración de Zipnova
4. Configurar los tipos de servicio en la configuración de Envíos y Zonas de Woocommerce (Woocommerce > Settings > Shipping > [Nombre de la zona] > Envío con Zipnova/Zippin)


== Frequently Asked Questions ==

= ¿Es necesario tener una cuenta en Zipnova? =
Si, deberás tener una cuenta activa y configurada. Puedes crear una cuenta en https://www.zipnova.com

= ¿Tiene algun costo usar el plugin? =
El uso del plugin es gratuito. Solo pagarás por los envíos que realices.

= ¿En donde se puede usar Zipnova? =
Podrás hacer envíos con transportes de Argentina, Chile y México.


== Screenshots ==

1. Configuración: ![settings](https://zippin-ar.s3.amazonaws.com/help_assets/kzCiQyeFB2uCRcxncq3beOFr5wqLjf9L3G4DWqbp.png)

2. Carrito: ![cart](https://zippin-ar.s3.amazonaws.com/help_assets/vIsIgr5VsVKejiXhmZnC2z7TJcUixtHNn8n30nPl.png)

3. Orden: ![order](https://zippin-ar.s3.amazonaws.com/help_assets/jChwGrciRtmNzEzDqNFq5sOVfG6ClkWDFlWeOHeB.png)

4. Tracking: ![track](https://zippin-ar.s3.amazonaws.com/help_assets/g5OjpvwUHlvwjKkfc4HqAR2pB5QJTrLJytFjraTQ.png)


== Changelog ==

= 2.6 (2026-06-24) =
Fixed reading of DNI/RUT fields with HPOS active
Fixed fatal error on status change for non-Zipnova orders (PHP 8 compatibility)
Added configurable max pickup points setting

= 2.5 (2025-04-04) =
Applied rebranding and renaming of Zippin to Zipnova
Improvements on the tracking page


= 2.4 (2024-09-26) =
Added HPOS compatibility
Fixed bug with virtual products
Fixes bug with regions compatibility setting in CL
Fixes bug with zipcodes in CL

= 2.3 (2023-04-19) =
Added Mexico support

= 2.2 (2022-08-24) =
Bug Fixes

= 2.1 (2022-07-27) =
Bug Fixes
Added compatibility with Contabilium plugin

= 2.0 (2022-05-30) =
Support for Chile
Option to set a customer document custom field
Bug fixing

= 1.7 (2021-09-09) =
Support for express delivery
Added compatibility with Facturante plugin
Performance improvements
Bug fixing

= 1.6 (2020-10-07) =
Support for pickup points delivery
Performance improvements
Bug fixing

= 1.5 (2020-08-18) =
Bug fixing

= 1.4 (2020-06-18) =
Change grouping mode to use new api functionality

= 1.3 (2020-06-03) =
Add free shipping threshold
Add field Additional Charge
Change Rol Access
Change Menu Page
Bug fixes and minor improvements

= 1.2 (2020-05-19) =
Compatibilidad con atributo logistic type
Bugfixing

= 1.1 (2019-06-24) =
Bugfixing

= 1.0 (2019-05-31) =
Primer versión. Permite conectar la cuenta, cotizar y crear envíos.


== Upgrade Notice ==

= 2.0 =
Cambio importante para soporte multipais. Es necesario reconfigurar el plugin.

= 1.0 =
Primer versión.
