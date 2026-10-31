# Campus Facility Issue Reporting System

A PHP + MySQL web app for the **AMIT3253 Cloud Computing for Business** capstone assignment
(202609). Students and staff report broken campus facilities to the Estate Management Office and follow each repair from Open to Resolved. Use this folder as-is as your Phase 2/3 starting point so you can focus on
the AWS infrastructure (VPC, EC2, RDS, ELB, ASG) instead of writing app code from scratch.

## Features

**Every logged-in user**
- Location cards on the homepage with search, each showing how many active issues it has.
- **`locations.php`**: every location with the issues currently being worked on, plus totals
  (locations, active issues, resolved this month).
- **`board.php`** (Issue Board): every reported issue, filterable by status and location,
  sorted by priority. Reporter names are never shown to other users.
- Report an issue: location, category (fixed list, not free text), title, description and
  priority.
- **Duplicate reports are prevented concurrency-safely**: an Open/In Progress issue of the same
  category at the same location blocks a new report, checked with the location row locked
  (`SELECT ... FOR UPDATE`) inside a transaction. The category dropdown also greys out
  categories that already have an active report at the chosen location (a fetch to
  **`issue_availability.php`**).
- "My Reports" on the homepage with status and the Estate Office's note; edit/withdraw while
  still Open (locked once work starts).
- Login/logout, account page (the Student/Staff ID is set by the admin and can't be changed),
  dark/light mode, password visibility toggle. **There is no public registration** — the admin
  creates every account.
- Contact form and feedback page (topic + star rating), both moderated by admin.

**Admin panel** (`admin/`, gated by `is_admin`; admins land on `admin/issues.php`)
- Move issues through Open → In Progress → Resolved → Closed with a note shown to the
  reporter; filter by status; delete reports. Shows the reporter's name and email.
- Full CRUD for locations (with photo upload).
- Moderate feedback and view contact messages.
- **Create every account** (`admin/user_create.php`): students/staff (with Student/Staff ID and
  faculty) or admins. Duplicate emails and IDs are rejected.
- Manage user accounts: promote/demote admin access, delete an account (cascades to their
  reports and feedback). An admin can never delete or demote
  their own account.

There's deliberately **no admin dashboard** (`admin/index.php`).

## Demo accounts

Seeded by `schema.sql`:

| Email | Password | Role |
|---|---|---|
| `admin@example.com` | `admin123` | Admin (lands on the admin panel) |
| `student@example.com` | `student123` | Student |

**Change these passwords (or the seed rows in `schema.sql`) before deploying anywhere beyond a
local demo** — they're well-known credentials once this code is shared. There is **no public registration**: an admin creates every student, staff and admin account from **Users → Add User**.

## Ideas for additional functions

The functions above are already provided. If you want to build something new on top of
them, here are some ideas to start from. Each one adds new behaviour that reads and writes
the database, and can be built with the same query and page patterns used elsewhere in this
app. You are welcome to come up with your own instead.

| Idea | Effort | What it involves |
|---|---|---|
| **Photo on a report** | Easy | Let reporters attach a photo of the problem. Reuse `handle_image_upload()` and add an `image_url` column to `issues` — stored on S3 when configured. |
| **"Me too" on an existing issue** | Easy | Instead of only blocking a duplicate report, let users add their support to the existing issue. New `issue_supporters` table; show the count on the Issue Board. |
| **Update history** | Easy | Record every status change and note in an `issue_updates` table and show a timeline on each report. |
| **Rate the repair** | Easy | Once an issue is Resolved, the reporter rates the repair (1-5 stars) and leaves a comment. |
| **Technician assignment** | Moderate | A `technicians` table managed by the admin; assign each issue to a technician and filter the admin list by technician. |
| **Admin dashboard** | Easy | An `admin/index.php` with issues per category and location, and average time from Open to Resolved. |

Document any function you add in your report: what it does, before/after screenshots, and the
tables or columns it uses.

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
   DB_HOST=localhost DB_USER=root DB_PASS=yourpassword DB_NAME=facility_issues_db
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
| `schema.sql` | Creates the database, all tables (`users`, `locations`, `issues`, `feedback`, `contact_messages`, `sessions`) and seed data |
| `config.php` | Database connection — reads `DB_HOST`/`DB_USER`/`DB_PASS`/`DB_NAME` (environment or `.env`), plus S3 photo storage config |
| `healthz.php` | ALB health check target — `200` if the DB connection works, `500` otherwise |
| `auth.php` | Database-backed PHP sessions + helpers: `current_user_id()`, `require_login()`, `require_admin()` |
| `helpers.php` | Image upload/delete (local or S3), faculty list, and this app's own helpers |
| `index.php` | Homepage: location cards with search + My Reports |
| `locations.php`, `board.php` | Locations with active issues; Issue Board of all reports |
| `create.php` / `edit.php` / `delete.php` | Issue report CRUD (own reports only) |
| `issue_availability.php` | JSON endpoint used to grey out already-reported categories |
| `login.php` / `logout.php` / `account.php` | Accounts (passwords hashed with `password_hash()`, never plaintext) |
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

Admins can upload a photo for each location. Uploads are validated with
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
   scp -i your-key.pem -r ./facility-issue-reporting ec2-user@<public-ipv4>:/tmp/
   ```
   Then on the instance:
   ```
   sudo cp -r /tmp/facility-issue-reporting/. /var/www/html/
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

Good targets for Apache Bench / JMeter / Locust: `board.php` and `index.php` (read-heavy). Every page except
`login.php` and `healthz.php` needs a login, so your load test must first POST to `login.php`
(with a demo account), keep the session cookie it gets back, and send that cookie with every
request — Locust and JMeter both do this with a cookie manager / client session. `healthz.php`
needs no cookie but only checks the database connection, so it isn't a realistic test on its own.

## Login is required

**Every page needs a login** except `login.php` (and
`healthz.php`, which the load balancer calls). This is enforced in one place: the
`PUBLIC_PAGES` check at the bottom of `auth.php`, which every page loads, so a new page you add
is protected automatically. Logged-out visitors are redirected to the login page, and the
navigation is hidden until they log in.

This login identifies who is using the system; it's not a substitute for real campus
authentication (SSO/VPN), which is out of scope for this proof of concept.
