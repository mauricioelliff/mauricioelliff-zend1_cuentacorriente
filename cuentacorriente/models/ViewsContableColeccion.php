<?php
/*
 * 
 * Proceso:
 * 
 * INPUT
 * 
 * 
 */
require_once 'admin/models/ViewColeccion.php';

require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';

// require_once 'default/models/Query.php';

class ViewsContableColeccion extends ViewColeccion
{
    
    
    public function getEVAsConCualquierMontoDeEstosAlumnos( $alumnos_id=null )
    {
        $otrosWheres = ($alumnos_id)? array('alumnos_id' => $alumnos_id):null;
        
        return
            // Tengo 2 fns para obtenerlos. Una con más data que la otra
            // y ambas, tardan el mismo tiempo para lograrlo.
            $this
            // 1) con màs data en cada item
            //    ->getAlumnosValoresCobrablesDesdeSuInicioHastaHoy( $alumnos_id );   

            // 2) con menos data
                ->getEVAsConCualquierMonto( $otrosWheres );
    }
    
    public function getEVAsConCualquierMonto( $otrosWheres=null )
    {
        return $this->getAlumnosValores($fechaInicio='2000-01-01', 
                                        $fechaFin=date('Y-m-d'), 
                                        $sedeId=0, 
                                        $soloMayoresACero=false, 
                                        $excluirCancelados=true,
                                        $otrosWheres );
    }
    
    public function getEVAsConMontoMayorACero( $otrosWheres=null )
    {
        return $this->getAlumnosValores($fechaInicio='2000-01-01', 
                                        $fechaFin=date('Y-m-d'), 
                                        $sedeId=0, 
                                        $soloMayoresACero=true, 
                                        $excluirCancelados=true,
                                        $otrosWheres );
    }
    
    
    
    
    /*
     * OBTIENE ITEMS FACTURABLES  ( PUEDEN HABER SIDO FACTURADOS O NO)
     * 
     * VALOR = PRECIO .   
     * NO ES DEUDA HASTA QUE NO SE REGISTRE EN LA CUENTA CORRIENTE.
     * 
     * Devuelve los valores de los alumnos
     * 
     * INPUT
     * $fechaInicio         Opcional
     * $fechaFin            Opcional
     * $sedeId              Opcional
     * $soloMayoresACero    Opcional
     * $otrosWheres         Opcional    Si es simplemente un INT, 
     *                                  asumo que refiere a un alumnos_id,
     *                                  sino deberá ser un array asociativo
     * 
     * OUTPUT
     * <array>
     *      key alumnos_id
     *      <array>
                'sedes_id', 
                'anio',
                'sedes_cursosxanio_id',
                'scxaa_id',
                'nombre_computacional',     //  "profesorado"
                'clasificador_nombre',      //  "nivel"
                'clasificador_valor',       //  "3"
                'evscxa_id',
                'ev_id',
                'ev_abreviatura',
                'eva_id',
                'fecha_inicio_calculado
                'evscxa_valor',
                'alumnos_id', 
                'apellido',
                'nombres',
                'nombre_espiritual',
                'valor_modificado',
                'valor_final_calculado'
                'fecha_finalizo'
     */
    public function getAlumnosValores( 
                                        $fechaInicio=null, 
                                        $fechaFin=null, 
                                        $sedeId=0, 
                                        $soloMayoresACero=true, 
                                        $excluirCancelados=true,
                                        $otrosWheres=false )
    {
        $select = $this ->select()
                        ->setIntegrityCheck(false);
        $select 
                ->from( array(  'view_alumnos_valores' ), 
                        array(  'sedes_id', 
                                'anio',
                                'sedes_cursosxanio_id', 
                                'scxaa_id',
                                'nombre_humano',            //  "Profesorado de Nathayoga Nivel 3"
                                'nombre_computacional',     //  "profesorado"
                                'clasificador_nombre',      //  "nivel"
                                'clasificador_valor',       //  "3"
                                'cursos_id',
                                'evscxa_id',
                                'ev_id',
                                'ev_abreviatura',
                                'eva_id',
                                'fecha_inicio_calculado',
                                'evscxa_valor',
                                'alumnos_id', 
                                'apellido',
                                'nombres',
                                'nombre_espiritual',
                                'valor_modificado',
                                'valor_modificado_motivo',
                                'valor_final_calculado',
                                'fecha_finalizo'
                            ) );
        if( $fechaInicio ){
            $select->where( 'fecha_inicio_calculado >= "'.$fechaInicio.'" ');

            if( $excluirCancelados ){
                // y que el alumno no se haya dado de baja.
                // El motivo aquí no se tendrá en cuenta. Por eso es importante que esté la fecha 
                // en los alumnos dado de baja.
                $select->where(
                            '( fecha_finalizo IS NULL OR fecha_finalizo > fecha_inicio_calculado )'
                            //'( fecha_finalizo IS NULL AND (finalizo_motivo IS NULL OR finalizo_motivo = "" ) ) OR '.
                            //'( fecha_finalizo IS NOT NULL && fecha_finalizo > "'.$fechaInicio.'" ) '
                            );
            }
        }
        if( $fechaFin ){
            $select->where( 'fecha_inicio_calculado <= "'.$fechaFin.'" ');
        }
        
        if( $soloMayoresACero ){
            $select->where( 'valor_final_calculado IS NOT NULL AND valor_final_calculado > 0 ');
        }
        
        $select->order( array('fecha_inicio_calculado') );
        
        if( $sedeId != 0 ){
            $select->where( 'sedes_id = '.$sedeId );
        }
        
        if( $otrosWheres && !is_null($otrosWheres) ){
            $select = $this->construirElWhere( $select, $otrosWheres );
        }
       
        // print( $select ); die();
        
        $filasArray = $this->fetchAll($select)->toArray();
        
        // para mayor manipulabilidad, agrupo los items por alumno
        $porAlumno = arrays_agruparPorKey( $filasArray, $key='alumnos_id' );
        
        // y además de por alumno, las keys dentro de cada uno de sus items
        // será por EVSCXA_id
        $resultadoPorKeyAlumnoYKeyEvscxaid = array();
        foreach( $porAlumno as $alumnos_id => $arrayArray ){
            foreach( $arrayArray as $values ){
                $resultadoPorKeyAlumnoYKeyEvscxaid[$alumnos_id][$values['evscxa_id']]=$values;
            }
        }
        return $resultadoPorKeyAlumnoYKeyEvscxaid;
    }
    
    

    
    /*
     * OBTIENE ITEMS FACTURABLES  ( PUEDEN HABER SIDO FACTURADOS O NO)
     * 
     * VALOR = PRECIO .   
     * NO ES DEUDA HASTA QUE NO SE REGISTRE EN LA CUENTA CORRIENTE.
     * 
     * Es similar a la fn getAlumnosValores(), 
     * solo que trabaja con la view que tiene más data en cada registro
     * 
     * OUPTUT
     *   array
     *      array
                [0] => array(69) {
                  ["id"] => string(1) "3"
                  ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 1"
                  ["nombre_computacional"] => string(11) "profesorado"
                  ["descripcion"] => string(27) "Profesorado de Yoga Curso 1"
                  ["clasificador_valor"] => string(1) "1"
                  ["clasificador_nombre"] => string(5) "nivel"
                  ["cursos_id_padre"] => string(1) "2"
                  ["cursos_id"] => string(1) "3"
                  ["plan"] => string(4) "2010"
                  ["nombre_sede_centro"] => string(6) "Tandil"
                  ["sedes_id"] => string(1) "4"
                  ["cursosxanio_id"] => string(1) "1"
                  ["anio"] => string(4) "2014"
                  ["scxaa_id"] => string(2) "26"
                  ["sedes_cursosxanio_id"] => string(1) "5"
                  ["alumnos_id"] => string(2) "14"
                  ["scxaa_fecha_alta"] => string(10) "1999-01-01"
                  ["concurriendo"] => string(1) "1"
                  ["fecha_finalizo"] => NULL
                  ["finalizo_motivo"] => NULL
                  ["dni"] => string(2) "14"
                  ["apellido"] => string(13) "Baico Neumann"
                  ["nombres"] => string(10) "Evelyn Sue"
                  ["id_viejo"] => NULL
                  ["legajo"] => string(4) "7001"
                  ["nombre_espiritual"] => string(0) ""
                  ["fecha_nacimiento"] => string(10) "2016-05-18"
                  ["mail"] => string(24) "evelyn.baico@hotmail.com"
                  ["facebook"] => string(15) "Evelyn Deschain"
                  ["telefono_fijo"] => string(7) "52-5747"
                  ["telefono_celular"] => string(16) "(249) 15-4627236"
                  ["telefono_whatsapp"] => NULL
                  ["fecha_alta"] => string(10) "2016-04-03"
                  ["estado"] => NULL
                  ["estado_cuando"] => NULL
                  ["observaciones"] => string(0) ""
                  ["preinscripcion_id"] => string(1) "0"
                  ["preinscripcion_fecha"] => NULL
                  ["preinscripcion_sede_centro_id"] => string(1) "3"
                  ["lugar_nacimiento"] => NULL
                  ["nacionalidad"] => NULL
                  ["direccion"] => NULL
                  ["localidad"] => NULL
                  ["provincia"] => NULL
                  ["pais"] => NULL
                  ["profesion"] => NULL
                  ["como_se_entero"] => NULL
                  ["ha_realizado_algun_curso"] => NULL
                  ["cual_curso_ha_realizado"] => NULL
                  ["practica_al_momento_de_inscripcion"] => NULL
                  ["practica_donde"] => NULL
                  ["modo_de_curso_de_interes_al_inscribirse"] => NULL
                  ["nombre_foto"] => NULL
                  ["evscxa_id"] => string(2) "45"
                  ["evscxa_fecha_inicio"] => string(10) "2014-03-01"
                  ["fecha_inicio_calculado"] => string(10) "2014-03-01"
                  ["evcxa_numero_de_orden"] => NULL
                  ["ev_id"] => string(1) "1"
                  ["ev_abreviatura"] => string(3) "MAT"
                  ["ev_dia_inicio_de_cobro_default"] => string(1) "1"
                  ["ev_mes_inicio_de_cobro_default"] => string(1) "3"
                  ["ev_numero_de_orden"] => string(1) "1"
                  ["evscxa_valor"] => NULL
                  ["eva_id"] => string(3) "144"
                  ["valor_modificado"] => string(3) "300"
                  ["valor_modificado_motivo"] => NULL
                  ["valor_final_calculado"] => string(3) "300"
                  ["alumno_fecha_finalizo"] => NULL
                  ["esta_en_fecha_de_cobro_a_hoy"] => string(1) "1"
     * 
     */
    public function getAlumnosValoresCobrablesDesdeSuInicioHastaHoy( $alumnos_id=null )
    {
        $sql =  'SELECT * FROM view_alumnos_valores_a_hoy '.
                'WHERE '.(($alumnos_id)? 'alumnos_id IN ("'.implode('", "',$alumnos_id ).'" ) AND ' : '' ).
                'valor_final_calculado IS NOT NULL AND valor_final_calculado > 0 AND '.
                'esta_en_fecha_de_cobro_a_hoy = 1'
                ;
        $query = new Query();
        $filasArray = $query->ejecutarQuery( $sql );
        return $filasArray;
    }
    
    
    
    public function getEVSCXA( $sedes_id, $anio )
    {
        $sql =  'SELECT * FROM view_elementosvaluados_por_sedes_cursos_y_planes AS view_evscxa '.
                'WHERE sedes_id = '.$sedes_id.' AND anio = '.$anio;
        $query = new Query();
        $filasArray = $query->ejecutarQuery( $sql );
        return $filasArray;
    }
}