# Plan de Acción - Implementación del Webhook Evolution API

Este plan detalla los pasos para implementar el manejo de webhooks de Evolution API en el plugin `crm-evolution-sender`, comenzando por lo más simple y probando cada avance. Las modificaciones se realizarán archivo por archivo.

**1. Registrar Payload Completo**

*   **Archivo:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-rest-api.php`
*   **Acción:** Modificar `crm_evolution_webhook_handler_callback` para registrar (usando `crm_log`) el cuerpo JSON completo recibido.
*   **Objetivo:** Confirmar recepción de datos y funcionamiento básico del endpoint.

**2. Extraer Evento e Instancia**

*   **Archivo:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-rest-api.php`
*   **Acción:** Modificar `crm_evolution_webhook_handler_callback` para extraer y registrar específicamente los campos `event` e `instance` del JSON.
*   **Objetivo:** Verificar el parseo básico y la obtención de identificadores clave.

**3. Implementar Estructura `switch`**

*   **Archivo:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-rest-api.php`
*   **Acción:** Implementar la estructura `switch ($data['event'])`. Para cada `case` conocido, registrar un mensaje simple indicando el evento recibido.
*   **Objetivo:** Establecer la lógica de enrutamiento para diferentes eventos.

**4. Extraer Datos Básicos del Mensaje (`messages.upsert`)**

*   **Archivo:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-rest-api.php`
*   **Acción:** Dentro del `case 'messages.upsert':`, extraer y registrar: `instance`, `sender`, `data.key.remoteJid`, `data.key.fromMe`, `data.messageType`, `data.messageTimestamp`, `data.key.id`.
*   **Objetivo:** Asegurar el acceso a la información esencial del mensaje.

**5. Identificar Usuario WP Asociado (`messages.upsert`)**

*   **Archivo:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-rest-api.php` (y posibles funciones auxiliares)
*   **Acción:** Dentro del `case 'messages.upsert':`, implementar lógica básica para buscar/identificar el usuario WP asociado al `data.key.remoteJid` usando `crm_get_user_id_from_jid` (busca por `billing_phone`). Registrar si existe o necesita creación y su ID.
*   **Objetivo:** Conectar mensajes con usuarios de WordPress.
*   **Nota:** No crea usuarios aún en este paso, solo busca.

**6. Crear Post `crm_chat` para Mensajes de Texto (`messages.upsert`)**

*   **Archivo:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-rest-api.php`
*   **Acción:** Dentro del `case 'messages.upsert':`, si el mensaje es de tipo `conversation`, crear un post simple `crm_chat` con el texto como `post_content`. Registrar el ID del post creado.
*   **Objetivo:** Probar la creación del CPT para almacenar mensajes.

**7. Añadir Metadatos Básicos al Post `crm_chat` (`messages.upsert`)**

*   **Archivo:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-rest-api.php`
*   **Acción:** Añadir metadatos al post `crm_chat`: `_crm_instance_name`, `_crm_is_outgoing`, `_crm_message_id_wa`, `_crm_timestamp_wa`, `_crm_associated_user_id`.
*   **Objetivo:** Guardar información esencial asociada al mensaje (`_crm_sender_jid`, `_crm_recipient_jid`, `_crm_message_type`, `_crm_is_group_message` también se añadieron).

**8. Manejar Multimedia (Base64) (`messages.upsert`)**

*   **Archivo:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-rest-api.php`
*   **Acción:** Dentro del `case 'messages.upsert':`, si el mensaje es multimedia con `base64` (ubicado en `data.message.base64`), usar `crm_process_base64_image` para guardar en la Biblioteca de Medios y asociar al post `crm_chat` (guardando ID en `_crm_media_attachment_id`). Extraer `mimetype` y `caption` de `data.message.[tipoMessage]`.
*   **Objetivo:** Implementar manejo de archivos adjuntos recibidos.

**9. Iteración y Refinamiento**

*   **Acciones Implementadas:**
    *   **Creación Automática de Usuarios:** Si `crm_get_user_id_from_jid` no encuentra un usuario para un JID individual, se llama a `crm_create_user_from_webhook` para crearlo (con rol 'customer', username `wa_NUMERO`, email placeholder, y guardando `billing_phone`, `display_name`, `_crm_evolution_jid`).
    *   **Obtención de Avatar:** Después de crear un usuario nuevo, se llama a `crm_fetch_and_save_avatar`. Esta función:
        *   Obtiene la **URL y API Key globales** de la configuración del plugin (`get_option('crm_evolution_api_url')`, `get_option('crm_evolution_api_token')`).
        *   Llama al endpoint `/chat/fetchProfilePictureUrl/{instance}` de la API de Evolution.
        *   Si obtiene una URL de avatar (`pps.whatsapp.net/...`), usa `crm_process_url_image` para descargarla, guardarla en la Biblioteca de Medios y almacenar el `attachment_id` en el metadato `_crm_avatar_attachment_id` del usuario.
    *   **Manejo Básico de Otros Eventos:** Se añadió lógica básica en el `switch` para `connection.update` y `qrcode.updated` (solo logging por ahora).
*   **Acciones Pendientes:**
    *   Añadir manejo de errores más robusto.
    *   Implementar lógica completa para `connection.update`, `qrcode.updated`, `groups.upsert`, `group.update`, `group.participants.update`.
    *   Añadir soporte para más tipos de mensajes multimedia (audio, documento, sticker) y otros (ubicación, contacto).
    *   Implementar seguridad en el `permission_callback` del webhook.
    *   Refinar la gestión de grupos ("usuario-grupo").
*   **Objetivo:** Completar, robustecer y ampliar la funcionalidad del webhook.

