<?php
declare(strict_types=1);
// Referencia. La fuente de verdad es el entorno o el archivo .env.
return [
    'environment' => getenv('ARCA_ENVIRONMENT') ?: 'homologacion',
    'cuit' => getenv('ARCA_CUIT') ?: '',
    'point_of_sale' => getenv('ARCA_POINT_OF_SALE') ?: '',
    'certificate_path' => getenv('ARCA_CERT_PATH') ?: '',
    'private_key_path' => getenv('ARCA_KEY_PATH') ?: '',
    'storage_path' => getenv('ARCA_STORAGE_PATH') ?: 'storage',
];
