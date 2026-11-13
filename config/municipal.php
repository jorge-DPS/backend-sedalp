<?php

return [
    'poa_max_upload_kb' => (int) env('MUNICIPAL_POA_MAX_UPLOAD_KB', 20480),
    'poa_max_file_bytes' => (int) env('MUNICIPAL_POA_MAX_FILE_BYTES', 10737418240),
    'poa_chunk_bytes' => (int) env('MUNICIPAL_POA_CHUNK_BYTES', 8388608),
    'poa_upload_ttl_hours' => (int) env('MUNICIPAL_POA_UPLOAD_TTL_HOURS', 24),
    'poa_max_active_uploads' => (int) env('MUNICIPAL_POA_MAX_ACTIVE_UPLOADS', 10),
    'poa_assembly_timeout' => (int) env('MUNICIPAL_POA_ASSEMBLY_TIMEOUT', 3600),
    'photo_max_upload_kb' => (int) env('MUNICIPAL_PHOTO_MAX_UPLOAD_KB', 5120),
    'photo_width' => 800,
    'photo_height' => 800,
];
