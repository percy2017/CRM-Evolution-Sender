# CRM Evolution Sender

Plugin para WordPress que integra Evolution API para gestionar instancias, usuarios de WP y envíos masivos de mensajes multimedia.

**Autor:** [Ing. Percy Alvarez](https://percyalvarez.com)
**Página del Plugin:** [https://percyalvarez.com/plugins-wordpress](https://percyalvarez.com/plugins-wordpress)
**Versión:** 1.0.0 (o la versión actual)
**Requiere WP:** 5.0 o superior
**Probado hasta:** (Versión de WP con la que has probado)
**Requiere PHP:** 7.4 o superior
**Licencia:** GPLv2 o posterior
**URI de Licencia:** https://www.gnu.org/licenses/gpl-2.0.html

---

## Descripción

**CRM Evolution Sender** es una herramienta diseñada para centralizar la gestión de tus comunicaciones a través de WhatsApp directamente desde tu panel de WordPress. Integra la potente [Evolution API](https://github.com/EvolutionAPI/evolution-api) para permitirte:

*   Administrar múltiples instancias de Evolution API.
*   Gestionar los usuarios registrados en tu sitio WordPress.
*   Crear, programar y monitorear campañas de envío masivo de mensajes, incluyendo contenido multimedia.

## Características Principales

*   **Gestión de Instancias (CRUD):** Añade, visualiza, actualiza y elimina conexiones a tus instancias de Evolution API de forma segura.
*   **Gestión de Usuarios WP (CRUD):** Administra los usuarios de tu sitio WordPress (crear, leer, actualizar, eliminar) desde una interfaz dedicada dentro del plugin.
*   **Gestión de Marketing (CRUD):** Crea campañas de envío masivo, define mensajes, adjunta archivos multimedia (imágenes, videos, documentos) y gestiona el estado de los envíos.
*   **Interfaz Intuitiva:** Panel de administración con pestañas (Instancias | Usuarios | Marketing) para una navegación sencilla y clara, utilizando el icono de WhatsApp para fácil identificación.
*   **Configuración Sencilla:** Página de ajustes dedicada para configurar las credenciales globales de la API.
*   **Tareas Programadas:** Utiliza WP-Cron (`crm-cron.php`) para gestionar envíos programados o tareas en segundo plano (detalles específicos de implementación pueden variar).
*   **Interfaz Moderna:** Uso de DataTables para tablas interactivas y SweetAlert2 para notificaciones amigables.

## Requisitos

*   WordPress versión 5.0 o superior.
*   PHP versión 7.4 o superior.
*   Acceso a una instancia funcional de **Evolution API (v1.8 o compatible)**.
*   Credenciales válidas (URL de la API y Token/API Key) para tu instancia de Evolution API.

## Instalación

1.  **Descarga:**
    *   Descarga el archivo `.zip` del plugin desde la página de Releases de este repositorio (Asegúrate de crear releases en GitHub).
    *   O clona el repositorio: `git clone URL_A_TU_REPOSITORIO.git` y comprime la carpeta `crm-evolution-sender` en un archivo `.zip`.
2.  **Subida a WordPress:**
    *   Ve a tu panel de administración de WordPress: `Plugins` -> `Añadir nuevo`.
    *   Haz clic en el botón `Subir plugin` en la parte superior.
    *   Selecciona el archivo `.zip` descargado (`crm-evolution-sender.zip`).
    *   Haz clic en `Instalar ahora`.
3.  **Activación:**
    *   Una vez instalado, haz clic en `Activar plugin`.

## Configuración Inicial

1.  Después de activar el plugin, ve a `Ajustes` -> `CRM Evolution Sender` en el menú de administración de WordPress (o busca el enlace `Ajustes` debajo del nombre del plugin en la lista de plugins instalados).
2.  Ingresa la **URL Base** y el **Token (API Key)** de tu instancia principal de Evolution API. Estos serán los valores por defecto si no se especifican otros en la gestión de instancias.
3.  Guarda los cambios.

## Uso

1.  Busca el nuevo elemento de menú **"CRM Evolution Sender"** (con el icono de WhatsApp) en la barra lateral de administración de WordPress.
2.  Dentro del panel del plugin, encontrarás tres pestañas principales:
    *   **Instancias:** Aquí puedes añadir nuevas conexiones a instancias de Evolution API, ver las existentes, editarlas o eliminarlas. Deberás proporcionar la URL y el Token para cada instancia específica si gestionas varias.
    *   **Usuarios:** Visualiza, añade, edita o elimina usuarios de WordPress directamente.
    *   **Marketing:** Crea tus campañas de envío masivo. Define el nombre de la campaña, selecciona la instancia de API a usar, redacta el mensaje, adjunta archivos multimedia si es necesario, selecciona los destinatarios (usuarios WP, números específicos, etc. - *especificar cómo se seleccionan*) y programa o inicia el envío.

## Desarrollo

Este plugin utiliza las siguientes librerías front-end:

*   SweetAlert2: Para modales y notificaciones.
*   DataTables: Para mejorar la visualización e interacción con las tablas de datos.

Las librerías se encuentran en el directorio `assets/vendor/`. Los estilos CSS personalizados están en `assets/style.css` y el JavaScript personalizado en `assets/app.js`.

Las tareas programadas (CRON) se definen y gestionan en `crm-cron.php`.

## Contribuciones

Las contribuciones son bienvenidas. Si deseas colaborar, por favor:

1.  Haz un Fork del repositorio.
2.  Crea una nueva rama (`git checkout -b feature/nueva-funcionalidad`).
3.  Realiza tus cambios y haz commit (`git commit -am 'Añade nueva funcionalidad'`).
4.  Haz push a la rama (`git push origin feature/nueva-funcionalidad`).
5.  Abre un Pull Request.

Por favor, asegúrate de que tu código sigue los estándares de codificación de WordPress.

## Soporte

Si encuentras algún error o tienes alguna sugerencia, por favor abre un Issue en el repositorio de GitHub.

---

*Desarrollado por Ing. Percy Alvarez - percyalvarez.com*
