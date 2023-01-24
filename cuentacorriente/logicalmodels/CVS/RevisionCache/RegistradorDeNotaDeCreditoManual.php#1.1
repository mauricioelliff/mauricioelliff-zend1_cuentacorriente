<?php

require_once 'cuentacorriente/logicalmodels/RegistroCredito.php';
require_once 'cuentacorriente/logicalmodels/RegistradorDePago.php';
require_once 'admin/logicalmodels/EvscxaDescripcionModificada.php';

/**
 * 
 *
 * @author mauricio
 */
class RegistradorDeNotaDeCreditoManual extends RegistroCredito
{
    public function registrar()
    {
        $x=$this->_check();
        if( $x !== true ){
            return $x;
        }
        
        $this->_calcular();
        
        if( !$this->simular ){
            $this->_impactar(); 
        }
        return $this->respuestas;
    }

    private function _check()
    {
        $alumnos_id = $this->datosDelMov['alumnos_id'];
        $evscxaIdSeleccionados = (is_array( $this->datosDelMov['seleccion_deuda_item'] ))? $this->datosDelMov['seleccion_deuda_item'] : array();

        // $itemsValidosActuales = $this->_cuentaCorrienteColeccion->getEvscxaActuales( $alumnos_id, $datosDelMov['anio'] );
        $itemsConDeuda = $this->cuentaCorrienteColeccion->getEvscxaPorSaldar( $alumnos_id );
        
        // Los elementos seleccionados, deben existir en el dominio de los posibles.
        if( count($evscxaIdSeleccionados)>0 ){  
            $flagSePagaranAlgoDeLoSeleccionado = false;
            foreach( $evscxaIdSeleccionados as $evscxa_id => $monto ){
                $itemValidoId = arrays_getKeyConValorBuscado($itemsConDeuda, 'evscxa_id', $evscxa_id );
                if( $itemValidoId ){
                    $saldoItem = abs($itemsConDeuda[$evscxa_id]['monto'])-abs($itemsConDeuda[$evscxa_id]['cobertura']);
                    if( $monto > $saldoItem ){
                        return ['ERROR'=> 'No debe ingresar un monto mayor a la deuda.' ];
                    }
                    $flagSePagaranAlgoDeLoSeleccionado = true;
                    break;
                }
                //$EvscxaDescripcionModificada = new EvscxaDescripcionModificada();
                //$descripcionNormalizada = $EvscxaDescripcionModificada->getDescripcion( $evscxa_id );
                //$this->datosDelMov['motivo']= ( $descripcionNormalizada )? $descripcionNormalizada : $this->datosDelMov['motivo'];
            }
            if( !$flagSePagaranAlgoDeLoSeleccionado ){
                return ['ERROR'=> 'El item seleccionado no es correcto para aplicar un crédito.' ];
            }
        }
        return true;
    }   
    
    
    // Obtiene toda la data necesaria como si el crédito fuese registrado
    private function _calcular()
    {
        $this->calcularMontos();
        $this->CuentaCorrienteCredito = new CuentaCorriente( $this->datosDelMov );

        $this->_calculaAcademico();
        
        // Arma el string de motivo
        $this->modificaCredito( 'motivo', $this->descripcionMotivosAcademico() );
        $this->respuestas['CuentaCorrienteCredito'] = $this->CuentaCorrienteCredito;
    }
    
    private function _impactar()
    {
        if( !$this->_escrituraDelCredito() ){   
            return FALSE; 
        }
        
        $this->modificaCredito( 'cobertura', 0 );
        // Se impactan los pagos en los debitos (cobertura y en tabla de relación)
        $this->coberturaOperaciones
                ->impactarCredito(  $this->CuentaCorrienteCredito,  // actualizado post distribución 
                                    $this->respuestas['evscxa'] );
    }
    private function _calculaAcademico()
    {
        // Pongo los items seleccionados al principio.
        $listaDeudas = $this->cuentaCorrienteColeccion
                            ->getRowsPorSaldar( $this->alumnos_id, 
                                                array_keys( $this->evscxaIdsPaga ) );
        // Rows de la tabla cuentas corrientes:
        $DebitosDondeDistribuir = $this->cuentaCorrienteColeccion
                                        ->transformarLaListaDeArraysEnListaDeObjetos( $listaDeudas );
        // array de evscxa_id a que row de cuentas corrientes aplica
        $aux = arrays_getAlgunasKeysArrays($listaDeudas, ['evscxa_id']);
        
        $evscxaIdXCtacteId = array_flip( array_map(function ($x){return (int)$x['evscxa_id'];},$aux ) );
      
        $deb = $this->coberturaOperaciones->distribuirUnCredito($this->CuentaCorrienteCredito, 
                                                                $this->evscxaIdsPaga,
                                                                $evscxaIdXCtacteId,
                                                                $DebitosDondeDistribuir );
        // Agrego a los totales, los datos luego de distribuir el crédito
        $this->respuestas = $deb + $this->respuestas;
//ver(  $this->respuestas, ' $this->respuestas' );        
    }
        
    // El motivo se alterará después, cuando se vea, a que se distribuyó el pago.
    private function _escrituraDelCredito()
    {
        if( !$id = $this->cuentaCorrienteColeccion
                                ->altaGeneral( $this->CuentaCorrienteCredito, 'CuentaCorriente' ) ){
            return FALSE;
        }
        $this->modificaCredito('id', $id);
        // Auditoría
        $this->auditoriaColeccion
                ->registrar( 'alta', 'cuentas_corrientes', $this->CuentaCorrienteCredito->getId(), 
                            arrays_getAlgunasKeys( $this->datosDelMov, $this->getCamposIndispensables($this->datosDelMov['tipo_operacion']) ) );
        return true;
    }
    
}
