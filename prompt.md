# Resumen de Progreso - Integración Webhook Evolution API (CRM Evolution Sender)

**Contexto:** Estamos implementando el manejo del webhook de Evolution API en el plugin `crm-evolution-sender` para WordPress. El objetivo es registrar usuarios y su historial de chat.

**Archivo Principal Modificado:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-rest-api.php`

**Progreso Realizado:**

1.  **Manejo Básico del Webhook (`crm_evolution_webhook_handler_callback`):**
    *   Recibe peticiones POST en `/wp-json/crm-evolution-api/v1/webhook`.
    *   Extrae y loguea el `event` y la `instance`.
    *   Utiliza un `switch` para manejar diferentes eventos (actualmente enfocado en `messages.upsert`).

2.  **Extracción de Datos (`messages.upsert`):**
    *   Extrae correctamente: `remoteJid`, `senderJid`, `fromMe`, `pushName`, `instanceName`.
    *   Extrae datos básicos del mensaje: `message_id_wa`, `timestamp_wa`, `is_group`, `participant_jid`, `message_content` (texto), `message_type`.

3.  **Gestión de Usuarios (`crm_find_or_create_user_with_avatar` y `crm_process_single_jid`):**
    *   **Lógica Principal:** Busca un usuario WP existente por `_crm_whatsapp_jid`.
    *   **Creación Única:** Si el usuario **no existe** y es un JID individual (`@s.whatsapp.net`):
        *   Crea un nuevo usuario WP con rol `subscriber`.
        *   Genera `username` (`wa_NUMERO`) y `email` placeholder.
        *   Establece `display_name` (usa `pushName` solo si es mensaje entrante).
        *   Guarda los siguientes metadatos:
            *   `_crm_whatsapp_jid` (JID completo).
            *   `billing_phone` (Número de teléfono para compatibilidad WooCommerce).
            *   `_crm_lifecycle_tag` (Asigna la **primera** etiqueta de ciclo de vida definida en los ajustes del plugin como valor por defecto).
        *   Llama a `crm_fetch_and_save_avatar` **solo en este momento de creación**.
    *   **Usuario Existente:** Si el usuario **ya existe**, la función `crm_process_single_jid` **no realiza ninguna acción adicional** (ni actualización de datos, ni búsqueda/actualización de avatar). Simplemente devuelve el `user_id` existente.
    *   **Ignora Grupos:** No se crean usuarios WP para JIDs de grupo (`@g.us`).

4.  **Gestión de Avatares (`crm_fetch_and_save_avatar`):**
    *   Se llama **solo** cuando se crea un nuevo usuario.
    *   Verifica si el usuario ya tiene un `_crm_avatar_attachment_id`. Si lo tiene, no hace nada.
    *   Si no tiene avatar, llama a la API de Evolution (`POST /chat/fetchProfilePictureUrl/{instanceName}` con `{"number": jid}`) usando las credenciales **globales** (URL y Token) obtenidas mediante `get_option()` dentro de la función auxiliar `crm_evolution_api_request` (ubicada en `crm-ajax-handlers.php`).
    *   Si obtiene una URL válida, descarga la imagen, la guarda en la Biblioteca de Medios usando `media_handle_sideload`.
    *   Guarda el ID del adjunto en el metadato `_crm_avatar_attachment_id`.

5.  **Estándares:**
    *   Se acordó usar el prefijo `_crm_` para metadatos internos del plugin para ocultarlos de la interfaz estándar de campos personalizados.
    *   Se usa `billing_phone` sin prefijo por compatibilidad con WooCommerce.

**Estado Actual:** La lógica para identificar/crear usuarios y guardar su avatar (solo la primera vez) está implementada y probada.

**Siguiente Paso Pendiente:** Implementar la función `crm_save_chat_message` para guardar los detalles del mensaje (incluyendo multimedia desde Base64) en el Custom Post Type `crm_chat`, asociándolo al `contact_user_id` (`remote_user_id`).
