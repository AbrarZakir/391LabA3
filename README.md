# Workshop Appointments

The customer page shows remaining slots by date and accepts service bookings. The admin page lists appointments and lets staff change the date or mechanic. Both pages use the same booking rules.

## Run locally

1. Start the XAMPP database service. For a fresh setup, create and select a database named `workshop` in phpMyAdmin, then import `schema.sql`. The existing XAMPP database already has the tables and seed data.
2. From this folder, choose a password for the admin account and start PHP. In macOS Terminal with zsh, use:

   ```zsh
   read -rs 'workshop_password?Choose an admin password: '
   printf '\n'
   export WORKSHOP_ADMIN_PASSWORD_HASH="$(printf '%s' "$workshop_password" | php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);')"
   unset workshop_password
   php -S 127.0.0.1:8000
   ```

3. Open the [customer page](http://127.0.0.1:8000/) or [admin page](http://127.0.0.1:8000/admin.php). The admin username is `admin`.

## Deploy for assessment

1. On a host with HTTPS, PHP 8.1 or later, `pdo_mysql`, `mbstring`, and MySQL or MariaDB, create a database and import `schema.sql` into it.
2. Configure `WORKSHOP_DB_HOST`, `WORKSHOP_DB_PORT`, `WORKSHOP_DB_NAME`, `WORKSHOP_DB_USER`, and `WORKSHOP_DB_PASSWORD` in the server's PHP environment. Use a dedicated account with `SELECT`, `INSERT`, and `UPDATE` access to the project database.
3. Configure `WORKSHOP_ADMIN_PASSWORD_HASH` in that same environment. Use the hash from `password_hash()` as the value. The server must pass HTTP Basic authentication credentials to PHP.
4. Upload `index.php`, `book.php`, `admin.php`, `db.php`, `appointment_rules.php`, `workshop.css`, and `availability.js` to the site's document directory.
5. Open both pages at the public HTTPS URL. Check date changes, make a sample booking, and sign in to the admin page to reschedule it. Submit the public URL and provide the instructor's admin access through the course's submission process.
