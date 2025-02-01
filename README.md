# Work-It-Out API

An intelligent workout companion API that combines natural language input with AI-powered workout tracking. Describe your workouts in plain text and let our system handle the structured data storage and analysis.

## Project Progress

Progress
- [ ] Docker environment setup with PostgreSQL
- [ ] Basic Laravel installation
- [ ] JWT authentication implementation
- [ ] Database migrations for core tables (users, workouts, exercises, sets)
- [ ] User model with JWT configuration
- [ ] Workout and exercise models with relationships
- [ ] Basic test suite setup
- [ ] OpenAI integration
- [ ] Workout parsing service
- [ ] Input validation and sanitization
- [ ] Error handling
- [ ] Security middleware
- [ ] Comprehensive testing
- [ ] API documentation

## Features

- Natural language workout logging
- Structured exercise and set tracking
- JWT authentication
- PostgreSQL database for reliable data storage
- Docker environment for easy development and deployment
- Comprehensive test coverage
- OpenAI-powered workout parsing

## Tech Stack

- PHP 8.2
- Laravel 10
- PostgreSQL
- Docker & Docker Compose
- JWT Authentication
- OpenAI GPT
- PHPUnit for testing

## Getting Started

### Prerequisites

- Docker and Docker Compose
- OpenAI API key

### Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/work-it-out
cd work-it-out
```

2. Copy the environment file:
```bash
cp .env.example .env
```

3. Configure your environment variables:
- Set your PostgreSQL credentials
- Add your OpenAI API key
- Configure JWT secret

4. Start the Docker environment:
```bash
docker-compose up -d
```

5. Install dependencies:
```bash
docker-compose exec app composer install
```

6. Run migrations:
```bash
docker-compose exec app php artisan migrate
```

7. Generate JWT secret:
```bash
docker-compose exec app php artisan jwt:secret
```

## API Endpoints

### Authentication
- POST `/api/auth/login` - Get JWT token
- POST `/api/auth/refresh` - Refresh JWT token
- POST `/api/auth/logout` - Invalidate JWT token

### Workouts
- POST `/api/workouts` - Log a new workout
- GET `/api/workouts` - Get user's workouts
- GET `/api/workouts/{id}` - Get specific workout
- PUT `/api/workouts/{id}` - Update workout
- DELETE `/api/workouts/{id}` - Delete workout

## Testing

Run the test suite:
```bash
docker-compose exec app php artisan test
```

## Contributing

1. Fork the project
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.