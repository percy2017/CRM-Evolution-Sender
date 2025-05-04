jQuery(document).ready(function($) {
    // --- Manejo del selector de medios para CPT Campañas ---
    let mediaFrameCPT;

    // Botón Seleccionar/Subir
    $('body').on('click', '.crm-select-media-cpt', function(event) {
        event.preventDefault();
        // crm_js_log('Botón seleccionar CPT presionado'); // Asumiendo que crm_js_log está en app.js, quizás quitarlo aquí

        // Si el frame ya existe, ábrelo.
        if (mediaFrameCPT) {
            mediaFrameCPT.open();
            return;
        }

        // Crear el frame de medios.
        mediaFrameCPT = wp.media({
            title: 'Seleccionar o Subir Archivo Multimedia',
            button: {
                text: 'Usar este archivo'
            },
            multiple: false // No permitir selección múltiple
        });

        // Cuando se selecciona un archivo, obtener su URL y nombre.
        mediaFrameCPT.on('select', function() {
            const attachment = mediaFrameCPT.state().get('selection').first().toJSON();
            // crm_js_log('Archivo seleccionado CPT:', attachment);
            $('#crm_campaign_media_url').val(attachment.url);
            $('#media-filename-cpt').text(attachment.filename || attachment.title).show();
        });

        // Abrir el frame.
        mediaFrameCPT.open();
    });

    // Botón Limpiar
    $('body').on('click', '.crm-clear-media-cpt', function(event) {
        event.preventDefault();
        // crm_js_log('Botón limpiar CPT presionado');
        $('#crm_campaign_media_url').val('');
        $('#media-filename-cpt').text('').hide();
    });

    // Mostrar nombre de archivo al cargar la página si ya hay una URL
    const initialMediaUrlCPT = $('#crm_campaign_media_url').val();
    if (initialMediaUrlCPT) {
        try {
            const urlParts = initialMediaUrlCPT.split('/');
            const filename = urlParts[urlParts.length - 1];
             $('#media-filename-cpt').text(filename).show();
        } catch (e) {
             // crm_js_log('Error extrayendo nombre de archivo inicial CPT', e);
        }
    }
});