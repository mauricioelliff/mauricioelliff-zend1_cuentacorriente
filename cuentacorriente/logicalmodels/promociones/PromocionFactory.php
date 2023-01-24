<?php

/*
 * Crea la promocion correspondiente
 */

// require_once 'eventos/eventos/Evento.php';

require_once 'cuentacorriente/logicalmodels/promociones/PromocionMatricula.php';
require_once 'cuentacorriente/logicalmodels/promociones/PromoPlataformaMesBonificado.php';

/**
 * Description of PromocionFactory
 *
 * @author mauricio
 */
class PromocionFactory 
{    

    // PROMOCIONES VIGENTES 
    public static function promosDominio()
    {
        return [    // evento                       // metódo de atención
                'PagoRealizado'                         => '_logicaParaCrearPromoImplicitaEnPago',
                'InscripcionAlProfesoradoConPromoMat'   => '_logicaParInscripcionPromoMatriculaFormacion',
                    // 'Otra...',
                ];
    }
    
    
    public static function crearPromo( $Evento )
    {
        $classEvento = get_class($Evento);
        if( key_exists($classEvento, self::promosDominio()) ){
            $metodoDeAtencion = self::promosDominio()[ $classEvento ];
            return self::$metodoDeAtencion( $Evento );
        }
        return false; // el evento no tiene promo
    }
    
    /*
     * PromocionMatricula
     * 
     * Busca si hay alguna promoción tras llegar un pago de algo.
     * 
     * Si detecta que el pago realizado involucra algun tipo de promo, 
     * opera al respecto.
     * Hasta el momento de fines de 2020, no había promociones en sistema,
     * pero sí, se sabía, que una matrícula podría estar asociada a 
     * características de una promoción. 
     * Entonces si el pago refería a una MAT, habrá que congelar ese valor para el estudiante.
     * 
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
     * 
     * OUTPUT
     *  true    nada por hacer
     */
    public static function _logicaParaCrearPromoImplicitaEnPago( $Evento )
    {
        // Por ahora la única promo que puede estar dentro de un pago, es la MAT
        if( $evscxa_id_con_promo= PromocionMatricula::hayPromoImplicitaEnPago( $Evento->pagosData ) ){
            return new PromocionMatricula( $Evento->alumnos_id, $Evento->pagosData, $evscxa_id_con_promo );
        }
        return false; // no hay promo
    }
    
    
    /*
     * Evento 
     *          InscripcionAlProfesorado:
     *              ->alumnos_id
     */
    public static function _logicaParInscripcionPromoMatriculaFormacion( $Evento )
    {
        // inscripción a la cursada siguiente
        
        return new PromoPlataformaMesBonificado( $Evento->alumnos_id );
    }
    
}
