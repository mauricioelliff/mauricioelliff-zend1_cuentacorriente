<?php

if( APPLICATION_ENV == 'development' ){
    require_once 'api/TEST/ajna_centros_emulado/function/api_generar_comprobante_de_pago.php';
}else{
    require_once __GESTION_DIR_ABSOLUTO_A_ADMINISTRACION__.'function/api_recibos.php';  // recibos() 
}

// require_once 'extensiones/generales/MiMensajero.php'; POR AHORA SIN USO
require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';
require_once 'admin/models/SedeColeccion.php';
require_once 'api/models/ApiCuentaCorriente.php';

class ComprobanteDesdeAjnaCentros 
{
    
    const DeudaViejaNoIdentificada = 'DEUDA_NO_IDENTIFICADA';
    // private $_MiMensajero;
    
    /*
    private function __construct() {
        $this->_MiMensajero = new MiMensajero();
    }
     */
    
    
    /*
     * INPUT
     *  $alumnos_id
     * 
     * $listaDeudasPorEvscxa    SON LAS DEUDAS PREVIAS AL PAGO
     *  lista seleccionable  <array de arrays>
            [476] => array(29) {
              ["cuentas_corrientes_id"] => string(5) "33833"
              ["alumnos_id"] => string(3) "178"
              ["tipo_operacion"] => string(18) "FACTURA_AUTOMATICA"
              ["monto"] => string(4) "-700"
              ["cobertura"] => string(4) "-100"
              ["motivo"] => string(30) "2017, MAT, profesorado nivel 4"
              ["fecha_operacion"] => string(10) "2017-03-01"
              ["scxa_id"] => string(2) "46"
              ["sedes_id"] => string(1) "3"
              ["anio"] => string(4) "2017"
              ["cursos_id"] => string(1) "6"
              ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 4"
              ["descripcion"] => string(27) "Profesorado de Yoga Curso 4"
              ["valor_modificado"] => NULL
              ["valor_final_calculado"] => string(3) "700"
              ["ev_abreviatura"] => string(3) "MAT"
              ["evscxa_id"] => string(3) "476" *********************************
              ["evscxa_fecha_inicio"] => string(10) "2017-03-01"
              ["evscxa_valor"] => string(3) "700"
              ["ev_numero_de_orden"] => string(1) "1"
              ["ev_id"] => string(1) "1"
              ["prioridad_segun_anio"] => string(1) "3"
              ["scxa_ordenado"] => string(1) "2"
              ["sum_monto_debitos"] => string(4) "-700"
              ["sum_cobertura_debitos"] => string(4) "-100"
              ["sum_saldo_debitos"] => string(4) "-600"
              ["sum_monto_debitos_a_cuenta"] => string(1) "0"
              ["sum_cobertura_debitos_a_cuenta"] => string(1) "0"
              ["sum_saldo_debitos_a_cuenta"] => string(1) "0"
            }
     * 
        $debitosTrabajados
            <array>     Detalle con los objetos CuentaCorrienteDebito  saldados
                array(3) {
                  ["objetos_debito"] => array(2) {  OBJETOS QUE FUERON/SERÁN MODIFICADOS
                    [41861] => object(CuentaCorriente)#338 (12) {
                      ["_id":"CuentaCorriente":private] => string(5) "41861"
                      ["_alumnos_id":"CuentaCorriente":private] => string(3) "865"
                      ["_tipo_operacion":"CuentaCorriente":private] => string(18) "FACTURA_AUTOMATICA"
                      ["_fecha_operacion":"CuentaCorriente":private] => string(10) "2018-06-01"
                      ["_monto":"CuentaCorriente":private] => string(5) "-1700"
                      ["_cobertura":"CuentaCorriente":private] => int(-1700)
                      ["_motivo":"CuentaCorriente":private] => string(29) "2018, CU3 profesorado nivel 2"
                      ["_comprobante":"CuentaCorriente":private] => string(8) "no_tiene"
                      ["_persona_en_caja":"CuentaCorriente":private] => string(18) "proceso_automatico"
                      ["_observaciones":"CuentaCorriente":private] => string(276) "Sistemas inicializa coberturas. Le asigna $200Sistemas inicializa coberturas. Le asigna $300Sistemas inicializa coberturas. Le asigna $300Sistemas inicializa coberturas. Le asigna $55Sistemas inicializa coberturas. Le asigna $845Sistemas inicializa coberturas. Le asigna $1700"
                      ["_usuario_nombre":"CuentaCorriente":private] => string(18) "proceso_automatico"
                      ["_fecha_hora_de_sistema":"CuentaCorriente":private] => string(19) "2018-06-01 13:13:14.123456"
                    }
                    [41545] => object(CuentaCorriente)#344 (12) {
                      ["_id":"CuentaCorriente":private] => string(5) "41545"
                      ...
                    }
                  }
                  ["pagos"] => array(2) {
                    [41861] => int(1700)
                    [41545] => int(300)
                  }
                  ["evscxa"] => array(2) {
                    [334] => array              pagos que se hicieron a este item
                                ctacteid
                                [41861] => int(1700)
                                [41545] => int(300)
                    [335] => array
                                [41545] => int(300)
                  }
                  ["credito_disponible"] => int(0)
                }
     * 
     * Cuando sea un pago a cuenta irá así:
     * 
     * 
     */
    public function getComprobanteDesdeAjnaCentros( $alumnos_id, 
                                                    $sedes_id, 
                                                    $comprobante_envio, 
                                                    $comprobante_mail,            
                                                    array $listaDeudasPorEvscxa, 
                                                    array $debitosTrabajados,
                                                    $descOtroConcepto
                                                    )
    {
        /*
         * Función en Ajna Centros:
         * api_recibos()
         * 
         * INPUT
         * array(3) {
         *   ["dni"] => string(8) "17103958"
         *   ['sede_id']  => int(2) 13
         *   [usuario]
         *   ["pagos"] => array(1) {
         *     [5074] => array(3) {
         *       ["id"] => int(5074)
         *       ["descripcion"] => string(30) "2020, MAT, profesorado nivel 1"
         *       ["deuda_pre_pago"] => int(770)
         *       ["pago_ahora"] => int(5)
         *     }
         *   }
         *   ["impagos"] => array(1) {
         *     [0] => array(5) {
         *       ["dni"] => string(8) "17103958"
         *       ["id"] => int(5074)
         *       ["descripcion"] => string(20) "MAT de Nivel 1, 2020"
         *       ["descripcion_corta"] => string(3) "MAT"
         *       ["monto_deuda"] => int(765)  
         *     }
         *   }
         * }
         * 
         */
        $input =  array('origen'    => 'AA', // (Ajna)Académico
                        'dni'       => $alumnos_id ,
                        'sede_id'   => $sedes_id,
                        'envio_recibo' => $comprobante_envio,
                        'email'     => $comprobante_mail,
                        'usuario'   => USUARIO_NOMBRE,
                        'pagos'     => $this->_getDetalleDeLoPagado( $listaDeudasPorEvscxa, $debitosTrabajados, $descOtroConcepto ),
                        //'impagos'   => $this->_getDeudasFormateadas( $this->_getDeudasRecalculadas( $alumnos_id ) ),
                    );
        if( LOCALHOST ){
            $SedeColeccion = new SedeColeccion();
            $Sede = $SedeColeccion->obtenerPorIdGeneral($sedes_id, 'Sede', 'id_sede_centro');
            $codigoSede = $Sede->getCodigo();
            return $codigoSede.'-'.rand(1,1000000);
        }
        return api_recibos( $input ); // función de Ajna Centros.
    }
    
    /*
     * INPUT
     * $listaPreDeudasPorEvscxa     array de rows por pagar, key cta id
            array(2) {
              [120734] => array(28) {
                ["cuentas_corrientes_id"] => string(6) "120734"
                ["fecha_hora_de_sistema"] => string(26) "2022-06-15 00:14:39.467400"
                ["alumnos_id"] => string(8) "33664072"
                ["tipo_operacion"] => string(13) "DEBITO_MANUAL"
                ["monto"] => string(7) "-100.00"
                ["cobertura"] => string(6) "-50.00"
                ["motivo"] => string(17) "Ajuste correctivo"
                ["fecha_operacion"] => string(10) "2022-06-15"
                ["scxa_id"] => NULL
                ["sedes_id"] => NULL
                ["anio"] => NULL
                ["cursos_id"] => NULL
                ["nombre_computacional"] => NULL
                ["nombre_humano"] => NULL
                ["descripcion"] => NULL
                ["valor_modificado"] => NULL
                ["valor_final_calculado"] => NULL
                ["pago_asignado"] => NULL
                ["sedes_cursosxanio_alumnos_id"] => NULL
                ["ev_abreviatura"] => NULL
                ["ev_descripcion"] => NULL
                ["evscxa_id"] => NULL
                ["evscxa_fecha_inicio"] => NULL
                ["evscxa_valor"] => NULL
                ["ev_numero_de_orden"] => NULL
                ["ev_id"] => NULL
                ["prioridad_segun_anio"] => string(1) "1"
                ["scxa_ordenado"] => string(1) "1"
              }
     *  $debitosTrabajados
        array(7) {
          ["objetos_debito"] => array(2) {
            [120734] => object(CuentaCorriente)#724 (14) {
              ["_id":"CuentaCorriente":private] => string(6) "120734"
              ["_origen":"CuentaCorriente":private] => string(1) "A"
              ["_alumnos_id":"CuentaCorriente":private] => string(8) "33664072"
              ["_tipo_operacion":"CuentaCorriente":private] => string(13) "DEBITO_MANUAL"
              ["_fecha_operacion":"CuentaCorriente":private] => string(10) "2022-06-15"
              ["_monto":"CuentaCorriente":private] => string(7) "-100.00"
              ["_cobertura":"CuentaCorriente":private] => float(-100)
              ["_motivo":"CuentaCorriente":private] => string(17) "Ajuste correctivo"
              ["_comprobante_sede":"CuentaCorriente":private] => string(1) "5"
              ["_comprobante":"CuentaCorriente":private] => NULL
              ["_persona_en_caja":"CuentaCorriente":private] => string(7) "Prakash"
              ["_observaciones":"CuentaCorriente":private] => string(0) ""
              ["_usuario_nombre":"CuentaCorriente":private] => string(13) "USUARIO_LOCAL"
              ["_fecha_hora_de_sistema":"CuentaCorriente":private] => string(26) "2022-06-15 00:14:39.467400"
            }
            [120735] => object(CuentaCorriente)#728 (14) {
              ["_id":"CuentaCorriente":private] => string(6) "120735"
     *          ... ETC ...
            }
          }
          ["pagos"] => array(2) {
            [120734] => float(50)
            [120735] => float(200)
          }
          ["dondeDistribuir"] => array(0) {
          }
          ["evscxa"] => array(1) {
            [0] => array(2) {       KEY 0 PORQUE NO CORRESPONDE A NINGUN EVSCXA
              [120734] => float(50)
              [120735] => float(200)
            }
          }
          ["fueTodoPagado"] => bool(true)
          ["monto"] => string(3) "500"
          ["errores"] => array(0) {
          }
        }

     * OUTPUT
     * $detallesDelPago <array>   
     *                    'descripcion'    => <string> "2020, MAT Profesorado Nivel 1" o "A_CUENTA"
     *                    'deuda_pre_pago' => 2000  // antes de hacer el pago
     *                    'pago_ahora'     => 1900
     */
    private function _getDetalleDeLoPagado( $listaPreDeudasPorEvscxa, $debitosTrabajados, $descOtroConcepto )
    {
        $items = array();
        
        if( count($debitosTrabajados['objetos_debito'])==0 ){
            // se trata de un pago a cuenta
            $item['descripcion'] = ($descOtroConcepto)? $descOtroConcepto : 'A_CUENTA'; // OJO NO CAMBIAR ESTE STRING YA QUE SE DEFINIO ASI COMO INPUT PARA AJNA CENTROS
            $item['deuda_pre_pago']= 0;
            $item['id']=0;      // también seteo así en ApiCuentaCorriente.php, darFormatoParaAjniaPracticantes()
            $item['pago_ahora']= $debitosTrabajados['monto'];
            $items[]= $item;
            
        }else{
            foreach( $debitosTrabajados['evscxa'] as $evscxaId => $debitosPagados ){
                $item = array();
                // En la lista de deudas, los que no pudieron identificarse, 
                // se agrupan en la key 0.
                // 
                // Los totales llegan en otras keys del array;
                // y aunque tienen un motivo, podría haber otros englobados en ese.
                // Algunas veces luego de recibir un pago,
                // mis procesos los identifican y los van armando correctamente,
                // por eso podría ser que al ver la pantalla de recalculos
                // podrían aparecer nuevos conceptos, pero siempre bajo la deuda correctamente recalculada.
                if( $evscxaId==0 || !isset($listaPreDeudasPorEvscxa[$evscxaId]) ){
                    //$keyEnPreDeudas = 'A_CUENTA';
                    //$deuda = abs( $listaPreDeudasPorEvscxa[$keyEnPreDeudas]['sum_saldo_debitos_a_cuenta'] );
                    // La deuda es la suma de todas las rows con evscxa en null 
                    $sinEvscxa = arrays_filtrar( $listaPreDeudasPorEvscxa, ['scxa_id'=>null] );
                    $saldos = array_map( function($n){ return $n['monto']-$n['cobertura']; }, $sinEvscxa );
                    $deuda = abs( array_sum( array_values($saldos) ) );
                    // como descripción tomo la del primer elemento
                    $primer = getPrimero( $sinEvscxa );
                    $item['descripcion']= $primer['motivo'];
                }else{
                    $keyEnPreDeudas = $evscxaId;
                    $deuda = abs( $listaPreDeudasPorEvscxa[$keyEnPreDeudas]['sum_saldo_debitos'] );
                    $item['descripcion']= ( key_exists($keyEnPreDeudas,$listaPreDeudasPorEvscxa) 
                                            && key_exists('motivo',$listaPreDeudasPorEvscxa[$keyEnPreDeudas])
                                            && !is_null($listaPreDeudasPorEvscxa[$keyEnPreDeudas]['motivo']) )? $listaPreDeudasPorEvscxa[$keyEnPreDeudas]['motivo'] : self::DeudaViejaNoIdentificada;
                }
                
                $item['deuda_pre_pago'] = $deuda;
                $item['id']=$evscxaId;
                $item['pago_ahora']= array_sum( $debitosPagados );
                
                $items[$evscxaId]= $item;
            }
        }
        
        return $this->_unificarItemsNoIdentificados( $items );
    }
    
    /*
     * Podría solicitar nuevamente el cálculo de deudas. 1
     * O como hago aquí, sigo la misma fuente de datos usados en el calculo de "pagos"
     * recalculando la deuda segun lo pagado. 2
     * 
     * Opto por la opción 1.
     */
    private function _getDeudasRecalculadas( $alumnos_id )
    {
        $m = new CuentaCorrienteColeccion();
        return $m->getEvscxaPorSaldar($alumnos_id);
    }
    /* Opción 2 ( no la termine de armar bien )
    private function _getDeudasPostPago( $listaDeudasPorEvscxa, $debitosTrabajados )
    {
        if( count($listaDeudasPorEvscxa)==0 ){
            return array();
        }
        
        // recalculo la deuda de los items trabajados
        foreach( $listaDeudasPorEvscxa as $evscxaId => $deuda ){

            if( key_exists( $evscxaId, $debitosTrabajados['pagos'] ) ){

                $pagado = $debitosTrabajados['pagos'][$evscxaId];

                if( $deuda['evscxa_id']!=null ) {
                    $listaDeudasPorEvscxa[$evscxaId]['sum_saldo_debitos']-= $pagado;
                }else{
                    $listaDeudasPorEvscxa[$evscxaId]['sum_saldo_debitos_a_cuenta']-= $pagado;
                }
            }
        }
        return $listaDeudasPorEvscxa;
    }
     * 
     */
    
    private function _getDeudasFormateadas( $listaDeudasPorEvscxa )
    {
        $m = new ApiCuentaCorriente();
        return $m->darFormatoParaAjniaPracticantesDeUnEstudiante( $listaDeudasPorEvscxa );
    }
    
    
    private function _unificarItemsNoIdentificados( $items )
    {
        $itemSumaPago = 0;
        $itemSumaDeuda = 0;
        $flag = false;
        foreach( $items as $key => $item ){
            if( $item['descripcion'] == self::DeudaViejaNoIdentificada ){
                $itemSumaPago+=$item['pago_ahora'];
                $itemSumaDeuda+=$item['deuda_pre_pago'];
                unset( $items[$key] );
                $flag = true;
            }
        }
        if( $flag ){
            $items[]= array('descripcion'       => self::DeudaViejaNoIdentificada,
                            'deuda_pre_pago'    => $itemSumaDeuda,
                            'pago_ahora'        => $itemSumaPago,
                        );
        }
        return $items;
    }
    
}
