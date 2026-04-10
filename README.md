# 🎓 Academic English Training Web App

[![Agile Software Engineering](https://img.shields.io/badge/Course-Agile%20Software%20Engineering-blue.svg)](#)

A comprehensive platform designed for **DIISCU first-year students** to bridge the gap between general English and university-level academic communication.

---

## 🚀 Project Overview

The **Academic English Training Web Application** integrates learning, collaboration, and gamification into a single ecosystem. It helps students master academic vocabulary and communication skills through two distinct experiences:

* **Classic Web Interface**: Streamlined, efficient, and professional.
* **Game-style Interface**: An immersive 2D lobby built with **Godot** for interactive navigation.

---

## 🎯 Target Users

* **DIISCU Freshmen**: Students adapting to an English-mediated instruction (EMI) environment.
* **Academic Aspirants**: Learners preparing for academic research and collaboration.
* **Interactive Learners**: Students who prefer engaging, gamified educational experiences.

---

## 🛠 Tech Stack

| Layer | Technology | Purpose |
| :--- | :--- | :--- |
| **Backend** | `PHP` | Auth, API Handling & Business Logic |
| **Database** | `MySQL 8.0` | Persistent Data Storage |
| **Frontend Core** | `React` + `Vite` | Dynamic & Modern UI Components |
| **Game Engine** | `Godot` | Interactive Navigation & Game-style UI |
| **Realtime** | `WebSocket` | Live Chat & Instant Notifications |
| **Server** | `Nginx` + `PHP-FPM` | High-performance Web Serving |
| **Environment** | `Linux` | Production Deployment |

---

## ✨ Key Features

* **🔐 Unified Auth**: Single Sign-On (SSO) across learning modules, forums, and messaging.
* **📚 Academic Workflow**: Tailored practice for academic listening, response, and speaking.
* **📈 Progress Tracking**: Visual vocabulary learning with persistent history and review cycles.
* **💬 Social Ecosystem**: 
    * *Forum*: Peer-to-peer knowledge exchange.
    * *Message Center*: Direct and group real-time communication.
* **🏆 Team Challenges**: Collaborative weekly tasks with live leaderboards to boost motivation.
* **🎮 Dual-Mode Navigation**: Switch between a standard web dashboard and a Godot-powered "Game Lobby".
* **🛡️ Role-Based Access (RBAC)**: Distinct workflows and dashboards for Students and Admins.

---

## 📂 Repository Structure

```text
Deadline-Disco/
├── Auth/                 # Authentication & Session Management APIs
├── Academic-Practice/    # Core academic training modules
├── vocba_prac/           # Vocabulary learning engine
├── forum-project/        # Discussion boards (Frontend/API)
├── message-center-project/# Real-time chat system
├── GameUI/               # Integrated game-style web views
├── gameUI_src/           # Godot source code & Web exports
├── shared/               # Shared runtime configurations
├── admin_page/           # Administrative control panel
├── challenge/            # Team collaboration & Ranking modules
├── sql/                 # Database schema & Bootstrap scripts
├── redeploy.php          # Unified system startup script
└── doc/                  # Architecture & deployment manuals
```
---

## 💻 Local Deployment

### 1. Prerequisites
- **PHP** 8+
- **Node.js** 18+ and npm
- **MySQL** 8+

### 2. Configure 
Initialize your environment variables:

```bash
cp .env.example .env
```
**Note:** Ensure DB_* and REALTIME_* settings match your local environment.

### 3. Database Setup
Import the initial schema and data:

```bash
mysql -u root -p < sql/001_acadbeat_all_create_tables.sql
mysql -u root -p < sql/002_acadbeat_all_other_sql.sql
```

### 4. Running the Application
#### Stardard Mode
```bash
php redeploy.php
```

#### Development Mode (with extra services):

```bash
php redeploy.php --full
```

### Stop Services
```bash
php shutdown.php
```

---

## 🔗 Main Entrances (Local)
- Main Entrance: `http://127.0.0.1:8001/home.html`
- Academic Training Entrance: `http://127.0.0.1:8001/Academic-Practice/training.html`
- Forum Entrance: `http://127.0.0.1:8001/forum-project/dist/index.html?view=forum`
- Chat Entrance: `http://127.0.0.1:8001/message-center-project/dist/index.html`
- Team Entrance: `http://127.0.0.1:8001/challenge/challenge-panel.html`
- Game Entrance: `http://127.0.0.1:5500/index.html?ui=godot`

---

## 🤝 Welcome to Contribute
We follow the **Agile** development process. To contribute:

1. **Fork** the repo & create your feature branch.

2. **Sync:** Always pull the latest main before coding.

3. **Scope:** Focus on one bug fix or feature per PR.

4. **Test:** Verify locally using redeploy.php.

5. **Submit:** Open a PR with a clear summary of changes.

---

给你一个**完全匹配你现有 README 风格（不破坏结构，直接追加在最底部）**的 AI Cite👇

---

## 🤖 AI Assistance / Citation

This project was developed with the assistance of the following AI tools:
	•	ChatGPT – used for code generation, debugging, and technical explanation
	•	Gemini – used for idea generation and UI/UX design support
	•	Doubao – used for content drafting and alternative solution suggestions

**Scope of Use**

AI tools were used to:
	•	Assist in writing and refining code
	•	Debug issues and suggest fixes
	•	Provide UI/UX design ideas
	•	Improve documentation clarity

**Disclaimer**

All AI-generated content was reviewed, modified, and validated by the project team. Final implementations reflect the team’s own understanding and decisions.

---

_Developed as part of the Agile Software Engineering course._

