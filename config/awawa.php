<?php

use App\Enums\SubjectFeature;

return [

    /*
    |--------------------------------------------------------------------------
    | Thương hiệu
    |--------------------------------------------------------------------------
    */

    'brand' => [
        'name' => 'awawa',
        'tagline' => 'Đội tuyển học sinh giỏi',
        'primary' => '#2563EB',
        'primary_dark' => '#1E40AF',
        'primary_light' => '#3B82F6',
        'accent' => '#38BDF8',
        'surface' => '#F8FAFC',
        'night' => '#0B1220',
    ],

    /*
    |--------------------------------------------------------------------------
    | Danh sách môn mặc định (dùng cho seeder)
    |--------------------------------------------------------------------------
    |
    | Admin có thể thêm môn mới trực tiếp trong trang quản trị.
    |
    */

    'default_subjects' => [
        ['code' => 'toan', 'name' => 'Toán học', 'color' => '#2563EB'],
        ['code' => 'vat-li', 'name' => 'Vật lí', 'color' => '#0EA5E9'],
        ['code' => 'hoa-hoc', 'name' => 'Hóa học', 'color' => '#F97316'],
        ['code' => 'sinh-hoc', 'name' => 'Sinh học', 'color' => '#22C55E'],
        ['code' => 'tin-hoc', 'name' => 'Tin học', 'color' => '#6366F1'],
        ['code' => 'ngu-van', 'name' => 'Ngữ văn', 'color' => '#EC4899'],
        ['code' => 'lich-su', 'name' => 'Lịch sử', 'color' => '#B45309'],
        ['code' => 'dia-li', 'name' => 'Địa lí', 'color' => '#14B8A6'],
        ['code' => 'tieng-anh', 'name' => 'Tiếng Anh', 'color' => '#8B5CF6'],
        ['code' => 'gdktpl', 'name' => 'Giáo dục kinh tế & pháp luật', 'color' => '#64748B'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tính năng theo môn
    |--------------------------------------------------------------------------
    */

    'features' => [
        'keys' => array_map(fn (SubjectFeature $feature) => $feature->value, SubjectFeature::cases()),
        'default' => SubjectFeature::defaultEnabledValues(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth
    |--------------------------------------------------------------------------
    |
    | allowed_domains / allowed_emails để trống nghĩa là chấp nhận mọi tài khoản.
    |
    */

    'google' => [
        'allowed_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_ALLOWED_DOMAINS', '')),
        ))),
        'allowed_emails' => array_values(array_filter(array_map(
            fn (string $email) => strtolower(trim($email)),
            explode(',', (string) env('GOOGLE_ALLOWED_EMAILS', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'default' => env('AI_DEFAULT_PROVIDER', 'openrouter'),

        'providers' => [
            'openrouter' => [
                'label' => 'OpenRouter',
                'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
                'api_key' => env('OPENROUTER_API_KEY'),
                'model' => env('OPENROUTER_DEFAULT_MODEL', 'openrouter/free'),
            ],
            'agnes' => [
                'label' => 'Agnes AI',
                'base_url' => env('AGNES_BASE_URL'),
                'api_key' => env('AGNES_API_KEY'),
                'model' => env('AGNES_DEFAULT_MODEL', 'agnes-chat'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    */

    'webpush' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notebook (AI Studio)
    |--------------------------------------------------------------------------
    */

    'notebook' => [
        'max_sources' => (int) env('NOTEBOOK_MAX_SOURCES', 20),
        'max_file_mb' => (int) env('NOTEBOOK_MAX_FILE_MB', 10),
        'max_source_chars' => (int) env('NOTEBOOK_MAX_SOURCE_CHARS', 200000),
        'max_prompt_chars' => (int) env('NOTEBOOK_MAX_PROMPT_CHARS', 400000),
        'chunk_size' => (int) env('NOTEBOOK_CHUNK_SIZE', 1000),
        'chunk_overlap' => (int) env('NOTEBOOK_CHUNK_OVERLAP', 150),
        'stream' => (bool) env('AI_STREAM', true),
        'history_messages' => (int) env('NOTEBOOK_HISTORY_MESSAGES', 8),

        'tavily' => [
            'base_url' => env('TAVILY_BASE_URL', 'https://api.tavily.com'),
            'api_key' => env('TAVILY_API_KEY'),
            'max_results' => (int) env('TAVILY_MAX_RESULTS', 8),
        ],
    ],

];
