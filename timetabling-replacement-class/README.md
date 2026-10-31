# Timetabling & Replacement Class System

A PHP + MySQL web app for the **AMIT3253 Cloud Computing for Business** capstone assignment
(202609). Students and lecturers view the weekly class timetable; lecturers request replacement classes, which appear on the timetable once an admin approves them. Use this folder as-is as your Phase 2/3 starting point so you can focus on
the AWS infrastructure (VPC, EC2, RDS, ELB, ASG) instead of writing app code from scratch.

## Features

**Public site**
- Course cards with search on the homepage, and a **Courses** page listing every course's
  regular weekly classes (day, time slot, type, venue).
- **`timetable.php`**: a weekly grid (time slots × Monday–Friday) for all courses or one
  course, with previous/this/next week navigation. Approved replacement classes are shown in
  green on their new date, and the regular class they replace is struck through, so students
  know not to turn up.
- **`replacements.php`**: every approved replacement class from today onwards.
- Register/login/logout, account page, dark/light mode, password visibility toggle, TAR UMT
  faculty dropdown at registration.
- Contact form and feedback page (topic + star rating), both moderated by admin.

**Lecturers** (accounts created by an admin, `users.role = 'lecturer'`)
- Request a replacement class for a course they teach: the date of the class being replaced,
  a replacement date, a fixed time slot (from `time_slots`, not free text), a venue and a
  reason. "My Replacement Requests" on the homepage, with edit/withdraw while Pending.
- **Venue clashes are prevented concurrency-safely.** A venue is busy if a regular weekly class
  uses it on that weekday and slot, or another Pending/Approved replacement holds it. The venue
  row is locked (`SELECT ... FOR UPDATE`) and the clash check + insert run in one transaction,
  so two lecturers can't be promised the same room at the same instant.
- **You can't even select a busy venue.** The venue dropdown greys out venues already in use for
  the chosen date and slot (a fetch to **`venue_availability.php`**), and slots that have already
  started today are greyed out too. The server re-checks everything on submit.
- The original date must be a day the course actually has a regular class; replacements must
  be on a weekday and not in the past.

**Admin panel** (`admin/`, gated by `is_admin`; admins land on `admin/replacements.php`)
- Approve/reject replacement requests with a note shown to the lecturer. Re-approving a
  previously rejected request re-checks that its venue is still free.
- Manage the regular weekly timetable (`admin/timetable.php`) — a database unique key stops two
  classes being put in the same venue, day and slot.
- Full CRUD for courses (with lecturer assignment) and venues (with photo upload).
- Moderate feedback and view contact messages.
- Manage user accounts: create **lecturer** or admin accounts, promote/demote admin access,
  delete an account (cascades to their replacement requests and feedback; courses they taught
  become unassigned). An admin can never delete or demote their own account.

There's deliberately **no admin dashboard** (`admin/index.php`).

## Demo accounts

Seeded by `schema.sql`:

| Email | Password | Role |
|---|---|---|
| `admin@example.com` | `admin123` | Admin (lands on the admin panel) |
| `lecturer@example.com` | `lecturer123` | Lecturer |
| `student@example.com` | `student123` | Student |

**Change these passwords (or the seed rows in `schema.sql`) before deploying anywhere beyond a
local demo** — they're well-known credentials once this code is shared. Students register their own accounts; lecturer accounts are created by an admin from **Users → Add Admin / Lecturer**.

## Tech stack

Plain procedural PHP (no framework) + MySQL via `mysqli`. All queries use prepared
statements and all output is escaped with `htmlspecialchars()`.

## Requirements

- PHP 8.x with the `mysqli` extension
- MySQL 5.7+ / MariaDB / Amazon RDS (MySQL-compatible)
- A web server (Apache/Nginx) or just `php -S` for local testing

## Quick start (local)

1. Create the database and import the schema (this also creates the database and seeds the
   demo accounts and sample data):
   ```
   mysql -u root -p < schema.sql
   ```
2. Point `config.php` at your MySQL instance — either copy `.env.example` to `.env` and fill in
   `DB_HOST`/`DB_USER`/`DB_PASS`/`DB_NAME`, or export them as environment variables:
   ```
   DB_HOST=localhost DB_USER=root DB_PASS=yourpassword DB_NAME=timetabling_db
   ```
3. Serve the folder, e.g.:
   ```
   php -S localhost:8000
   ```
4. Visit `http://localhost:8000/` and log in with one of the demo accounts above.

Sample data with dates (sessions, deadlines, replacement classes) is relative to the day you
import `schema.sql`, so the demo always has current data. Re-import it to reset.

## Project structure

| Path | Purpose |
|---|---|
| `schema.sql` | Creates the database, all tables (`users`, `courses`, `venues`, `time_slots`, `timetable_entries`, `replacement_classes`, `feedback`, `contact_messages`, `sessions`) and seed data |
| `config.php` | Database connection — reads `DB_HOST`/`DB_USER`/`DB_PASS`/`DB_NAME` (environment or `.env`), plus S3 photo storage config |
| `healthz.php` | ALB health check target — `200` if the DB connection works, `500` otherwise |
| `auth.php` | Database-backed PHP sessions + helpers: `current_user_id()`, `require_login()`, `require_admin()`, `require_lecturer()` |
| `helpers.php` | Image upload/delete (local or S3), faculty list, and this app's own helpers |
| `index.php` | Homepage: course cards + My Replacement Requests (lecturers) or Upcoming Replacement Classes |
| `timetable.php` | Weekly timetable grid with replacements applied |
| `courses.php`, `replacements.php` | Course list with weekly classes; approved replacements |
| `create.php` / `edit.php` / `delete.php` | Replacement request CRUD (lecturers, own requests only) |
| `venue_availability.php` | JSON endpoint used to grey out busy venues |
| `register.php` / `login.php` / `logout.php` / `account.php` | Accounts (passwords hashed with `password_hash()`, never plaintext) |
| `about.php`, `contact.php`, `feedback.php` | Informational pages, contact form, feedback |
| `partials/` | Shared navbar/footer, included by every page |
| `admin/` | Admin-only pages |
| `uploads/` | Uploaded photos (when S3 isn't configured) |
| `style.css` | Shared styling (navbar, cards, forms, tables, dark/light mode) |

## Running behind a load balancer

Two things that break when a PHP app runs on more than one EC2 instance are already handled:

- **Sessions are stored in the database** (the `sessions` table, see `auth.php`), not on local
  disk — so a user stays logged in whichever instance the ALB sends their next request to.
- **`healthz.php`** returns `200` only if the database connection succeeds (`500` otherwise).
  Point your target group's health check at it so an instance that can't reach RDS is pulled
  out of rotation.

The third — uploaded photos — is covered next.

## Photos: local disk by default, S3 already wired up (just needs your bucket)

Admins can upload a photo for each venue. Uploads are validated with
`getimagesize()` (not just the file extension) and capped at 5MB. Where they're stored depends
on `AWS_S3_BUCKET`:
- **Unset (default)**: saved into `uploads/`, and `image_url` stores a path like
  `/uploads/xxx.jpg`. Nothing to configure.
- **Set**: uploaded to that S3 bucket instead (hand-written Signature Version 4 signing over
  PHP's built-in stream wrapper — no AWS SDK, no Composer), and `image_url` stores the full
  object URL.

Once there's more than one EC2 instance behind the ALB, a photo saved to local disk only exists
on the instance that handled the upload, so the others show a broken image — S3 fixes this.
Switching S3 on only affects *new* uploads; the seeded sample photos stay local.

Credentials are tried two ways: first an IAM role attached to the EC2 instance (via the
metadata service, nothing hardcoded), then explicit `AWS_ACCESS_KEY_ID` /
`AWS_SECRET_ACCESS_KEY` / `AWS_SESSION_TOKEN` — e.g. from an AWS Academy Learner Lab's "AWS
Details" panel — set in `.env` (copy `.env.example`; `.env` is git-ignored so it's never
committed). Learner Lab credentials expire and rotate; if uploads suddenly fail, refresh them.
**What's still on you**: creating the bucket and a public-read bucket policy (or CloudFront),
getting one of the two credential methods working, and setting `AWS_S3_BUCKET`/`AWS_S3_REGION`.

Notes for EC2 deployment:
- `uploads/` needs to be writable by the web server user: `chmod 775 uploads`.
- PHP's default `upload_max_filesize` (often 2M) is smaller than the 5MB this app allows —
  raise `upload_max_filesize` / `post_max_size` in `php.ini` and restart the web server.

## Phase 2: running it on a single EC2 instance

1. **Launch the instance**: Amazon Linux 2023 AMI, `t2.micro`/`t3.micro`, with a key pair.
2. **Security group**: inbound `SSH (22)` from your IP only and `HTTP (80)` from `0.0.0.0/0`.
3. **Connect**: `ssh -i your-key.pem ec2-user@<public-ipv4>`
4. **Install a LAMP stack**:
   ```
   sudo dnf install -y httpd php php-mysqli mariadb105-server
   sudo systemctl enable --now httpd mariadb
   ```
5. **Copy this folder onto the instance** (from your own machine):
   ```
   scp -i your-key.pem -r ./timetabling-replacement-class ec2-user@<public-ipv4>:/tmp/
   ```
   Then on the instance:
   ```
   sudo cp -r /tmp/timetabling-replacement-class/. /var/www/html/
   sudo chown -R apache:apache /var/www/html
   sudo chmod -R 775 /var/www/html/uploads
   ```
6. **Import the schema**: `sudo mysql_secure_installation`, then `mysql -u root -p < schema.sql`
7. **Point the app at the database**: copy `.env.example` to `.env` and set `DB_HOST`/`DB_USER`/
   `DB_PASS`/`DB_NAME`.
8. **Test it**: open `http://<public-ipv4>/`.

## Phase 3: moving the database to RDS

1. Create an RDS MySQL instance in a private subnet (per the assignment's VPC design).
2. From an EC2 instance in the same VPC, import the schema:
   ```
   mysql -h <rds-endpoint> -u <user> -p < schema.sql
   ```
3. Set `DB_HOST` (and `DB_USER`/`DB_PASS`/`DB_NAME` if different) in `.env` to the RDS
   endpoint — `config.php` doesn't need to change.
4. Restrict the RDS security group to accept port 3306 only from the web tier's security group.

## Load testing

Good targets for Apache Bench / JMeter / Locust: `timetable.php` and `replacements.php` (public, read-heavy — the start-of-semester rush). Pages that change data need a
logged-in session cookie and a POST body — start with the public pages, then script a login if
you want to test writes.

## A note on authentication and the assignment brief

The assignment's assumptions say the web application is publicly accessible without an
authentication gateway, and that a simple login may be used to identify users but isn't
assessed as a security control. This app uses login to know who is reporting, applying,
checking in or requesting — it is not a substitute for real campus authentication (SSO/VPN),
which is out of scope for this proof of concept.
