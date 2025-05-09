# Documentación de Payloads - Evolution API y Webhooks

**Referencia API Oficial:** [https://doc.evolution-api.com/](https://doc.evolution-api.com/)
---

## Endpoints de la API de Evolution

Ejemplos de llamadas comunes a la API REST de Evolution, mostrando el cuerpo de la petición (Request Body) y la respuesta esperada (Response Body) en formato JSON.

### 1. Crear Instancia (`POST /instance/create`)

**Request Body:**
```json
{
  "instanceName": "<string>",
  "token": "<string>",
  "qrcode": true,
  "number": "<string>",
  "integration": "WHATSAPP-BAILEYS",
  "webhook": "<string>",
  "webhook_by_events": true,
  "events": [
    "APPLICATION_STARTUP"
  ],
  "reject_call": true,
  "msg_call": "<string>",
  "groups_ignore": true,
  "always_online": true,
  "read_messages": true,
  "read_status": true,
  "websocket_enabled": true,
  "websocket_events": [
    "APPLICATION_STARTUP"
  ],
  "rabbitmq_enabled": true,
  "rabbitmq_events": [
    "APPLICATION_STARTUP"
  ],
  "sqs_enabled": true,
  "sqs_events": [
    "APPLICATION_STARTUP"
  ],
  "typebot_url": "<string>",
  "typebot": "<string>",
  "typebot_expire": 123,
  "typebot_keyword_finish": "<string>",
  "typebot_delay_message": 123,
  "typebot_unknown_message": "<string>",
  "typebot_listening_from_me": true,
  "proxy": {
    "host": "<string>",
    "port": "<string>",
    "protocol": "http",
    "username": "<string>",
    "password": "<string>"
  },
  "chatwoot_account_id": 123,
  "chatwoot_token": "<string>",
  "chatwoot_url": "<string>",
  "chatwoot_sign_msg": true,
  "chatwoot_reopen_conversation": true,
  "chatwoot_conversation_pending": true
}

Response Body (Ejemplo):

{
  "instance": {
    "instanceName": "teste-docs",
    "instanceId": "af6c5b7c-ee27-4f94-9ea8-192393746ddd",
    "webhook_wa_business": null,
    "access_token_wa_business": "",
    "status": "created"
  },
  "hash": {
    "apikey": "123456"
  },
  "settings": {
    "reject_call": false,
    "msg_call": "",
    "groups_ignore": true,
    "always_online": false,
    "read_messages": false,
    "read_status": false,
    "sync_full_history": false
  }
}


 
## Fetch Instances
[
  {
    "instance": {
      "instanceName": "example-name",
      "instanceId": "421a4121-a3d9-40cc-a8db-c3a1df353126",
      "owner": "553198296801@s.whatsapp.net",
      "profileName": "Guilherme Gomes",
      "profilePictureUrl": null,
      "profileStatus": "This is the profile status.",
      "status": "open",
      "serverUrl": "https://example.evolution-api.com",
      "apikey": "B3844804-481D-47A4-B69C-F14B4206EB56",
      "integration": {
        "integration": "WHATSAPP-BAILEYS",
        "webhook_wa_business": "https://example.evolution-api.com/webhook/whatsapp/db5e11d3-ded5-4d91-b3fb-48272688f206"
      }
    }
  },
  {
    "instance": {
      "instanceName": "teste-docs",
      "instanceId": "af6c5b7c-ee27-4f94-9ea8-192393746ddd",
      "status": "close",
      "serverUrl": "https://example.evolution-api.com",
      "apikey": "123456",
      "integration": {
        "token": "123456",
        "webhook_wa_business": "https://example.evolution-api.com/webhook/whatsapp/teste-docs"
      }
    }
  }
]





Eventos del Webhook
{
    "event": "connection.update",
    "instance": "tigo1",
    "data": {
        "instance": "tigo1",
        "state": "close",
        "statusReason": 401
    },
    "destination": "https://2a6a-45-183-185-82.ngrok-free.app/wp-json/crm-evolution-api/v1/webhook",
    "date_time": "2025-05-02T03:01:20.092Z",
    "sender": "59169375664@s.whatsapp.net",
    "server_url": "http://localhost:8080",
    "apikey": "0FD36D5D-D230-4F07-AA76-8EB7EE6490B4"
}


# MENSAJES ENTRANTES
---

## Solo Texto
Webhook recibido - Datos JSON: {
    "event": "messages.upsert",
    "instance": "tigo1",
    "data": {
        "key": {
            "remoteJid": "59171146267@s.whatsapp.net",
            "fromMe": false,
            "id": "3EB03E3CA617ADA52AEA79"
        },
        "pushName": "percy alvarez E1",
        "message": {
            "conversation": "hola - text",
            "messageContextInfo": {
                "deviceListMetadata": {
                    "senderKeyHash": "KbLD8xUeBypUMw==",
                    "senderTimestamp": "1745845546",
                    "senderAccountType": "E2EE",
                    "receiverAccountType": "E2EE",
                    "recipientKeyHash": "cDHBwCwls7yt2g==",
                    "recipientTimestamp": "1745900845"
                },
                "deviceListMetadataVersion": 2,
                "messageSecret": "Q3ngYL6Mi8gzb1naAYiHy\/Z36XGRXYzfZfJXBbSlzxk="
            }
        },
        "messageType": "conversation",
        "messageTimestamp": 1746132673,
        "owner": "tigo1",
        "source": "web"
    },
    "destination": "https:\/\/592c-200-87-152-161.ngrok-free.app\/wp-json\/crm-evolution-api\/v1\/webhook",
    "date_time": "2025-05-01T17:51:14.031Z",
    "sender": "59169375664@s.whatsapp.net",
    "server_url": "http:\/\/localhost:8080",
    "apikey": "0CE98F9E-36BE-401A-99FA-EA4E300942C4"
}

## Texto con Multimedia (imagen)
Webhook recibido - Datos JSON: {
    "event": "messages.upsert",
    "instance": "tigo1",
    "data": {
        "key": {
            "remoteJid": "59171146267@s.whatsapp.net",
            "fromMe": false,
            "id": "3EB083096BF5E4096E4137"
        },
        "pushName": "percy alvarez E1",
        "message": {
            "imageMessage": {
                "url": "https:\/\/mmg.whatsapp.net\/o1\/v\/t62.7118-24\/f2\/m238\/AQPnw3VpmxWhEi-XMtGpfrTyULu0ziRO-J1OO-kQIA-0Ne5NMOAQPE6160Bn5z8lie4xdaak9UzPFYMmeKwgm2ZJ5spYFrd2vaab_m67uQ?ccb=9-4&oh=01_Q5AaIR7L3lYtMrU7ISCIBeFODRarILnUkReVQnandbZ5O_wh&oe=681ACB1E&_nc_sid=e6ed6c&mms3=true",
                "mimetype": "image\/jpeg",
                "caption": "hola - text con multimedia (imagen)",
                "fileSha256": "hOGJCXvlENXj5fxZPPu8Ne6xHFM2S9WJPb\/unywn5qE=",
                "fileLength": "52046",
                "height": 720,
                "width": 720,
                "mediaKey": "Mh9Y7zz1AB9pl1tZe9Rc\/32HVRz7NCi8dHD+VlsTQ3U=",
                "fileEncSha256": "bu+kkcG5tZYGy6H5+CRYBsNxvsqFYpjww75cAFCw6oU=",
                "directPath": "\/o1\/v\/t62.7118-24\/f2\/m238\/AQO5GKDOirN12GT9jW7-JBzcSzx2frzX5s-JbMphpTdRjiUkcrNzxAb0Ge078fWQQ-mJQW64YGYIWQOU8InMkaXG8j84b7XBBYJqqWVRlQ?ccb=9-4&oh=01_Q5Aa1QEsZzTJWI83gC8wgfPJI8XrQUn9z4uOkOPhhjEgXbDYFw&oe=683B5DFC&_nc_sid=e6ed6c",
                "mediaKeyTimestamp": "1744000682",
                "jpegThumbnail": "\/9j\/4AAQSkZJRgABAQAAAQABAAD\/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD\/.....  (minuatura en base64 no importante)",
                "viewOnce": false
            },
            "messageContextInfo": {
                "deviceListMetadata": {
                    "senderKeyHash": "KbLD8xUeBypUMw==",
                    "senderTimestamp": "1745845546",
                    "senderAccountType": "E2EE",
                    "receiverAccountType": "E2EE",
                    "recipientKeyHash": "cDHBwCwls7yt2g==",
                    "recipientTimestamp": "1745900845"
                },
                "deviceListMetadataVersion": 2,
                "messageSecret": "9VYEITr++oq4AW\/3V3MRY5UQFoK9Hb3U2MG\/5Ezhcts="
            },
            "base64": "\/9j\/4AAQSkZJRgABAQAAAQABAAD\/2wBDAAsICAoIBwsKCQoNDAsNERwSEQ8PESIZGhQcKSQrKigkJyctMkA3LTA9MCcnOEw5PUNFSElIKzZPVU5GVEBHSEX\/....  (multimedia en base64 este es el dato importar a manegar para guardar el multimedia en media de wp)"
        },
        "contextInfo": null,
        "messageType": "imageMessage",
        "messageTimestamp": 1746132796,
        "owner": "tigo1",
        "source": "web"
    },
    "destination": "https:\/\/592c-200-87-152-161.ngrok-free.app\/wp-json\/crm-evolution-api\/v1\/webhook",
    "date_time": "2025-05-01T17:53:16.293Z",
    "sender": "59169375664@s.whatsapp.net",
    "server_url": "http:\/\/localhost:8080",
    "apikey": "0CE98F9E-36BE-401A-99FA-EA4E300942C4"
}

## Texto con multimedia (video)
Webhook recibido - Datos JSON: {
    "event": "messages.upsert",
    "instance": "tigo1",
    "data": {
        "key": {
            "remoteJid": "59171146267@s.whatsapp.net",
            "fromMe": false,
            "id": "3EB080FBD16B588D5E1065"
        },
        "pushName": "percy alvarez E1",
        "message": {
            "videoMessage": {
                "url": "https:\/\/mmg.whatsapp.net\/v\/t62.7161-24\/31794339_1357284842157195_4164647479449731576_n.enc?ccb=11-4&oh=01_Q5Aa1QGtENqi7SD4_dM1pQ-aPdjRBFLL44nbOOQ6dgaMlhIjJA&oe=683B52F1&_nc_sid=5e03e0&mms3=true",
                "mimetype": "video\/mp4",
                "fileSha256": "0LJSj243\/pQEGQRUlMtZX1X4b40G0zqdM0xhXsD2Rus=",
                "fileLength": "2797968",
                "seconds": 16,
                "mediaKey": "TY8JB\/SHVclVD6VFQXyiZAHo1+JQQ4EWNLtnUH8EDKU=",
                "caption": "hola - text con multimea (video)",
                "gifPlayback": false,
                "height": 1024,
                "width": 576,
                "fileEncSha256": "iS3\/czxIsQHHCxIioGOJW8EhbylHz3OGDkWj9aT4GYA=",
                "directPath": "\/v\/t62.7161-24\/31794339_1357284842157195_4164647479449731576_n.enc?ccb=11-4&oh=01_Q5Aa1QGtENqi7SD4_dM1pQ-aPdjRBFLL44nbOOQ6dgaMlhIjJA&oe=683B52F1&_nc_sid=5e03e0",
                "mediaKeyTimestamp": "1746133032",
                "jpegThumbnail": "\/9j\/4AAQSkZJRgABAQAAAQABAAD\/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD\/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEB.....(minuatura base64 no importante)",
                "viewOnce": false
            },
            "messageContextInfo": {
                "deviceListMetadata": {
                    "senderKeyHash": "KbLD8xUeBypUMw==",
                    "senderTimestamp": "1745845546",
                    "senderAccountType": "E2EE",
                    "receiverAccountType": "E2EE",
                    "recipientKeyHash": "cDHBwCwls7yt2g==",
                    "recipientTimestamp": "1745900845"
                },
                "deviceListMetadataVersion": 2,
                "messageSecret": "YOWZSISWjUsFz4Cs6ImAyuQ6QojgEGUYoKsyvDUgMa4="
            },
            "base64": "AAAAGGZ0eXBtcDQyAAAAAG1wNDJpc29tAAAAGGJlYW0BAAAAAQAAAAAAAAAGAAAAAAAjEW1vb3YAAABsbXZoZAAAAAAAAAAAAAAAAAAArEQAC1vCAAEAAAEAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAABoBdHJhawAAAFx0a2hkAAAABwAAAAAAAAAAAAAAAQAAAAAAC0KUAAAAAAAAAAAAAAAAAQAAAAABAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAQAAAAAJAAAAEAAAAAAAZnW1kaWEAAAAgbWRoZAAA0v74.............(video base64 importante)"
        },
        "contextInfo": null,
        "messageType": "videoMessage",
        "messageTimestamp": 1746133036,
        "owner": "tigo1",
        "source": "web"
    },
    "destination": "https:\/\/592c-200-87-152-161.ngrok-free.app\/wp-json\/crm-evolution-api\/v1\/webhook",
    "date_time": "2025-05-01T17:57:17.256Z",
    "sender": "59169375664@s.whatsapp.net",
    "server_url": "http:\/\/localhost:8080",
    "apikey": "0CE98F9E-36BE-401A-99FA-EA4E300942C4"
}


# MENSAJES SALIENTE
---

## Solo Texto
Webhook recibido - Datos JSON: {
    "event": "messages.upsert",
    "instance": "tigo1",
    "data": {
        "key": {
            "remoteJid": "59171146267@s.whatsapp.net",
            "fromMe": true,
            "id": "3EB0B7AA9746D9215C1340"
        },
        "pushName": "IPTV - T1",
        "message": {
            "conversation": "menasje saliente solo texto"
        },
        "messageType": "conversation",
        "messageTimestamp": 1746133821,
        "owner": "tigo1",
        "source": "web"
    },
    "destination": "https:\/\/592c-200-87-152-161.ngrok-free.app\/wp-json\/crm-evolution-api\/v1\/webhook",
    "date_time": "2025-05-01T18:10:21.803Z",
    "sender": "59169375664@s.whatsapp.net",
    "server_url": "http:\/\/localhost:8080",
    "apikey": "0CE98F9E-36BE-401A-99FA-EA4E300942C4"
}

## audio
(
    [event] => messages.upsert
    [instance] => entel2
    [data] => Array
        (
            [key] => Array
                (
                    [remoteJid] => 59178742806@s.whatsapp.net
                    [fromMe] => 
                    [id] => 70B23FB274D480B740FCC07E6427D21B
                )

            [pushName] => Silenia
            [message] => Array
                (
                    [audioMessage] => Array
                        (
                            [url] => https://mmg.whatsapp.net/v/t62.7117-24/35629873_663410686581758_8880443034391015442_n.enc?ccb=11-4&oh=01_Q5Aa1QFOLsA1To6-6hEMZRRZcujMTCWb5pqSeUW7-fmOn3we9g&oe=683DFAA0&_nc_sid=5e03e0&mms3=true
                            [mimetype] => audio/ogg; codecs=opus
                            [fileSha256] => tDg3nBM3DUH1IA2fPdyXv4wgAPEoMP4sg2wZZX0igdM=
                            [fileLength] => 41589
                            [seconds] => 19
                            [ptt] => 1
                            [mediaKey] => HMyn8WkBQ4MNj5fXCyqy52dqLnXwT85mlu59ncqn9Ao=
                            [fileEncSha256] => 5MrilQFDdxsD66FsEriM4WNPlLaD5pB36a9+A3SjkM4=
                            [directPath] => /v/t62.7117-24/35629873_663410686581758_8880443034391015442_n.enc?ccb=11-4&oh=01_Q5Aa1QFOLsA1To6-6hEMZRRZcujMTCWb5pqSeUW7-fmOn3we9g&oe=683DFAA0&_nc_sid=5e03e0
                            [mediaKeyTimestamp] => 1746312067
                            [waveform] => ABYiISRhYkMfFFlRTRogHF47S1RXRVNGTTdKPFROTlhKVlBLUiBNN1BOTldWNhUfFh8nYSVSWCtIWhYOJhAVJQ==
                        )

                    [messageContextInfo] => Array
                        (
                            [deviceListMetadata] => Array
                                (
                                    [senderKeyHash] => DNbTU9GqqaIAOA==
                                    [senderTimestamp] => 1745161996
                                    [recipientKeyHash] => phPTTwyQjw86RQ==
                                    [recipientTimestamp] => 1746228286
                                )

                            [deviceListMetadataVersion] => 2
                            [messageSecret] => MpDHT2fDpw0oBnOsyYJ1YGbIoEtdkcdI+vuOeZEEt94=
                        )

                    [base64] => T2dnUwACAAAAAAAAAAAAAAAAAAAAACqCBoIBE09wdXNIZWFkAQFoAIA+AAAAAABPZ2dTAAAAAAABavz..........
                )

            [contextInfo] => 
            [messageType] => audioMessage
            [messageTimestamp] => 1746312085
            [owner] => entel2
            [source] => android
        )

    [destination] => https://20c6-45-183-185-82.ngrok-free.app/wp-json/crm-evolution-api/v1/webhook
    [date_time] => 2025-05-03T19:41:25.554Z
    [sender] => 59168964000@s.whatsapp.net
    [server_url] => http://localhost:8080
    [apikey] => 272F3936-48CE-4CC9-884F-FA833E942936
)

## Texto con multimedia (imagen)
webhook recibido - Datos JSON: {
    "event": "messages.upsert",
    "instance": "tigo1",
    "data": {
        "key": {
            "remoteJid": "59171146267@s.whatsapp.net",
            "fromMe": true,
            "id": "3EB0E559DC0FE60E423298"
        },
        "pushName": "IPTV - T1",
        "message": {
            "imageMessage": {
                "url": "https:\/\/mmg.whatsapp.net\/o1\/v\/t62.7118-24\/f2\/m238\/AQMSw2EkwagsQICL9hn9N2o78-MxGsu8fBUPbkib-J--4Fab1673DZ7ftMbICXVq9mc7TcIEeNuWmSbffNXlYl_4cP6L47PGlO_uAcmQcw?ccb=9-4&oh=01_Q5Aa1QH59sVgYJdaJT3ocIHPX2kN0OqjmcxtM3HgNOFBlYno_Q&oe=683B52D2&_nc_sid=e6ed6c&mms3=true",
                "mimetype": "image\/jpeg",
                "caption": "mensaje saliente con multimedia (imagen)",
                "fileSha256": "1ilx7DOHO77gcwUiV2B5l7rZQVTe+KtJ4uJxg8bLkV4=",
                "fileLength": "68236",
                "height": 526,
                "width": 526,
                "mediaKey": "VR01GpfvHSqwxaYxlqFGqUX4uVjHzi3wgmQWkjT1ksA=",
                "fileEncSha256": "SysFXpsEAkH4kSl3LvbSPGjYcBUanUXVQAYopjH8VFc=",
                "directPath": "\/o1\/v\/t62.7118-24\/f2\/m238\/AQMSw2EkwagsQICL9hn9N2o78-MxGsu8fBUPbkib-J--4Fab1673DZ7ftMbICXVq9mc7TcIEeNuWmSbffNXlYl_4cP6L47PGlO_uAcmQcw?ccb=9-4&oh=01_Q5Aa1QH59sVgYJdaJT3ocIHPX2kN0OqjmcxtM3HgNOFBlYno_Q&oe=683B52D2&_nc_sid=e6ed6c",
                "mediaKeyTimestamp": "1746133901",
                "jpegThumbnail": "\/9j\/4AAQSkZJRgABAQAAAQABAAD\/4gHYSUNDX1BST0ZJTEUAAQEAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFlaAAABKAAAABRiWFlaAAABPAAAABR3dHB0AAABUAAAABRyVFJDAAABZAAAwVTjs....  (minuatura base64 no importante)",
                "viewOnce": false
            },
            "base64": "\/9j\/4AAQSkZJRgABAQAAAQABAAD\/4gHYSUNDX1BST0ZJTEUAAQEAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFla..... (multimea base64 imagen)"
        },
        "contextInfo": null,
        "messageType": "imageMessage",
        "messageTimestamp": 1746133903,
        "owner": "tigo1",
        "source": "web"
    },
    "destination": "https:\/\/592c-200-87-152-161.ngrok-free.app\/wp-json\/crm-evolution-api\/v1\/webhook",
    "date_time": "2025-05-01T18:11:44.254Z",
    "sender": "59169375664@s.whatsapp.net",
    "server_url": "http:\/\/localhost:8080",
    "apikey": "0CE98F9E-36BE-401A-99FA-EA4E300942C4"
}

## Texto con multimedia (video)
  Webhook recibido - Datos JSON: {
    "event": "messages.upsert",
    "instance": "tigo1",
    "data": {
        "key": {
            "remoteJid": "59171146267@s.whatsapp.net",
            "fromMe": true,
            "id": "3EB003A2297E288C1E4B17"
        },
        "pushName": "IPTV - T1",
        "message": {
            "videoMessage": {
                "url": "https:\/\/mmg.whatsapp.net\/v\/t62.7161-24\/29551220_1314347976321118_3167230320740861317_n.enc?ccb=11-4&oh=01_Q5Aa1QGjGJ935KrUwuF7B11UQtTjsapEuwdyVKeaQZoE9vom9Q&oe=683B4733&_nc_sid=5e03e0&mms3=true",
                "mimetype": "video\/mp4",
                "fileSha256": "xWm4ZAOEpAnST7XMrZC0LYfTJEQ8TMutaMw0Oyo0sFQ=",
                "fileLength": "15741525",
                "seconds": 74,
                "mediaKey": "x5HsvMd98Z7iLBMcRx1kPcEaL4d7jTHWbjm6FoC2lvg=",
                "caption": "texto con video",
                "gifPlayback": false,
                "height": 720,
                "width": 1280,
                "fileEncSha256": "Eo1ZYAA5P33EC54jOp31SObUDNiPZiQkLhbxNV2iYSs=",
                "directPath": "\/v\/t62.7161-24\/29551220_1314347976321118_3167230320740861317_n.enc?ccb=11-4&oh=01_Q5Aa1QGjGJ935KrUwuF7B11UQtTjsapEuwdyVKeaQZoE9vom9Q&oe=683B4733&_nc_sid=5e03e0",
                "mediaKeyTimestamp": "1746134057",
                "jpegThumbnail": "\/9j\/4AAQSkZJRgABAQAAAQABAAD\/4gHYSUNDX1BST0ZJTEUAAQEAAAHIAAAAAAQwAABtbnR .....  (minuatura base64 no importante)",
                "viewOnce": false
            },
            "base64": "JQD9V\/rfSUk9PYp+dgGnZkBlZsVPLq71XqvVeMxrGdUnBBO2LUyZatWrVS4pmQKcZmYDxmZgBqcfN5vN4Y44tTj5vN5sWbHHHHFncHkAAYBLAcYC4MFUnFupzpW223da59HJxXlsgpZWNOaE6BooQnBCqqnHFDUshCIQkmqWVtpwilsssmSqpxRxHZePsdyg\/hfiVPmnwfu8naIH1zVyV4SO5zMD14YSCzMzMzAAAbMAATizAT4d2C0qqqnHCZFkLNFlkGV3Xdd1bI1OTixYGmmqVVVf\/f\/v3X8\/nhnMAzeAKznLFZyGYMY+fbN+\/v7+\/uQAGT4+Pj4+PjMAPf39\/f39\/fQAAPj4+Pj4+PgAJ9\/f39\/f39yAB\/j4+Pj4+MwADvv7+8cP5BJl4LYJSAUgFJwpeVdAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAOA..... (multimea base64 video)",
        },
        "contextInfo": null,
        "messageType": "videoMessage",
        "messageTimestamp": 1746134065,
        "owner": "tigo1",
        "source": "web"
    },
    "destination": "https:\/\/592c-200-87-152-161.ngrok-free.app\/wp-json\/crm-evolution-api\/v1\/webhook",
    "date_time": "2025-05-01T18:14:26.683Z",
    "sender": "59169375664@s.whatsapp.net",
    "server_url": "http:\/\/localhost:8080",
    "apikey": "0CE98F9E-36BE-401A-99FA-EA4E300942C4"
  }


  -----------------------
  multiples archivos...
  [03-May-2025 01:11:25 UTC] Webhook recibido - Array
(
    [event] => messages.upsert
    [instance] => entel2
    [data] => Array
        (
            [key] => Array
                (
                    [remoteJid] => 59171146267@s.whatsapp.net
                    [fromMe] => 
                    [id] => 3EB0977DD0B4FC0255C8BE
                )

            [pushName] => percy alvarez E1
            [message] => Array
                (
                    [imageMessage] => Array
                        (
                            [url] => https://mmg.whatsapp.net/o1/v/t62.7118-24/f2/m235/AQPdgRAYbg9b9oPxbTNblfz1Vf6odBKXYAWkOsf2zT66jKhnkm6NB8sgtJjpBeRNecVA9gzBerO4i_nCkUZItDyEX4qlQqHG06_cgzr9-A?ccb=9-4&oh=01_Q5Aa1QFjlQhO3kLz4pTVuAKe95hZ87RtCtw3uS1h47VZO7CVyg&oe=683CF7DB&_nc_sid=e6ed6c&mms3=true
                            [mimetype] => image/jpeg
                            [fileSha256] => ID6p8Tmi7DfPC+EanMX2jSFfbaXCF4oABjVDKME2Ok8=
                            [fileLength] => 321827
                            [height] => 933
                            [width] => 1600
                            [mediaKey] => PDqZ39FFnlnO3w1DMjgvNemGe2zYqO47b+WnuylN5i0=
                            [fileEncSha256] => 43QJvVeJoF+tXKQPk033NZ8LyFhSLskAQKmBQYTEavw=
                            [directPath] => /o1/v/t62.7118-24/f2/m235/AQPdgRAYbg9b9oPxbTNblfz1Vf6odBKXYAWkOsf2zT66jKhnkm6NB8sgtJjpBeRNecVA9gzBerO4i_nCkUZItDyEX4qlQqHG06_cgzr9-A?ccb=9-4&oh=01_Q5Aa1QFjlQhO3kLz4pTVuAKe95hZ87RtCtw3uS1h47VZO7CVyg&oe=683CF7DB&_nc_sid=e6ed6c
                            [mediaKeyTimestamp] => 1746234675
                            [jpegThumbnail] => /9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD+2IRCQQiLuMjBOWbaucakaeHSqWc3G730d47Wl66NPRvrZr3MspuvGftbpqbUU01eNlq/dStq9b7r5HsvhPwT4A1TwbqPii41jQdO1TTvtkMGl6hqKt9u2JmOfarCRRluIlBL+Vjem/wCX8jx2d57hswhhIU5ypz5W5qDfJd6p3Ti9viuuXmu07a+tiKEsVhMVPDwlCcJvlSdnJRivh913jJ3aVnd6KSVrZHhHQvBl/qC2+vX9tZWk2o+W90d7CC3LKDIIwwZgoJOOpxjOa/U8onhZ4LmxTXP2kU1aSMZN2IpCfU1tFHNNsqyk46muiCOGq3YpzE88muuCPNqtlGcnnmu2meVXbLelE/Z35/wCWh/kK+fzv/eI/4f1ZtgH+7fr+iP/Z
                            [contextInfo] => Array
                                (
                                    [disappearingMode] => Array
                                        (
                                            [initiator] => CHANGED_IN_CHAT
                                        )

                                )

                            [viewOnce] => 
                        )

                    [messageContextInfo] => Array
                        (
                            [deviceListMetadata] => Array
                                (
                                    [senderKeyHash] => KbLD8xUeBypUMw==
                                    [senderTimestamp] => 1745845546
                                    [senderAccountType] => E2EE
                                    [receiverAccountType] => E2EE
                                    [recipientKeyHash] => phPTTwyQjw86RQ==
                                    [recipientTimestamp] => 1746228286
                                )

                            [deviceListMetadataVersion] => 2
                            [messageSecret] => 8ERRD7Kd0Blup8s9Ke3w468+kV8GBMvcMCQX0xRXa1s=
                        )

                    [base64] => /9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MwBX0s6bd6lJNbgiQpKc9PfrWNjueDq0lzNDvDnxqufD1p9jZvttq3I2Xr1ygXnyyxdT7EHtXHiOG8LiI2a1OzDZ3U5kr6Ff41fHK1v/AA/BrV80a67kxEmuvMo4vmg6x5HDWMyatGrHAwtozhvhp/wAlBh6cNOR/3y1e/mlH/hNhLqfA8HS5+JJwWiuzH8fc+LtSLYz557V6eSx5MGpNas+X44qN5tVg+5zxGeo6nNeu7S0R8da0rdENaMDmlyspJyemxGcHgZpWZTvFa7kkGVfAPvSQLa7JgWLBsDPuKsi1y5b2qyLvfucDFAjsPBWoL4d8T6Rr0SMDY3kN+L90mzSsUnobvw6Yf8LP8ABx/6j+n/APpQlVE48xf+zT9D9FUAAVdoGT1HWtLnwVx7KzE/MMDpSC4pUgAEA7uBz3oDcZgqe2R2p2uJnxB8ePg34i8d/HvxLJaalawwNa2l0EldUXBiRNjMQdpyvAPOPrWdSShqVGN0SfsefDe+8N/GrWri+fH864ccv9mkdOE0rpHSDpX56fYLZH/9k=
                )

            [contextInfo] => Array
                (
                    [disappearingMode] => Array
                        (
                            [initiator] => CHANGED_IN_CHAT
                        )

                )

            [messageType] => imageMessage
            [messageTimestamp] => 1746234679
            [owner] => entel2
            [source] => web
        )

    [destination] => https://0971-2803-b60-bb00-a751-c4ca-b12a-ab9d-c697.ngrok-free.app/wp-json/crm-evolution-api/v1/webhook
    [date_time] => 2025-05-02T22:11:19.948Z
    [sender] => 59168964000@s.whatsapp.net
    [server_url] => http://localhost:8080
    [apikey] => 272F3936-48CE-4CC9-884F-FA833E942936
)


------------
grupo
[Webhook] Datos recibidos: 

{"event":"messages.upsert","instance":"tigo1","data":{"key":

{"remoteJid":"120363141381952108@g.us","fromMe":false,"id":"10C148D6B21E9B5A960AEEE61C8C9065","participant":"24666483777619@lid"},

"pushName":"percy alvarez E1","message":
{"conversation":"test 001  hola","senderKeyDistributionMessage":{"groupId":"120363141381952108@g.us","axolotlSenderKeyDistributionMessage":"MwjLq6qCBhABGiD\/s3Nsp6d8br9w0kyTw5XH1vsLmvyb1xx4xjdT2NJXVyIhBRD1RjBZ1mAaBJjNRdXovPk0VoDpYknM6MLYYge8bQQy"},"messageContextInfo":
{"deviceListMetadata":
{"senderKeyHash":"cNVtsyrjkWwrTw==","senderTimestamp":"1746522944","recipientKeyHash":"trdPiEp3TwSiSw==","recipientTimestamp":"1746549745"},"deviceListMetadataVersion":2,"messageSecret":"4MhBBtHGlLSxV5YPwlFugptoy176FcHCg\/DUyZgFTQU="}},

"messageType":"conversation","messageTimestamp":1746651774,"owner":"tigo1","source":"android"},"destination":"https:\/\/7acd-189-28-78-49.ngrok-free.app\/wp-json\/crm-evolution-api\/v1\/webhook","date_time":"2025-05-07T18:02:54.577Z","sender":"59169375664@s.whatsapp.net","server_url":"http:\/\/localhost:8080","apikey":"4D1D97E2-B0DE-4419-99BB-DCE2A9ACFC5A"}


Datos recibidos: {"event":"messages.upsert","instance":"tigo1","data":{"key":{"remoteJid":"120363332108137298@g.us","fromMe":false,"id":"3A64A0D75B60AB42F46A","participant":"156985366458569@lid"},"pushName":"Majo Suarez Ribera","message":{"extendedTextMessage":{"text":"Perfumes de 35 ML de mujer y hombre a 1 a 16 Bs y 2 en 30 bs","contextInfo":{"expiration":86400,"disappearingMode":{"initiator":"CHANGED_IN_CHAT","trigger":"CHAT_SETTING","initiatedByMe":false}}},"messageContextInfo":{"messageSecret":"61bbozqdqUkkSC+vKT8t6QpvHty1BKTVmTYLdsF0swE="}},"contextInfo":{"expiration":86400,"disappearingMode":{"initiator":"CHANGED_IN_CHAT","trigger":"CHAT_SETTING","initiatedByMe":false}},"messageType":"extendedTextMessage","messageTimestamp":1746745680,"owner":"tigo1","source":"ios"},"destination":"https:\/\/e78d-190-129-199-163.ngrok-free.app\/wp-json\/crm-evolution-api\/v1\/webhook","date_time":"2025-05-08T20:08:00.251Z","sender":"59169375664@s.whatsapp.net","server_url":"http:\/\/localhost:8080","apikey":"4D1D97E2-B0DE-4419-99BB-DCE2A9ACFC5A"}