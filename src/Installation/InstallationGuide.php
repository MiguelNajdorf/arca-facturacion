<?php
declare(strict_types=1);
namespace ArcaFacturacion\Installation;
final class InstallationGuide
{
    public const CLI = 'cli';
    public const WEB = 'web';
    /** @param array<string,string>|null $links */
    public function __construct(private readonly ?array $links = null) {}
    public function environmentExplanation(): string
    {
        return <<<'TEXT'
Elegi el ambiente:

[1] Homologacion
    Es el entorno de pruebas.
    No genera comprobantes fiscales reales.
    Sirve para aprender, integrar y verificar que todo funcione.
    Usa certificados y autorizaciones propios de homologacion.

[2] Produccion
    Es el entorno real.
    Los comprobantes emitidos tienen validez fiscal.
    Requiere certificado de produccion, punto de venta Web Services real
    y autorizacion correspondiente dentro de ARCA.

Recomendacion:
Si es tu primera integracion, comenza en homologacion.

Importante:
Un certificado de homologacion no sirve en produccion. Para pasar a
produccion deberas preparar otra clave, otro CSR, otro certificado y una
configuracion separada. arca_facturacion no sobrescribe un ambiente con el otro.
TEXT;
    }
    public function aliasExplanation(): string
    {
        return <<<'TEXT'
Alias descriptivo del certificado

El alias es solamente un nombre para reconocer esta integracion dentro de ARCA.
No es el CUIT, no es el punto de venta y no es una contrasena.

Elegi un nombre claro. Ejemplos: arca-homologacion,
sistema-ventas-pruebas, mi-empresa-wsfe o facturacion-produccion.

"arca-homologacion" es solo un ejemplo. Podes escribir el alias que prefieras.
TEXT;
    }
    /** @param array<string,mixed> $state */
    public function certificateInstructions(array $state, string $interface = self::CLI): string
    {
        $environment = (string) ($state['environment'] ?? 'homologacion');
        $key = (string) ($state['key_path'] ?? 'storage/certificados/' . $environment . '/privada.key');
        $csr = (string) ($state['csr_path'] ?? 'storage/certificados/' . $environment . '/solicitud.csr');
        $alias = (string) ($state['alias'] ?? 'arca');
        $links = $this->officialLinks();
        if ($environment === 'homologacion') {
            return "PASO 3 DE 6 - Obtener el certificado de homologacion\n\n" .
                "Ya generamos dos archivos:\n\nClave privada:\n{$key}\n\nSolicitud de certificado:\n{$csr}\n\n" .
                "La clave privada queda unicamente en tu computadora.\n\n" .
                "No la subas a ARCA.\nNo la compartas.\nNo la envies por correo.\nNo la subas a GitHub.\n\n" .
                "El archivo que si debes utilizar en ARCA es:\n\n{$csr}\n\n" .
                "Ese archivo contiene la solicitud que ARCA utilizara para generar tu certificado digital de homologacion.\n\n" .
                "Ahora debes realizar estos pasos:\n\n" .
                "1. Ingresa al sitio oficial de ARCA con tu CUIT y clave fiscal.\n\n" .
                "2. Abri el servicio WSASS.\n\n" .
                "3. Si WSASS no aparece entre tus servicios, adherilo desde el Administrador de Relaciones de Clave Fiscal.\n\n" .
                "4. Dentro de WSASS, elegi la opcion \"Nuevo Certificado\".\n\n" .
                "5. En el campo de nombre simbolico o alias, escribi exactamente el alias configurado durante esta instalacion:\n\n{$alias}\n\n" .
                "   Este valor es el alias real que elegiste; no lo reemplaces por un alias de ejemplo.\n\n" .
                "6. Abri el archivo solicitud.csr con un editor de texto plano, por ejemplo Bloc de notas o Visual Studio Code:\n\n{$csr}\n\n" .
                "7. Copia todo el contenido del archivo, incluyendo estas dos lineas:\n\n" .
                "   -----BEGIN CERTIFICATE REQUEST-----\n\n   y\n\n   -----END CERTIFICATE REQUEST-----\n\n" .
                "8. Pega el contenido completo en el campo de solicitud PKCS#10 o CSR que muestre WSASS.\n\n" .
                "9. Confirma la creacion del certificado.\n\n" .
                "10. Descarga el certificado generado por ARCA.\n\n" .
                "11. Guardalo en una ubicacion facil de encontrar.\n\n" .
                "El archivo descargado puede tener extension:\n\n- .crt\n- .cer\n- .pem\n\n" .
                "No cambies manualmente su contenido.\n\n" .
                "Manual oficial para crear el certificado en WSASS: {$links['wsass_certificate']}\n" .
                "La interfaz de ARCA puede cambiar. Si una pantalla no coincide, consulta la documentacion oficial: {$links['certificates']}\n\n" .
                $this->certificateNextAction($interface);
        }
        $specific = $environment === 'homologacion'
            ? "En homologacion usa WSASS. Busca la opcion oficial \"Nuevo Certificado\".\nCompleta el nombre simbolico con tu alias ({$alias}) y presenta el CSR.\nManual: {$links['wsass_certificate']}"
            : "En produccion ingresa con clave fiscal a \"Administracion de Certificados Digitales\".\nSelecciona al representado correcto, busca \"Agregar Alias\", escribe {$alias}\ny sube el CSR. Guia oficial: {$links['production_certificate']}";
        return "PASO 3 DE 6 - Obtener el certificado de {$environment}\n\n" .
            "Ya generamos dos archivos:\n\nClave privada:\n{$key}\n\nSolicitud de certificado:\n{$csr}\n\n" .
            "La clave privada queda unicamente en tu computadora. Conservala y hace un respaldo seguro.\n" .
            "No la subas a ARCA. No la compartas. No la envies por correo. No la subas a GitHub.\n\n" .
            "El archivo que si debes usar en ARCA es el CSR:\n{$csr}\n\n" .
            "Ese archivo no es secreto: contiene la solicitud para que ARCA emita el certificado.\n\n{$specific}\n\n" .
            "El resultado esperado es un certificado descargable, normalmente .crt, .cer o .pem.\n" .
            "La interfaz puede cambiar; usa la documentacion oficial: {$links['certificates']}\n\n" .
            $this->certificateNextAction($interface);
    }
    /** @param array<string,mixed> $state */
    public function authorizationInstructions(array $state, string $interface = self::CLI): string
    {
        $environment = (string) ($state['environment'] ?? 'homologacion');
        $alias = (string) ($state['alias'] ?? 'arca');
        $cuit = (string) ($state['cuit'] ?? '');
        $links = $this->officialLinks();
        $specific = $environment === 'homologacion'
            ? "En WSASS busca la opcion oficial \"Crear Autorizacion a Servicio\".\nElegi el alias {$alias}, completa el CUIT representado terminado en " . substr($cuit, -4) . "\ny selecciona el servicio de facturacion electronica/WSFE disponible.\nGuia oficial: {$links['wsass_authorization']}"
            : "En produccion usa \"Administrador de Relaciones de Clave Fiscal\".\nCrea una nueva relacion para el Webservice de Facturacion Electronica y\nselecciona como representante el computador fiscal/alias {$alias}.\nGuia oficial: {$links['production_authorization']}";
        return "PASO 4 DE 6 - Autorizar el servicio de facturacion\n\n" .
            "El certificado ya esta instalado, pero por si solo no concede permiso para facturar.\n" .
            "Debes relacionar el alias, el CUIT representado y el servicio WSFE en el ambiente correcto.\n\n{$specific}\n\n" .
            "Resultado esperado: ARCA debe dejar creada la autorizacion entre el servicio y el alias.\n" .
            $this->authorizationNextAction($interface);
    }
    /** @param array<string,mixed> $state */
    public function validationInstructions(array $state, string $interface = self::CLI): string
    {
        $environment = (string) ($state['environment'] ?? 'homologacion');
        $action = $interface === self::WEB
            ? "La autenticacion online ya fue comprobada en el paso anterior.\n\nEn el formulario que aparece debajo, indica un importe de prueba y presiona\n\"Simular y finalizar\". La simulacion valida los datos localmente y no envia nada a ARCA.\n\nSi cerras el navegador, volve a esta misma URL para retomar."
            : "Primero ejecuta: php bin/arca doctor --online\nEl resultado esperado es que WSAA, WSFE y el punto de venta aparezcan como OK.\n\nDespues ejecuta: php bin/arca factura:simular --total=15000\nLa simulacion valida los datos localmente y no envia nada a ARCA.\n\nSi interrumpis el proceso, usa instalacion:continuar.";
        return "PASO 5 DE 6 - Validar y simular\n\n{$action}\n\nAmbiente activo: {$environment}.";
    }
    /** @param array<string,mixed> $state */
    public function firstEmissionInstructions(array $state): string
    {
        $environment = (string) ($state['environment'] ?? 'homologacion');
        $warning = $environment === 'produccion'
            ? 'ATENCION: en produccion este comando crea un comprobante fiscal real.'
            : 'En homologacion la emision sirve para comprobar el recorrido completo sin validez fiscal.';
        return "PASO 6 DE 6 - Primera emision controlada\n\n{$warning}\n\n" .
            "Revisa primero la simulacion y luego ejecuta:\n" .
            "php bin/arca factura:probar --total=15000\n\n" .
            "El comando vuelve a mostrar el resumen y pide una confirmacion explicita antes de enviar.\n" .
            "El resultado esperado es un estado autorizado con CAE, o un rechazo explicado sin ocultar el siguiente paso.";
    }
    public function productionInstructions(): string
    {
        $links = $this->officialLinks();
        return "Preparar produccion no significa cambiar una variable. El flujo crea una configuracion separada.\n\n" .
            "Necesitas: nueva clave y CSR de produccion; certificado emitido por ARCA para produccion;\n" .
            "autorizacion del Webservice de Facturacion Electronica; punto de venta Web Services real;\n" .
            "doctor --online correcto y una prueba controlada.\n\n" .
            "Homologacion queda en storage/certificados/homologacion/. Produccion queda en\n" .
            "storage/certificados/produccion/. Bases, tickets, locks y logs tambien quedan separados.\n\n" .
            "Certificados: {$links['certificates']}\nFacturacion y puntos de venta: {$links['billing']}";
    }
    /** @return array<string,string> */
    public function officialLinks(): array
    {
        return $this->links ?? (new OfficialLinks())->all();
    }
    private function certificateNextAction(string $interface): string
    {
        if ($interface === self::WEB) {
            return "PAUSA: este paso ocurre fuera de arca_facturacion. Cuando tengas el certificado, volve a esta pagina.\n\n" .
                "En el formulario que aparece debajo, presiona \"Seleccionar archivo\", elige el certificado descargado (.crt, .cer o .pem) y luego presiona \"Guardar y continuar\".\n\n" .
                "No selecciones la clave privada ni el archivo solicitud.csr.";
        }
        return "PAUSA: este paso ocurre fuera de arca_facturacion. Cuando tengas el certificado, volve a esta consola y ejecuta:\n\n" .
            "php bin/arca certificado:instalar \"C:\\ruta\\al\\certificado.crt\"\n\n" .
            "Despues podes continuar con:\n\nphp bin/arca instalacion:continuar";
    }
    private function authorizationNextAction(string $interface): string
    {
        if ($interface === self::WEB) {
            return "Cuando termines, volve a esta pagina y presiona \"Comprobar con ARCA y continuar\".\n\n" .
                "arca_facturacion intentara autenticarse y solo marcara la autorizacion y la conexion como verificadas si ARCA responde correctamente.";
        }
        return "Luego volve a la consola y ejecuta: php bin/arca doctor --online\n\n" .
            "doctor intentara autenticarse. Si funciona, arca_facturacion marcara automaticamente la\n" .
            "autorizacion y la conexion como verificadas. No hace falta marcar nada a mano.";
    }
}
