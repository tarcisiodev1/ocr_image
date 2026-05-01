<div align="center">
  <h1>🧠 LLM OCR Extraction System</h1>
  <p><strong>A robust Laravel web application dedicated to performing high-accuracy Optical Character Recognition (OCR) using local Large Language Models (LLMs).</strong></p>
</div>

---

## 📖 About the Project

This project leverages modern web architecture to process images and extract textual data with the high precision characteristic of Vision Language Models (VLMs). Designed with a focus on performance and reliability, the application offloads the heavy lifting of AI inferences to background queues, ensuring a fast, non-blocking user experience on the frontend.

By communicating with a local AI server (such as LM Studio running `glm-ocr`), the application guarantees data privacy, avoiding third-party cloud API costs and providing a fully self-hosted solution for text extraction, document parsing, and sanitization.

## ✨ Key Features

- **Asynchronous Processing:** Long-running AI inference tasks are dispatched to isolated background Job queues, preventing HTTP request timeouts.
- **Real-Time UX (Polling):** The UI seamlessly polls the backend for processing updates without requiring page reloads, transitioning states from 'pending' to 'completed'.
- **Clean Architecture:** Built over solid engineering principles, featuring `Actions` (invokables) for single-responsibility logic routing, decoupling business rules from controllers.
- **Local AI Integration:** Designed specifically to interact with Local LLMs via REST APIs, fully capturing, sanitizing, and filtering zero-width spaces or artifacts from AI responses. 
- **Robust Automated Testing:** A comprehensive test suite using `Pest PHP` covering HTTP request faking, queue state transitions, JSON structural validation, and fallback mechanisms.

## 🛠️ Stack & Technologies

- **Backend:** PHP 8.4+, Laravel 13
- **Database / Queue:** SQLite (relational records) & Database Queue Driver
- **AI Backend / Integration:** LM Studio API (Local VLM processing)
- **Testing:** Pest PHP (Feature & Integration tests)
- **Frontend:** Vanilla JS & Blade Templates

---

## 🚀 Getting Started

To run this application locally, you will need PHP 8.4+, Composer, and LM Studio server running a compatible Vision Language Model locally on port 1234.

### 1. Installation

Clone the repository and install dependencies:
```bash
composer install
```

Prepare your environment file:
```bash
cp .env.example .env
php artisan key:generate
```

### 2. Database & Storage Setup

```bash
# Create SQLite Database (If using sqlite)
touch database/database.sqlite

# Run migrations
php artisan migrate

# Link local storage for image uploads
php artisan storage:link
```

### 3. Execution

You will need two terminal windows to run the application fully (due to the async architecture).

Terminal 1 - Web Server:
```bash
php artisan serve
```

Terminal 2 - Queue Worker:
```bash
php artisan queue:work
```

### 4. Running the Local AI (LM Studio)
Ensure LM Studio is running in the background and the Local Inference Server is started on `http://127.0.0.1:1234`. The model recommended for OCR tasks is `glm-ocr` or similar vision-capable models.

---

## 🧪 Testing

The codebase relies on **Pest PHP** for highly expressive and documented tests ensuring the application behaves accurately without needing to spin up a real AI server each time.

```bash
# Run the entire test suite
./vendor/bin/pest
```

---

<div align="center">
  <i>Developed to showcase modern asynchronous architecture and Local AI integration within the Laravel ecosystem.</i>
</div>