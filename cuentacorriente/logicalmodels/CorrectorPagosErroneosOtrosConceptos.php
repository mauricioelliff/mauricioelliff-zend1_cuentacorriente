<?php

require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';
require_once 'cuentacorriente/logicalmodels/DesasignarCredito.php';

/*
 * 
 */

/**
 * Description of CorrectorPagosErroneos
 *
 * @author mauricio
 */
class CorrectorPagosErroneosOtrosConceptos 
{
    private $_CuentaCorrienteColeccion;
    private $_DesasignacionCredito;
    private $_erroneos_col=[];
    private $_exceptuados_col=[];
    private $_noProcesados=[];
    
    private $_palabraClave;
    private $_motivo;
    
    
    public function __construct( $palabraClave, $motivo ) {
        $this->_CuentaCorrienteColeccion = new CuentaCorrienteColeccion();
        $this->_DesasignacionCredito = new DesasignarCredito();
        
        $this->_palabraClave = $palabraClave;
        $this->_motivo = $motivo;
    }   
    
    public function corregir()
    {
        $this->_erroneos_col = $this->_getErroneos();
        $this->_exceptuados_col = $this->getExceptuados();

        $this->_desasignarCreditos();
        $this->_dejarlosConCoberturaCompleta();
        //$this->_cambiarlesMotivoObservaciones( $erroneos_col );
        $this->_generarUnaNotaDebitoCubierta();

        ver( $this->_erroneos_col, '$erroneos_col' );
        ver( $this->_exceptuados_col, '$exeptuados_col' );
        ver( $this->_noProcesados, '_noProcesados' );
    }
    
    private function _getErroneos()
    {
        $buscar = [ 'observaciones LIKE "'.$this->_palabraClave.'%"',
                    'motivo NOT LIKE "%'.$this->_palabraClave.'%"'     ];
        return $this->_CuentaCorrienteColeccion->obtenerGeneral( $buscar, 'id', 'CuentaCorriente' );
    }
    
    // Encuentra situaciones que provocan una excepcion, 
    // y deberá corregirse a mano.
    private function getExceptuados()
    {
        // 1. Aquellos erroneos que tienen una nota de débito, de igual monto al taller
        // con fecha posterior al ingreso del pago del taller.
        $exeptuados = array();
        foreach( $this->_erroneos_col as $Pago ){
            $buscar = [ 'tipo_operacion'=> 'DEBITO_MANUAL', 
                        'alumnos_id'    => $Pago->getAlumnosId(),
                        'monto <= -'.$Pago->getMonto(),
                        'fecha_hora_de_sistema >= "'.$Pago->getFechaHoraDeSistema().'" '
                        ];
            if( $Debito=$this->_CuentaCorrienteColeccion
                    ->obtenerGeneral( $buscar, 'id', 'CuentaCorriente', false, true ) ){
                $exeptuados[$Debito->getId()]=$Debito;
            }
        }
        return $exeptuados;
    }
    
    private function _desasignarCreditos()
    {
        foreach( $this->_erroneos_col as $Pago ){
            if( key_exists( $Pago->getId(), $this->_exceptuados_col ) ){
                continue;
            } 
            if( !$this->_DesasignacionCredito->procesar( $Pago ) ){
                $this->_noProcesados[]=$Pago;
            }
        }
    }
    
    private function _dejarlosConCoberturaCompleta()
    {
        foreach( $this->_erroneos_col as $Pago ){
            if( key_exists( $Pago->getId(), $this->_exceptuados_col ) ){
                continue;
            } 
            
            $pagoArray = $Pago->convertirEnArray();
            $pagoArray['cobertura'] = $pagoArray['monto'];
            $pagoArray['motivo'] = $this->_motivo;
            $this->_CuentaCorrienteColeccion
                ->modificacionactualizacionGeneral($pagoArray, 'CuentaCorriente');
        }
    }
    
    private function _generarUnaNotaDebitoCubierta()
    {
        foreach( $this->_erroneos_col as $Pago ){
            if( key_exists( $Pago->getId(), $this->_exceptuados_col ) ){
                continue;
            } 
            $values = [ 'monto'             => -($Pago->getMonto()),
                        'cobertura'         => -($Pago->getMonto()),
                        'motivo'            => $this->_motivo,
                        'observaciones'     => $this->_motivo,
                        'alumnos_id'        => $Pago->getAlumnosId(),
                        'tipo_operacion'    => 'DEBITO_AUTOMATICO',
                        'fecha_operacion'   => date('Y-m-d', restaDias($Pago->getFechaOperacion(),1)),
                        'persona_en_caja'   => 'Sistemas',
                        'usuario_nombre'    => 'Sistemas'
                        ];
            $this->_CuentaCorrienteColeccion->altaGeneral( $values, 'CuentaCorriente' );
        }
    }
    
}
