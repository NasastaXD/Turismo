=== Caaguazú SSO CEAD ===
Contributors: municipalidadcaaguazu
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Acceso de un clic desde el panel del CEAD al Portal de Promotores Turísticos, sin registro nuevo ni contraseña propia del portal.

== Description ==

El CEAD tiene un curso de Servicios Turísticos. Este plugin deja que su alumnado
y docentes entren al Portal de Promotores Turísticos con un clic desde el panel
del colegio, ya identificados y con el rol que les corresponde acá.

El CEAD afirma quién es la persona (un código opaco de un solo uso, servidor a
servidor); este plugin decide qué cuenta y qué permisos le corresponden en el
Portal. No crea usuarios de WordPress ni toca su cookie — todo corre sobre
`caaguazu-cuentas`, el sistema de cuentas universal del ecosistema.

**IMPORTANTE — este plugin va en caaguazu.net, NO en el sitio del CEAD.**

La integración tiene dos mitades, en dos WordPress distintos, que no comparten
código: sólo el secreto compartido y el contrato HTTP.

| Dónde | Qué corre ahí | Qué hace |
|---|---|---|
| **caaguazu.net** | **este plugin** (`caaguazu-sso-cead`) | Recibe `/acceso-cead?code=`, canjea el código contra el CEAD, resuelve la cuenta, aplica el rol y abre sesión. |
| **sitio del CEAD** | su propio plugin (`cead-acad`) | Marca el curso participante, genera el código, expone `/wp-json/cead-sso/v1/redeem` y pinta el botón "Ir al portal". |

Por eso este plugin declara `caaguazu-cuentas` y `caaguazu-portal` como
dependencias: los tres viven en caaguazu.net. La mitad del CEAD **no depende de
ninguno de ellos** — es WordPress pelado más el endpoint del contrato.

**Reglas de negocio, ya decididas para esta integración:**

* Un email que ya tiene cuenta en el portal, pero sin vincular a un `cead_uid`
  todavía, se **rechaza** — no se vincula solo por email. Es la puerta de un
  robo de cuenta (quien controle ese email en el CEAD pasaría a manejar la
  cuenta existente). Un admin lo vincula a mano desde
  **Herramientas → Vincular cuenta CEAD**.
* Rol lógico → rol del panel `promotor` de `caaguazu-portal`:
  `alumno_turismo` → Mini Promotor, `docente_turismo` → Promotor. Un rol que
  el CEAD mande y no esté en ese mapa se rechaza (no se inventa un permiso).
* Entran al panel `promotor` que ya existe — no a uno aparte.

== Instalación ==

Todo esto es **en caaguazu.net** (ver el cuadro de arriba: en el sitio del CEAD
no se instala nada de este plugin).

1. Requiere `caaguazu-cuentas` y `caaguazu-portal` activos en ese mismo sitio.
2. Subir `caaguazu-sso-cead` a `/wp-content/plugins/` de caaguazu.net y activar.
3. Definir en el `wp-config.php` de caaguazu.net (nunca como opción editable
   desde la base), antes de la línea `/* That's all, stop editing! */`:

   ```php
   define( 'CEAD_TUR_SSO_SECRET', '…64 hex; generar con: openssl rand -hex 32…' );
   define( 'CEAD_TUR_SSO_URL', 'https://<sitio-del-cead>/wp-json/cead-sso/v1/redeem' );
   ```

   El **secreto va idéntico en los dos sitios** (un lado firma, el otro
   verifica). La **URL es direccional**: acá se guarda la del CEAD; el CEAD, a
   su vez, guarda `https://caaguazu.net/acceso-cead` para armar su botón.
   `CEAD_TUR_SSO_URL` no se define en el sitio del CEAD — ese sitio *sirve* ese
   endpoint, no se lo llama a sí mismo.

4. Ir a **Ajustes → Enlaces permanentes** y guardar (activa la rewrite rule
   de `/acceso-cead`).
5. El botón "Ir al portal" del panel del CEAD apunta a
   `https://caaguazu.net/acceso-cead?code=<código>`.

== Auditoría ==

Cada intento de canje (éxito, rechazo o error) queda en
**Herramientas → Vincular cuenta CEAD**, con el motivo cuando se rechazó.

== Seguridad ==

* El navegador nunca ve datos de la persona — solo un código sin significado
  en la URL. El intercambio real (código → email/nombre/rol) es servidor a
  servidor, firmado con HMAC-SHA256 y una ventana de 5 minutos contra
  desfase de reloj.
* La ruta pública (`/acceso-cead`) no acepta ningún destino de redirección
  desde la URL — siempre termina en el panel del Portal. Nada de `next=` ni
  `redirect_to=`: eso sería un open-redirect justo después de abrir sesión.
* Sesión de SSO sin "recordarme" (dura lo que dura cualquier sesión del
  sistema de cuentas, no más) — el acceso vive del vínculo con el CEAD.
