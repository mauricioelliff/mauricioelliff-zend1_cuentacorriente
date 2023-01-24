<?php

/*
 * Llega un pago,
 * si corresponde a la MAT, congela el valor para ese estudiante,
 * (esto hará que respete la promoción a que puede haberse adherido el estudiante)
 * 
 * La MAT de por sí, siempre es tratada como una promoción.
 * Una vez que el estudiante pague parcial o totalmente la MAT,
 * el valor quedará congelado de ahí en más 
 * y será tratado entonces como un precio particular.
 * Su valor ya no se verá afectado por cambios del precio general.
 * 
 * Para posibilitar eso, se inserta aquí el EVA (si no existe aun)
 * sin importar que sea igual al precio general.
 */

require_once 'cuentacorriente/logicalmodels/promociones/Promocion.php';
require_once 'cuentacorriente/logicalmodels/promociones/PromocionInterfase.php';

require_once 'admin/models/ElementoValuadoAlumnoColeccion.php';
require_once 'default/models/Query.php';

/**
 * Crea un EVA (ElementoValuadoAlumno) si no existe, 
 * para fijar el precio de la MAT al estudiante,
 * y que no pueda modificarse si posteriormente la MAT cambia de precio general.
 *
 * @author mauricio
 */
class PromocionMatricula extends Promocion implements PromocionInterfase
{
    private $alumnos_id;
    private $pagosData;
    
    private $_ElementoValuadoAlumnoColeccion;
    
    const valor_modificado_motivo_descuento = 'D';
    
    public function __construct( $alumnos_id, $pagosData, $evscxa_id_con_promo=null ) {
        $this->alumnos_id = $alumnos_id;
        $this->pagosData = $pagosData;
        if( is_null($evscxa_id_con_promo) ){
            $this->evscxa_id_con_promo = self::hayPromoImplicitaEnPago( $pagosData );
        }else{
            $this->evscxa_id_con_promo = $evscxa_id_con_promo;
        }
        
        $this->_ElementoValuadoAlumnoColeccion = new ElementoValuadoAlumnoColeccion();
    }
    
    /*
     * Esta función tiene por objeto detectar si el pago de la MAT tiene alguna promo.
     * 
     * 
     * INPUT
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
     *           [88928] => int(2200)       // CTACTE_ID => $
     *         }
     *       }
     * 
     * OUTPUT
     *  evscxa_id o FALSE
     */
    public static function hayPromoImplicitaEnPago( $pagosData )
    {
        $evPagadosData = self::getDataElementosValuadosPagados( $pagosData );
        if( !$evPagadosData ){
            return false;
        }
        
        // Debería chequear que se está pagando la MAT y que el mes de pago es anterior
        // al comienzo de formación. 
        // Pero dado que es poco probable que luego de comenzado, justo varie el precio
        // de la MAT para alguien que se inscriba con posterioridad al comienzo de cursada,
        // lo dejo simplemente, con el check de abreviatura = 'MAT'
        $ev_abreviaturas = self::getAbreviaturasDeEVInvolucradosEnPago( $evPagadosData );
        return( array_search('MAT',$ev_abreviaturas) );
    }
        
    /*
     * Crea un EVA (ElementoValuadoAlumno) si no existe, 
     * para fijar el precio de la MAT al estudiante,
     * y que no pueda modificarse si posteriormente la MAT cambia de precio general.
     */
    public function operarPromo( $fechaSolicitud=null )
    {
        // $evscxa_id = self::hayPromoImplicitaEnPago( $this->pagosData );
        
        foreach( $this->pagosData['evscxa'] as $evscxa_id => $pagoValues ){
            
            // ¿es el item de promo?
            if( $evscxa_id <> $this->evscxa_id_con_promo ){
                continue;   // sigo evaluando los otros pagos llegados
            }
            
            // Obtener el precio general 
            $precioGeneral = $this->_getPrecioGeneral( $evscxa_id );
            
            foreach( $pagoValues as $ctacteId => $montoPagado ){                
                
                // obtengo data necesaria. Debería ser la deuda de MAT
                $CtaCteFactura = $this->_getFactura( $this->pagosData['objetos_debito'] );
                if( !$CtaCteFactura ){
                    return false;   // hay algún problema, no encuentro la FACTURA
                }
                
                $scxaa_id = $this->_scxaa_id( $evscxa_id, $CtaCteFactura->getAlumnosId() );
                if( !$scxaa_id ){
                    // NO ENCONTRE ALUMNO!!
                    return false;
                }
                
                // insert del eva
                $buscar = [ 'elementosvaluados_sedes_cursosxanio_id' => $evscxa_id,
                            'sedes_cursosxanio_alumnos_id' => $scxaa_id ];
                $existe = $this->_ElementoValuadoAlumnoColeccion
                                ->obtenerGeneral( $buscar, 'id', 'ElementoValuadoAlumno' );
                if( $existe ){
                    //nada
                }else{
                    $buscar['valor_modificado_motivo']= self::valor_modificado_motivo_descuento;
                    $buscar['valor_modificado'] = abs($precioGeneral);
                    
                    $this->_ElementoValuadoAlumnoColeccion->altaGeneral( $buscar, 'ElementoValuadoAlumno' );
                }
                
                // con trabajar uno de los pagos hechos referidos al item, ya alcanza.
                break;
            }
        }
    }
    
    // Devuelve el item que es Factura, (origen de la deuda tratada)
    private function _getFactura( $debitos )
    {
        foreach( $debitos as $Debito ){
            if( $Debito->getTipoOperacion() == 'FACTURA_AUTOMATICA' ){
                return $Debito;
            }
        }
        return false;
    }
    
    private function _scxaa_id( $evscxa_id, $alumnos_id )
    {
        $sql = "SELECT scxaa.id AS scxaa_id 
                FROM view_elementosvaluados_por_sedes_cursos_y_planes AS view
                LEFT JOIN yoga_sedes_cursosxanio_alumnos AS scxaa
                ON scxaa.sedes_cursosxanio_id = view.sedes_cursosxanio_id
                WHERE view.evscxa_id = $evscxa_id AND ev_abreviatura = 'MAT'
                AND scxaa.alumnos_id = '$alumnos_id'";

        $Query = new Query();
        $rowValues = $Query->ejecutarQuery($sql);
        if( !$rowValues || count($rowValues)==0 ){
            return false;
        }
        $scxaa_id = (int)$rowValues[0]['scxaa_id'];
        return $scxaa_id;
    }
    
    private function _getPrecioGeneral( $evscxa_id )
    {
        $sql = "SELECT evscxa_valor 
                FROM view_elementosvaluados_por_sedes_cursos_y_planes AS view
                WHERE evscxa_id = $evscxa_id ";
        $Query = new Query();
        $rowValues = $Query->ejecutarQuery($sql);
        if( !$rowValues || count($rowValues)==0 ){
            return false;
        }
        $precioGeneral = (int)$rowValues[0]['evscxa_valor'];
        return $precioGeneral;
    }
        
}
