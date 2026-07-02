# Triage Assistant

Symfony 7 application that processes WhatsApp support conversations using an LLM and returns structured analysis for each chat: category, sentiment, urgency score and a suggested reply draft for the human agent.

Built with Docker, so no local PHP or MySQL installation is required.

---

## Stack

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.2 (FPM) | Runs inside Docker |
| Symfony | 7.4 | Skeleton install with selected bundles |
| Nginx | Alpine | Reverse proxy, port 8080 |
| MySQL | 8.0 | Runs inside Docker, port 3306 |
| OpenRouter | API v1 | LLM provider, OpenAI-compatible format |
| Tailwind CSS | CDN | No build step required |

---

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running
- A valid [OpenRouter](https://openrouter.ai) API key

That's it. No PHP, no Composer, no Symfony CLI needed on your machine.

---

## Setup

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
docker-compose up -d
```

This will pull the PHP, Nginx and MySQL images, build the application container and start everything. First run takes a couple of minutes.

**4. Install PHP dependencies**

```bash
docker-compose exec php composer install
```

**5. Open the application**

```
http://localhost:8080/triage
```

The first load will take around 30-60 seconds as the application processes all 50 conversations sequentially. Do not refresh while it loads.

---

## Available endpoints

| Route | Method | Description |
|---|---|---|
| `/triage` | GET | Visual interface showing each chat alongside its AI analysis card, sorted by urgency |
| `/api/triage` | GET | Same analysis as structured JSON, ready for integration with other systems |

---

## Project structure

```
triage-assistant/
├── Dockerfile                  # PHP 8.2-FPM image with required extensions
├── docker-compose.yml          # PHP, Nginx and MySQL services
├── nginx/
│   └── default.conf            # Nginx config pointing to Symfony public/
├── src/
│   ├── Controller/
│   │   └── TriageController.php
│   ├── DTO/
│   │   └── ChatAnalysis.php    # Immutable result of a single chat analysis
│   ├── Enum/
│   │   ├── ChatCategory.php    # TECHNICAL_SUPPORT | BILLING | RETURN | PRODUCT_INQUIRY | SPAM | OTHER
│   │   └── ChatSentiment.php   # POSITIVE | NEUTRAL | NEGATIVE | FRUSTRATED
│   └── Service/
│       ├── ChatAnalyzerService.php     # LLM integration, prompt building, response parsing
│       └── ChatAnalysisException.php  # Specific exception for analysis failures
├── templates/
│   └── triage/
│       └── index.html.twig     # Tailwind CSS view with chat + analysis card layout
├── data/
│   └── mock_chats.json         # 50 simulated WhatsApp support conversations
├── .env.example                # Environment variable template
└── README.md
```

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

---

## Docker notes

The project uses a bind mount (`- .:/var/www/html`) so any file changes made locally are reflected inside the container immediately — no need to rebuild the image for code changes.

To stop the containers:

```bash
docker-compose down
```

To rebuild the PHP image after modifying the Dockerfile:

```bash
docker-compose up -d --build
```

---

## Ideas for next iteration

**Async processing with Symfony Messenger**
Processing 50 conversations sequentially in a single HTTP request adds significant latency. Each analysis could be dispatched as a message and processed by background workers, with results stored in MySQL and the UI updated progressively.

**Grouping similar incidents**
If multiple chats report the same issue in parallel (e.g. payment errors, service outage), detect them as a mass incident and trigger a single alert instead of treating each one individually.

**Automatic team routing**
Extend the analysis to include the specific team a ticket should be assigned to (Level 1 Support, Billing, Legal, Logistics), reducing manual triage time after classification.

**Result persistence**
Store each analysis in MySQL to enable historical reporting, category frequency metrics and sentiment trends over time.
