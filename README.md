# Work-It-Out API

An intelligent workout companion API that transforms natural language workout descriptions into structured data. Simply describe your workout in plain text (e.g., "Did 3 sets of bench press: 100kg for 5 reps, 110kg for 3, 120kg for 1"), and let our AI-powered system handle the parsing and storage.

## Project Progress

### Iteration 1 (Current)
- [x] Docker environment setup with PostgreSQL
- [x] Basic Laravel installation
- [x] Core models and migrations
  - [x] Workouts
  - [x] Exercises
  - [x] Exercise Sets
- [x] OpenAI integration
  - [x] Workout text parsing service
  - [x] Structured data transformation
- [x] API Endpoints
  - [x] Routes configuration
  - [x] Controller implementation
  - [x] Testing endpoints
- [x] Basic test suite 
  - [x] Model factories
  - [x] Database seeders
  - [x] API endpoint tests
- [x] API documentation with Scramble

### Iteration 2 (Planned)
- [ ] User authentication and relations
- [ ] Input validation
  - [ ] Workout description sanitization
  - [ ] Non-workout prompt filtering
- [ ] API Enhancements
  - [x] Pagination
  - [ ] Rate limiting
  - [ ] Error handling improvements
- [ ] User profiles and preferences
- [ ] AI Features
  - [ ] Multiple trainer personalities
  - [ ] Workout suggestions
  - [ ] Evolving AI generated user profile
- [ ] Analytics
  - [ ] Progress tracking
  - [ ] Performance metrics
  - [ ] Training history analysis

### Iteration 3 (Conceptual)
- WhatsApp / SMS integration
- Google Docs integration

Note: All weight measurements are assumed to be in kilograms (kg) for now.

## Features (Iteration 1)

- Natural language workout logging
- AI-powered workout parsing
- Structured exercise and set data storage
- RESTful API endpoints
- Auto-generated API documentation
- Comprehensive test coverage

## Tech Stack

- PHP 8.3
- Laravel 11
- PostgreSQL
- Docker & Docker Compose
- OpenAI GPT
- PHPUnit for testing
- Scramble for API documentation

## Run Locally with Docker

1. Clone the repository:
```bash
git clone https://github.com/yourusername/work-it-out
cd work-it-out
```

2. Create environment files:
```bash
# Root directory
echo "DB_DATABASE=workout
DB_USERNAME=workout
DB_PASSWORD=secret" > .env

# Laravel directory
cp src/.env.example src/.env
```

3. Update src/.env with required settings:
```env

OPENAI_API_KEY=your_api_key_here
```

4. Install dependencies and build:
```bash
docker compose run --rm api composer install
docker compose build
```

5. Setup application:
```bash
docker compose run --rm api php artisan key:generate
docker compose run --rm api php artisan install:api
```

6. Start the application:
```bash
docker compose up -d
```

7. Setup database:
```bash
docker compose run --rm api php artisan migrate:fresh --seed
```

## API Endpoints

### Core Endpoints
- `GET /api/workouts` - List all workouts
- `POST /api/workouts` - Create a new workout
- `GET /api/workouts/{id}` - Get a specific workout
- `DELETE /api/workouts/{id}` - Delete a workout

### Documentation & Development
- `GET /docs/api` - API Documentation UI
- `GET /docs/api.json` - OpenAPI specification
- `GET /telescope` - Development debugging dashboard

### Example Usage

Create a workout:
```bash
curl -X POST http://localhost:8000/api/workouts \
  -H "Content-Type: application/json" \
  -d '{"description": "Did 3 sets of bench press: 100kg for 5 reps, 110kg for 3, 120kg for 1"}'
```

Get all workouts:
```bash
curl http://localhost:8000/api/workouts
```

## Testing

Run the test suite:
```bash
docker compose exec api php artisan test
```

## OpenAI API Setup

1. Get your API key:
   - Visit [OpenAI's platform](https://platform.openai.com/api-keys)
   - Sign up or log in to your OpenAI account
   - Create a new API key
   - Copy the key (it will only be shown once)

2. Add to environment:
   ```bash
   # In src/.env
   OPENAI_API_KEY=your_api_key_here
   OPENAI_ORGANIZATION=org-... # Optional
   ```

3. Install OpenAI package (already included in composer.json):
   ```bash
   docker compose exec api composer require openai-php/laravel
   ```

4. Generate configuration:
   ```bash
   docker compose exec api php artisan openai:install
   ```

5. Verify installation:
   ```bash
   # Create a workout to test OpenAI integration
   curl -X POST http://localhost:8000/api/workouts \
     -H "Content-Type: application/json" \
     -d '{"description": "3 sets bench press: 60kg 5 reps"}'
   ```

Note: The free tier of OpenAI API has rate limits and usage quotas. Monitor your usage on the OpenAI dashboard to avoid unexpected charges.


## API Documentation

Access the auto-generated API documentation:
- UI Documentation: http://localhost:8000/docs/api
- OpenAPI Spec: http://localhost:8000/docs/api.json
- Development Tools: http://localhost:8000/telescope

## Example Response Format

```json
{
    "workout": {
        "id": 1,
        "raw_input": "Did 3 sets of bench press: 100kg for 5 reps, 110kg for 3, 120kg for 1",
        "parsed_data": {
            "exercises": [
                {
                    "name": "Bench Press",
                    "sets": [
                        {
                            "reps": 5,
                            "weight": 100
                        },
                        {
                            "reps": 3,
                            "weight": 110
                        },
                        {
                            "reps": 1,
                            "weight": 120
                        }
                    ]
                }
            ]
        },
        "performed_at": "2024-02-03T21:00:00.000000Z",
        "created_at": "2024-02-03T21:00:00.000000Z",
        "updated_at": "2024-02-03T21:00:00.000000Z"
    }
}
```


![planned ERD (could chnage slightly)](https://github.com/Hersh3yy/work-it-out/blob/main/image.jpg?raw=true)
