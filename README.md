# Trivia Night

Trivia Night is a responsive, real-time multiplayer quiz for up to 12 players per room. Players sign in from their own devices, join with a six-character invite code or link, and answer the same timed questions together. The game runs on PHP and MySQL/MariaDB and uses short polling, so it does not need a separate WebSocket service.

## How to play

1. Create an account and sign in.
2. One player creates a room and shares its invite link or six-character code.
3. Other players create accounts, sign in on their own devices, and join the room before it starts.
4. The host starts the game once at least two people have joined.
5. Everyone gets the same 10 questions, with 20 seconds to answer each. A question advances when everyone answers or its timer ends.
6. A correct answer earns one point; wrong or unanswered questions earn zero. The highest score wins, and a tie is shared.

New account passwords must be at least 6 characters and include at least one letter and one number.

## Requirements

- PHP 8.1+ with PDO MySQL enabled
- MySQL 5.7+ or MariaDB 10.2+
- Apache or another web server configured to run PHP
- HTTPS for a public website

## Local setup with XAMPP

1. Copy this folder into `C:\xampp\htdocs\Multiplayer_trivia_quiz_game`.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. In phpMyAdmin, create a new, empty database named `trivia_game`, select it, and import `schema.sql` once. This creates the tables and inserts 10 sample questions. Do not import it repeatedly; each import adds the sample questions again.
4. In `config.php`, set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` to match your local MySQL account.
5. Set the `SETUP_KEY` definition in `config.php` to a new, private, long random value. Do not reuse your database password.
6. Visit `http://localhost/Multiplayer_trivia_quiz_game/setup.php`, enter that setup key, and create the first admin account. Setup closes after the first admin account is created.
7. Sign in, create a room, and use **Admin** to manage the question bank.

## Hosting

1. Choose a PHP/MySQL host and connect a domain or use the URL supplied by the host. Create a database and database user in the hosting control panel.
2. Upload the project files to the site's document root (often `public_html`). Enable PHP 8.1+ and PDO MySQL, and turn on HTTPS.
3. Import `schema.sql` into the new database.
4. In `config.php`, set the database connection values to the credentials supplied by your host. Alternatively, configure the `TRIVIA_DB_HOST`, `TRIVIA_DB_NAME`, `TRIVIA_DB_USER`, and `TRIVIA_DB_PASS` environment variables.
5. Configure a unique, private `TRIVIA_SETUP_KEY` environment variable. If your host does not support environment variables, replace the `SETUP_KEY` fallback in `config.php` with a long random value. Never deploy with an empty setup key.
6. Open `/setup.php` on your site's HTTPS URL, enter the setup key, and create your first admin account. Remove `setup.php` from the web root after setup as an extra precaution.
7. Visit your site URL, create an account, and invite players. Share the actual public URL from your hosting provider; this project folder by itself is not online.

For production, disable PHP error display and enable server-side error logging. Use a unique admin password and keep database backups. Do not upload a development database or real credentials to a public source repository.
