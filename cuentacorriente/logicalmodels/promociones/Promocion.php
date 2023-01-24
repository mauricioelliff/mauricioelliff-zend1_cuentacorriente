<?php

/*
 * 
 */
require_once 'extensiones/generales/MiMensajero.php';

/**
 * Description of Promocion
 *
 * @author mauricio
 */
abstract class Promocion 
{
    protected $MiMensajero;  // vive en Zend_Registry. Lo uso para clases que no son Coleccion
    
    public function __construct(){
        $this->MiMensajero = new MiMensajero();
    }
    
    public function addColeccionMensajes( $mensajesNuevos )
    {
        $this->MiMensajero->addColeccionMensajes( $mensajesNuevos ); 
    }
    
    
    /*
     * $pagosData   <array>
     *     array(5) {
     *       "objetos_debito" => <Array post pago> con las deudas a las que se destino todo o parte del pago:
     *       ["objetos_debito"] => array(1) {
     *         [88928] => object(CuentaCorriente)#682 (13) {
     *           ["_id":"CuentaCorriente":private] => string(5) "88928"
     *           ["_alumnos_id":"CuentaCorriente":private] => string(8) "19023279"
     *           ["_tipo_operacion":"CuentaCorriente":private] => string(18) "FACTURA_AUTOMATICA"
     *           ["_fecha_operacion":"CuentaCorriente":private] => string(10) "2020-12-01"
     *           ["_monto":"CuentaCorriente":private] => string(5) "-2500"
     *           ["_cobertura":"CuentaCorriente":private] => int(-2200)
     *           ["_motivo":"CuentaCorriente":private] => string(30) "2021, MAT, profesorado nivel 1"
     *           ["_comprobante_sede":"CuentaCorriente":private] => NULL
     *           ["_comprobante":"CuentaCorriente":private] => NULL
     *           ["_persona_en_caja":"CuentaCorriente":private] => string(18) "proceso_automatico"
     *           ["_observaciones":"CuentaCorriente":private] => NULL
     *           ["_usuario_nombre":"CuentaCorriente":private] => string(13) "USUARIO_LOCAL"
     *           ["_fecha_hora_de_sistema":"CuentaCorriente":private] => string(26) "2021-01-31 17:32:47.418000"
     *         }
     *       }
     *          "pagos" => <array> con parcial destinado a cada item
     *       ["pagos"] => array(1) {
     *         [88928] => int(2200)
     *       }
     *          "evscxa" => <array> con evscxa_id trabajado, ctacte_id y monto destinado.
     *       ["evscxa"] => array(1) {
     *         [6285] => array(1) {
     *           [88928] => int(2200)
     *         }
     *       }
     */
    public static function getDataElementosValuadosPagados( $pagosData )
    {
        if( !isset($pagosData['evscxa']) || count($pagosData['evscxa'])==0 ){
            return false;
        } 
        $sql = 'SELECT * FROM view_elementosvaluados_por_sedes_cursos_y_planes '.
                'WHERE evscxa_id IN ( '.implode(', ', array_keys($pagosData['evscxa']) ).') ';
        $Query = new Query();
        $resu = $Query->ejecutarQuery($sql);
        // Mejoro la salida, poniendo como key a evscxa_id
        if( !is_array($resu) || count($resu)==0 ){
            return false;
        }
        $resultado = [];
        foreach( $resu as $values ){
            $resultado[ $values['evscxa_id'] ] = $values;
        }
        return $resultado;
    }
    public static function getAbreviaturasDeEVInvolucradosEnPago( $rowsValues )
    {
        $resultado = [];
        $evs = arrays_getAlgunasKeysArrays( $rowsValues, [ 'evscxa_id', 'ev_abreviatura'] );
        foreach( $evs as $values ){
            $resultado[ $values['evscxa_id'] ] = $values['ev_abreviatura'];
        }
        return $resultado;
    }

}
