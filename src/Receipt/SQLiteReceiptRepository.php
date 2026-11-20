<?php
declare(strict_types=1);
namespace ArcaFacturacion\Receipt;
use ArcaFacturacion\Exception\StorageException;
use ArcaFacturacion\Invoice\Invoice;
use PDO;
use PDOException;
final class SQLiteReceiptRepository implements ReceiptRepository
{
    private PDO $pdo;
    public function __construct(string $databasePath)
    {
        $directory = dirname($databasePath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new StorageException('No se pudo crear la carpeta de SQLite.'); }
        try {
            $this->pdo = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
            $this->pdo->exec('PRAGMA journal_mode=WAL; PRAGMA synchronous=FULL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
            $this->migrate();
            @chmod($databasePath, 0600);
        } catch (PDOException $error) { throw new StorageException('No se pudo iniciar la persistencia SQLite.', 'sqlite_init', null, $error); }
    }
    public function findByIdempotencyKey(string $key): ?ReceiptRecord { return $this->one('SELECT * FROM receipts WHERE idempotency_key = :value', $key); }
    public function findByOperationId(string $operationId): ?ReceiptRecord { return $this->one('SELECT * FROM receipts WHERE operation_id = :value', $operationId); }
    public function createPending(string $operationId, string $key, string $fingerprint, Invoice $invoice, int $salesPoint): ReceiptRecord
    {
        $now = date(DATE_ATOM);
        try {
            $statement = $this->pdo->prepare('INSERT INTO receipts (operation_id,idempotency_key,fingerprint,status,voucher_type,sales_point,amount,request_json,created_at,updated_at) VALUES (:op,:key,:fingerprint,\'pending\',:type,:point,:amount,:request,:created,:updated)');
            $statement->execute(['op' => $operationId, 'key' => $key, 'fingerprint' => $fingerprint, 'type' => $invoice->voucherType, 'point' => $salesPoint, 'amount' => $invoice->total, 'request' => $this->encode($invoice->jsonSerialize()), 'created' => $now, 'updated' => $now]);
        } catch (PDOException $error) { throw new StorageException('No se pudo registrar el intento antes de emitir.', 'sqlite_insert', null, $error); }
        return $this->required($operationId);
    }
    public function markProcessing(string $operationId, int $voucherNumber): ReceiptRecord { return $this->update($operationId, 'processing', ['voucher_number' => $voucherNumber]); }
    /**
     * @param array<string, mixed> $response
     * @param list<string> $observations
     */
    public function markAuthorized(string $operationId, array $response, array $observations = []): ReceiptRecord
    {
        return $this->update($operationId, 'authorized', ['cae' => (string) ($response['cae'] ?? ''), 'cae_expiration' => (string) ($response['cae_expiration'] ?? ''), 'response_json' => $this->encode($response), 'observations_json' => $this->encode($observations)]);
    }
    /**
     * @param list<array<string, mixed>> $errors
     * @param array<string, mixed>|null $response
     */
    public function markRejected(string $operationId, array $errors, ?array $response = null): ReceiptRecord { return $this->update($operationId, 'rejected', ['errors_json' => $this->encode($errors), 'response_json' => $response === null ? null : $this->encode($response)]); }
    public function markUnknown(string $operationId, array $errors): ReceiptRecord { return $this->update($operationId, 'unknown', ['errors_json' => $this->encode($errors)]); }
    public function recent(int $limit = 20): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM receipts ORDER BY id DESC LIMIT :limit');
        $statement->bindValue('limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return array_map(fn (array $row): ReceiptRecord => $this->hydrate($row), $statement->fetchAll());
    }
    /** @param array<string,mixed> $fields */
    private function update(string $operationId, string $status, array $fields): ReceiptRecord
    {
        $allowed = ['voucher_number', 'cae', 'cae_expiration', 'response_json', 'errors_json', 'observations_json'];
        $set = ['status = :status', 'updated_at = :updated'];
        $values = ['status' => $status, 'updated' => date(DATE_ATOM), 'operation_id' => $operationId];
        foreach ($fields as $key => $value) { if (in_array($key, $allowed, true)) { $set[] = $key . ' = :' . $key; $values[$key] = $value; } }
        $statement = $this->pdo->prepare('UPDATE receipts SET ' . implode(', ', $set) . ' WHERE operation_id = :operation_id');
        $statement->execute($values);
        if ($statement->rowCount() !== 1) { throw new StorageException('No se encontro la operacion que se intentaba actualizar.'); }
        return $this->required($operationId);
    }
    private function one(string $sql, string $value): ?ReceiptRecord
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['value' => $value]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }
    private function required(string $operationId): ReceiptRecord { return $this->findByOperationId($operationId) ?? throw new StorageException('La operacion no quedo almacenada.'); }
    /** @param array<string,mixed> $row */
    private function hydrate(array $row): ReceiptRecord
    {
        return new ReceiptRecord((string) $row['operation_id'], (string) $row['idempotency_key'], (string) $row['fingerprint'], (string) $row['status'], (int) $row['voucher_type'], (int) $row['sales_point'], (float) $row['amount'], $this->decode($row['request_json']), $row['voucher_number'] === null ? null : (int) $row['voucher_number'], $row['cae'] === null ? null : (string) $row['cae'], $row['cae_expiration'] === null ? null : (string) $row['cae_expiration'], $row['response_json'] === null ? null : $this->decode($row['response_json']), $this->decode($row['errors_json'] ?? '[]'), $this->decode($row['observations_json'] ?? '[]'), (string) $row['created_at'], (string) $row['updated_at']);
    }
    /** @param array<string,mixed>|list<mixed> $value */
    private function encode(array $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
    /** @return array<mixed> */
    private function decode(mixed $value): array { $decoded = json_decode((string) $value, true); return is_array($decoded) ? $decoded : []; }
    private function migrate(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS receipts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            operation_id TEXT NOT NULL UNIQUE,
            idempotency_key TEXT NOT NULL UNIQUE,
            fingerprint TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('pending','processing','authorized','rejected','unknown')),
            voucher_type INTEGER NOT NULL,
            sales_point INTEGER NOT NULL,
            voucher_number INTEGER NULL,
            amount NUMERIC NOT NULL,
            cae TEXT NULL,
            cae_expiration TEXT NULL,
            request_json TEXT NOT NULL,
            response_json TEXT NULL,
            errors_json TEXT NOT NULL DEFAULT '[]',
            observations_json TEXT NOT NULL DEFAULT '[]',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        ); CREATE INDEX IF NOT EXISTS idx_receipts_lookup ON receipts(sales_point,voucher_type,voucher_number); CREATE INDEX IF NOT EXISTS idx_receipts_status ON receipts(status);");
    }
}
