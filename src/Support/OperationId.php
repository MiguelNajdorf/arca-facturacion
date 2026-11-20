<?php
declare(strict_types=1);
namespace ArcaFacturacion\Support;
final class OperationId
{
    public static function create(): string { return 'arca_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)); }
}
