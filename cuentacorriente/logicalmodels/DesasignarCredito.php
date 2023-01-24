<?php

require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';
require_once 'cuentacorriente/models/CuentaCorrienteElementoValuadoColeccion.php';
require_once 'admin/models/AuditoriaColeccion.php';

require_once 'extensiones/generales/MiMensajero.php'; 

/**
 * Sirve para desasignar un pago.
 * Es decir que revisa sus débitos y les quita la cobertura correspondiente.
 *  
 *
 * @author mauricio
 */
class DesasignarCredito {
    
    private $_cuentaCorrienteColeccion;
    private $_CuentaCorrienteElementoValuadoColeccion;
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
     * Sirve para borrar un pago o crédito mal hecho.
     * 
     * INPUT
     * $CuentaCorriente <object Cuenta Corriente> representa la row a eliminar.
     * 
     * 
     * Funcionamiento:
     *  - identifica su ctacte_id, y la cobertura que aplico 
     *  - identifica a que items deuda aplicó ese crédito( en cuentascorrientes_elementosvaluados )
     *  - Se verifica que las coberturas a corregir, coincidan con el total del crédito erróneo.
     *  - Se disminuye las coberturas de esas deudas.
     *  - Se elimina el credito erróneo de ctasctes, y por cascada de ctasctes evaluados
     */
    public function procesar( CuentaCorriente $Pago )
    {
        $evctacte_col = $this->_getCuentaCorrienteElementosValuados( $Pago );
        $totalAsignado = $this->_sumPagosAsignados( $evctacte_col );
        
        // check
        if( $Pago->getCobertura() <> $totalAsignado ){
            echo 'CASO CON PROBLEMA: LA SUMA DE LO ASIGNADO NO ES IGUAL A LA COBERTURA ACTUAL<BR>';
            ver($Pago,'PAGO');
            ver($totalAsignado,'$totalAsignado SUMAS ASIGNADAS A DEUDAS');
            ver($evctacte_col,'$deudasAplicadas');
            return false;
        }
        
        $this->_desasignarDebitos( $Pago, $evctacte_col );
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
    
    private function _sumPagosAsignados( $evctacte_col )
    {
        if( !$evctacte_col ) return 0;
        
        $total = 0;
        foreach( $evctacte_col as $CuentaCorrienteElementoValuado ){
            $total+= $CuentaCorrienteElementoValuado->getMontoAsignado();
        }
        return $total;
    }
    
    /*
     * INPUT
     * $evctacte_col    Indicación de las cosas que se pagaron.
     *                  A su vez, cada uno de esos débitos, podría tener 
     *                  varios débitos referentes al mismo item.
     *                  Ej. una factura que luego se le aplicaron Nota de Débito.
     */
    private function _desasignarDebitos( $Pago, $evctacte_col )
    {
        foreach( $evctacte_col as $CuentaCorrienteElementoValuado ){

            $alumnos_id = $Pago->getAlumnosId();
            $evscxa_id = $CuentaCorrienteElementoValuado->getElementosValuadosSedesCursosxanioId();
            $montoADisminuir = abs( $CuentaCorrienteElementoValuado->getMontoAsignado() ); 

            $debitos_col = $this->_getDebitos( $evscxa_id, $alumnos_id );

            foreach( $debitos_col as $CuentaCorriente ){
                if( $montoADisminuir<=0 ){
                    break;
                }
                $cobertura = $CuentaCorriente->getCobertura();
                // Calculo el valor que aumentará la deuda
                $montoCorrectivo = ( $cobertura <= -$montoADisminuir )? $montoADisminuir : abs($cobertura);

                $this->_disminuirCobertura( $CuentaCorriente, $montoCorrectivo );
                //ver($CuentaCorriente,"DEBITO DISMINUYE EN $montoCorrectivo, QUEDARÁ EN ".($cobertura+$montoCorrectivo) );
                $montoADisminuir-=$montoCorrectivo;
            }
            //return ($montoADisminuir==0)? true :false; // Si es mayor a cero es que no se completó la petición
        }
    }
    
    /* Brinda las rows de CuentaCorriente que refieren al debito generado por el evscxa
     * Con orden de más viejo a más nuevo.  */
    private function _getDebitos( $evscxa_id, $alumnos_id )
    {
        $cuentasArray = array();
        
        $motivosBuscados = $this->_cuentaCorrienteColeccion->getTipoOperacionDebe();
        $sql =  'SELECT ctas.* FROM yoga_cuentas_corrientes AS ctas '.
                'INNER JOIN yoga_cuentascorrientes_elementosvaluados AS evs '.
                'ON evs.cuentas_corrientes_id = ctas.id '.
                'WHERE tipo_operacion IN ( "'.implode('", "', $motivosBuscados ).'" ) '.
                ' AND ctas.alumnos_id = "'.$alumnos_id.'" '.
                ' AND evs.elementosvaluados_sedes_cursosxanio_id = '.$evscxa_id.
                ' ORDER BY fecha_hora_de_sistema DESC';
        $query = new Query();
        $resultado = $query->ejecutarQuery( $sql );
        if( $resultado && count($resultado)>0 ){
            foreach( $resultado as $values ){
                $cuentasArray[] = new CuentaCorriente( $values );
            }
        }
        return $cuentasArray;
    }
    
    // Modifica la cobertura de débito
    // $montoCorrectivo (en positivo)
    private function _disminuirCobertura( $CuentaCorriente, $montoCorrectivo )
    {
        $CuentaCorrienteArray = $CuentaCorriente->convertirEnArray();
        $CuentaCorrienteArray['cobertura']=$CuentaCorriente->getCobertura()+$montoCorrectivo;
 
        $this->_cuentaCorrienteColeccion
                ->modificacionactualizacionGeneral($CuentaCorrienteArray, 'CuentaCorriente');
        $this->_auditoriaColeccion
                ->registrar( 'modificacion', 'cuentas_corrientes', $CuentaCorriente->getId(),
                            [   'descripcion'=>'Proceso correctivo desasignar crédito',
                                'alumnos_id'=>$CuentaCorriente->getAlumnosId(),
                                'quita_en_cobertura' => $montoCorrectivo,
                            ]
                        );
    }
    
    
    /*
    private function _generarNotaDebito( $alumnos_id, $saldoCobertura, $CuentaCorriente )
    {
        echo '<br>Hay que insertar una nota de débito con estos valores:<br>';
        
        $CuentaCorrienteArray = 
            [ 
                "monto"           => -$saldoCobertura,
                "fecha_operacion" => date('Y-m-d'),
                "comprobante"     => null,
                "comprobante_tipo"=> "manual", 
                "persona_en_caja" => 'Mauricio',
                "observaciones"   => 'Corrección monto parcial error duplicación pago',
                "tipo_operacion"  => "DEBITO_MANUAL",
                "motivo"          => "Reparación errores de sistema. Pago duplicado.",
            ];
        ver( $CuentaCorrienteArray, '$CuentaCorrienteArray' );
        
        echo "<br> y disminuir la relación con ElementosValuados en $saldoCobertura <br>";
        
    }
     * 
     */
    
}
