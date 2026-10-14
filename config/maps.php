<?php

return [
    'ogr2ogr_binary' => env('MAPS_OGR2OGR_BINARY', 'ogr2ogr'),
    'proj_data' => env('MAPS_PROJ_DATA'),
    'gdal_data' => env('MAPS_GDAL_DATA'),
    'max_upload_kb' => 10240,
    'max_expanded_bytes' => 41943040,
    'max_geojson_bytes' => 20971520,
    'max_features' => 5000,
    'max_positions' => 200000,
    'conversion_timeout' => 60,
];
