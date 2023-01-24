<?php

require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';
require_once 'cuentacorriente/models/CuentaCorrienteElementoValuadoColeccion.php';
require_once 'admin/models/AuditoriaColeccion.php';

require_once 'extensiones/generales/MiMensajero.php'; 

class PagoDesasignador 
{
    private $_CuentaCorriente_pago;
    private $_CuentaCorriente_deudas;
    private $_detalleAsignaciones;
    private $_CuentaCorrienteColeccion;
    private $_CuentaCorrienteElementoValuadoColeccion;
    
    public function __construct( $CuentaCorrienteColeccion, $pagoId )
    {
        $this->_CuentaCorrienteColeccion = $CuentaCorrienteColeccion;
        $this->_CuentaCorrienteElementoValuadoColeccion = new CuentaCorrienteElementoValuadoColeccion();
        $this->_pagoId = $pagoId;
        
        $this->_CuentaCorriente_pago    = $CuentaCorrienteColeccion->obtenerPorIdGeneral( $pagoId, 'CuentaCorriente' );
        $this->_detalleAsignaciones     = $CuentaCorrienteColeccion->getDetalleDelPago( $pagoId );
        $this->_CuentaCorriente_deudas  = $CuentaCorrienteColeccion->getFacturasDelPago( $this->_CuentaCorriente_pago );
    }
    
    public function desasignar()
    {
        $this->_restarCoberturasAItemsDeuda();
        $this->_restarCoberturaDelPago();
        $this->_eliminarAsignacionesAItemsDeuda();
    }
    
    private function _restarCoberturasAItemsDeuda()
    {
        if( !$this->_CuentaCorriente_deudas ){
            return; // no tiene deudas asociadas en este pago
        }
        foreach( $this->_detalleAsignaciones as $values ){
            $CuentaCorriente = $this->_CuentaCorriente_deudas[ $values['deuda_id'] ];
            $cuentaCorrienteValues = $CuentaCorriente->convertirEnArray();
            // cancelo el monto pagado
            $cuentaCorrienteValues['cobertura']+= $values['pago_asignado'];
            $this->_actualizarCuenta( $cuentaCorrienteValues );
        }
    }
    
    private function _restarCoberturaDelPago()
    {
        $asignados = array_values_recursive( 
                                arrays_getAlgunasKeysArrays( $this->_detalleAsignaciones, 'pago_asignado' )
                            );
        $totalAsignado = array_sum( $asignados );
        $pagoValues = $this->_CuentaCorriente_pago->convertirEnArray();
        $pagoValues['cobertura']-=$totalAsignado;
        // y modificación de su descripción
        $pagoValues['observaciones']= 'Antes asignado a: '.$pagoValues['motivo'].'. '.$pagoValues['observaciones'];
        $pagoValues['motivo']= 'REASIGNADO-';
        $this->_actualizarCuenta( $pagoValues );
    }
    
    private function _actualizarCuenta( $values )
    {
        $this->_CuentaCorrienteColeccion
                ->modificacionactualizacionGeneral( $values, 'CuentaCorriente' );
    }

    private function _eliminarAsignacionesAItemsDeuda()
    {
        $this->_CuentaCorrienteElementoValuadoColeccion
                ->eliminarGeneral( ['cuentas_corrientes_id' => $this->_CuentaCorriente_pago->getId() ] );
    }
    
    
}
