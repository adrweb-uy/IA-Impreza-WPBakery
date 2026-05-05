=== ADR-IA-Edit ===
Contributors: adrianraineri
Tags: ia, inteligencia artificial, wpbakery, impreza, diseño, claude, gemini, ai, generador
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integra IAs (Anthropic Claude y Google Gemini) en el editor de WordPress para generar diseños con Impreza y WPBakery Page Builder.

== Description ==

**ADR-IA-Edit** es un plugin de WordPress que conecta tu sitio con las IAs más avanzadas del mercado (Anthropic Claude y Google Gemini) para ayudarte a generar código de diseño directamente desde el editor de posts.

**Características principales:**

* 🤖 **Integración con IA**: Soporte para Anthropic Claude y Google Gemini
* 🎨 **Optimizado para Impreza + WPBakery**: Genera shortcodes válidos directamente
* ✍️ **Panel en el editor**: Escribí tu instrucción y la IA genera el código
* ⬆️ **Inserción automática**: El código generado se inserta directamente en el editor
* 🔌 **Extensible**: Arquitectura preparada para agregar nuevos proveedores y temas
* 🔒 **Seguro**: Verificación de nonce y capacidades en todas las peticiones

**Tipos de generación disponibles:**
* Shortcodes de WPBakery Page Builder
* HTML/CSS personalizado
* Mejora del contenido existente

== Installation ==

1. Subí la carpeta `adr-ia-edit` al directorio `/wp-content/plugins/`
2. Activá el plugin en el menú "Plugins" de WordPress
3. Andá a **ADR-IA-Edit → Opciones** y configurá tu API Key
4. Abrí cualquier post o página y usá el panel "ADR-IA-Edit" en la columna lateral

== Frequently Asked Questions ==

= ¿Dónde obtengo las API Keys? =

* **Anthropic (Claude)**: https://console.anthropic.com/account/keys
* **Google Gemini**: https://aistudio.google.com/app/apikey

= ¿Funciona con el editor clásico y Gutenberg? =

Sí, el plugin es compatible con ambos editores. La inserción automática funciona con TinyMCE (editor clásico) y con el editor de bloques (Gutenberg).

= ¿Se puede agregar soporte para otros temas? =

Sí. El plugin está diseñado para ser extensible. Podés agregar nuevos temas usando el filtro `adr_ia_edit_themes` y nuevos proveedores de IA usando `adr_ia_edit_providers`.

== Changelog ==

= 1.0.0 =
* Versión inicial
* Soporte para Anthropic Claude
* Soporte para Google Gemini
* Panel en el editor de posts/páginas
* Página de Opciones con API Keys
* Integración con Impreza + WPBakery Page Builder
* Inserción automática del código generado

== Upgrade Notice ==

= 1.0.0 =
Primera versión del plugin. Instalá y configurá tus API Keys para comenzar.
