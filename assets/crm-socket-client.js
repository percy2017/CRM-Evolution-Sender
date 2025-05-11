/* global io, crm_evolution_sender_params */
jQuery(document).ready(function ($) {
    'use strict';

    if (typeof io === 'undefined') {
        console.error('Socket.IO client library not loaded. CRM real-time features will not work.');
        return;
    }

    if (!crm_evolution_sender_params || !crm_evolution_sender_params.socket_io_server_url) {
        console.error('Socket.IO server URL not provided. CRM real-time features will not work.');
        return;
    }

    const serverUrl = crm_evolution_sender_params.socket_io_server_url;
    const roomName = 'wordpress_crm_updates'; // Sala para las actualizaciones del CRM
    let socket;

    console.log(`[CRM Socket Client] Attempting to connect to Socket.IO server at: ${serverUrl}`);

    try {
        socket = io(serverUrl, {
            transports: ['websocket'], // Priorizar WebSockets
            reconnectionAttempts: 5, // Intentar reconectar 5 veces
            reconnectionDelay: 3000, // Esperar 3 segundos entre intentos
        });

        socket.on('connect', () => {
            console.log(`[CRM Socket Client] Successfully connected to Socket.IO server. Socket ID: ${socket.id}`);
            console.log(`[CRM Socket Client] Attempting to join room: ${roomName}`);

            socket.emit('join_room', { roomName: roomName }, (response) => {
                if (response && response.success) {
                    console.log(`[CRM Socket Client] Successfully joined room: ${response.room}. Message: ${response.message}`);
                    // Aquí podrías disparar un evento jQuery personalizado si necesitas que otras partes de tu JS reaccionen
                    // $(document).trigger('crmSocketRoomJoined', response);
                } else {
                    console.error(`[CRM Socket Client] Failed to join room: ${roomName}. Server response:`, response);
                }
            });
        });

        socket.on('new_crm_message', (data) => {
            console.log('[CRM Socket Client] Received new_crm_message:', data);
            // Disparar un evento jQuery personalizado para que app.js (u otros) puedan reaccionar.
            $(document).trigger('crm:newMessageBySocket', [data]);
        });

        socket.on('connect_error', (error) => {
            console.error('[CRM Socket Client] Connection error:', error.message, error);
        });

        socket.on('disconnect', (reason) => {
            console.warn(`[CRM Socket Client] Disconnected from Socket.IO server. Reason: ${reason}`);
            if (reason === 'io server disconnect') {
                // El servidor cerró la conexión intencionalmente
                socket.connect(); // Intentar reconectar manualmente si es necesario
            }
            // si la razón es 'io client disconnect', fue el cliente quien cerró la conexión.
        });

    } catch (error) {
        console.error('[CRM Socket Client] Error initializing Socket.IO connection:', error);
    }

});