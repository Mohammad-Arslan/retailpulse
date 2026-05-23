<?php

declare(strict_types=1);

return [

    'disk' => env('MEDIA_DISK', 'public'),

    'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 5120),

    'max_images_per_model' => (int) env('MEDIA_MAX_IMAGES_PER_MODEL', 10),

    'max_width' => (int) env('MEDIA_MAX_WIDTH', 1200),

    'max_height' => (int) env('MEDIA_MAX_HEIGHT', 1200),

    'thumbnail_width' => (int) env('MEDIA_THUMBNAIL_WIDTH', 300),

    'thumbnail_height' => (int) env('MEDIA_THUMBNAIL_HEIGHT', 300),

    'quality' => (int) env('MEDIA_QUALITY', 85),

    'allowed_mimes' => ['jpeg', 'jpg', 'png', 'webp'],

];
