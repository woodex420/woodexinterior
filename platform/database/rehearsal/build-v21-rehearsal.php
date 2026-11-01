<?php

declare(strict_types=1);

/**
 * A rehearsal copy of the Woodex v2.1 CRM tables, for `woodex:import-v21 --dry-run`.
 *
 * The dry run is the last gate before real data moves (docs/08 §1, docs/18 §4). Waiting for a
 * production dump to rehearse it is how surprises reach the cut-over, so this script builds the five
 * tables the importer reads — with the v2.1 column names and the real vocabularies
 * (`wx_leads.stage`: new, contacted, visit, quote, hold, won, lost; `wx_users.role`: admin, editor) —
 * and fills them with a small, obviously synthetic set: 3 users, 3 clients, 12 leads, 20 notes,
 * 6 bookings.
 *
 * The rows are fictional. They exist to exercise every branch of the importer (a lead with no phone,
 * a note whose lead was never imported, a booking with no enquiry) and to prove the report and its
 * rollback work. Nothing here is evidence about real volumes.
 *
 * Usage:
 *   php database/rehearsal/build-v21-rehearsal.php storage/app/private/rehearsal-v21.sqlite
 *   LEGACY_DB_DRIVER=sqlite LEGACY_DB_SQLITE_PATH=storage/app/private/rehearsal-v21.sqlite \
 *     php artisan woodex:import-v21 --dry-run
 */
$path = $argv[1] ?? 'storage/app/private/rehearsal-v21.sqlite';

if (is_file($path)) {
    unlink($path);
}

$db = new PDO('sqlite:'.$path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec('CREATE TABLE wx_users (
    id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL, role TEXT NOT NULL DEFAULT \'editor\',
    pass_hash TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, pw_ver INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL, last_login TEXT NULL)');

$db->exec('CREATE TABLE wx_clients (
    id INTEGER PRIMARY KEY, name TEXT NOT NULL, phone TEXT, email TEXT, company TEXT, city TEXT,
    address TEXT, notes TEXT, created_at TEXT NOT NULL, type TEXT, source TEXT, tags TEXT, line TEXT,
    designation TEXT, updated_at TEXT)');

$db->exec('CREATE TABLE wx_leads (
    id INTEGER PRIMARY KEY, created_at TEXT NOT NULL, source TEXT NOT NULL, page TEXT,
    name TEXT NOT NULL, phone TEXT, email TEXT, service TEXT, message TEXT, fields TEXT,
    stage TEXT NOT NULL DEFAULT \'new\', assigned_to INTEGER, followup TEXT, value INTEGER NOT NULL DEFAULT 0,
    lost_reason TEXT, client_id INTEGER, tags TEXT, is_read INTEGER NOT NULL DEFAULT 0, ip TEXT,
    company TEXT, designation TEXT, location TEXT, line TEXT, lead_type TEXT, project_type TEXT,
    budget TEXT, area TEXT, priority TEXT, quote_status TEXT, last_contact TEXT, next_at TEXT, next_type TEXT)');

$db->exec('CREATE TABLE wx_lead_notes (
    id INTEGER PRIMARY KEY, lead_id INTEGER NOT NULL, t TEXT NOT NULL, user_name TEXT,
    text TEXT NOT NULL, sys INTEGER NOT NULL DEFAULT 0, kind TEXT, outcome TEXT)');

$db->exec('CREATE TABLE wx_bookings (
    id INTEGER PRIMARY KEY, created_at TEXT NOT NULL, lead_id INTEGER, name TEXT NOT NULL,
    phone TEXT NOT NULL, email TEXT, type TEXT NOT NULL, city TEXT, address TEXT, d TEXT NOT NULL,
    tm TEXT NOT NULL, dur INTEGER NOT NULL DEFAULT 60, status TEXT NOT NULL DEFAULT \'pending\',
    staff_id INTEGER, note TEXT, src TEXT, reminded INTEGER NOT NULL DEFAULT 0)');

$users = $db->prepare('INSERT INTO wx_users (id, name, email, role, pass_hash, created_at) VALUES (?, ?, ?, ?, ?, ?)');
$users->execute([1, 'Imtize Ahmad', 'imtize@woodex.test', 'admin', '$2y$12$rehearsal', '2025-11-02 09:00:00']);
$users->execute([2, 'Sana Tariq', 'sana@woodex.test', 'admin', '$2y$12$rehearsal', '2025-12-14 10:30:00']);
$users->execute([3, 'Bilal Raza', 'bilal@woodex.test', 'editor', '$2y$12$rehearsal', '2026-01-08 12:00:00']);

$clients = $db->prepare('INSERT INTO wx_clients (id, name, phone, email, company, city, address, created_at, type, source, line) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$clients->execute([1, 'Hamza Sheikh', '+92 300 1111111', 'hamza@example.test', null, 'Lahore', 'DHA Phase 6, Lahore', '2025-11-10 09:15:00', 'residential', 'referral', 'retail']);
$clients->execute([2, 'Ayesha Khan', '+92 321 2222222', 'ayesha@example.test', 'Khan Interiors', 'Karachi', 'Clifton Block 2', '2025-12-01 14:00:00', 'corporate', 'instagram', 'retail']);
$clients->execute([3, 'Usman Ali', '+92 333 3333333', null, null, 'Islamabad', 'F-7/2', '2026-01-20 11:00:00', 'residential', 'site-visit', 'retail']);

$leads = $db->prepare('INSERT INTO wx_leads (id, created_at, source, page, name, phone, email, service, message, stage, assigned_to, followup, value, client_id, location, lead_type, project_type, budget, area, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$leads->execute([1, '2026-02-01 10:00:00', 'website', '/services/kitchen', 'Sara Malik', '+92 301 4444444', 'sara@example.test', 'Kitchen design', 'Need a kitchen redo', 'new', 1, '2026-02-05', 1800000, null, 'Lahore', 'residential', 'kitchen', '15-25 lakh', '10 marla', 'high']);
$leads->execute([2, '2026-02-02 11:30:00', 'whatsapp', null, 'Ahmed Raza', '+92 302 5555555', null, 'Full home', '3 bedrooms', 'contacted', 2, '2026-02-06', 4500000, 1, 'Lahore', 'residential', 'full-home', '40+ lakh', '1 kanal', 'high']);
$leads->execute([3, '2026-02-03 09:45:00', 'instagram', null, 'Hina Aslam', '+92 303 6666666', 'hina@example.test', 'Office interior', null, 'quote', 2, null, 900000, 2, 'Karachi', 'corporate', 'office', '10-15 lakh', '1200 sqft', 'medium']);
$leads->execute([4, '2026-02-04 12:00:00', 'website', '/contact', 'Nadia Iqbal', '+92 304 7777777', null, 'Consultation', 'Site visit please', 'visit', 1, null, 0, null, 'Lahore', 'residential', 'kitchen', '5-10 lakh', '5 marla', 'low']);
$leads->execute([5, '2026-02-05 12:00:00', 'website', '/contact', 'Faisal Nadeem', '+92 305 7777777', null, 'Consultation', null, 'hold', null, null, 0, null, 'Lahore', 'residential', 'kitchen', '5-10 lakh', '5 marla', 'low']);
$leads->execute([6, '2026-02-06 12:00:00', 'estimator', '/cost-guides', 'Rabia Shah', '+92 306 7777777', 'rabia@example.test', 'Estimator', null, 'won', 2, null, 2600000, null, 'Islamabad', 'residential', 'full-home', '25-40 lakh', '1 kanal', 'high']);
$leads->execute([7, '2026-02-07 12:00:00', 'website', '/contact', 'Kamran Butt', '+92 307 7777777', null, 'Consultation', null, 'lost', null, null, 0, null, 'Lahore', 'residential', 'kitchen', '5-10 lakh', '5 marla', 'low']);
$leads->execute([8, '2026-02-08 12:00:00', 'booking', null, 'Sadia Yousaf', '+92 308 7777777', null, 'Consultation', null, 'new', null, null, 0, null, 'Lahore', 'residential', 'kitchen', '5-10 lakh', '5 marla', 'low']);
// No phone and no email: the importer must keep the name and warn rather than invent a contact.
$leads->execute([9, '2026-02-09 12:00:00', 'website', '/contact', 'Walk-in Enquiry', null, null, 'Consultation', null, 'new', null, null, 0, null, 'Lahore', 'residential', 'kitchen', '5-10 lakh', '5 marla', 'low']);
// An unreachable phone: imported, but the number is kept in the notes and a warning is written.
$leads->execute([10, '2026-02-10 12:00:00', 'website', '/contact', 'Bad Number', '0300', null, 'Consultation', null, 'new', null, null, 0, null, 'Lahore', 'residential', 'kitchen', '5-10 lakh', '5 marla', 'low']);
$leads->execute([11, '2026-02-11 12:00:00', 'chat', null, 'Chat Visitor', '+92 309 7777777', null, 'Consultation', null, 'new', null, null, 0, null, 'Lahore', 'residential', 'kitchen', '5-10 lakh', '5 marla', 'low']);
$leads->execute([12, '2026-02-12 12:00:00', 'corporate', null, 'Office Client', '+92 310 7777777', 'office@example.test', 'Office fit-out', null, 'contacted', 3, null, 3200000, 2, 'Karachi', 'corporate', 'office', '25-40 lakh', '2000 sqft', 'medium']);

$notes = $db->prepare('INSERT INTO wx_lead_notes (id, lead_id, t, user_name, text, sys, kind, outcome) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$notes->execute([1, 1, '2026-02-01 10:05:00', 'Sana Tariq', 'Called, asked for a site visit.', 0, 'call', 'connected']);
$notes->execute([2, 1, '2026-02-01 10:06:00', null, 'Lead created from the website form.', 1, 'system', null]);
$notes->execute([3, 2, '2026-02-02 12:00:00', 'Bilal Raza', 'Sent the moodboard.', 0, 'whatsapp', null]);
// A note whose lead is not in the source: the importer must skip it and say so.
$notes->execute([4, 99, '2026-02-03 12:00:00', 'Sana Tariq', 'Orphan note — its lead was deleted in v2.1.', 0, 'call', 'no answer']);
for ($i = 5; $i <= 20; $i++) {
    $notes->execute([$i, ($i % 8) + 1, '2026-02-'.sprintf('%02d', $i).' 15:00:00', 'Sana Tariq', "Follow-up note {$i}", 0, 'call', $i % 2 === 0 ? 'connected' : 'no answer']);
}

$bookings = $db->prepare('INSERT INTO wx_bookings (id, created_at, lead_id, name, phone, email, type, city, address, d, tm, dur, status, staff_id, note, src, reminded) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$bookings->execute([1, '2026-02-04 09:00:00', 1, 'Sara Malik', '+92 301 4444444', 'sara@example.test', 'site-visit', 'Lahore', 'House 12, DHA Phase 5', '2026-02-10', '11:00', 60, 'confirmed', 1, 'Bring the tile samples', 'website', 0]);
$bookings->execute([2, '2026-02-05 09:00:00', 2, 'Ahmed Raza', '+92 302 5555555', null, 'consultation', 'Lahore', null, '2026-02-12', '15:30', 45, 'pending', 2, null, 'whatsapp', 0]);
// A booking with no enquiry: the importer must create a lead for it.
$bookings->execute([3, '2026-02-06 09:00:00', null, 'Walk-in Booking', '+92 304 8888888', null, 'consultation', 'Lahore', null, '2026-02-18', '10:00', 30, 'pending', null, null, 'website', 0]);
// An impossible time: skipped with a warning, not guessed.
$bookings->execute([4, '2026-02-07 09:00:00', 3, 'Hina Aslam', '+92 303 6666666', null, 'consultation', 'Karachi', null, '2026-02-19', '99:99', 30, 'pending', null, null, 'website', 0]);
for ($i = 5; $i <= 6; $i++) {
    $bookings->execute([$i, '2026-02-08 09:00:00', null, "Booking {$i}", '+92 304 8888888', null, 'consultation', 'Lahore', null, '2026-02-'.(18 + $i), '10:00', 30, 'pending', null, null, 'website', 0]);
}

$counts = [];
foreach (['wx_users', 'wx_clients', 'wx_leads', 'wx_lead_notes', 'wx_bookings'] as $table) {
    $counts[$table] = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

echo "Rehearsal database written to {$path}\n";
foreach ($counts as $table => $count) {
    echo "  {$table}: {$count}\n";
}
echo "Synthetic rows — fictional people. Not evidence about production volumes.\n";
