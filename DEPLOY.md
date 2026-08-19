# Deploying BUK EIMS to Render + Neon

**BUK EIMS** is the Bayero University Kano Exam Incident Management System. This package is a PHP 8.2 + Apache application with a PostgreSQL database layer and React/Tailwind interfaces loaded through CDN assets.

> The application is suitable for an academic demonstration or controlled pilot. Before using it for live disciplinary records, replace the demo credentials, configure persistent evidence storage, review institutional retention rules, and complete a security assessment.

## 1. Technology stack

| Layer | Technology |
|---|---|
| Web server | Apache 2.4 inside the official PHP 8.2 Apache image |
| Backend | PHP 8.2 with PDO and PostgreSQL (`pdo_pgsql`) |
| Frontend | React 18 through Babel Standalone CDN and Tailwind CSS CDN |
| Icons and charts | Font Awesome CDN and Chart.js CDN |
| Database | PostgreSQL, recommended through Neon |
| Hosting | Render Docker Web Service or any Docker-capable PHP host |
| Branding | Bayero University Kano blue, navy, gold, and warm-neutral UI system |

## 2. Required files and environment variable

The application expects one environment variable:

```text
DATABASE_URL=postgresql://USER:PASSWORD@HOST/DATABASE?sslmode=require
```

`db.php` reads this connection string, creates the application schema idempotently on the first request, creates the timestamp trigger, and seeds the demonstration accounts. No manual SQL migration is required for a fresh deployment.

## 3. Deploy with Neon and Render Blueprint

### Step 1: Create the PostgreSQL database

Create a project at [Neon](https://neon.tech/), create or select a database, and copy its pooled PostgreSQL connection string. Keep the connection string private.

### Step 2: Push the source to GitHub

Create a private repository and push the contents of this folder, including `Dockerfile`, `docker-entrypoint.sh`, `render.yaml`, `db.php`, `api.php`, the PHP portal files, `assets/buk-logo.png`, and the `uploads` directory.

```bash
git init
git add .
git commit -m "Brand application for Bayero University Kano"
git branch -M main
git remote add origin https://github.com/YOUR_ACCOUNT/YOUR_PRIVATE_REPOSITORY.git
git push -u origin main
```

### Step 3: Create the Render service

In Render, choose **New → Blueprint**, select the repository, and allow Render to read `render.yaml`. The blueprint creates a Docker web service named `buk-eims`.

In the service’s **Environment** settings, add:

```text
DATABASE_URL = your Neon pooled connection string
```

Deploy the service. Render builds the Docker image, installs PostgreSQL support, starts Apache on the port supplied by Render, and exposes the application URL.

### Step 4: Open the application

Visit the Render URL and confirm that the Bayero University Kano crest, BUK EIMS name, blue-and-gold theme, landing page, and login screen appear correctly. The first login request initializes the database schema and demonstration records.

## 4. Local Docker deployment

Docker is the most reliable local test path because it matches the production PHP and Apache environment.

```bash
docker build -t buk-eims .
docker run --rm -p 8080:10000 \
  -e PORT=10000 \
  -e DATABASE_URL="postgresql://USER:PASSWORD@HOST/DATABASE?sslmode=require" \
  buk-eims
```

Open [http://localhost:8080](http://localhost:8080).

To use a local PostgreSQL container instead of Neon, create a PostgreSQL database, then pass its connection string through `DATABASE_URL`. The application will create its own tables on first request.

## 5. Local PHP development without Docker

PHP 8.2 or newer and the PostgreSQL PDO extension are required.

```bash
php -S 127.0.0.1:8080
```

Set `DATABASE_URL` in the shell before starting PHP:

```bash
export DATABASE_URL="postgresql://USER:PASSWORD@HOST/DATABASE?sslmode=require"
php -S 127.0.0.1:8080
```

This server is for development only. Use Apache or another production-grade web server for deployment.

## 6. Demonstration accounts

The database bootstrap creates these demonstration accounts:

| Role | Email | Password |
|---|---|---|
| System Administrator | `admin@buk.edu.ng` | `Admin@1234` |
| Exam Officer | `officer@buk.edu.ng` | `Officer@1234` |
| Invigilator | `invigilator@buk.edu.ng` | `Invigi@1234` |
| HOD / Dean | `hod@buk.edu.ng` | `Hod@12345` |
| Committee Member | `committee@buk.edu.ng` | `Commit@1234` |

These credentials are intended only for demonstration. Change or disable them before importing real university data. Do not expose them in a public repository or production environment.

## 7. Persistent evidence storage

The `uploads/` directory stores incident evidence files. Render’s free container filesystem is ephemeral, so files can disappear after a restart or redeploy. For a real pilot, replace local file storage in `api.php` with an object-storage service such as an S3-compatible bucket, and store only controlled object keys in the database.

## 8. Production checklist

Before go-live, configure HTTPS, use a private repository, replace the seed credentials, restrict database access, add database backups, configure persistent evidence storage, validate upload MIME types and size limits, review CSRF and session settings, apply institutional role approvals, and test the full incident workflow with authorized BUK stakeholders.

## 9. Branding assets

The BUK crest used by the interface is stored at `assets/buk-logo.png`. The UI theme uses the following palette:

| Token | Hex | Usage |
|---|---|---|
| BUK Blue | `#0B5C8E` | Primary actions, headings, active navigation |
| BUK Navy | `#06385B` | Sidebar gradients, hero backgrounds, dark surfaces |
| BUK Sky | `#0875B4` | Secondary emphasis and hover states |
| BUK Gold | `#C6A15B` | Institutional accent, active borders, highlights |
| BUK Gold Light | `#E4C777` | High-contrast accent text and subtle highlights |
| BUK Ink | `#071A2B` | Dark background and high-emphasis text |

The official university website is available at [buk.edu.ng](https://buk.edu.ng/).


## 10. Upgraded user-facing workspace

After login, users are routed to `workspace.php`, the modern BUK EIMS workspace. It contains the role-aware dashboard, task inbox, notifications, global search, incident registry, guided incident wizard, case workspace, hearings, calendar, reports, drafts, announcements, and profile preferences.

Administrators and examination officers can open `operations.php` for the dedicated Operations console. It provides the triage queue, review/return/close decisions, case-opening action, committee assignment board, CSV report export, academic-session management, and announcement publishing.

The workspace frontend is precompiled into `assets/workspace.js`. The deployed page does not require Babel Standalone to run in the browser. Font Awesome is loaded as CSS rather than DOM-mutating JavaScript so the React workspace remains stable during rerenders.

## 11. Post-deployment functional smoke test

After deployment, test the following sequence:

1. Open the application landing page and sign in with an authorized account.
2. Confirm that the dashboard shows the signed-in user, live summary cards, task panel, notifications, and announcements.
3. Open **Drafts**, create a named draft, confirm that it appears in the list, and delete it after testing.
4. Open **Incidents**, start the guided report wizard, verify the student level, course, department, venue, offence, narrative, and optional evidence fields, then submit a test report.
5. Open **Operations** as an administrator or examination officer and confirm that the report appears in the triage queue.
6. Mark the report reviewed, open a case, assign a committee member, and verify that the case appears in **Cases**.
7. Open the case workspace, add an internal note, schedule a hearing, review the timeline, print the case summary, and test the appeal workflow.
8. Open **Reports** and download a CSV report.
9. Open **Academic sessions** and verify that the active session can be saved.
10. Publish a test announcement and verify that it appears in the workspace announcements panel.

If the Render root returns 404 for `api.php`, `workspace.php`, `operations.php`, or `assets/buk-logo.png`, redeploy from the folder containing `Dockerfile`, `render.yaml`, `index.php`, `api.php`, `db.php`, `workspace.php`, `operations.php`, and `assets/` at the repository root.

See `FEATURES.md` for the functional feature map and role-by-role usage summary.


## 12. Public landing page

The public `index.php` now uses the same BUK EIMS design language as the authenticated workspace. It includes the BUK-branded hero, operations preview, capabilities, workflow explanation, role-specific value sections, staff sign-in panel, responsive mobile navigation, and the new `assets/landing.css` and `assets/landing.js` files. The staff sign-in still calls `api.php?action=login` and redirects to `workspace.php` after successful authentication.


## 13. Staff account requests and approval

The public landing page includes a **Need access? Request a staff account** link beneath the staff sign-in form. New staff members submit their institutional details and requested role. Requests are stored with a `pending` status and are not login-enabled until an administrator approves them.

An administrator signs in, opens the modern workspace, selects **User management**, and opens the **Account requests** tab. The administrator can approve or decline each request. Approval creates the active user and initializes their workspace preferences. The **User directory** tab shows active and disabled staff accounts.

For production use, administrators should verify staff identity and role eligibility before approving requests. The self-registration workflow does not grant administrator privileges; administrator accounts must be created by an existing administrator through the direct user-management form.
