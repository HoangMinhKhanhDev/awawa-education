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
                'base_url' => env('AGNES_BASE_URL', 'https://apihub.agnes-ai.com/v1'),
                'api_key' => env('AGNES_API_KEY'),
                'model' => env('AGNES_DEFAULT_MODEL', ''),
            ],
        ],

        /*
         * Khi nhà cung cấp miễn phí trả 429, chờ một chút rồi thử lại cùng provider
         * trước khi báo lỗi, tránh bắt giáo viên ngồi chờ hẹn giờ 60 giây.
         */
        'rate_limit' => [
            'retry_delay' => (int) env('AI_RATE_LIMIT_RETRY_DELAY', 10),
            'max_attempts' => (int) env('AI_RATE_LIMIT_MAX_ATTEMPTS', 2),
            'max_total_wait' => (int) env('AI_RATE_LIMIT_MAX_TOTAL_WAIT', 20),
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

        // Mỗi chunk ~1.000 ký tự. Số chunk quyết định độ dài prompt nên quyết định
        // luôn tốc độ: 12 chunk là khoảng 12k ký tự, đủ trả lời mà không phải gửi
        // cả cuốn sách lên mỗi tin nhắn.
        'max_context_chunks' => (int) env('NOTEBOOK_MAX_CONTEXT_CHUNKS', 12),
        'max_artifact_context_chunks' => (int) env('NOTEBOOK_MAX_ARTIFACT_CONTEXT_CHUNKS', 8),
        'chunk_size' => (int) env('NOTEBOOK_CHUNK_SIZE', 1000),
        'chunk_overlap' => (int) env('NOTEBOOK_CHUNK_OVERLAP', 150),
        'stream' => (bool) env('AI_STREAM', true),
        'history_messages' => (int) env('NOTEBOOK_HISTORY_MESSAGES', 6),
        'max_notebooks' => (int) env('NOTEBOOK_MAX_NOTEBOOKS', 20),

        // Trần token cho câu trả lời của một lần soạn. Nhà cung cấp miễn phí chậm
        // theo tỉ lệ gần như tuyến tính với số token sinh ra, nên hạ trần này là
        // cách rút ngắn thời gian chờ rõ rệt nhất.
        'max_artifact_tokens' => (int) env('NOTEBOOK_MAX_ARTIFACT_TOKENS', 6000),

        /*
        | Bao lâu được chờ một lần gọi AI khi soạn nội dung. Soạn chạy ngoài web
        | request nên chờ lâu hơn chat được nhiều; giữ dưới trần `max_execution_time`
        | của hosting (Hostinger Business cho tối đa 360 giây) để lỗi hết thời gian
        | đến từ phía ta chứ không phải từ host cắt tiến trình.
        */
        'artifact_timeout' => (int) env('NOTEBOOK_ARTIFACT_TIMEOUT', 300),

        /*
        | Một ngưỡng treo duy nhất cho mọi lần soạn đang dở: đề lớn nhất hợp lệ
        | mất vài phút, nên quá 30 phút tức là không còn tiến trình nào lo nữa,
        | dù là hàng chờ cron chết hay tiến trình nền chết giữa chừng.
        */
        'stale_minutes' => (int) env('NOTEBOOK_STALE_MINUTES', 30),

        // Số nội dung một lượt cron được nhận, và trần thời gian cho cả lượt, để
        // giáo viên bấm "Tạo" nhiều lần không phải xếp hàng từng phút một.
        'pending_batch' => (int) env('NOTEBOOK_PENDING_BATCH', 5),
        'pending_time_budget' => (int) env('NOTEBOOK_PENDING_TIME_BUDGET', 240),

        // Bộ nhớ cho tiến trình nền soạn nội dung, nên có cao hơn mặc định của PHP.
        'generation_memory' => env('NOTEBOOK_GENERATION_MEMORY', '1024M'),

        'tavily' => [
            'base_url' => env('TAVILY_BASE_URL', 'https://api.tavily.com'),
            'api_key' => env('TAVILY_API_KEY'),
            'max_results' => (int) env('TAVILY_MAX_RESULTS', 8),
        ],
    ],

];
