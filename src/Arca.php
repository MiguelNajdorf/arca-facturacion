<?php
declare(strict_types=1);
namespace ArcaFacturacion;
use ArcaFacturacion\Application\InvoiceService;
use ArcaFacturacion\Authentication\WsaaClient;
use ArcaFacturacion\Config\ArcaConfig;
use ArcaFacturacion\Config\ConfigLoader;
use ArcaFacturacion\Infrastructure\ArcaGateway;
use ArcaFacturacion\Infrastructure\RequestMapper;
use ArcaFacturacion\Infrastructure\SoapArcaGateway;
use ArcaFacturacion\Invoice\Invoice;
use ArcaFacturacion\Invoice\InvoiceBuilder;
use ArcaFacturacion\Invoice\InvoiceResult;
use ArcaFacturacion\Invoice\InvoiceValidator;
use ArcaFacturacion\Receipt\ReceiptRepository;
use ArcaFacturacion\Receipt\SQLiteReceiptRepository;
use ArcaFacturacion\Support\Logger;
final class Arca
{
    private function __construct(private readonly ArcaConfig $config, private readonly ArcaGateway $gateway, private readonly InvoiceService $service) {}
    public static function desdeEntorno(?string $projectRoot = null): self
    {
        $root = $projectRoot ?? dirname(__DIR__);
        $config = (new ConfigLoader($root))->load();
        $gateway = new SoapArcaGateway($config, new WsaaClient($config), new RequestMapper());
        $repository = new SQLiteReceiptRepository($config->databasePath());
        return self::crear($config, $gateway, $repository);
    }
    public static function crear(ArcaConfig $config, ArcaGateway $gateway, ReceiptRepository $repository): self
    {
        $mapper = new RequestMapper();
        $logger = new Logger($config->logsPath() . '/arca.log', $config->logLevel);
        return new self($config, $gateway, new InvoiceService($config, $gateway, $repository, new InvoiceValidator(), $mapper, $logger));
    }
    public function facturaA(): InvoiceBuilder { return new InvoiceBuilder(1, 'Factura A'); }
    public function facturaB(): InvoiceBuilder { return new InvoiceBuilder(6, 'Factura B'); }
    public function facturaC(): InvoiceBuilder { return new InvoiceBuilder(11, 'Factura C'); }
    public function notaCreditoA(): InvoiceBuilder { return new InvoiceBuilder(3, 'Nota de credito A'); }
    public function notaCreditoB(): InvoiceBuilder { return new InvoiceBuilder(8, 'Nota de credito B'); }
    public function notaCreditoC(): InvoiceBuilder { return new InvoiceBuilder(13, 'Nota de credito C'); }
    public function emitir(Invoice|InvoiceBuilder $invoice, string $idempotencyKey): InvoiceResult { return $this->service->issue($invoice instanceof InvoiceBuilder ? $invoice->construir() : $invoice, $idempotencyKey); }
    /** @return array<string,mixed> */ public function simular(Invoice|InvoiceBuilder $invoice, bool $advanced = false): array { return $this->service->simulate($invoice instanceof InvoiceBuilder ? $invoice->construir() : $invoice, $advanced); }
    public function recuperar(string $operationId): InvoiceResult { return $this->service->recover($operationId); }
    /** @return array<string,string> */ public function estadoServicios(): array { return $this->gateway->health(); }
    public function ultimoComprobante(int $voucherType): int { return $this->gateway->lastVoucher($this->config->salesPoint, $voucherType); }
    /** @return array<string,mixed>|null */ public function consultarComprobante(int $voucherType, int $number): ?array { return $this->gateway->findVoucher($this->config->salesPoint, $voucherType, $number); }
    /** @return list<array<string,mixed>> */ public function puntosDeVenta(): array { return $this->gateway->salesPoints(); }
    /** @return list<array<string,mixed>> */ public function condicionesIva(string $class = ''): array { return $this->gateway->receiverVatConditions($class); }
    /** @return list<array<string,mixed>> */ public function tiposComprobante(): array { return $this->gateway->voucherTypes(); }
    /** @return list<\ArcaFacturacion\Receipt\ReceiptRecord> */ public function operacionesRecientes(int $limit = 20): array { return $this->service->recent($limit); }
}
