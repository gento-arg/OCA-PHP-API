<?php
/**
 * $Id$
 *
 * Copyright (c) 2015, Juancho Rossi.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * OCA Express y OCA Express Pak son propiedad de Organización Coordinadora Argentina (OCA)
 */

/**
 * OCA PHP API Class
 *
 * @link https://github.com/gento-arg/OCA-PHP-API
 * @version 0.1.1
 */

class Oca
{
    const VERSION = '1.0';
    protected $webservice_url = 'webservice.oca.com.ar';

    const FRANJA_HORARIA_8_17HS = 1;
    const FRANJA_HORARIA_8_12HS = 2;
    const FRANJA_HORARIA_14_17HS = 3;

    const URL_TARIFAR_CORPORATIVO = '/epak_tracking/Oep_TrackEPak.asmx/Tarifar_Envio_Corporativo';

    private $Cuit;
    private $Operativa;

    // ========================================================================

    public function __construct($cuit = '', $operativa = '')
    {
        $this->Cuit = trim($cuit);
        $this->Operativa = trim($operativa);
    }

    public function getOperativa()
    {
        return $this->Operativa;
    }

    public function setOperativa($operativa)
    {
        $this->Operativa = $operativa;
    }

    public function getCuit($cuit)
    {
        return $this->Cuit;
    }

    public function setCuit($cuit)
    {
        $this->Cuit = $cuit;
    }

    protected function mapXml($xml, $map)
    {
        $mapa = [];
        $retorno = [];

        array_walk($map, function ($value, $key) use (&$mapa) {
            if (is_numeric($key)) {
                $key = $value;
            }
            $mapa[$key] = $value;
        });

        foreach ($xml as $item) {
            $data = [];
            array_walk($mapa, function ($value, $key) use ($item, &$data) {
                $data[$key] = $item->getElementsByTagName($value)->item(0)->nodeValue;
            });
            $retorno[] = $data;
        }

        return $retorno;
    }

    // =========================================================================

    /**
     * Sets the useragent for PHP to use
     *
     * @return string
     */
    public function setUserAgent()
    {
        return 'OCA-PHP-API ' . self::VERSION . ' - github.com/nerioespina/OCA-PHP-API';
    }

    protected function sendPost($data, $url)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_URL => "{$this->webservice_url}{$url}",
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $dom = new DOMDocument();
        $body = curl_exec($ch);
        @$dom->loadXML($body);
        $xpath = new DOMXpath($dom);

        return @$xpath->query("//NewDataSet/Table");
    }

    // =========================================================================

    /**
     * Tarifar un Envío Corporativo
     *
     * @param string $PesoTotal
     * @param string $VolumenTotal
     * @param string $CodigoPostalOrigen
     * @param string $CodigoPostalDestino
     * @param string $CantidadPaquetes
     * @param string $ValorDeclarado
     * @return array $e_corp conteniendo el tipo de tarifador y el precio del envío.
     */
    public function tarifarEnvioCorporativo(
        $PesoTotal,
        $VolumenTotal,
        $CodigoPostalOrigen,
        $CodigoPostalDestino,
        $CantidadPaquetes,
        $ValorDeclarado
    ) {
        $_query_string = [
            'PesoTotal' => $PesoTotal,
            'VolumenTotal' => $VolumenTotal,
            'CodigoPostalOrigen' => $CodigoPostalOrigen,
            'CodigoPostalDestino' => $CodigoPostalDestino,
            'CantidadPaquetes' => $CantidadPaquetes,
            'ValorDeclarado' => $ValorDeclarado,
            'Cuit' => $this->Cuit,
            'Operativa' => $this->Operativa,
        ];

        $result = $this->sendPost($_query_string, self::URL_TARIFAR_CORPORATIVO);

        $retorno = $this->mapXml($result, [
            'Tarifador',
            'Precio',
            'Ambito',
            'PlazoEntrega',
            'Adicional',
            'Total',
        ]);

        if (count($retorno) > 0) {
            return (object) array_shift($retorno);
        }

        return null;
    }

    // =========================================================================

    /**
     * Dado el CUIT del cliente con un rango de fechas se devuelve una lista con todos los Envíos realizados en dicho período
     *
     * @param string $fechaDesde Fecha en formato DD-MM-YYYY (sin documentacion oficial)
     * @param string $fechaHasta Fecha en formato DD-MM-YYYY (sin documentacion oficial)
     * @return array $envios Contiene los valores NroProducto y NumeroEnvio
     */
    public function listEnvios($fechaDesde, $fechaHasta)
    {
        $_query_string = ['FechaDesde' => $fechaDesde,
            'FechaHasta' => $fechaHasta,
            'Cuit' => $this->Cuit,
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($_query_string),
            CURLOPT_URL => "{$this->webservice_url}/epak_tracking/Oep_TrackEPak.asmx/List_Envios",
            CURLOPT_FOLLOWLOCATION => true]);

        $dom = new DOMDocument();
        @$dom->loadXML(curl_exec($ch));
        $xpath = new DOMXpath($dom);

        $envios = [];
        foreach (@$xpath->query("//NewDataSet/Table") as $envio_corporativo) {
            $envios[] = ['NroProducto' => $envio_corporativo->getElementsByTagName('NroProducto')->item(0)->nodeValue,
                'NumeroEnvio' => $envio_corporativo->getElementsByTagName('NumeroEnvio')->item(0)->nodeValue,
            ];
        }

        return $envios;

    }

    // =========================================================================

    /**
     * Dado un envío se devuelven todos los eventos. En desarrollo, por falta de
     * documentación oficial se desconoce su comportamiento.
     *
     * @param integer $pieza
     * @param integer $nroDocumentoCliente
     * @return array $envios Contiene los valores NroProducto y NumeroEnvio
     */
    public function trackingPieza($pieza = '', $nroDocumentoCliente = '')
    {
        $_query_string = [
            'Pieza' => $pieza,
            'NroDocumentoCliente' => $nroDocumentoCliente,
            'Cuit' => $this->Cuit,
        ];

        $result = $this->sendPost($_query_string, '/epak_tracking/Oep_TrackEPak.asmx/Tracking_Pieza');

        return $this->mapXml($result, [
            'NumeroEnvio',
            'Motivo' => 'Descripcion_Motivo',
            'Estado' => 'Desdcripcion_Estado',
            'Sucursal' => 'SUC',
            'Fecha' => 'fecha',
        ]);
    }

    // =========================================================================

    /**
     * Devuelve todos los Centros de Imposición existentes cercanos al CP
     *
     * @param integer $CP Código Postal
     * @return array $c_imp con informacion de los centros de imposicion
     */
    public function getCentrosImposicionPorCP($CP = null)
    {
        if (!$CP) {
            return;
        }

        $ch = curl_init();

        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'CodigoPostal=' . (int) $CP,
            CURLOPT_URL => "{$this->webservice_url}/oep_tracking/Oep_Track.asmx/GetCentrosImposicionPorCP",
            CURLOPT_FOLLOWLOCATION => true]);

        $dom = new DOMDocument();
        @$dom->loadXML(curl_exec($ch));
        $xpath = new DOMXpath($dom);

        $c_imp = [];
        foreach (@$xpath->query("//NewDataSet/Table") as $ci) {
            $c_imp[] = $this->loadFields($ci, [
                'idCentroImposicion', 'IdSucursalOCA', 'Sigla', 'Descripcion',
                'Calle', 'Numero', 'Torre', 'Piso', 'Depto', 'Localidad',
                'IdProvincia', 'idCodigoPostal', 'Telefono', 'eMail',
                'Provincia', 'CodigoPostal',
            ]);
        }

        return $c_imp;
    }

    public function loadFields($ci, $fields)
    {
        $return = [];
        foreach ($fields as $field) {
            $valor = null;

            $item = $ci->getElementsByTagName($field)->item(0);
            if ($item != null) {
                $valor = $item->nodeValue;
            }

            $return[$field] = $valor;
        }

        return $return;
    }

    // =========================================================================

    /**
     * Devuelve todos los Centros de Imposición existentes
     *
     * @return array $c_imp con informacion de los centros de imposicion
     */
    public function getCentrosImposicion()
    {
        $ch = curl_init();

        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_URL => "{$this->webservice_url}/oep_tracking/Oep_Track.asmx/GetCentrosImposicion",
            CURLOPT_FOLLOWLOCATION => true]);

        $dom = new DOMDocument();
        @$dom->loadXML(curl_exec($ch));
        $xpath = new DOMXpath($dom);

        $c_imp = [];
        foreach (@$xpath->query("//NewDataSet/Table") as $ci) {
            $c_imp[] = $this->loadFields($ci, [
                'idCentroImposicion', 'Sigla', 'Descripcion',
                'Calle', 'Numero', 'Piso', 'Localidad', 'CodigoPostal',
            ]);
        }

        return $c_imp;
    }

    // =========================================================================

    /**
     * Obtiene listado de provincias
     *
     * @return array $provincias
     */
    public function getProvincias()
    {
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_URL => "{$this->webservice_url}/oep_tracking/Oep_Track.asmx/GetProvincias",
            CURLOPT_FOLLOWLOCATION => true]);
        $dom = new DOMDocument();
        $dom->loadXml(curl_exec($ch));
        $xpath = new DOMXPath($dom);

        $provincias = [];
        foreach (@$xpath->query("//Provincias/Provincia") as $provincia) {
            $provincias[] = ['id' => $provincia->getElementsByTagName('IdProvincia')->item(0)->nodeValue,
                'provincia' => $provincia->getElementsByTagName('Descripcion')->item(0)->nodeValue,
            ];
        }

        return $provincias;
    }

    // =========================================================================

    /**
     * Lista las localidades de una provincia
     *
     * @param integer $idProvincia
     * @return array $localidades
     */
    public function getLocalidadesByProvincia($idProvincia)
    {
        $_query_string = ['idProvincia' => $idProvincia];

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query($_query_string),
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_URL => "{$this->webservice_url}/oep_tracking/Oep_Track.asmx/GetLocalidadesByProvincia",
            CURLOPT_FOLLOWLOCATION => true]);
        $dom = new DOMDocument();
        $dom->loadXml(curl_exec($ch));
        $xpath = new DOMXPath($dom);

        $localidades = [];
        foreach (@$xpath->query("//Localidades/Provincia") as $provincia) {
            $localidades[] = ['localidad' => $provincia->getElementsByTagName('Nombre')->item(0)->nodeValue];
        }

        return $localidades;
    }

    // =========================================================================

    /**
     * Ingresa un envio al carrito de envios
     *
     * @param string $usuarioEPack: Usuario de ePak
     * @param string $passwordEPack: Password de acceso a ePak
     * @param string $xmlDatos: XML con los datos de Retiro, Entrega y características de los paquetes.
     * @param boolean $confirmarRetiro: Si se envía False, el envío quedará alojado en el
     *                                  Carrito de Envíos de ePak a la espera de la confirmación del mismo.
     *                                  Si se envía True, la confirmación será instantánea.
     * @return array $resumen
     */
    public function ingresoOR($usuarioEPack, $passwordEPack, $xmlRetiro, $confirmarRetiro = false, $diasRetiro = 1, $franjaHoraria = Oca::FRANJA_HORARIA_8_17HS)
    {
        $_query_string = [
            'usr' => $usuarioEPack,
            'psw' => $passwordEPack,
            'XML_Retiro' => $xmlRetiro,
            'ConfirmarRetiro' => $confirmarRetiro ? 'true' : 'false',
            'DiasRetiro' => $diasRetiro,
            'FranjaHoraria' => $franjaHoraria,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query($_query_string),
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_URL => "{$this->webservice_url}/oep_tracking/Oep_Track.asmx/IngresoOR",
            CURLOPT_FOLLOWLOCATION => true]);

        $xml = curl_exec($ch);
        file_put_contents('ingresoOr.xml', $xml);

        $dom = new DOMDocument();
        @$dom->loadXml($xml);
        $xpath = new DOMXPath($dom);

        $xml_detalle_ingresos = @$xpath->query("//Resultado/DetalleIngresos ");
        $xml_resumen = @$xpath->query("//Resultado/Resumen ")->item(0);

        $detalle_ingresos = [];

        foreach ($xml_detalle_ingresos as $item) {
            $detalle_ingresos[] = [
                'Operativa' => $item->getElementsByTagName('Operativa')->item(0)->nodeValue,
                'OrdenRetiro' => $item->getElementsByTagName('OrdenRetiro')->item(0)->nodeValue,
                'NumeroEnvio' => $item->getElementsByTagName('NumeroEnvio')->item(0)->nodeValue,
                'Remito' => $item->getElementsByTagName('Remito')->item(0)->nodeValue,
                'Estado' => $item->getElementsByTagName('Estado')->item(0)->nodeValue,
                'sucursalDestino' => $item->getElementsByTagName('sucursalDestino')->item(0)->nodeValue,
            ];
        }

        $resumen = [
            'CodigoOperacion' => $xml_resumen->getElementsByTagName('CodigoOperacion')->item(0)->nodeValue,
            'FechaIngreso' => $xml_resumen->getElementsByTagName('FechaIngreso')->item(0)->nodeValue,
            'MailUsuario' => $xml_resumen->getElementsByTagName('mailUsuario')->item(0)->nodeValue,
            'CantidadRegistros' => $xml_resumen->getElementsByTagName('CantidadRegistros')->item(0)->nodeValue,
            'CantidadIngresados' => $xml_resumen->getElementsByTagName('CantidadIngresados')->item(0)->nodeValue,
            'CantidadRechazados' => $xml_resumen->getElementsByTagName('CantidadRechazados')->item(0)->nodeValue,
        ];

        $resultado = ['detalleIngresos' => $detalle_ingresos, 'resumen' => $resumen];

        return $resultado;
    }

    // =========================================================================

    /**
     * Ingresa un envio al carrito de envios
     *
     * @param string $usuarioEPack: Usuario de ePak
     * @param string $passwordEPack: Password de acceso a ePak
     * @param string $xmlDatos: XML con los datos de Retiro, Entrega y características de los paquetes.
     * @param boolean $confirmarRetiro: Si se envía False, el envío quedará alojado en el
     *                                  Carrito de Envíos de ePak a la espera de la confirmación del mismo.
     *                                  Si se envía True, la confirmación será instantánea.
     * @return array $resumen
     */
    public function ingresoORMultiplesRetiros($usuarioEPack, $passwordEPack, $xmlDatos, $confirmarRetiro = false)
    {
        $_query_string = [
            'usr' => $usuarioEPack,
            'psw' => $passwordEPack,
            'xml_Datos' => $xmlDatos,
            'ConfirmarRetiro' => $confirmarRetiro ? 'true' : 'false',
            'ArchivoCliente' => '',
            'ArchivoProceso' => '',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query($_query_string),
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_URL => "{$this->webservice_url}/epak_tracking/Oep_TrackEPak.asmx/IngresoORMultiplesRetiros",
            CURLOPT_FOLLOWLOCATION => true]);

        $xml = curl_exec($ch);
        file_put_contents('ingresoORMultiplesRetiros.xml', $xml);

        $dom = new DOMDocument();
        @$dom->loadXml($xml);
        $xpath = new DOMXPath($dom);

        $xml_detalle_ingresos = @$xpath->query("//Resultado/DetalleIngresos ");
        $xml_resumen = @$xpath->query("//Resultado/Resumen ")->item(0);

        $detalle_ingresos = [];

        foreach ($xml_detalle_ingresos as $item) {
            $detalle_ingresos[] = [
                'Operativa' => $item->getElementsByTagName('Operativa')->item(0)->nodeValue,
                'OrdenRetiro' => $item->getElementsByTagName('OrdenRetiro')->item(0)->nodeValue,
                'NumeroEnvio' => $item->getElementsByTagName('NumeroEnvio')->item(0)->nodeValue,
                'Remito' => $item->getElementsByTagName('Remito')->item(0)->nodeValue,
                'Estado' => $item->getElementsByTagName('Estado')->item(0)->nodeValue,
                'sucursalDestino' => $item->getElementsByTagName('sucursalDestino')->item(0)->nodeValue,
            ];
        }

        $resumen = [
            'CodigoOperacion' => $xml_resumen->getElementsByTagName('CodigoOperacion')->item(0)->nodeValue,
            'FechaIngreso' => $xml_resumen->getElementsByTagName('FechaIngreso')->item(0)->nodeValue,
            'MailUsuario' => $xml_resumen->getElementsByTagName('mailUsuario')->item(0)->nodeValue,
            'CantidadRegistros' => $xml_resumen->getElementsByTagName('CantidadRegistros')->item(0)->nodeValue,
            'CantidadIngresados' => $xml_resumen->getElementsByTagName('CantidadIngresados')->item(0)->nodeValue,
            'CantidadRechazados' => $xml_resumen->getElementsByTagName('CantidadRechazados')->item(0)->nodeValue,
        ];

        $resultado = ['detalleIngresos' => $detalle_ingresos, 'resumen' => $resumen];

        return $resultado;
    }

    // =========================================================================

    /**
     * Obtiene los centros de costo por operativa
     *
     * @param string $cuit
     * @param string $operativa
     * @param boolean $confirmarRetiro: Si se envía False, el envío quedará alojado en el
     *                                  Carrito de Envíos de ePak a la espera de la confirmación del mismo.
     *                                  Si se envía True, la confirmación será instantánea.
     * @return array $centros
     */
    public function getCentroCostoPorOperativa($cuit, $operativa)
    {
        $_query_string = [
            'CUIT' => $cuit,
            'Operativa' => $operativa,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query($_query_string),
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_URL => "{$this->webservice_url}/oep_tracking/Oep_Track.asmx/GetCentroCostoPorOperativa",
            CURLOPT_FOLLOWLOCATION => true]);

        $dom = new DOMDocument();
        @$dom->loadXml(curl_exec($ch));
        $xpath = new DOMXPath($dom);

        $centros = [];
        foreach (@$xpath->query("//NewDataSet/Table") as $centro) {
            $centros[] = [
                'NroCentroCosto' => $centro->getElementsByTagName('NroCentroCosto')->item(0)->nodeValue,
                'Solicitante' => $centro->getElementsByTagName('Solicitante')->item(0)->nodeValue,
                'CalleRetiro' => $centro->getElementsByTagName('CalleRetiro')->item(0)->nodeValue,
                'NumeroRetiro' => $centro->getElementsByTagName('NumeroRetiro')->item(0)->nodeValue,
                'PisoRetiro' => $centro->getElementsByTagName('PisoRetiro')->item(0)->nodeValue,
                'DeptoRetiro' => $centro->getElementsByTagName('DeptoRetiro')->item(0)->nodeValue,
                'LocalidadRetiro' => $centro->getElementsByTagName('LocalidadRetiro')->item(0)->nodeValue,
                'CodigoPostal' => $centro->getElementsByTagName('codigopostal')->item(0)->nodeValue,
                'TelContactoRetiro' => $centro->getElementsByTagName('TelContactoRetiro')->item(0)->nodeValue,
                'EmaiContactolRetiro' => $centro->getElementsByTagName('EmaiContactolRetiro')->item(0)->nodeValue,
                'ContactoRetiro' => $centro->getElementsByTagName('ContactoRetiro')->item(0)->nodeValue,
            ];
        }

        return $centros;
    }

    // =========================================================================

    /**
     * Anula una orden generada
     *
     * @param string $user
     * @param string $pass
     * @param string $IdOrdenRetiro: Nro. de Orden de Retiro/Admisión
     *
     * @return array $centros
     */
    public function anularOrdenGenerada($user, $pass, $IdOrdenRetiro)
    {
        $_query_string = [
            'Usr' => $user,
            'Psw' => $pass,
            'IdOrdenRetiro' => $IdOrdenRetiro,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query($_query_string),
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_URL => "{$this->webservice_url}/epak_tracking/Oep_TrackEPak.asmx/AnularOrdenGenerada",
            CURLOPT_FOLLOWLOCATION => true]);

        $xml = curl_exec($ch);
        file_put_contents('anularOrdenGenerada.xml', $xml);

        $dom = new DOMDocument();
        @$dom->loadXml($xml);
        $xpath = new DOMXPath($dom);

        $centros = [];
        foreach (@$xpath->query("//NewDataSet/Table") as $centro) {
            $centros[] = [
                'IdResult' => $centro->getElementsByTagName('IdResult')->item(0)->nodeValue,
                'Mensaje' => $centro->getElementsByTagName('Mensaje')->item(0)->nodeValue,
            ];
        }

        return $centros;
    }

    // =========================================================================

    /**
     * Lista los envios
     *
     * @param string $cuit: CUIT del cliente [con guiones]
     * @param string $fechaDesde: DD-MM-AAAA
     * @param string $fechaHasta: DD-MM-AAAA
     *
     * @return array $envios
     */
    public function list_Envios($cuit, $fechaDesde = '01-01-2015', $fechaHasta = '01-01-2050')
    {
        $_query_string = [
            'cuit' => $cuit,
            'FechaDesde' => $fechaDesde,
            'FechaHasta' => $fechaHasta,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query($_query_string),
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_URL => "{$this->webservice_url}/epak_tracking/Oep_TrackEPak.asmx/List_Envios",
            CURLOPT_FOLLOWLOCATION => true]);

        $dom = new DOMDocument();
        @$dom->loadXml(curl_exec($ch));
        $xpath = new DOMXPath($dom);

        $envios = [];
        foreach (@$xpath->query("//NewDataSet/Table") as $envio) {
            $envios[] = [
                'NroProducto' => $envio->getElementsByTagName('NroProducto')->item(0)->nodeValue,
                'NumeroEnvio' => $envio->getElementsByTagName('NumeroEnvio')->item(0)->nodeValue,
            ];
        }

        return $envios;
    }

    // =========================================================================

    /**
     * Obtiene las etiquetas en formato HTML.
     *
     * @param string $IdOrdenRetiro
     * @param string $NroEnvio
     *
     * @return string $html
     */
    public function getHtmlDeEtiquetasPorOrdenOrNumeroEnvio($IdOrdenRetiro, $NroEnvio = '')
    {
        $_query_string = [
            'IdOrdenRetiro' => $IdOrdenRetiro,
            'NroEnvio' => $NroEnvio,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query($_query_string),
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_URL => "{$this->webservice_url}/oep_tracking/Oep_Track.asmx/GetHtmlDeEtiquetasPorOrdenOrNumeroEnvio",
            CURLOPT_FOLLOWLOCATION => true]);

        return curl_exec($ch);
    }

    // =========================================================================

    /**
     * Obtiene las etiquetas en formato PDF.
     *
     * @param string $IdOrdenRetiro
     * @param string $NroEnvio
     * @param boolean $LogisticaInversa
     *
     * @return string $pdf
     */
    public function getPDFDeEtiquetasPorOrdenOrNumeroEnvio($IdOrdenRetiro, $NroEnvio = '', $LogisticaInversa = false)
    {
        $_query_string = [
            'IdOrdenRetiro' => $IdOrdenRetiro,
            'NroEnvio' => $NroEnvio,
            'LogisticaInversa' => $LogisticaInversa ? 'true' : 'false',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query($_query_string),
            CURLOPT_USERAGENT => $this->setUserAgent(),
            CURLOPT_URL => "{$this->webservice_url}/oep_tracking/Oep_Track.asmx/GetPDFDeEtiquetasPorOrdenOrNumeroEnvio",
            CURLOPT_FOLLOWLOCATION => true]);

        return base64_decode(curl_exec($ch));
    }

}
