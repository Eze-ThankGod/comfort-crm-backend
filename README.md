# Comfort CRM

A backend API for a real-estate-focused Customer Relationship Management system built with **Laravel 12** and **JWT authentication**. Designed to manage leads, agents, tasks, call logs, automations, WhatsApp messaging, and performance reporting — all via a clean RESTful API.

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [User Roles](#user-roles)
- [API Overview](#api-overview)
- [Local Development](#local-development)
- [Environment Variables](#environment-variables)
- [cPanel Deployment](#cpanel-deployment)
- [Scheduled Tasks](#scheduled-tasks)
- [Default Credentials](#default-credentials)

---

## Features

| # | Feature | Details |
|---|---------|---------|
| 1 | **User Management** | Create agents, managers, admins with role-based access |
| 2 | **Lead Management** | Create, edit, delete, assign leads; CSV import; source & status tracking |
| 3 | **Task & Follow-up Management** | Tasks linked to leads with type, due date, reminders |
| 4 | **Call Feedback & Notes** | Log calls with outcome, duration, notes — timestamped per agent |
| 5 | **Activity Tracking** | Automatic audit log for all CRM actions |
| 6 | **Dashboards & Reports** | Role-adaptive dashboards; agent performance; lead funnel; call outcomes |
| 7 | **Notifications & Reminders** | In-app + email reminders for tasks; overdue alerts |
| 8 | **Security & Access Control** | JWT auth; role middleware; policy-based data isolation |
| 9 | **System Basics** | Search & filters, pagination, fast JSON responses |
| 10 | **WhatsApp Integration** | Meta Business API — send messages, receive webhooks, inbound tracking |
| 11 | **Automation Engine** | Trigger-condition-action rules: auto-assign, auto-task, status change |

---

## Tech Stack

- **PHP** 8.2+
- **Laravel** 12
- **JWT Auth** — `tymon/jwt-auth` v2
- **Database** — MySQL (recommended) / SQLite (dev)
- **Queue** — Database driver (upgradeable to Redis)
- **CSV Import** — `league/csv` v9
- **WhatsApp** — Meta Business Cloud API (v18)

---

## Project Structure

```
app/
├── Console/Commands/
│   └── ProcessTaskReminders.php   # Scheduler: reminders + overdue detection
├── Http/
│   ├── Controllers/Api/           # All API controllers
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── LeadController.php
│   │   ├── TaskController.php
│   │   ├── CallLogController.php
│   │   ├── ActivityController.php
│   │   ├── DashboardController.php
│   │   ├── ReportController.php
│   │   ├── NotificationController.php
│   │   ├── AutomationRuleController.php
│   │   └── WhatsAppController.php
│   └── Middleware/
│       ├── Authenticate.php       # API-safe auth (no login redirect)
│       └── CheckRole.php          # Role-based route guard
├── Jobs/
│   └── SendWhatsAppMessage.php    # Queued WhatsApp dispatch
├── Models/                        # Eloquent models with scopes & relations
├── Notifications/                 # TaskReminder, OverdueTask, LeadAssigned
├── Policies/                      # LeadPolicy, TaskPolicy, UserPolicy
└── Services/
    ├── ActivityService.php        # Auto activity logging
    ├── AutomationService.php      # Rule engine
    ├── LeadImportService.php      # CSV import with validation
    └── WhatsAppService.php        # Meta API integration

database/migrations/               # 8 migrations + base Laravel tables
routes/
└── api.php                        # All 57 API routes under /api/v1/
```

---

## User Roles

| Role | Permissions |
|------|------------|
| **Admin** | Full system access — users, leads, tasks, reports, automation, settings |
| **Manager** | All lead & task access, reports, automation rules; no user creation |
| **Agent** | Only sees and manages their own assigned leads and tasks |

---

## API Overview

All routes are prefixed with `/api/v1/`. Protected routes require:
```
Authorization: Bearer <jwt_token>
```

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/login` | Login and get JWT token |
| POST | `/auth/refresh` | Refresh JWT token |
| POST | `/auth/logout` | Invalidate token |
| GET | `/auth/me` | Get authenticated user |

### Leads
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/leads` | List leads (scoped by role) |
| POST | `/leads` | Create a lead |
| GET | `/leads/{id}` | View lead with tasks, calls, activity |
| PUT/PATCH | `/leads/{id}` | Update lead |
| DELETE | `/leads/{id}` | Delete lead (admin only) |
| PATCH | `/leads/{id}/assign` | Assign to agent |
| POST | `/leads/import` | Import leads from CSV |
| POST | `/leads/bulk-assign` | Bulk assign leads |

### Tasks
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tasks` | List tasks (scoped by role) |
| POST | `/tasks` | Create task |
| GET | `/tasks/{id}` | View task |
| PUT/PATCH | `/tasks/{id}` | Update task |
| DELETE | `/tasks/{id}` | Delete task |
| PATCH | `/tasks/{id}/complete` | Mark task complete |

### Call Logs
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/call-logs` | List call logs |
| POST | `/call-logs` | Log a call with outcome & notes |
| GET | `/call-logs/{id}` | View call log |
| PUT/PATCH | `/call-logs/{id}` | Update call log |
| DELETE | `/call-logs/{id}` | Delete call log |

### Activities
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/activities` | Audit log (filterable by lead, type, date) |

### Notifications
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notifications` | List notifications |
| GET | `/notifications/unread` | Unread count + list |
| PATCH | `/notifications/{id}/read` | Mark one as read |
| PATCH | `/notifications/read-all` | Mark all as read |
| DELETE | `/notifications/{id}` | Delete notification |

### Dashboard & Reports *(Admin/Manager)*
| Endpoint | Description |
|----------|-------------|
| `GET /dashboard` | Role-adaptive dashboard data |
| `GET /reports/agent-performance` | Calls, tasks, wins per agent |
| `GET /reports/lead-funnel` | Leads by status |
| `GET /reports/leads-by-source` | Leads grouped by source |
| `GET /reports/call-outcomes` | Call outcome breakdown |
| `GET /reports/calls-over-time` | Daily call volume series |
| `GET /reports/task-completion` | Task completion by type |

### Users *(Admin/Manager)*
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/users` | List all users |
| POST | `/users` | Create user |
| GET | `/users/agents` | List active agents |
| GET | `/users/{id}` | View user |
| PUT/PATCH | `/users/{id}` | Update user |
| DELETE | `/users/{id}` | Delete user (admin only) |
| PATCH | `/users/{id}/toggle-active` | Activate / deactivate user |

### WhatsApp
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/whatsapp/send` | Send a WhatsApp message to a lead |
| GET | `/whatsapp/history/{lead}` | Message history for a lead |
| GET/POST | `/webhooks/whatsapp` | Meta webhook receiver (public) |

### Automation Rules *(Admin/Manager)*
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/automation-rules` | List all rules |
| POST | `/automation-rules` | Create a rule |
| GET | `/automation-rules/{id}` | View rule |
| PUT/PATCH | `/automation-rules/{id}` | Update rule |
| DELETE | `/automation-rules/{id}` | Delete rule |
| PATCH | `/automation-rules/{id}/toggle` | Enable / disable rule |

---

## CSV Import Format

When importing leads via `POST /leads/import`, use a CSV with these columns:

| Column | Required | Notes |
|--------|----------|-------|
| `name` | ✅ | Full name of the lead |
| `phone` | — | Mobile number |
| `email` | — | Email address |
| `source` | — | `website`, `portal`, `referral`, `whatsapp`, `social_media`, `cold_call`, `other` |
| `status` | — | `new`, `contacted`, `interested`, `viewing`, `won`, `lost` |
| `location` | — | Area / city |
| `property_type` | — | e.g. Apartment, Villa |
| `budget_min` | — | Numeric |
| `budget_max` | — | Numeric |
| `notes` | — | Free text |

---

## Local Development

### Requirements

- PHP 8.2+
- Composer
- MySQL or SQLite
- Node.js (optional)

### Setup

```bash
git clone https://github.com/your-org/comfort-crm.git
cd comfort-crm

composer install

cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edit `.env` with your database credentials, then:

```bash
php artisan migrate
php artisan db:seed

php artisan serve
```

API available at: `http://localhost:8000/api/v1/`

### Run the Queue Worker

```bash
php artisan queue:listen --tries=3
```

---

## Environment Variables

```dotenv
APP_NAME="Comfort CRM"
APP_ENV=local
APP_KEY=              # generated by: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=comfort_crm
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=           # generated by: php artisan jwt:secret
JWT_TTL=60            # token lifetime in minutes

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="noreply@comfort-crm.com"
MAIL_FROM_NAME="Comfort CRM"

# WhatsApp (Meta Business API)
WHATSAPP_API_URL=https://graph.facebook.com/v18.0
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_VERIFY_TOKEN=comfort-crm-webhook

QUEUE_CONNECTION=database
CACHE_STORE=database
```

---

## cPanel Deployment

### Build for Production

Run the build script:

```bash
./cpanel-build.sh
```

This generates `laravel-deploy.zip` with all necessary files.

---

### Deploy to cPanel

#### 1. Upload to cPanel

1. Login to **cPanel → File Manager**
2. Navigate to `public_html` or your subdomain folder
3. Upload `laravel-deploy.zip`
4. **Extract** the zip file
5. Move contents from the `build/` folder to the parent directory

#### 2. Install Dependencies

Via SSH or cPanel Terminal:

```bash
composer install --optimize-autoloader --no-dev
```

#### 3. Configure Environment

Edit `.env` with production credentials:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=your_cpanel_db
DB_USERNAME=your_cpanel_user
DB_PASSWORD=your_cpanel_password

JWT_SECRET=your_generated_secret
```

#### 4. Run Deployment Commands

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### 5. Set Permissions

```bash
chmod -R 755 storage bootstrap/cache
```

#### 6. Point Web Root to `/public`

Configure the domain/subdomain document root to `public_html/public`.

If the hosting panel doesn't allow changing document root, add this to `public_html/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

#### 7. Set Up the Cron Job

In **cPanel → Cron Jobs**, add:

```
* * * * * /usr/local/bin/php /home/yourusername/public_html/artisan schedule:run >> /dev/null 2>&1
```

---

### `cpanel-build.sh`

```bash
#!/bin/bash
set -e

echo "Building Comfort CRM for production..."

composer install --optimize-autoloader --no-dev
php artisan config:clear
php artisan route:clear
php artisan view:clear

mkdir -p build
rsync -av \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='tests' \
    --exclude='*.log' \
    --exclude='.env' \
    --exclude='storage/logs/*' \
    . build/

zip -r laravel-deploy.zip build/
rm -rf build/

echo "Done! Upload laravel-deploy.zip to your cPanel server."
```

Make it executable:

```bash
chmod +x cpanel-build.sh
```

---

## Scheduled Tasks

The following tasks run automatically via the Laravel scheduler (requires the cron job above):

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `crm:task-reminders` | Every 5 minutes | Send reminders 30 min before due; mark overdue tasks as missed |

---

## Default Credentials

> ⚠️ **Change all passwords immediately after first login in production.**

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@comfort-crm.com | `password` |
| Manager | manager@comfort-crm.com | `password` |
| Agent | agent1@comfort-crm.com | `password` |
| Agent | agent2@comfort-crm.com | `password` |

---

## License

Private — All rights reserved. Comfort Real Estate.


Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
