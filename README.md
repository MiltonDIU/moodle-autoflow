# 🚀 MoodleAutoFlow

<p align="center">
  <strong>Next-Generation Automated Course Ingestion, Multi-AI Curriculum Extraction & Video Streaming Provisioner for Moodle LMS</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12%2B-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12+">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Moodle-4.5%20%7C%205.x-F98012?style=for-the-badge&logo=moodle&logoColor=white" alt="Moodle">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/AI-Gemini%20%7C%20OpenAI%20%7C%20Claude-4f46e5?style=for-the-badge&logo=openai&logoColor=white" alt="Multi-AI">
  <img src="https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge" alt="MIT License">
</p>

---

## 🌟 Overview

**MoodleAutoFlow** is an enterprise-grade automated pipeline designed to bridge Google Drive course content repositories with Moodle Learning Management Systems (LMS). 

Instead of manual, labor-intensive course authoring, MoodleAutoFlow automatically scans Google Drive folders containing instructor handbooks, lecture slides, question banks, and weekly videos. It then orchestrates **Multi-Model AI Extraction** to produce a 16-week university-grade syllabus (including learning objectives, discussion topics, assignments, and interactive quiz checks) and provisions the complete course directly into Moodle via high-speed REST Web Services or CLI automation.

---

## ✨ Key Features

### 1. 📂 Intelligent Google Drive Course Scanner
* Automatically discovers course subfolders by Google Drive Folder ID.
* Automatically identifies weekly lecture videos (`Week X.1.mp4`, `Week X.2.mp4`), syllabus documents (`.docx`, `.pdf`), lecture slides (`.pptx`), and test banks.
* Extracts course codes, titles, and material structures with zero manual entry.

### 2. 🧠 Multi-Provider AI Curriculum Engine
Choose your AI provider from the intuitive Settings UI with live connection tests:
* **Google Gemini:** Ultra-fast extraction using `gemini-2.5-flash` or `gemini-1.5-pro`.
* **OpenAI:** Industry-standard structured curriculum generation via `gpt-4o` or `gpt-4o-mini`.
* **Anthropic Claude:** Deep pedagogical analysis using `claude-3-5-sonnet` or `claude-3-5-haiku`.
* **Built-in Smart Heuristic Parser:** 100% offline rule-based parser that requires zero API keys or external credits.
* **Per-Week Extraction:**
  * Weekly Module Titles & Comprehensive Overviews
  * 3–5 Measurable Learning Objectives (Bloom's Taxonomy aligned)
  * Key Vocabulary & Conceptual Definitions
  * Discussion Forum Prompts with Grading Rubrics
  * Applied Assignments / Hands-on Activity Briefs
  * Self-Check Quizzes with Multiple-Choice Questions (MCQs), Answer Keys & Detailed Explanations

### 3. 🎥 Multi-Storage Video & Streaming CDN Architecture
Deliver lecture videos flexibly without vendor lock-in:
* **Google Drive Direct:** Zero upload delay, generates responsive embedded iframe players directly from Google Drive.
* **Bunny.net Stream:** Fast global HLS video transcoding and video CDN delivery.
* **Cloudflare R2:** S3-compatible cloud storage with zero egress bandwidth charges.
* **AWS S3 & CloudFront:** Enterprise Amazon Web Services storage integration.

### 4. 🎓 Seamless Moodle LMS Integration
* Connects to any remote or local Moodle instance via **Moodle Web Services REST API** or direct CLI.
* Automatically duplicates template course structures (preserving standard course layouts, gradebooks, and activity styles).
* Injects weekly HTML descriptions, embed players, assignment activities, discussion forums, and quizzes.
* Compatible with **Moodle 4.5.2+** and **Moodle 5.x**.

---

## 🏗️ System Architecture

```text
  [ Google Drive Folder ] 
  (Handbooks, Videos, Test Banks)
             │
             ▼
   [ MoodleAutoFlow Ingestion ]
   (Discovers & maps course assets)
             │
      ┌──────┴──────────────────────────┐
      ▼                                 ▼
[ Multi-AI Engine ]           [ Multi-Storage Video Engine ]
- Google Gemini               - Direct Google Drive Embeds
- OpenAI ChatGPT              - Bunny.net Stream (HLS)
- Anthropic Claude            - Cloudflare R2
- Offline Heuristic           - AWS S3 / CloudFront
      │                                 │
      └──────────────┬──────────────────┘
                     ▼
          [ 16-Week Curriculum ]
       (Objectives, Readings, Discussions,
        Assignments, Quizzes, Video Links)
                     │
                     ▼
      [ Moodle REST Web Services ]
    (core_course_duplicate, update, etc.)
                     │
                     ▼
             [ Target Moodle LMS ]
   (Fully provisioned ready-to-teach course)
```

---

## 🛠️ Tech Stack

* **Framework:** Laravel 12+ (PHP 8.4)
* **Database:** MySQL 8.0+
* **Frontend:** Blade, Vanilla CSS with Modern Glassmorphism/Dark Theme, Bootstrap 5, FontAwesome 6
* **LMS Compatibility:** Moodle 4.5+, Moodle 5.x (REST API / CLI)
* **AI SDKs:** Google Generative AI REST, OpenAI REST, Anthropic REST
* **Storage Drivers:** Google Drive REST API, Bunny Stream API, AWS S3 SDK

---

## 🚀 Getting Started

### Prerequisites

* **PHP 8.2+** (PHP 8.4 recommended) with extensions: `pdo_mysql`, `curl`, `mbstring`, `xml`, `zip`.
* **Composer**
* **MySQL 8.0+**
* Access to a Moodle instance (Local or Remote) with Web Services enabled.

---

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/MiltonDIU/moodle-autoflow.git
   cd moodle-autoflow
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Configure Environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Update Database Credentials in `.env`:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=project_nextgen
   DB_USERNAME=your_mysql_user
   DB_PASSWORD=your_mysql_password
   ```

5. **Run Migrations:**
   ```bash
   php artisan migrate
   ```

6. **Start Local Development Server:**
   ```bash
   php artisan serve
   ```
   Access the dashboard at `http://localhost:8000`.

---

## ⚙️ Configuration (Settings Page)

Navigate to `http://localhost:8000/settings` to configure your environment:

1. **Moodle LMS Setup:**
   * **Moodle Base URL:** `http://elearn.local` or `https://lms.yourdomain.com`
   * **Master Template Course ID:** The skeleton course cloned for new courses (e.g. `129` or `4`).
   * **Web Services REST Token:** Generated from Moodle *(Site administration > Server > Web services > Manage tokens)*.
   * Click **"Test Moodle Connection"** to verify live connectivity.

2. **AI Provider Selection:**
   * Select your preferred AI: **Gemini**, **OpenAI**, **Claude**, or **Offline Heuristic**.
   * Enter your API Key and click **"Test AI Connection"**.

3. **Video Storage Driver:**
   * Choose between **Google Drive Direct**, **Bunny.net Stream**, **Cloudflare R2**, or **AWS S3**.
   * Click **"Test Storage Driver"** to ensure credentials are valid.

4. **Google Drive Root Folder ID:**
   * Set your shared drive root folder ID to scan multiple course packages simultaneously.

---

## 📖 Usage Workflow

1. **Scan Google Drive:**
   * From the Dashboard, click **"Scan Drive"**.
   * All discovered course folders appear in the Course Ingestion table.

2. **Extract & Preview:**
   * Click **"Process Course"** to download handbooks and trigger AI curriculum synthesis.
   * Review the generated 16-week interactive curriculum, objectives, readings, and quizzes via the **"Preview Curriculum"** modal.

3. **Deploy to Moodle:**
   * Click **"Sync to Moodle"**.
   * The system provisions the course, assigns modules, builds weeks, embeds video players, and sets up quizzes automatically.

---

## 🔒 Security & Privacy

* Credentials and API keys are stored in your private local MySQL database.
* The `.env` file is excluded from git version control.
* Sensitive Moodle Web Service tokens are never exposed in public responses.

---

## 📄 License

This project is open-source software licensed under the [MIT License](LICENSE).

---

<p align="center">
  Developed with ❤️ by <a href="https://github.com/MiltonDIU">Milton</a>
</p>
