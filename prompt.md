
## Resumen del Progreso Actual:

Hemos estado trabajando en mejorar la interfaz de usuario del chat dentro del plugin "CRM Evolution Sender" para WordPress. Los principales avances hasta ahora son:

1.  **Actualización de UI al Enviar Mensaje:**
    *   **Logrado:** La lista de chats del panel izquierdo se actualiza automáticamente después de enviar un mensaje, colocando la conversación activa al principio.
    *   **Archivos Modificados:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\assets\app.js` (funciones `crm_send_message_ajax` y `crm_send_media_message_ajax`).

2.  **Funcionalidad "Nuevo Chat" (Crear/Buscar Contacto):**
    *   **Logrado:**
        *   Se añadió un botón "Nuevo Chat" (icono `+`) en la cabecera de la lista de chats.
        *   Al hacer clic, un modal (SweetAlert2) solicita un número de teléfono.
        *   **Backend (PHP):**
            *   Una función AJAX (`crm_fetch_whatsapp_profile_ajax_callback`) llama a una función principal (`crm_process_whatsapp_contact_by_phone`).
            *   `crm_process_whatsapp_contact_by_phone`:
                *   Obtiene la lista de instancias activas de Evolution API (usando `wp_remote_request` directo para `/instance/fetchInstances`).
                *   Itera sobre las instancias para llamar a `/chat/fetchProfile` (usando `crm_evolution_api_request_v2`) con el número proporcionado.
                *   Si se encuentra el perfil en WhatsApp:
                    *   Obtiene el JID (`wuid`) del perfil.
                    *   Busca en WordPress un usuario con ese `_crm_whatsapp_jid`.
                    *   Si el usuario WP existe, se actualizan sus datos (nombre, `_crm_instance_name` con la instancia que lo encontró, avatar, etc.).
                    *   Si el usuario WP no existe, se crea uno nuevo con los datos de la API (nombre, JID, teléfono, avatar, `_crm_instance_name`, etiqueta de ciclo de vida, etc.).
                *   Devuelve el `user_id` del contacto procesado.
        *   **Frontend (JS):**
            *   Recibe los datos del usuario WP (ID, nombre, avatar, JID).
            *   Muestra una notificación de éxito.
            *   Refresca la lista de chats (`loadRecentConversations()`).
            *   Abre automáticamente la vista de chat con el contacto (`openChatView()`).
    *   **Archivos Modificados/Creados:**
        *   `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-chat-history.php` (botón HTML).
        *   `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\assets\style.css` (estilos para el botón y cabecera).
        *   `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-ajax-handlers.php` (funciones `crm_process_whatsapp_contact_by_phone` y `crm_fetch_whatsapp_profile_ajax_callback`).
        *   `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\assets\app.js` (lógica del modal, AJAX, y `openChatView`).

3.  **Menú de Instancias (Botón "..."):**
    *   **Logrado (Listar):**
        *   Se añadió un botón "..." en la cabecera de la lista de chats.
        *   Al hacer clic, se muestra/oculta un menú desplegable.
        *   El menú se puebla dinámicamente con la lista de instancias obtenidas de la API de Evolution (usando una nueva función AJAX `crm_get_all_evolution_instances_ajax_callback` que llama a `/instance/fetchInstances` con `wp_remote_request`).
        *   Cada ítem del menú muestra el nombre de la instancia y un indicador de estado (conectada/desconectada).
        *   Se añadió un ítem "Todas las instancias" por defecto.
    *   **Archivos Modificados/Creados:**
        *   `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-chat-history.php` (HTML del botón y dropdown).
        *   `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\assets\style.css` (estilos para el menú y los indicadores de estado).
        *   `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-ajax-handlers.php` (función `crm_get_all_evolution_instances_ajax_callback`).
        *   `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\assets\app.js` (lógica para mostrar/ocultar menú y cargar instancias).

4.  **Emojis Adicionales:**
    *   **Logrado:** Se añadieron más opciones de emojis al selector.
    *   **Archivos Modificados:** `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-chat-history.php`.

## Planes Inmediatos / Próximos Pasos:

1.  **Implementar Filtros Combinados para la Lista de Chats:**
    *   **Objetivo:** Permitir al usuario filtrar la lista de chats combinando el filtro de "Tipo de Chat" (Todos, Favoritos, Contactos, Grupos - botones existentes) con el "Filtro de Instancia" (del menú "...").
    *   **Plan Detallado:**
        *   **Backend (PHP - `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-ajax-handlers.php`):**
            *   Crear una **NUEVA** función AJAX, por ejemplo, `crm_get_filtered_recent_conversations_ajax_callback()`.
            *   Esta función recibirá dos parámetros del frontend: `instance_filter` y `filter_type`.
            *   Construirá dinámicamente el `meta_query` para `WP_Query` basado en estos dos filtros:
                *   Condición para `_crm_instance_name` si `instance_filter` no es 'all'.
                *   Condiciones para `_crm_is_favorite` (si `filter_type` es 'favorites').
                *   Condiciones para `_crm_is_group` (si `filter_type` es 'contacts' o 'groups').
            *   Devolverá la lista de conversaciones filtradas (similar a como lo hace `crm_get_recent_conversations_ajax` pero con los filtros aplicados).
        *   **Frontend (JS - `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\assets\app.js`):**
            *   **Gestión de Estado:** Definir variables globales para mantener el estado actual de los filtros (ej. `currentFilterType = 'all'`, `currentInstanceFilter = 'all'`).
            *   **Manejadores de Eventos:**
                *   Para los botones de filtro existentes (`.filter-button`):
                    *   Al hacer clic, actualizar `currentFilterType`.
                    *   Actualizar visualmente qué botón está activo.
                    *   Llamar a una función (ej. `loadFilteredConversations()`) que usará la nueva acción AJAX.
                *   Para los ítems del menú de instancias (`.instance-filter-item`):
                    *   Al hacer clic, actualizar `currentInstanceFilter`.
                    *   Actualizar visualmente qué ítem del menú está activo.
                    *   Llamar a `loadFilteredConversations()`.
                    *   Cerrar el menú desplegable.
            *   **Nueva Función `loadFilteredConversations()` (o modificar `loadRecentConversations`):**
                *   Leerá `currentFilterType` y `currentInstanceFilter`.
                *   Hará la llamada AJAX a la nueva acción PHP `crm_get_filtered_recent_conversations`, enviando ambos filtros.
                *   Actualizará la lista de chats con la respuesta.

2.  **Implementar Estados de Conversación:**
    *   **Objetivo:** Permitir a los agentes asignar estados (Abierto, Cerrado, Pendiente, Derivado) a las conversaciones para una mejor gestión.
    *   **Plan Detallado (Modelo: Un Usuario = Una Conversación Continua en términos de estado):**
        *   **Almacenamiento del Estado Principal:** Usar un `user_meta` para el usuario de WordPress (ej. `_crm_user_conversation_state`).
        *   **Registro de Cambios de Estado en el Chat:** Cada vez que el estado cambie, se creará un post `crm_chat` (tipo 'system' o 'event') en el historial del usuario indicando el cambio.
        *   **Interfaz para Cambiar el Estado:** Añadir un `<select>` dropdown en el sidebar de detalles del contacto (`#contact-details-column`).
        *   **Backend (PHP - `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-ajax-handlers.php`):**
            *   Crear una nueva función AJAX (ej. `crm_update_conversation_state_ajax_callback()`).
            *   Recibirá `user_id` y `new_state`.
            *   Validará el estado.
            *   Actualizará el `user_meta` `_crm_user_conversation_state`.
            *   Creará el post `crm_chat` de tipo sistema.
            *   Actualizará `_crm_last_message_timestamp` del usuario.
            *   Devolverá éxito y datos del mensaje de sistema.
        *   **Frontend (UI - `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\crm-chat-history.php`):**
            *   Añadir el HTML para el `<select>` de estados en el sidebar.
        *   **Frontend (JS - `c:\xampp\htdocs\wp-content\plugins\crm-evolution-sender\assets\app.js`):**
            *   **Cargar Estado Actual:** `loadContactDetails(userId)` deberá obtener y mostrar el estado actual en el dropdown.
            *   **Manejador de Evento para el Dropdown de Estados:**
                *   Al cambiar, llamar a la nueva acción AJAX `crm_update_conversation_state_ajax`.
                *   Al recibir éxito, mostrar notificación, renderizar el mensaje de sistema en el chat y refrescar la lista de conversaciones.

## Puntos a Recordar / Consideraciones:

*   Mantener la consistencia en el manejo de errores y notificaciones al usuario.
*   Optimizar las consultas a la base de datos a medida que se añaden más filtros.
*   Asegurar que los nonces de seguridad se usen en todas las llamadas AJAX.
*   Revisar la usabilidad de la interfaz a medida que se añaden nuevas funcionalidades.
