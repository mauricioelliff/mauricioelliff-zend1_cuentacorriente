<?php

/*
 * 
 */

require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';
// require_once 'cuentacorriente/models/CuentaCorrienteElementoValuadoColeccion.php';
require_once 'admin/models/AuditoriaColeccion.php';

require_once 'extensiones/generales/MiMensajero.php'; 


/**
 * - Desasignar el pago
 * - Marcar el pago como anulado
 * - Crear el nuevo pago
 * - Crear las asignaciones nuevas
 * 
 * INPUT 
 * $cuentaCorrienteId
 * 
 */
class PagoAnulador
{
    private $_cuentaCorrienteId;
    private $_cuentaCorrienteColeccion;
    
    public function __construct( $cuentaCorrienteId )
    {
        $this->_cuentaCorrienteId = $cuentaCorrienteId;
        $this->_cuentaCorrienteColeccion = new CuentaCorrienteColeccion();
    }
    
    public function anular()
    {
        $this->_restarCoberturas();
        $this->_eliminarAsignaciones();
        $asignacionesArray = $this->cuentaCorrienteColeccion
                                    ->getDetalleDelPago( $this->_cuentaCorrienteId );
        
    }
    
}
