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

/** Italian name => words that make a spoken request match that button (it, en, de, fr, es). */
const DEFAULT_KEYWORDS = [
    'Chiama la reception' => 'reception, portineria, chiamare, receptionist, front desk, rezeption, réception, recepción',
    'Informazioni' => 'informazione, informazioni, domanda, sapere, information, info, question, frage, renseignement, pregunta',
    'Sveglia' => 'sveglia, svegliare, svegliatemi, svegliarmi, wake, wake-up, alarm, weckruf, wecken, réveil, réveiller, despertador, despertar',
    'Late check-out' => 'late check-out, late checkout, check-out tardi, lasciare la camera tardi, später check-out, départ tardif, salida tardía',
    'Taxi o transfer' => 'taxi, tassì, transfer, navetta, aeroporto, stazione, cab, shuttle, airport, station, flughafen, bahnhof, aéroport, gare, aeropuerto, estación',
    'Deposito bagagli' => 'bagagli, bagaglio, valigie, valigia, deposito, luggage, baggage, suitcase, gepäck, koffer, bagages, valise, equipaje, maletas',
    'Chiave o tessera' => 'chiave, chiavi, tessera, badge, key, keycard, key card, schlüssel, karte, clé, llave, tarjeta',
    'Conto e fattura' => 'conto, fattura, pagare, pagamento, ricevuta, bill, invoice, pay, receipt, rechnung, bezahlen, facture, payer, cuenta, factura, pagar',
    'Parcheggio' => 'parcheggio, parcheggiare, auto, macchina, garage, parking, park, car, parkplatz, parken, voiture, aparcamiento, aparcar, coche',
    'Problema Wi‑Fi' => 'wifi, wi-fi, internet, rete, connessione, password, wlan, verbindung, connexion, conexión, contraseña',
    'Consigli e prenotazioni' => 'consiglio, consigli, consigliare, ristorante, prenotare, prenotazione, escursione, museo, visita, tour, recommend, recommendation, booking, book, excursion, restaurant, empfehlung, ausflug, conseil, réservation, consejo, reserva, excursión',
    'Segnalazione' => 'segnalazione, segnalare, reclamo, lamentela, lamentare, problema, complaint, complain, report, beschwerde, réclamation, plainte, queja, reclamación',
    'Emergenza medica' => 'emergenza, medico, dottore, ambulanza, malore, sto male, ferito, emergency, doctor, ambulance, sick, hurt, injured, arzt, notfall, krank, médecin, urgence, blessé, médico, emergencia, enfermo, herido',
    'Sicurezza' => 'sicurezza, aiuto, polizia, ladro, furto, pericolo, security, help, police, thief, danger, sicherheit, hilfe, polizei, sécurité, police, au secours, seguridad, ayuda, policía',
    'Asciugamani' => 'asciugamano, asciugamani, telo, teli, accappatoio, towel, towels, bathrobe, handtuch, handtücher, bademantel, serviette, serviettes, peignoir, toalla, toallas, albornoz',
    'Cuscini o coperte' => 'cuscino, cuscini, coperta, coperte, piumone, lenzuola, lenzuolo, pillow, pillows, blanket, duvet, sheets, kissen, decke, bettwäsche, oreiller, couverture, draps, almohada, manta, sábanas',
    'Cortesia bagno' => 'sapone, shampoo, bagnoschiuma, carta igienica, dentifricio, spazzolino, cortesia, soap, toilet paper, toothbrush, toothpaste, toiletries, seife, toilettenpapier, zahnbürste, savon, papier toilette, brosse à dents, jabón, papel higiénico, cepillo',
    'Rifare la camera' => 'rifare, rifare la camera, pulire la camera, pulizia, riordinare, letto, make up, make up the room, clean the room, cleaning, housekeeping, zimmer reinigen, aufräumen, faire la chambre, ménage, limpiar, arreglar la habitación',
    'Minibar' => 'minibar, frigobar, frigo, mini bar, frigorifero, fridge, kühlschrank, frigo, nevera',
    'Lavanderia o stiratura' => 'lavanderia, lavare, stirare, stiratura, ferro da stiro, bucato, laundry, ironing, iron, wash, washing, wäsche, bügeln, waschen, blanchisserie, repassage, laver, lavandería, planchar, lavar',
    'Culla o lettino' => 'culla, lettino, bambino, neonato, cot, crib, baby, babybett, kinderbett, lit bébé, berceau, cuna, bebé',
    'Pulizia extra' => 'pulizia extra, sporco, sporca, pulire, aspirapolvere, extra cleaning, dirty, vacuum, schmutzig, staubsauger, sale, nettoyage, aspirateur, sucio, limpieza, aspiradora',
    'Aria condizionata o riscaldamento' => 'aria condizionata, condizionatore, riscaldamento, termosifone, caldo, freddo, temperatura, clima, air conditioning, aircon, heating, heater, hot, cold, temperature, klimaanlage, heizung, heiß, kalt, climatisation, chauffage, chaud, froid, aire acondicionado, calefacción, calor, frío',
    'TV' => 'tv, televisione, televisore, telecomando, canali, television, remote, channels, fernseher, fernbedienung, télévision, télécommande, televisión, mando',
    'Acqua calda' => 'acqua calda, acqua fredda, doccia, hot water, shower, warmwasser, dusche, eau chaude, douche, agua caliente, ducha',
    'Luci' => 'luce, luci, lampada, lampadina, interruttore, corrente, elettricità, presa, light, lights, lamp, bulb, power, socket, licht, lampe, strom, steckdose, lumière, ampoule, prise, luz, luces, bombilla, enchufe',
    'Perdita d\'acqua' => 'perdita, perde acqua, allagamento, gocciola, rubinetto, lavandino, scarico, otturato, water leak, leak, leaking, dripping, tap, faucet, sink, drain, clogged, wasserleck, tropft, wasserhahn, abfluss, fuite, robinet, lavabo, bouché, fuga, gotea, grifo, desagüe, atascado',
    'Cassaforte' => 'cassaforte, safe, tresor, coffre-fort, caja fuerte',
    'Serratura' => 'serratura, porta, non si apre, non si chiude, lock, door, türschloss, tür, serrure, porte, cerradura, puerta',
    'Rumore' => 'rumore, rumoroso, chiasso, vicini, noise, noisy, loud, neighbours, lärm, laut, bruit, bruyant, ruido',
    'Ordina dal bar' => 'bar, bere, bevanda, bevande, drink, drinks, caffè, birra, vino, acqua, aperitivo, cocktail, spritz, coffee, beer, wine, water, getränk, kaffee, bier, wasser, boisson, café, bière, bebida, cerveza, agua',
    'Servizio in camera' => 'servizio in camera, mangiare, cibo, pranzo, cena, ordinare, ordine, piatto, fame, panino, insalata, pasta, room service, food, lunch, dinner, order, hungry, sandwich, zimmerservice, essen, mittagessen, abendessen, repas, déjeuner, dîner, manger, comida, almuerzo, cena, comer',
    'Colazione in camera' => 'colazione, breakfast, frühstück, petit-déjeuner, petit déjeuner, desayuno, cornetto, cappuccino, croissant',
    'Esigenze alimentari' => 'allergia, allergie, allergico, intollerante, intolleranza, celiaco, glutine, vegano, vegetariano, lattosio, allergy, allergic, gluten, vegan, vegetarian, lactose, allergisch, glutenfrei, végétalien, végétarien, sans gluten, alergia, alérgico, sin gluten, vegano',
    'Prenota un tavolo' => 'prenotare un tavolo, tavolo, ristorante, cenare, book a table, table, reserve, tisch, tisch reservieren, réserver une table, reservar mesa, mesa',
];

/** Default spoken-request keywords of a button, by its Italian name (empty when it is not a default one). */
function default_keywords(string $itName): string
{
    return DEFAULT_KEYWORDS[$itName] ?? '';
}

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
    $st = $pdo->prepare('INSERT INTO request_types (hotel_id, department_id, icon, names, hint, keywords, ask_time, ask_items, urgent, lead_minutes, sort)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach (DEFAULT_REQUEST_TYPES as [$kind, $icon, $it, $en, $de, $fr, $es, $hint, $time, $items, $urgent, $lead]) {
        $names = json_encode(['it' => $it, 'en' => $en, 'de' => $de, 'fr' => $fr, 'es' => $es], JSON_UNESCAPED_UNICODE);
        $st->execute([$hotelId, $deptIds[$kind], $icon, $names, $hint, default_keywords($it) ?: null, $time, $items, $urgent, $lead, $sort++]);
    }
    $sort = 0;
    $st = $pdo->prepare('INSERT INTO menu_items (hotel_id, department_id, category, names, price, sort) VALUES (?, ?, ?, ?, ?, ?)');
    foreach (DEFAULT_MENU_ITEMS as [$kind, $cat, $it, $en, $price]) {
        $st->execute([$hotelId, $deptIds[$kind], $cat, json_encode(['it' => $it, 'en' => $en], JSON_UNESCAPED_UNICODE), $price, $sort++]);
    }
}
