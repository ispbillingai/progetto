<?php
/** Texts of the guest pages (the room QR), in the guest's browser language. */
declare(strict_types=1);

const GUEST_LANGS = ['it' => 'Italiano', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español'];

const GUEST_TEXT = [
    'room'         => ['it' => 'Camera', 'en' => 'Room', 'de' => 'Zimmer', 'fr' => 'Chambre', 'es' => 'Habitación'],
    'enter_code'   => ['it' => 'Inserisci il codice della camera', 'en' => 'Enter your room code', 'de' => 'Zimmercode eingeben', 'fr' => 'Saisissez le code de la chambre', 'es' => 'Introduce el código de la habitación'],
    'code_hint'    => ['it' => 'Il codice te lo dà la reception al check-in.', 'en' => 'Reception gives you the code at check-in.', 'de' => 'Den Code erhalten Sie beim Check-in an der Rezeption.', 'fr' => 'La réception vous donne le code à l\'arrivée.', 'es' => 'La recepción te da el código al hacer el check-in.'],
    'confirm'      => ['it' => 'Conferma', 'en' => 'Confirm', 'de' => 'Bestätigen', 'fr' => 'Valider', 'es' => 'Confirmar'],
    'wrong_code'   => ['it' => 'Codice errato. Riprova.', 'en' => 'Wrong code. Try again.', 'de' => 'Falscher Code. Bitte erneut versuchen.', 'fr' => 'Code incorrect. Réessayez.', 'es' => 'Código incorrecto. Inténtalo de nuevo.'],
    'too_many'     => ['it' => 'Troppi tentativi. Riprova tra qualche minuto o chiedi alla reception.', 'en' => 'Too many attempts. Try again in a few minutes or ask reception.', 'de' => 'Zu viele Versuche. Bitte in einigen Minuten erneut versuchen oder an der Rezeption fragen.', 'fr' => 'Trop de tentatives. Réessayez dans quelques minutes ou demandez à la réception.', 'es' => 'Demasiados intentos. Inténtalo en unos minutos o pregunta en recepción.'],
    'expired'      => ['it' => 'Il soggiorno di questa camera è terminato. Per usare il servizio chiedi il nuovo codice alla reception.', 'en' => 'The stay in this room has ended. Ask reception for the new code.', 'de' => 'Der Aufenthalt in diesem Zimmer ist beendet. Bitte fragen Sie an der Rezeption nach dem neuen Code.', 'fr' => 'Le séjour de cette chambre est terminé. Demandez le nouveau code à la réception.', 'es' => 'La estancia de esta habitación ha terminado. Pide el nuevo código en recepción.'],
    'cancel'       => ['it' => 'Annulla', 'en' => 'Cancel', 'de' => 'Abbrechen', 'fr' => 'Annuler', 'es' => 'Cancelar'],
    'send'         => ['it' => 'Invia', 'en' => 'Send', 'de' => 'Senden', 'fr' => 'Envoyer', 'es' => 'Enviar'],
    'note_label'   => ['it' => 'Vuoi aggiungere qualcosa?', 'en' => 'Anything to add?', 'de' => 'Möchten Sie etwas hinzufügen?', 'fr' => 'Quelque chose à ajouter ?', 'es' => '¿Quieres añadir algo?'],
    'note_ph'      => ['it' => 'Facoltativo', 'en' => 'Optional', 'de' => 'Optional', 'fr' => 'Facultatif', 'es' => 'Opcional'],
    'time_label'   => ['it' => 'A che ora?', 'en' => 'At what time?', 'de' => 'Um wie viel Uhr?', 'fr' => 'À quelle heure ?', 'es' => '¿A qué hora?'],
    'now'          => ['it' => 'Adesso', 'en' => 'Now', 'de' => 'Jetzt', 'fr' => 'Maintenant', 'es' => 'Ahora'],
    'today'        => ['it' => 'Oggi', 'en' => 'Today', 'de' => 'Heute', 'fr' => 'Aujourd\'hui', 'es' => 'Hoy'],
    'tomorrow'     => ['it' => 'Domani', 'en' => 'Tomorrow', 'de' => 'Morgen', 'fr' => 'Demain', 'es' => 'Mañana'],
    'items_label'  => ['it' => 'Cosa desideri?', 'en' => 'What would you like?', 'de' => 'Was möchten Sie?', 'fr' => 'Que souhaitez-vous ?', 'es' => '¿Qué deseas?'],
    'total'        => ['it' => 'Totale', 'en' => 'Total', 'de' => 'Gesamt', 'fr' => 'Total', 'es' => 'Total'],
    'pick_items'   => ['it' => 'Scegli almeno una voce.', 'en' => 'Choose at least one item.', 'de' => 'Bitte mindestens einen Artikel wählen.', 'fr' => 'Choisissez au moins un article.', 'es' => 'Elige al menos un artículo.'],
    'pick_time'    => ['it' => 'Scegli un orario.', 'en' => 'Choose a time.', 'de' => 'Bitte eine Uhrzeit wählen.', 'fr' => 'Choisissez une heure.', 'es' => 'Elige una hora.'],
    'hours'        => ['it' => 'Servizio {hours}', 'en' => 'Service {hours}', 'de' => 'Service {hours}', 'fr' => 'Service {hours}', 'es' => 'Servicio {hours}'],
    'closed_now'   => ['it' => 'Ora chiuso', 'en' => 'Closed now', 'de' => 'Jetzt geschlossen', 'fr' => 'Fermé actuellement', 'es' => 'Cerrado ahora'],
    'closed_msg'   => ['it' => 'Questo servizio è chiuso adesso ({hours}).', 'en' => 'This service is closed right now ({hours}).', 'de' => 'Dieser Service ist derzeit geschlossen ({hours}).', 'fr' => 'Ce service est fermé en ce moment ({hours}).', 'es' => 'Este servicio está cerrado ahora ({hours}).'],
    'your_requests'=> ['it' => 'Le tue richieste', 'en' => 'Your requests', 'de' => 'Ihre Anfragen', 'fr' => 'Vos demandes', 'es' => 'Tus solicitudes'],
    'st_open'      => ['it' => 'Inviata: ce ne occupiamo a breve.', 'en' => 'Sent: we will take care of it shortly.', 'de' => 'Gesendet: wir kümmern uns in Kürze darum.', 'fr' => 'Envoyée : nous nous en occupons rapidement.', 'es' => 'Enviada: nos ocupamos enseguida.'],
    'st_taken'     => ['it' => '{name} se ne sta occupando.', 'en' => '{name} is taking care of it.', 'de' => '{name} kümmert sich darum.', 'fr' => '{name} s\'en occupe.', 'es' => '{name} se está ocupando.'],
    'st_taken_x'   => ['it' => 'Ce ne stiamo occupando.', 'en' => 'We are taking care of it.', 'de' => 'Wir kümmern uns darum.', 'fr' => 'Nous nous en occupons.', 'es' => 'Nos estamos ocupando.'],
    'st_scheduled' => ['it' => 'Programmata per le {time}.', 'en' => 'Scheduled for {time}.', 'de' => 'Geplant für {time}.', 'fr' => 'Prévue à {time}.', 'es' => 'Programada para las {time}.'],
    'st_done'      => ['it' => 'Fatto ✓', 'en' => 'Done ✓', 'de' => 'Erledigt ✓', 'fr' => 'Fait ✓', 'es' => 'Hecho ✓'],
    'reply'        => ['it' => 'Risposta', 'en' => 'Reply', 'de' => 'Antwort', 'fr' => 'Réponse', 'es' => 'Respuesta'],
    'remind'       => ['it' => 'Sollecita', 'en' => 'Remind', 'de' => 'Erinnern', 'fr' => 'Relancer', 'es' => 'Recordar'],
    'reminded'     => ['it' => 'Sollecito inviato.', 'en' => 'Reminder sent.', 'de' => 'Erinnerung gesendet.', 'fr' => 'Relance envoyée.', 'es' => 'Recordatorio enviado.'],
    'wait'         => ['it' => 'Richiesta già inviata, attendi qualche secondo.', 'en' => 'Already sent, please wait a few seconds.', 'de' => 'Bereits gesendet, bitte einige Sekunden warten.', 'fr' => 'Déjà envoyée, patientez quelques secondes.', 'es' => 'Ya enviada, espera unos segundos.'],
    'sent'         => ['it' => 'Richiesta inviata.', 'en' => 'Request sent.', 'de' => 'Anfrage gesendet.', 'fr' => 'Demande envoyée.', 'es' => 'Solicitud enviada.'],
    'scheduled'    => ['it' => 'Richiesta programmata.', 'en' => 'Request scheduled.', 'de' => 'Anfrage geplant.', 'fr' => 'Demande programmée.', 'es' => 'Solicitud programada.'],
    'urgent_q'     => ['it' => 'Inviare una richiesta urgente? Tutto il personale viene avvisato subito.', 'en' => 'Send an urgent request? All the staff is alerted at once.', 'de' => 'Dringende Anfrage senden? Das gesamte Personal wird sofort benachrichtigt.', 'fr' => 'Envoyer une demande urgente ? Tout le personnel est alerté immédiatement.', 'es' => '¿Enviar una solicitud urgente? Se avisa a todo el personal de inmediato.'],
    'urgent'       => ['it' => 'Urgente', 'en' => 'Urgent', 'de' => 'Dringend', 'fr' => 'Urgent', 'es' => 'Urgente'],
    'dnd'          => ['it' => 'Non disturbare', 'en' => 'Do not disturb', 'de' => 'Bitte nicht stören', 'fr' => 'Ne pas déranger', 'es' => 'No molestar'],
    'dnd_on'       => ['it' => 'Non disturbare attivo: il personale non entrerà in camera. Tocca per disattivare.', 'en' => 'Do not disturb is on: the staff will not enter the room. Tap to turn it off.', 'de' => 'Bitte nicht stören ist aktiv: das Personal betritt das Zimmer nicht. Zum Ausschalten tippen.', 'fr' => 'Ne pas déranger activé : le personnel n\'entrera pas. Touchez pour désactiver.', 'es' => 'No molestar activado: el personal no entrará. Toca para desactivar.'],
    'info'         => ['it' => 'Informazioni utili', 'en' => 'Useful information', 'de' => 'Nützliche Informationen', 'fr' => 'Informations utiles', 'es' => 'Información útil'],
    'speak'        => ['it' => 'Parla', 'en' => 'Speak', 'de' => 'Sprechen', 'fr' => 'Parler', 'es' => 'Hablar'],
    'speak_hint'   => ['it' => 'Di\' cosa ti serve, ad esempio "mi servono due asciugamani"', 'en' => 'Say what you need, e.g. "I need two towels"', 'de' => 'Sagen Sie, was Sie brauchen, z. B. „zwei Handtücher bitte“', 'fr' => 'Dites ce qu\'il vous faut, par ex. « deux serviettes »', 'es' => 'Di lo que necesitas, p. ej. "dos toallas"'],
    'listening'    => ['it' => 'Ti ascolto…', 'en' => 'Listening…', 'de' => 'Ich höre…', 'fr' => 'Je vous écoute…', 'es' => 'Te escucho…'],
    'heard'        => ['it' => 'Ho capito: “{text}”', 'en' => 'I heard: “{text}”', 'de' => 'Verstanden: „{text}“', 'fr' => 'J\'ai compris : « {text} »', 'es' => 'He entendido: “{text}”'],
    'no_match'     => ['it' => 'Non ho trovato il pulsante giusto: la richiesta va alla reception. Controlla e invia.', 'en' => 'No matching button: the request goes to reception. Check it and send.', 'de' => 'Kein passender Button: die Anfrage geht an die Rezeption. Prüfen und senden.', 'fr' => 'Aucun bouton ne correspond : la demande va à la réception. Vérifiez et envoyez.', 'es' => 'No encontré el botón adecuado: la solicitud va a recepción. Revisa y envía.'],
    'not_heard'    => ['it' => 'Non ho sentito nulla. Riprova.', 'en' => 'I did not hear anything. Try again.', 'de' => 'Ich habe nichts gehört. Bitte erneut versuchen.', 'fr' => 'Je n\'ai rien entendu. Réessayez.', 'es' => 'No he oído nada. Inténtalo de nuevo.'],
    'mic_denied'   => ['it' => 'Microfono non consentito: abilitalo nelle impostazioni del browser.', 'en' => 'Microphone not allowed: enable it in the browser settings.', 'de' => 'Mikrofon nicht erlaubt: bitte in den Browsereinstellungen freigeben.', 'fr' => 'Micro non autorisé : activez-le dans les réglages du navigateur.', 'es' => 'Micrófono no permitido: actívalo en los ajustes del navegador.'],
    'error'        => ['it' => 'Errore di connessione. Riprova.', 'en' => 'Connection error. Try again.', 'de' => 'Verbindungsfehler. Bitte erneut versuchen.', 'fr' => 'Erreur de connexion. Réessayez.', 'es' => 'Error de conexión. Inténtalo de nuevo.'],
    'not_found'    => ['it' => 'Questo QR non è attivo. Chiedi alla reception.', 'en' => 'This QR code is not active. Please ask reception.', 'de' => 'Dieser QR-Code ist nicht aktiv. Bitte fragen Sie an der Rezeption.', 'fr' => 'Ce QR code n\'est pas actif. Demandez à la réception.', 'es' => 'Este código QR no está activo. Pregunta en recepción.'],
];

function guest_lang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;
    $pick = (string) ($_GET['lang'] ?? $_COOKIE['glang'] ?? '');
    if (!isset(GUEST_LANGS[$pick])) {
        $pick = 'it';
        foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
            $code = strtolower(substr(trim($part), 0, 2));
            if (isset(GUEST_LANGS[$code])) { $pick = $code; break; }
        }
    } elseif (isset($_GET['lang'])) {
        setcookie('glang', $pick, ['expires' => time() + 365 * 86400, 'path' => app_path(), 'samesite' => 'Lax']);
    }
    return $lang = $pick;
}

function gt(string $key): string
{
    return GUEST_TEXT[$key][guest_lang()] ?? GUEST_TEXT[$key]['it'] ?? $key;
}

/** All texts in the guest's language, for guest.js. */
function guest_texts(): array
{
    $out = [];
    foreach (array_keys(GUEST_TEXT) as $k) $out[$k] = gt($k);
    return $out;
}
