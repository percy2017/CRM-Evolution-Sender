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

**Estado Actual:**
1.  **Gestión de Usuarios y Avatares:** La lógica para identificar/crear usuarios WP (`subscriber`) y guardar su avatar (solo en la creación) está implementada y probada.
2.  **Guardado de Mensajes:** La lógica para guardar los detalles del mensaje (incluyendo multimedia desde Base64) en el Custom Post Type `crm_chat`, asociándolo al `contact_user_id` (como `post_author` y metadato `_crm_contact_user_id`), está implementada y probada.
3.  **Interfaz Historial de Chats (Estilo WhatsApp Web):**
    *   Se creó un submenú "Historial Chats" bajo "CRM Evolution".
    *   Se creó un archivo dedicado `crm-chat-history.php` para la página.
    *   Se implementó la estructura HTML de dos columnas (lista de chats | mensajes).
    *   Se añadieron estilos CSS básicos para el layout, la lista de chats y las burbujas de mensajes.
    *   Se implementó la carga AJAX de la lista de conversaciones (izquierda), mostrando:
        *   Avatar del contacto.
        *   Nombre del contacto.
        *   Snippet del último mensaje (con prefijo para media).
        *   Hora del último mensaje.
        *   Nombre de la instancia.
    *   Se implementó la carga AJAX de los mensajes de la conversación seleccionada (derecha), mostrando:
        *   Burbujas de chat diferenciadas (entrante/saliente).
        *   Contenido de texto.
        *   Imágenes, videos y enlaces a documentos adjuntos.
        *   Hora del mensaje.
        *   Scroll automático al último mensaje.
    *   **Interfaz de Gestión de Instancias (Tarjetas):**
        *   Se creó un archivo `crm-instances.php` para la página principal del plugin (vista de tarjetas).
        *   Se crearon `assets/admin-instances.css` y `assets/admin-instances.js` para esta vista.
        *   Carga AJAX de instancias y renderizado como tarjetas usando `wp.template`.
        *   Funcionalidad para Añadir (modal Thickbox), Obtener QR (modal), Eliminar (con confirmación SweetAlert y desconexión previa en API).
        *   **Actualización Automática Modal QR (Heartbeat):**
            *   Webhook (`crm-rest-api.php`) guarda eventos `connection.update` y `qrcode.updated` en transients.
            *   Heartbeat PHP (`crm-instances.php`) lee transients y envía actualizaciones al JS.
            *   Heartbeat JS (`admin-instances.js`) cierra el modal al conectar y actualiza la imagen QR si cambia.
        *   Hora del mensaje.
        *   Scroll automático al último mensaje.
4.  **Manejo de Eliminación de Usuario:**
    *   Al eliminar un usuario WP y elegir "Borrar todo el contenido", se eliminan correctamente los posts `crm_chat` asociados (porque `post_author` es el `contact_user_id`).
    *   Se implementó una función (`crm_delete_user_avatar_on_user_delete` en `crm-evolution-sender.php`) enganchada a `delete_user` para eliminar el archivo de avatar de la Biblioteca de Medios.
5.  **Actualización Automática de Chats (Heartbeat):**
    *   Se utiliza la API Heartbeat de WordPress.
    *   JS envía el timestamp del último chequeo general (`lastChatCheckTimestamp`) y, si un chat está abierto, su `userId` (`currentOpenChatUserId`) y el timestamp del último mensaje mostrado (`lastDisplayedMessageTimestamp`).
    *   PHP (`crm_handle_heartbeat_request`) comprueba si hay mensajes nuevos para el chat abierto o mensajes nuevos en general.
    *   PHP devuelve los mensajes nuevos para el chat abierto (en `crm_new_messages_for_open_chat`) y/o un flag si la lista general necesita refrescarse (`crm_needs_list_refresh`).
    *   JS (`heartbeat-tick`) procesa la respuesta: añade mensajes nuevos al chat abierto o recarga la lista de conversaciones.
6.  **Envío de Mensajes desde Historial (Análisis):**
    *   Se añadió el área HTML/CSS para el input de texto y botones de adjuntar/enviar, oculta por defecto y visible al seleccionar un chat.
    *   **Plan:**
        *   Guardar `instance_name` y `jid` del chat activo en JS.
        *   Usar `wp.media` para el botón de adjuntar.
        *   Crear acción AJAX (`crm_send_chat_message`) para enviar datos al backend.
        *   Backend llamará a la API Evolution (`/message/sendText` o `/message/sendMedia`) y luego a `crm_save_chat_message` para guardar el mensaje saliente.
        *   El Heartbeat mostrará el mensaje enviado en la interfaz.
