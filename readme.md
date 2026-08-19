# Bayero University Kano — BUK EIMS

## Exam Incident Management System

BUK EIMS is a secure web application for reporting, reviewing, investigating, adjudicating, and archiving examination-malpractice incidents at Bayero University Kano. It provides role-aware dashboards for administrators, examination officers, invigilators, HODs or deans, and committee members.

## Main application files

| File | Responsibility |
|---|---|
| `index.php` | Public landing page, staff portal entry, login, and registration interface |
| `admin.php` | System-administrator dashboard, users, incidents, cases, reports, and settings |
| `eo.php` | Examination-officer triage, case workflow, and notice-board reports |
| `users.php` | Role-aware portal for invigilators, HODs, deans, and committee members |
| `uers.php` | Legacy role-aware portal retained for compatibility |
| `api.php` | Backend router, authentication, CSRF protection, and API actions |
| `db.php` | PostgreSQL connection, schema bootstrap, triggers, and demonstration seed data |
| `assets/buk-logo.png` | Bayero University Kano crest used by the interface |

## Stack

The application uses PHP 8.2, Apache, PostgreSQL, PDO PostgreSQL, React 18 through Babel Standalone, Tailwind CSS CDN, Axios, Font Awesome, and Chart.js.

## Branding

The interface uses a BUK-inspired institutional palette based on blue, navy, gold, warm neutrals, and high-contrast white surfaces. The product name is **BUK EIMS**, and the interface identifies the institution as **Bayero University Kano**.

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

Read [`DEPLOY.md`](DEPLOY.md) for the complete Neon, Render, Docker, local-PHP, database, storage, credentials, and production-readiness instructions.

## Demonstration credentials

The first database request seeds demonstration users. These credentials are for testing only and must be replaced or disabled before live use.

| Role | Email | Password |
|---|---|---|
| System Administrator | `admin@buk.edu.ng` | `Admin@1234` |
| Exam Officer | `officer@buk.edu.ng` | `Officer@1234` |
| Invigilator | `invigilator@buk.edu.ng` | `Invigi@1234` |
| HOD / Dean | `hod@buk.edu.ng` | `Hod@12345` |
| Committee Member | `committee@buk.edu.ng` | `Commit@1234` |

## Security notes

The application includes CSRF tokens for state-changing requests, server-side role-based access control, PDO prepared statements, bcrypt password hashing, session-based authentication, and controlled workflow transitions. These controls should still be reviewed and hardened before production deployment.

The official university website is available at [buk.edu.ng](https://buk.edu.ng/).
