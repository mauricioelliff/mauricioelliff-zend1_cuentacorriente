<?php
/*
 * Sirve para borrar un item de deuda o débito mal hecho.
 * 
 */

require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';
require_once 'cuentacorriente/models/CuentaCorrienteElementoValuadoColeccion.php';
require_once 'admin/models/AuditoriaColeccion.php';
/*
require_once 'cuentacorriente/logicalmodels/FuncionesSobreEVAs.php';
require_once 'cuentacorriente/logicalmodels/CoberturaOperaciones.php';
require_once 'cuentacorriente/logicalmodels/ComprobanteDesdeAjnaCentros.php';
require_once 'cuentacorriente/logicalmodels/VerificadorCuentaCorriente.php';
require_once 'admin/models/SedeCursoxanioAlumnoColeccion.php';
require_once 'admin/models/AlumnoColeccion.php';
 */
require_once 'extensiones/generales/MiMensajero.php'; 

/**
 * Description of EliminarCredito
 *
 * @author mauricio
 */
class EliminarDebito {
    
    private $_cuentaCorrienteColeccion;
    //private $_funcionesSobreEVAs;
    //private $_coberturaOperaciones;
    //private $_verificadorCuentaCorriente;
    private $_auditoriaColeccion;
    private $_MiMensajero;
    
    public function __construct() 
    {
        $this->_cuentaCorrienteColeccion = new CuentaCorrienteColeccion();
        $this->_CuentaCorrienteElementoValuadoColeccion = new CuentaCorrienteElementoValuadoColeccion();
        $this->_auditoriaColeccion = new AuditoriaColeccion();
        $this->_MiMensajero = new MiMensajero();
    }
    
    
    /*
     * 
     * INPUT
     * $CuentaCorriente <object CuentaCorriente> representa la row a eliminar.
     * 
     * 
     * Funcionamiento:
     *  - identifica su ctacte_id, y la cobertura que aplico 
     *  - identifica a que ev ID corresponde( en cuentascorrientes_elementosvaluados )
     *  - Se verifica que las coberturas a corregir, coincidan con el total del pago erróneo.
     *  - Se disminuye las coberturas de esas pagos.
     *  - Se elimina el débito erróneo de ctasctes, y por cascada de ctasctes evaluados
     */
    public function procesar( CuentaCorriente $CuentaCorriente )
    {
        $CuentaCorrienteElementoValuado = 
                getPrimero( $this->_getCuentaCorrienteElementosValuados( $CuentaCorriente ) );
        
        $this->_disminuirCoberturaDeSuPago( $CuentaCorriente, $CuentaCorrienteElementoValuado );
        $this->_cuentaCorrienteColeccion->eliminarGeneral( 'id='.$CuentaCorriente->getId() );
        return true;
    }
    
    
    /*
     * OUTPUT
     * <array> de objetos CuentaCorrienteElementoValuado
     */
    private function _getCuentaCorrienteElementosValuados( $CuentaCorriente )
    {
        return $this->_CuentaCorrienteElementoValuadoColeccion
                    ->obtenerGeneral(
                        [ 'cuentas_corrientes_id' => $CuentaCorriente->getId() ], 
                            'id', 
                            'CuentaCorrienteElementoValuado'
                        );
    }
    
    // $CuentaCorriente Débito erróneo
    private function _disminuirCoberturaDeSuPago( $CuentaCorriente, $CuentaCorrienteElementoValuado )
    {
        $alumnos_id = $CuentaCorriente->getAlumnosId();
        $evscxa_id = $CuentaCorrienteElementoValuado->getElementosValuadosSedesCursosxanioId();
        $creditosAsignados = $this->_creditosEnCuentaCorriente( $evscxa_id, $alumnos_id );
        $montoADisminuir = abs( $CuentaCorriente->getCobertura() );// parto de un valor positivo
        
        foreach( $creditosAsignados as $CuentaCorrienteCredito ){
            if( $montoADisminuir<=0 ){
                break;
            }
            $cobertura = $CuentaCorrienteCredito->getCobertura();
            // Calculo el valor que aumentará la deuda
            $montoCorrectivo = ( $cobertura >= $montoADisminuir )? $montoADisminuir : $cobertura;
            
            $this->_escribirNuevaCobertura( $CuentaCorrienteCredito, $montoCorrectivo );
            $montoADisminuir-=$montoCorrectivo;
        }
        return $montoADisminuir; // Si es mayor a cero es que no se completó la petición
    }
    
    /* Brinda las rows de CuentaCorriente que refieren al debito generado por el evscxa
     * Con orden de más viejo a más nuevo.  */
    private function _creditosEnCuentaCorriente( $evscxa_id, $alumnos_id )
    {
        $cuentasArray = array();
        
        $motivosBuscados = $this->_cuentaCorrienteColeccion->getTipoOperacionHaber();
        $sql =  'SELECT ctas.* FROM yoga_cuentas_corrientes AS ctas '.
                'INNER JOIN yoga_cuentascorrientes_elementosvaluados AS evs '.
                'ON evs.cuentas_corrientes_id = ctas.id '.
                'WHERE tipo_operacion IN ( "'.implode('", "', $motivosBuscados ).'" ) '.
                ' AND ctas.alumnos_id = "'.$alumnos_id.'" '.
                ' AND evs.elementosvaluados_sedes_cursosxanio_id = '.$evscxa_id.
                ' ORDER BY fecha_hora_de_sistema ';
        $query = new Query();
        $resultado = $query->ejecutarQuery( $sql );
        if( $resultado && count($resultado)>0 ){
            foreach( $resultado as $values ){
                $cuentasArray[] = new CuentaCorriente( $values );
            }
        }
        return $cuentasArray;
    }
    
    // Modifica la cobertura de crédito
    private function _escribirNuevaCobertura( $CuentaCorriente, $montoCorrectivo )
    {
        $CuentaCorrienteArray = $CuentaCorriente->convertirEnArray();
        $CuentaCorrienteArray['cobertura']=$CuentaCorriente->getCobertura()-$montoCorrectivo;
 
        $this->_cuentaCorrienteColeccion
                ->modificacionactualizacionGeneral($CuentaCorrienteArray, 'CuentaCorriente');
        $this->_auditoriaColeccion
                ->registrar( 'modificacion', 'cuentas_corrientes', $CuentaCorriente->getId(),
                            [   'descripcion'=>'Modificar cobertura cŕedito, en Eliminar Débito',
                                'alumnos_id'=>$CuentaCorriente->getAlumnosId(),
                                'quita_en_cobertura' => $montoCorrectivo,
                                'comprobante' => $CuentaCorriente->getComprobante(),
                            ]
                        );
        echo '<br>Comprobante '.$CuentaCorriente->getComprobante();
    }

    
}
