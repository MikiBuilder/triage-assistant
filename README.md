# Triage Assistant

🔗 **Live demo:** [https://triage-assistant-rxd9.onrender.com/triage](https://triage-assistant-rxd9.onrender.com/triage)

> Note: hosted on Render's free tier — the instance spins down after inactivity, so the first load after a while may take 30-60 seconds to wake up, on top of the sequential LLM processing time below.

Symfony 7 application that processes WhatsApp support conversations using an LLM and returns structured analysis for each chat: category, sentiment, urgency score and a suggested reply draft for the human agent.

Built with Docker, so no local PHP installation is required.

---

## Stack


|
 Tool 
|
 Version 
|
 Notes 
|
|
---
|
---
|
---
|
|
 PHP 
|
 8.2 (FPM) 
|
 Runs inside Docker 
|
|
 Symfony 
|
 7.4 
|
 Skeleton install with selected bundles 
|
|
 Nginx 
|
 Alpine (local) / Debian (production) 
|
 Reverse proxy in front of PHP-FPM 
|
|
 OpenRouter 
|
 API v1 
|
 LLM provider, OpenAI-compatible format 
|
|
 Tailwind CSS 
|
 CDN 
|
 No build step required 
|

No database is used. Conversations are read directly from a static JSON fixture (`data/mock_chats.json`), and results are not persisted between requests.

---

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running
- A valid [OpenRouter](https://openrouter.ai) API key

That's it. No PHP, no Composer, no Symfony CLI needed on your machine.

---

## Setup (local development)

**1. Clone the repository**

```bash
git clone https://github.com/MikiBuilder/triage-assistant.git
cd triage-assistant
```

**2. Create your environment file**

```bash
cp .env.example .env
```

Open `.env` and fill in your OpenRouter API key:

```env
OPENROUTER_API_KEY=your-api-key-here
```

**3. Start the containers**

```bash
docker compose up -d
```

This will pull the PHP and Nginx images, build the application container and start everything.

**4. Install PHP dependencies**

```bash
docker compose exec php composer install
```

**5. Open the application**

http://localhost:8080/triage


The first load will take around 30-60 seconds as the application processes all 50 conversations sequentially. Do not refresh while it loads.

---

## Available endpoints

| Route | Method | Description |
|---|---|---|
| `/triage` | GET | Visual interface showing each chat alongside its AI analysis card, sorted by urgency |
| `/api/triage` | GET | Same analysis as structured JSON, ready for integration with other systems |

---

## Deployment

The live demo runs on [Render](https://render.com)'s free tier, using a **single Docker image** (`Dockerfile.render`) that bundles Nginx, PHP-FPM and Supervisor together — Render's free plan only runs one container per service, so the local multi-container setup (`docker-compose.yml`) isn't used in production.

Key differences from local development:

- **No database.** Doctrine was removed entirely (`composer.json`, `config/bundles.php`, `config/packages/doctrine*.yaml`) since it was unused scaffolding — the app only ever reads from the static JSON fixture.
- **Single container, multi-process.** `docker/render/supervisord.conf` runs Nginx and PHP-FPM side by side inside one image, with both processes' logs forwarded to stdout/stderr so they're visible in Render's log viewer.
- **Dynamic port binding.** Render assigns the public port via the `PORT` environment variable at runtime, not build time. `docker/render/start.sh` substitutes it into the Nginx config on container start using `envsubst`.
- **Runtime permissions.** `var/cache` is owned by `www-data` (the user PHP-FPM runs as) *after* Symfony's own cache-clear step, not before — otherwise Symfony regenerates cache files as root and PHP-FPM can't write to them.
- **Extended timeouts.** Since `/triage` processes 50 conversations sequentially against an LLM, both Nginx (`fastcgi_read_timeout`) and PHP (`max_execution_time`) are raised to 300s to avoid 504 errors on the free tier's default limits.

Environment variables required on Render: `APP_ENV=prod`, `APP_SECRET`, `OPENROUTER_API_KEY`, `OPENROUTER_BASE_URL`.

---

## Project structure

triage-assistant/
├── Dockerfile # PHP 8.2-FPM image for local dev (used with docker-compose.yml)
├── Dockerfile.render # Single-image build for Render (Nginx + PHP-FPM + Supervisor)
├── docker-compose.yml # PHP + Nginx services for local development
├── docker/
│ └── render/
│ ├── nginx.conf.template # Nginx config with ${PORT} substitution
│ ├── supervisord.conf # Runs nginx + php-fpm in one container
│ └── start.sh # Entrypoint: port binding, permissions, cache warmup
├── nginx/
│ └── default.conf # Nginx config for local dev
├── src/
│ ├── Controller/
│ │ └── TriageController.php
│ ├── DTO/
│ │ └── ChatAnalysis.php # Immutable result of a single chat analysis
│ ├── Enum/
│ │ ├── ChatCategory.php # TECHNICAL_SUPPORT | BILLING | RETURN | PRODUCT_INQUIRY | SPAM | OTHER
│ │ └── ChatSentiment.php # POSITIVE | NEUTRAL | NEGATIVE | FRUSTRATED
│ └── Service/
│ ├── ChatAnalyzerService.php # LLM integration, prompt building, response parsing
│ └── ChatAnalysisException.php # Specific exception for analysis failures
├── templates/
│ └── triage/
│ └── index.html.twig # Tailwind CSS view with chat + analysis card layout
├── data/
│ └── mock_chats.json # 50 simulated WhatsApp support conversations
├── .env.example # Environment variable template
└── README.md


---

## Technical decisions

**Enums for category and sentiment**
Values are defined as PHP 8.1 backed enums. If the LLM returns a value outside the catalog, `Enum::from()` throws a `ValueError` immediately rather than letting invalid data propagate silently through the application.

**Dynamic prompt generation from enum values**
Both enums expose a static `values()` method that returns their cases as a formatted string. The system prompt is built using these values directly, so adding a new category or sentiment only requires a change in the enum — the prompt stays in sync automatically.

```php
public static function values(): string
{
    return implode('" | "', array_column(self::cases(), 'value'));
}
```

**Immutable DTO**
`ChatAnalysis` is a `readonly` class. Once constructed from the LLM response, its data cannot be modified anywhere in the flow.

**Full conversation history passed to the LLM**
The entire `messages` array is sent on every request, not just the last message. This is required for the model to understand contextual references across turns — for example, a message saying "how do I cancel it" only makes sense if the model can see that a Pro subscription was mentioned two messages earlier.

**Per-chat error isolation**
`ChatAnalysisException` is caught individually for each conversation inside `processChats()`. A timeout or invalid response on one chat does not stop the rest from being processed. The failed chat is marked with an error message in the UI while the others render normally.

**Urgency scoring and sorting**
Each analysis includes an `urgency` field (1–5) calculated by the LLM based on customer tone and issue severity. Results are sorted descending by urgency before rendering, so agents see the most critical cases first regardless of the order they appear in the source JSON.

**No database**
Doctrine was part of the initial Symfony skeleton but was never used — the app is read-only against a static JSON fixture. It was removed to keep the deployment footprint minimal and avoid needing a managed database for what is, functionally, a stateless analysis tool.

---

## Docker notes (local)

The project uses a bind mount (`- .:/var/www/html`) so any file changes made locally are reflected inside the container immediately — no need to rebuild the image for code changes.

To stop the containers:

```bash
docker compose down
```

To rebuild the PHP image after modifying the Dockerfile:

```bash
docker compose up -d --build
```

---

## Ideas for next iteration

**Async processing with Symfony Messenger**
Processing 50 conversations sequentially in a single HTTP request adds significant latency. Each analysis could be dispatched as a message and processed by background workers, with results stored progressively and the UI updated as they complete.

**Grouping similar incidents**
If multiple chats report the same issue in parallel (e.g. payment errors, service outage), detect them as a mass incident and trigger a single alert instead of treating each one individually.

**Automatic team routing**
Extend the analysis to include the specific team a ticket should be assigned to (Level 1 Support, Billing, Legal, Logistics), reducing manual triage time after classification.

**Result persistence**
Store each analysis (e.g. in SQLite or a lightweight managed database) to enable historical reporting, category frequency metrics and sentiment trends over time.
