<?php
/**
 * Default catalogue of a new hotel: departments, what a guest can ask for (with names in
 * the five guest languages) and a few example room-service items. The manager then edits
 * everything in Admin › Reparti, Richieste and Menu.
 */
declare(strict_types=1);

/** kind => [name, icon, open_from, open_to] */
const DEFAULT_DEPARTMENTS = [
    'reception'    => ['Reception', '🛎️', null, null],
    'housekeeping' => ['Piani', '🧹', null, null],
    'maintenance'  => ['Manutenzione', '🔧', null, null],
    'bar'          => ['Bar', '🍹', '10:00:00', '23:00:00'],
    'kitchen'      => ['Cucina', '🍽️', '07:00:00', '22:00:00'],
];

/** [dept kind, icon, it, en, de, fr, es, hint (it), ask_time, ask_items, urgent, lead_minutes] */
const DEFAULT_REQUEST_TYPES = [
    ['reception', '🛎️', 'Chiama la reception', 'Call reception', 'Rezeption rufen', 'Appeler la réception', 'Llamar a recepción', null, 0, 0, 0, 0],
    ['reception', 'ℹ️', 'Informazioni', 'Information', 'Informationen', 'Informations', 'Información', 'Scrivi la tua domanda', 0, 0, 0, 0],
    ['reception', '⏰', 'Sveglia', 'Wake-up call', 'Weckruf', 'Réveil', 'Despertador', null, 1, 0, 0, 0],
    ['reception', '🕛', 'Late check-out', 'Late check-out', 'Später Check-out', 'Départ tardif', 'Salida tardía', 'Fino a che ora?', 0, 0, 0, 0],
    ['reception', '🚕', 'Taxi o transfer', 'Taxi or transfer', 'Taxi oder Transfer', 'Taxi ou transfert', 'Taxi o traslado', 'Dove vai e per quante persone?', 1, 0, 0, 30],
    ['reception', '🧳', 'Deposito bagagli', 'Luggage storage', 'Gepäckaufbewahrung', 'Bagagerie', 'Consigna de equipaje', null, 0, 0, 0, 0],
    ['reception', '🔑', 'Chiave o tessera', 'Key or key card', 'Schlüssel oder Karte', 'Clé ou carte', 'Llave o tarjeta', 'Smarrita, seconda copia, non funziona…', 0, 0, 0, 0],
    ['reception', '🧾', 'Conto e fattura', 'Bill and invoice', 'Rechnung', 'Note et facture', 'Cuenta y factura', 'Dati per la fattura, se servono', 0, 0, 0, 0],
    ['reception', '🅿️', 'Parcheggio', 'Parking', 'Parkplatz', 'Parking', 'Aparcamiento', 'Targa dell\'auto', 0, 0, 0, 0],
    ['reception', '📶', 'Problema Wi‑Fi', 'Wi‑Fi problem', 'WLAN-Problem', 'Problème Wi‑Fi', 'Problema de Wi‑Fi', null, 0, 0, 0, 0],
    ['reception', '🗺️', 'Consigli e prenotazioni', 'Tips and bookings', 'Tipps und Reservierungen', 'Conseils et réservations', 'Consejos y reservas', 'Ristoranti, escursioni, musei…', 0, 0, 0, 0],
    ['reception', '😕', 'Segnalazione', 'Report a problem', 'Beschwerde', 'Signaler un problème', 'Reclamación', 'Raccontaci cosa non va', 0, 0, 0, 0],
    ['reception', '🚨', 'Emergenza medica', 'Medical emergency', 'Medizinischer Notfall', 'Urgence médicale', 'Emergencia médica', 'Cosa è successo?', 0, 0, 1, 0],
    ['reception', '🆘', 'Sicurezza', 'Security', 'Sicherheit', 'Sécurité', 'Seguridad', 'Cosa è successo?', 0, 0, 1, 0],

    ['housekeeping', '🛁', 'Asciugamani', 'Towels', 'Handtücher', 'Serviettes', 'Toallas', 'Quanti?', 0, 0, 0, 0],
    ['housekeeping', '🛏️', 'Cuscini o coperte', 'Pillows or blankets', 'Kissen oder Decken', 'Oreillers ou couvertures', 'Almohadas o mantas', null, 0, 0, 0, 0],
    ['housekeeping', '🧴', 'Cortesia bagno', 'Toiletries', 'Pflegeprodukte', 'Produits de toilette', 'Artículos de aseo', 'Sapone, shampoo, carta igienica…', 0, 0, 0, 0],
    ['housekeeping', '🧹', 'Rifare la camera', 'Make up the room', 'Zimmer reinigen', 'Faire la chambre', 'Arreglar la habitación', null, 1, 0, 0, 0],
    ['housekeeping', '🧊', 'Minibar', 'Minibar', 'Minibar', 'Minibar', 'Minibar', 'Cosa manca?', 0, 0, 0, 0],
    ['housekeeping', '👕', 'Lavanderia o stiratura', 'Laundry or ironing', 'Wäsche oder Bügeln', 'Blanchisserie ou repassage', 'Lavandería o planchado', null, 0, 0, 0, 0],
    ['housekeeping', '👶', 'Culla o lettino', 'Baby cot', 'Babybett', 'Lit bébé', 'Cuna', null, 0, 0, 0, 0],
    ['housekeeping', '🗑️', 'Pulizia extra', 'Extra cleaning', 'Zusätzliche Reinigung', 'Nettoyage supplémentaire', 'Limpieza extra', null, 0, 0, 0, 0],

    ['maintenance', '❄️', 'Aria condizionata o riscaldamento', 'Air conditioning or heating', 'Klimaanlage oder Heizung', 'Climatisation ou chauffage', 'Aire acondicionado o calefacción', 'Troppo caldo, troppo freddo, non parte…', 0, 0, 0, 0],
    ['maintenance', '📺', 'TV', 'TV', 'Fernseher', 'Télévision', 'Televisión', null, 0, 0, 0, 0],
    ['maintenance', '🚿', 'Acqua calda', 'Hot water', 'Warmwasser', 'Eau chaude', 'Agua caliente', null, 0, 0, 0, 0],
    ['maintenance', '💡', 'Luci', 'Lights', 'Licht', 'Éclairage', 'Luces', null, 0, 0, 0, 0],
    ['maintenance', '💧', 'Perdita d\'acqua', 'Water leak', 'Wasserleck', 'Fuite d\'eau', 'Fuga de agua', null, 0, 0, 0, 0],
    ['maintenance', '🔒', 'Cassaforte', 'Safe', 'Safe', 'Coffre-fort', 'Caja fuerte', null, 0, 0, 0, 0],
    ['maintenance', '🚪', 'Serratura', 'Door lock', 'Türschloss', 'Serrure', 'Cerradura', null, 0, 0, 0, 0],
    ['maintenance', '🔊', 'Rumore', 'Noise', 'Lärm', 'Bruit', 'Ruido', 'Da dove viene?', 0, 0, 0, 0],

    ['bar', '🍹', 'Ordina dal bar', 'Order from the bar', 'Bar-Bestellung', 'Commander au bar', 'Pedir al bar', null, 0, 1, 0, 0],

    ['kitchen', '🍽️', 'Servizio in camera', 'Room service', 'Zimmerservice', 'Service en chambre', 'Servicio de habitaciones', null, 0, 1, 0, 0],
    ['kitchen', '🥐', 'Colazione in camera', 'Breakfast in the room', 'Frühstück im Zimmer', 'Petit-déjeuner en chambre', 'Desayuno en la habitación', null, 1, 1, 0, 30],
    ['kitchen', '🥗', 'Esigenze alimentari', 'Dietary needs', 'Ernährungswünsche', 'Besoins alimentaires', 'Necesidades alimentarias', 'Allergie, intolleranze, senza glutine, vegano…', 0, 0, 0, 0],
    ['kitchen', '🍷', 'Prenota un tavolo', 'Book a table', 'Tisch reservieren', 'Réserver une table', 'Reservar mesa', 'Per quante persone?', 1, 0, 0, 0],
];

/** [dept kind, category, it, en, price] – examples the manager will replace. */
const DEFAULT_MENU_ITEMS = [
    ['bar', 'Bevande', 'Acqua naturale 0,5 l', 'Still water 0.5 l', 2.00],
    ['bar', 'Bevande', 'Acqua frizzante 0,5 l', 'Sparkling water 0.5 l', 2.00],
    ['bar', 'Bevande', 'Caffè espresso', 'Espresso', 1.50],
    ['bar', 'Bevande', 'Cappuccino', 'Cappuccino', 2.50],
    ['bar', 'Bevande', 'Tè o tisana', 'Tea or herbal tea', 2.50],
    ['bar', 'Bevande', 'Succo di frutta', 'Fruit juice', 3.00],
    ['bar', 'Bevande', 'Birra 0,33 l', 'Beer 0.33 l', 4.00],
    ['bar', 'Bevande', 'Calice di vino', 'Glass of wine', 5.00],
    ['bar', 'Aperitivi', 'Spritz', 'Spritz', 6.00],
    ['kitchen', 'Colazione', 'Colazione continentale', 'Continental breakfast', 12.00],
    ['kitchen', 'Colazione', 'Cornetto e cappuccino', 'Croissant and cappuccino', 5.00],
    ['kitchen', 'Piatti', 'Club sandwich', 'Club sandwich', 9.00],
    ['kitchen', 'Piatti', 'Insalata mista', 'Mixed salad', 7.00],
    ['kitchen', 'Piatti', 'Pasta al pomodoro', 'Pasta with tomato sauce', 9.00],
];

/** Creates the departments, request types and example items of a new hotel. */
function seed_hotel_defaults(int $hotelId): void
{
    $pdo = db();
    $deptIds = [];
    $sort = 0;
    foreach (DEFAULT_DEPARTMENTS as $kind => [$name, $icon, $from, $to]) {
        $pdo->prepare('INSERT INTO departments (hotel_id, kind, name, icon, open_from, open_to, sort) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$hotelId, $kind, $name, $icon, $from, $to, $sort++]);
        $deptIds[$kind] = (int) $pdo->lastInsertId();
    }
    $sort = 0;
    $st = $pdo->prepare('INSERT INTO request_types (hotel_id, department_id, icon, names, hint, ask_time, ask_items, urgent, lead_minutes, sort)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach (DEFAULT_REQUEST_TYPES as [$kind, $icon, $it, $en, $de, $fr, $es, $hint, $time, $items, $urgent, $lead]) {
        $names = json_encode(['it' => $it, 'en' => $en, 'de' => $de, 'fr' => $fr, 'es' => $es], JSON_UNESCAPED_UNICODE);
        $st->execute([$hotelId, $deptIds[$kind], $icon, $names, $hint, $time, $items, $urgent, $lead, $sort++]);
    }
    $sort = 0;
    $st = $pdo->prepare('INSERT INTO menu_items (hotel_id, department_id, category, names, price, sort) VALUES (?, ?, ?, ?, ?, ?)');
    foreach (DEFAULT_MENU_ITEMS as [$kind, $cat, $it, $en, $price]) {
        $st->execute([$hotelId, $deptIds[$kind], $cat, json_encode(['it' => $it, 'en' => $en], JSON_UNESCAPED_UNICODE), $price, $sort++]);
    }
}
