# Bayero University Kano — BUK EIMS

## Exam Incident Management System

BUK EIMS is a secure web application for reporting, reviewing, investigating, adjudicating, and archiving examination-malpractice incidents at Bayero University Kano. It provides a modern, role-aware unified workspace for administrators, examination officers, invigilators, HODs, deans, and disciplinary committee members.

## Main application files

| File | Responsibility |
|---|---|
| `index.php` | Public landing page, staff portal entry, login, and registration interface |
| `workspace.php` | Modern unified role-aware staff workspace and case dossier interface |
| `operations.php` | Dedicated control centre for triage, committee assignment, and academic sessions |
| `api.php` | Hardened REST API router, authentication, CSRF protection, RBAC, evidence streaming, and audit trail |
| `db.php` | PostgreSQL connection, cached schema bootstrap, triggers, and demonstration seed data |
| `migrate.php` | Standalone CLI and container database migration and seeding utility |
| `uploads/.htaccess` | Apache security controls disabling script execution in evidence storage |
| `assets/workspace.js` | Complete React 18 frontend: Case Dossiers, Evidence Gallery, Hearings, Sanctions, Drafts |
| `assets/buk-logo.png` | Bayero University Kano crest used across institutional branding |

## Technology Stack

The application uses PHP 8.2, Apache 2.4, PostgreSQL (recommended via Neon), PDO PostgreSQL, React 18, Tailwind CSS, Axios, and Font Awesome.

## Branding

The interface uses a BUK-inspired institutional palette based on blue (`#0B5C8E`), navy (`#062F4A`), gold (`#C6A15B`), warm neutrals, and high-contrast surfaces. The product name is **BUK EIMS**, and the institution is identified as **Bayero University Kano**.

## Quick start with Docker

```bash
docker build -t buk-eims .
docker run --rm -p 8080:10000 \
  -e PORT=10000 \
  -e DATABASE_URL="postgresql://USER:PASSWORD@HOST/DATABASE?sslmode=require" \
  buk-eims
```

Open [http://localhost:8080](http://localhost:8080).

## Deployment

Read [`DEPLOY.md`](DEPLOY.md) for complete Neon, Render, Docker, local-PHP, database, storage, credentials, and production-readiness instructions.

## Demonstration credentials

The database bootstrap creates demonstration accounts on first run:

| Role | Email | Password |
|---|---|---|
| System Administrator | `admin@buk.edu.ng` | `Admin@1234` |
| Exam Officer | `officer@buk.edu.ng` | `Officer@1234` |
| Invigilator | `invigilator@buk.edu.ng` | `Invigi@1234` |
| HOD / Dean | `hod@buk.edu.ng` | `Hod@12345` |
| Committee Member | `committee@buk.edu.ng` | `Commit@1234` |

## Security & Architecture Highlights

1. **Hardened Evidence Uploads**: Real MIME-type checking (`finfo`), extension whitelisting, randomized storage filenames, and direct `.htaccess` script execution prevention.
2. **Authenticated Evidence Gateway**: Evidence files are served via `api.php?action=download_evidence`, enforcing role-based access control and preventing unauthorized public leaks.
3. **Robust CSRF & Session Security**: CSRF tokens are cryptographically bound to the user's active `session_id`, invalidated upon use, with `HttpOnly` and `SameSite=Lax` cookie flags.
4. **Decoupled Database Migrations**: Per-request DDL has been replaced with cached schema validation and a standalone `migrate.php` runner, eliminating Neon connection bottlenecks.
5. **Comprehensive Audit Trail**: Security-relevant events (logins, triage, stage advancements, sanctions, user management) are recorded in `audit_logs`.
6. **360° Case Dossier**: Complete investigation workflow with stage progression controls, formal sanctions recording, hearings scheduler, and printable notice-board summaries.
7. **University Senate Working Papers & Ratification Engine**: Compiles resolved malpractice cases into formal Senate Working Papers (`BUK/SEN/...`), records statutory meeting numbers, tracks ratification status (`recommended_to_senate`, `senate_approved`, `senate_amended`, `senate_rejected`), and exports official Gazette extracts.
8. **Multi-Tier Disciplinary Jurisdiction**: Tiered routing between Departmental Panels (HOD), Faculty Malpractice Committees (FEMC), and Central CEMC with full escalation audit trails.
9. **Emergency Hall Security Alert**: Real-time distress dispatch for exam hall invigilators to summon Campus Security Command and Chief Invigilators, broadcast via high-visibility alerts.
10. **Physical Evidence Safe Lockers & Chain-of-Custody**: Physical repository management (`Safe A1, Shelf 2`), unique barcode tag generator (`BUK-EV-...`), and audited multi-custodian handover ledger.
11. **Dynamic Timetable Pre-fill & Collusion Network**: Real-time examination schedule auto-fill for course, venue, date, and session; visual clustering of concurrent infractions by venue and course to detect organized malpractice rings.
12. **Repeat Offender Radar**: Instant student misconduct history scanning on matriculation entry, highlighting prior offenses, risk level classification, and active sanctions.
13. **Official BUK Letterhead Generator**: Print-ready Bayero University Kano stationery for Formal Query Letters, Hearing Notices, Witness Subpoenas, and Senate Disciplinary Orders citing University Examination Regulations.
14. **Clean White UI Design**: High-contrast, minimalist pure white theme (`#ffffff`) across the workspace, operations console, and public landing page, eliminating colored/gradient canvas fills for crisp typography and subtle slate borders.

Official University Website: [buk.edu.ng](https://buk.edu.ng/).
