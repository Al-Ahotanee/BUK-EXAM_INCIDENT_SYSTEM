# BUK EIMS — Functional Feature Guide

## Product areas implemented

The upgraded BUK EIMS package now provides a unified workspace for incident reporting, task management, case investigation, hearings, appeals, calendars, reports, announcements, saved drafts, preferences, and operations control.

### Shared workspace

Users receive a modern role-aware dashboard with live summary cards for incidents, open cases, resolved cases, and overdue tasks. The workspace includes a personal task inbox, notifications panel, institutional announcements, global case and incident search, responsive navigation, a BUK-branded visual system, printable case summaries, and profile preferences.

### Incident reporting

Invigilators, examination officers, and administrators can create guided three-step incident reports with student details, matriculation number, student level, course, department, examination date and time, venue, semester, academic session, offence type, narrative, and optional evidence upload. The report wizard validates required fields and submits the record to the incident registry.

### Saved drafts

Authorized reporting users can create named drafts, see progress and update time, continue a draft into the incident workflow, and delete drafts. Drafts are available from the **Drafts** workspace section.

### Case management

Authorized users can search cases, open the case workspace, review a chronological timeline, inspect hearings and appeals, add internal notes, print an official case summary, submit an appeal, and move through the case workflow.

### Operations console

Administrators and examination officers can open `operations.php` for a dedicated control centre containing:

- Triage queue for incoming reports.
- Review, return, close, duplicate, and escalate decisions.
- Case-opening action from an incident report.
- Committee assignment board.
- Case-outcome report builder and CSV export.
- Academic-session setup and active-session management.
- Announcement publishing.

### Hearings and calendars

The workspace includes hearing scheduling, hearing status visibility, an upcoming-hearings calendar, venue/session lookups, and a dedicated hearings section.

### Reports and analytics

Users can review case-stage summaries, monthly activity data, incident mix, and a printable/exportable case report. Administrators can also export operational data as CSV from the Operations console.

### Administration

Administrators can publish announcements, configure academic sessions, manage workspace preferences, and use the operations control centre for triage, assignments, reporting, and institutional communications.

## Role entry points

| Role | Primary functions |
|---|---|
| Administrator | Full workspace, operations console, triage, assignments, reports, sessions, announcements, settings |
| Examination Officer | Incident review, tasks, cases, hearings, reports, drafts, and workflow operations |
| Invigilator | Guided incident reporting, saved drafts, assigned tasks, incident history |
| HOD / Dean | Case and hearing visibility, assigned tasks, reports, review workflow |
| Committee Member | Case timeline, hearings, appeals, notes, and decision workflow |

## Sandbox test

The sandbox test environment uses the local PostgreSQL seed accounts documented in `DEPLOY.md`. The application has been tested for PHP syntax, API bootstrap, database table creation, dashboard rendering, operations-console rendering, saved-draft creation, and browser-console stability.
