<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | One model per job (App\Ai\AiJob)
    |--------------------------------------------------------------------------
    |
    | classify: what kind of message this is (only with AI_CLASSIFIER=llm).
    | log: the log reader, which turns a message into exercises and sets.
    | limitation: the injury reader. coach: answers and the morning message.
    | profile: the written profile.
    |
    | A job asks its own model first, then the log job's model, then each of
    | the fallbacks below (App\Ai\ModelChain). An empty provider or model
    | is skipped in favour of the log job's; when the log job is empty too,
    | the default provider (AI_DEFAULT_PROVIDER) and its default text model
    | answer. timeout is in seconds; the library's own default is 60, and one
    | hung model held the poller and everyone behind it for that long. The
    | queue worker's --timeout (docker/supervisor/supervisord.conf) outlasts
    | all of them twice over.
    |
    */

    'jobs' => [
        'classify' => [
            'provider' => env('AI_CLASSIFY_PROVIDER'),
            'model' => env('AI_CLASSIFY_MODEL'),
            'timeout' => (int) env('AI_CLASSIFY_TIMEOUT', 15),
        ],
        'log' => [
            'provider' => env('AI_LOG_PROVIDER'),
            'model' => env('AI_LOG_MODEL'),
            'timeout' => (int) env('AI_LOG_TIMEOUT', 30),
        ],
        'limitation' => [
            'provider' => env('AI_LIMITATION_PROVIDER'),
            'model' => env('AI_LIMITATION_MODEL'),
            'timeout' => (int) env('AI_LIMITATION_TIMEOUT', 20),
        ],
        'coach' => [
            'provider' => env('AI_COACH_PROVIDER'),
            'model' => env('AI_COACH_MODEL'),
            'timeout' => (int) env('AI_COACH_TIMEOUT', 45),
        ],
        'profile' => [
            'provider' => env('AI_PROFILE_PROVIDER'),
            'model' => env('AI_PROFILE_MODEL'),
            'timeout' => (int) env('AI_PROFILE_TIMEOUT', 60),
        ],
    ],

    /*
    | Models tried after a job's own (and the log job's) when those are
    | overloaded, rate limited or unreachable, in order, as provider:model,
    | comma separated: AI_FALLBACKS=gemini:gemini-3.5-flash,deepseek:deepseek-flash
    | (App\Ai\ModelChain).
    */
    'fallbacks' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_FALLBACKS', ''))))),

    /*
    | Prices per million tokens in USD, keyed by model, from the provider's
    | own pricing page (read 12 November 2026, docs/direction-2026-11-14.md).
    | Read by php artisan ai:usage. A model without a price is reported as
    | unpriced; nothing is guessed. DeepSeek bills a lower rate off-peak;
    | the peak rate is kept here as the upper bound. Gemini 3.8 Flash
    | doubles on 1 January 2027. Mistral (read 15 November 2026) is priced at
    | the EU endpoint's rate, 1.1 times the global list price.
    */
    'prices' => [
        'gemini-3.5-flash-lite' => ['input' => 0.30, 'output' => 2.50],
        'gemini-3.5-flash' => ['input' => null, 'output' => null],
        'gemini-3.8-flash' => ['input' => 0.75, 'output' => 3.75],
        'deepseek-flash' => ['input' => 0.30, 'output' => 1.20],
        'deepseek-v4-pro' => ['input' => 1.32, 'output' => 3.96],
        'mistral-medium-3-5' => ['input' => 1.65, 'output' => 8.25],
        'mistral-large-4-0' => ['input' => 0.77, 'output' => 2.31],
        'mistral-small-2603' => ['input' => 0.165, 'output' => 0.66],
    ],

    /*
    | Tokens one person may use per calendar month before coach questions
    | and the morning message pause until the 1st (logging is never capped).
    | 3 million is about 500 coach answers: at most about $1 on
    | gemini-3.5-flash-lite and $5 on deepseek-v4-pro at peak. 0 means no cap.
    */
    'monthly_tokens_per_user' => (int) env('AI_MONTHLY_TOKENS_PER_USER', 3_000_000),

    /*
    | The classify step (what kind of message is this). Plain rules always run
    | first. "llm" asks ClassifierAgent on the log job's model; "laya" and "jev"
    | ask a typed-decision (System One) model over HTTP: Laya self-hosted with
    | laya-serve, Jev hosted by TypeSafe. Below min_confidence the answer falls
    | back to the llm classifier when fallback is on.
    */
    'classifier' => [
        'driver' => env('AI_CLASSIFIER', 'llm'),
        'min_confidence' => (float) env('AI_CLASSIFIER_MIN_CONFIDENCE', 0.6),
        'fallback' => (bool) env('AI_CLASSIFIER_FALLBACK', true),
        'laya' => [
            'url' => env('LAYA_URL', 'http://localhost:8765'),
            'path' => env('LAYA_PATH', '/v1/systemone'),
            'key' => env('LAYA_API_KEY'),
            'model' => env('LAYA_MODEL', 'laya-typed-decisions'), // English only for now; scored best in the lab
        ],
        'jev' => [
            'url' => env('TYPESAFE_BASE_URL', 'https://api.typesafe.ai'),
            'path' => env('TYPESAFE_PATH', '/v1/systemone'),
            'key' => env('TYPESAFE_API_KEY'),
            'model' => env('TYPESAFE_MODEL', 'jev'),
        ],
    ],

    'conversations' => [
        // One call per new thread: do not spend a second call naming it.
        'generate_title' => false,
    ],

    'default_for_images' => 'gemini',
    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
            'image_deployment' => env('AZURE_OPENAI_IMAGE_DEPLOYMENT', 'gpt-image-1'),
            'store' => env('AZURE_OPENAI_STORE', true),
        ],

        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
            'key' => env('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => env('AWS_USE_DEFAULT_CREDENTIALS', true),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
            'models' => [
                'text' => [
                    'default' => env('AI_TEXT_MODEL', 'gemini-3.5-flash'),
                    'cheapest' => env('AI_TEXT_MODEL', 'gemini-3.5-flash-lite'),
                ],
            ],
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
            // https://api.eu.mistral.ai/v1 keeps inference in the EU; the global default promises no location.
            'url' => env('MISTRAL_URL', 'https://api.mistral.ai/v1'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY', 'msty-not-needed'),
            // OPENAI_URL points at Msty MLX locally (host.docker.internal for Sail containers).
            // Swap to a cloud / hosted Gemma endpoint via a single env change in production.
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            // Disable server-side storage: Msty does not support it, and no
            // agent here keeps a conversation (each call gets its context).
            'store' => false,
            'models' => [
                'text' => [
                    'default' => env('AI_TEXT_MODEL'),
                    'cheapest' => env('AI_TEXT_MODEL'),
                ],
            ],
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
        ],
    ],

];
