# Vignan's Question Paper Feedback Portal
### Vignan's Foundation for Science, Technology & Research (Deemed to be University)

A comprehensive PHP + MySQL web application for collecting external subject expert summative question paper feedback and internal paper setter Action Taken Reports (ATR). It automatically populates the official university Word template and supports instant Word, PDF, and high-fidelity print views.

---

## 🏛️ Technology Stack & Architecture

- **Backend**: PHP 8.2+ with PDO MySQL & Session Authentication
- **Database**: MySQL / MariaDB (`vignan_qp_feedback` database)
- **Frontend**: Responsive HTML5, Modern Vanilla CSS (tailored to Vignan University Red & Blue brand colors), Vanilla JavaScript (Canvas signature pad, real-time char counters, dynamic filters)
- **Document Generation**: Word (.docx) template populator, Printable browser PDF engine, and bulk ZIP archiver
- **Brand Identity**: Vignan University Logo (Shield emblem, bold red `VIGNAN'S` typography, and blue foundation banner)

---

## 🚀 Quick Start on XAMPP (Windows)

1. **Place in XAMPP htdocs:**
   Ensure this project folder is located at `c:\xampp\htdocs\external`.

2. **Start Apache & MySQL:**
   Open the **XAMPP Control Panel** and click **Start** for both **Apache** and **MySQL**.

3. **Initialize Database & Demo Accounts:**
   Open your web browser and visit:
   ```
   http://localhost/external/seed.php
   ```
   This automatically creates the MySQL database `vignan_qp_feedback`, tables, sample courses, and login credentials.

4. **Access the Portal:**
   Visit:
   ```
   http://localhost/external/
   ```

---

## 🔑 Login Credentials

| Role | Email / ID | Password / Access Code |
| :--- | :--- | :--- |
| **Administrator** | Examination Cell | `admin123` |
| **External Expert (Demo)** | `ramanathan.expert@iitm.ac.in` | View on `/seed.php` or Admin Portal |
| **Paper Setter (Demo)** | `srinivas.cse@vignan.ac.in` | View on `/seed.php` or Admin Portal |

---

## 🔄 Core Workflow

1. **Administrator:**
   - **Upload Courses:** Upload Excel/CSV course lists with paper setter faculty info.
   - **Allocate Experts:** Select courses and assign external scrutinizers (or create new experts inline).
   - **Credentials Management:** Export all login credentials as CSV or reset access codes.
   - **Unlock / Re-open:** Unlock previously submitted forms for faculty corrections.
   - **Bulk Export:** Download status reports, ratings matrix, or bulk ZIP bundles organized by Year/Semester/Department.

2. **External Scrutinizer (Expert):**
   - Logs in with registered email & 8-character access code.
   - Evaluates question papers across **9 standard criteria** (Ratings 1–5 and per-criterion remarks).
   - Selects overall recommendation and writes detailed suggestions.
   - Uploads or draws a digital signature (with automatic white background removal) and optional seal.
   - Submits and locks the evaluation.

3. **Paper Setter (Faculty):**
   - Reviews external expert's submitted ratings and observations.
   - Completes the 4 mandatory declaration checkboxes.
   - Writes the Summary of Major Changes Incorporated (limited to 800 chars for template compliance).
   - Checks retained documents and applies digital signature.
   - Submits and locks the Action Taken Report.

4. **Document Generation:**
   - Generates official Word documents (`.docx`), high-fidelity printable views (`view_form.php`), or print-to-PDF.

---

## 📁 Key File Structure

```
├── assets/
│   ├── css/style.css           # Vignan-branded modern design system
│   ├── js/main.js              # Canvas signature pad, modals, toast notifications
│   └── img/
│       ├── vignan-logo.svg     # Crisp vector university logo
│       └── vignan-logo.png     # High-resolution raster logo
├── config/
│   ├── config.php              # Central config, base URLs, limits, passwords
│   └── db.php                  # PDO MySQL connection & auto-schema installer
├── includes/
│   ├── auth.php                # Role-based session authentication guards
│   ├── criteria.php            # Verbatim 9 criteria, recommendations, declarations
│   ├── services.php            # Core business logic layer
│   ├── docgen.php              # Word (.docx) & ZIP archive generator
│   ├── header.php              # Common HTML head and brand assets
│   ├── navbar.php              # Dynamic role-based navigation bar
│   └── footer.php              # Common footer component
├── api/
│   └── index.php               # RESTful JSON API endpoints
├── index.php                   # Unified login portal (Expert / Setter / Admin)
├── dashboard.php               # Admin analytics dashboard with Chart.js
├── courses.php                 # Course management (Upload, Add, Edit, Delete)
├── allocate.php                # Batch external expert allocation interface
├── people.php                  # Faculty & external scrutinizer credentials
├── downloads.php               # Single & bulk document download center
├── expert_courses.php          # External expert allocated courses view
├── expert_feedback.php         # Question paper scrutiny evaluation form
├── setter_courses.php          # Paper setter courses view
├── setter_atr.php              # Action Taken Report submission form
├── profile.php                 # Digital signature drawing pad & seal uploader
├── view_form.php               # High-fidelity printable document view
├── download_doc.php            # Word (.docx) & ZIP builder endpoint
├── export.php                  # CSV report exporter (status, ratings, credentials)
├── schema.sql                  # MySQL database schema definition
└── seed.php                    # One-click demo database seeder
```
