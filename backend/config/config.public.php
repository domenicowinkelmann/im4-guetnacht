<?php
/**
 * Public Configuration
 */

define('DB_HOST', 'jk4b7p.myd.infomaniak.com');
define('DB_PORT', 3306);
define('DB_NAME', 'jk4b7p_im4_guetnacht');

define('APP_NAME', 'GuetNacht');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'production');

define('SESSION_LIFETIME', 60 * 60 * 24 * 7);

// CORS allowed origins
define('ALLOWED_ORIGINS', ['http://localhost', 'https://im4.domenicowinkelmann.ch']);

// Sensor data freshness threshold (seconds)
define('DATA_FRESHNESS_THRESHOLD', 300);
