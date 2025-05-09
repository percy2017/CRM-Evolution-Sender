# Proyecto de Desarrollo: Mejoras y Nuevas Funcionalidades para CRM Evolution Sender

## 1. Visión General del Plugin `crm-evolution-sender`

**CRM Evolution Sender** es un plugin de WordPress diseñado para centralizar y potenciar la comunicación vía WhatsApp mediante la integración con Evolution API. Permite la gestión de instancias, usuarios, campañas de marketing y, fundamentalmente, ofrece una interfaz de chat para la comunicación directa.

**Objetivos Actuales del Desarrollo:**

1.  **Mejorar la Interfaz de Chat Existente:** Implementar filtros avanzados (Todos, Favoritos, Grupos) y una gestión más robusta de contactos favoritos y entidades de grupo, incluyendo la obtención de nombres de grupo desde la API.
2.  **Integración con Plugin `pos-base`:** Permitir el envío de mensajes de WhatsApp (texto y multimedia) directamente desde un modal de eventos del calendario del plugin `pos-base`, utilizando las instancias gestionadas por `crm-evolution-sender`.

## 2. Componentes y Funcionalidades Existentes Relevantes

*   **Plugin `crm-evolution-sender`:**
    *   Gestión de múltiples instancias de Evolution API (CRUD).
    *   Administración de usuarios de WordPress (contactos).
    *   CPT `crm_chat` para almacenar el historial de mensajes.
    *   Interfaz de chat básica (en `crm-chat-history.php`) para visualizar conversaciones y enviar mensajes.
    *   Manejadores AJAX en `crm-ajax-handlers.php`.
    *   Lógica de webhooks en `crm-rest-api.php` para recibir mensajes y crear/actualizar usuarios y mensajes.
    *   Archivos de assets: `assets/app.js` y `assets/style.css`.
*   **Plugin `pos-base`:**
    *   Calendario con eventos.
    *   Modal de detalles de evento (definido en `pos-page.php`).
    *   Lógica JavaScript en `pos-base/assets/app.js`.
    *   Backend y API en `pos-base.php` y `pos-api.php`.

## 3. Nuevas Funcionalidades y Mejoras a Implementar

### A. Mejoras en la Interfaz de Chat de `crm-evolution-sender`

#### A.1. Filtros de Chat ("Todos", "Favoritos", "Grupos")

*   **Qué:** Añadir botones de filtro en la cabecera de la lista de chats para permitir al usuario visualizar:
    *   **Todos:** Todas las conversaciones recientes (comportamiento actual).
    *   **Favoritos:** Solo conversaciones con contactos marcados como favoritos.
    *   **Grupos:** Solo conversaciones que son con entidades de grupo.
*   **Cómo (Archivos en `crm-evolution-sender`):**
    *   **`crm-chat-history.php`:**
        *   Añadir el HTML para los tres botones de filtro (`<button data-filter="all">`, `<button data-filter="favorites">`, `<button data-filter="groups">`) dentro del `div.chat-list-header`.
    *   **`assets/style.css`:**
        *   Añadir estilos CSS para los botones de filtro, incluyendo un estado `active` para el filtro seleccionado.
    *   **`assets/app.js`:**
        *   Manejar el evento `click` en los botones de filtro.
        *   Al hacer clic, actualizar la clase `active` en los botones.
        *   Realizar una petición AJAX a `crm_get_recent_conversations_ajax` (en `crm-ajax-handlers.php`), pasando el tipo de filtro seleccionado.
        *   Actualizar la lista de chats en la interfaz con la respuesta.
    *   **`crm-ajax-handlers.php` (función `crm_get_recent_conversations_ajax`):**
        *   Modificar la función para aceptar un parámetro de filtro (ej: `filter_type`).
        *   Ajustar la `WP_Query` (o la lógica de obtención de conversaciones) según el filtro:
            *   `all`: Sin cambios en la consulta principal.
            *   `favorites`: Necesitará una consulta para obtener `user_id` de usuarios con `_crm_is_favorite_contact = true`, y luego obtener conversaciones de esos usuarios.
            *   `groups`: Necesitará una consulta para obtener `user_id` de usuarios con `_crm_is_group_entity = true`, y luego obtener las "conversaciones" asociadas a estas entidades de grupo.

#### A.2. Funcionalidad de "Favoritos"

*   **Qué:** Permitir al usuario marcar/desmarcar contactos (usuarios WP) como favoritos.
*   **Cómo (Archivos en `crm-evolution-sender`):**
    *   **Datos:**
        *   Utilizar un metadato de usuario: `_crm_is_favorite_contact` (booleano) en la tabla `wp_usermeta`.
    *   **Interfaz (en `crm-chat-history.php`):**
        *   En el panel de detalles del contacto (sidebar derecho), añadir un icono (ej: estrella) para marcar/desmarcar como favorito.
        *   El estado del icono debe reflejar el estado actual del contacto.
    *   **Lógica JavaScript (en `assets/app.js`):**
        *   Manejar el clic en el icono de estrella.
        *   Realizar una petición AJAX a una nueva acción para actualizar el estado de favorito.
        *   Actualizar visualmente el icono y, opcionalmente, la lista de chats si el filtro "Favoritos" está activo.
    *   **Lógica Backend (en `crm-ajax-handlers.php`):**
        *   Crear una nueva función callback AJAX (ej: `crm_toggle_favorite_contact_callback`).
        *   Recibirá el `user_id` del contacto y el nuevo estado de favorito.
        *   Verificará nonces y permisos.
        *   Actualizará el metadato `_crm_is_favorite_contact` para el usuario.
        *   Devolverá una respuesta de éxito/error.

#### A.3. Funcionalidad de "Grupos" (Identificación y Visualización)

*   **Qué:** Asegurar que las entidades de grupo de WhatsApp se creen y gestionen correctamente como usuarios WP, y que sus nombres se obtengan de la API de Evolution.
*   **Cómo (Archivos en `crm-evolution-sender`):**
    *   **Datos:**
        *   Utilizar un metadato de usuario: `_crm_is_group_entity` (booleano) en `wp_usermeta` para los usuarios WP que representan grupos.
    *   **`crm-rest-api.php`:**
        *   **Modificar `crm_process_single_jid()`:**
            *   Permitir que procese JIDs terminados en `@g.us`.
            *   Si el JID es de grupo:
                *   Al crear un nuevo usuario WP para el grupo, establecer `_crm_is_group_entity = true`.
                *   Generar un `user_login` (ej: `group_JIDNUM`) y un `display_name` inicial (ej: el JID del grupo).
                *   Llamar a una nueva función (ej: `crm_update_group_user_metadata_from_api()`) para obtener el nombre real del grupo.
            *   Si el usuario WP del grupo ya existe, llamar igualmente a `crm_update_group_user_metadata_from_api()` para asegurar que el nombre esté actualizado.
        *   **Crear `crm_update_group_user_metadata_from_api($user_id, $group_jid, $instance_name)`:**
            *   Esta función tomará el ID del usuario WP del grupo, su JID y el nombre de la instancia.
            *   Hará una petición `GET` al endpoint `/group/findGroupInfos/{instanceName}` de la API de Evolution (usando la función `crm_evolution_api_request` existente).
            *   La respuesta de la API será un array de objetos de grupo.
            *   Buscará en el array el objeto cuyo `id` coincida con `$group_jid`.
            *   Si se encuentra y tiene la propiedad `subject` (nombre del grupo), actualizará el `display_name` del usuario WP (`wp_update_user`).
            *   (Opcional futuro: manejar `pictureUrl` para el avatar del grupo).
        *   **Modificar `crm_evolution_webhook_handler_callback()`:**
            *   Cuando se reciba un mensaje de un grupo (`event: messages.upsert`, `key.remoteJid` termina en `@g.us`):
                *   Asegurar que se llame a `crm_process_single_jid()` con el JID del grupo (`key.remoteJid`). Esto es crucial para crear/actualizar la entidad de usuario WP para el grupo mismo y disparar la obtención de su nombre.
