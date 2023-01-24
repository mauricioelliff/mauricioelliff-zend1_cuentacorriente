<?php
/*
 * 
 * Examina el modelo de GASTOS vs CTACTE.
 * ( El modelo de GASTOS establece los valores de cada cuota para cada alumno.
 *   El modelo de CTACTES lleva ingresos y egresos.
 * )
 * 
 * Este modelo 
 * 
 *      - Repara y realiza ajustes para llevar a CTACTES a concordancia con GASTOS.
 *      - Resetea el valor del EVA en cero para los alumnos que se dieron de baja,
 *          siempre y cuando se conozca dicha fecha de baja.
 * 
 * 
 * 
 * 
 */
require_once 'cuentacorriente/models/ContableColeccion.php';

require_once 'admin/models/AlumnoColeccion.php';

require_once 'default/models/Query.php';


/*
 * 
 *
 */
class RelacionEntreModuloGastoYModuloCuentaCorriente extends Coleccion
{
    private $_tipo_operacion_que_es_ajuste = array(
                                                    'NOTA_CREDITO_MANUAL',
                                                    'DEBITO_MANUAL',
                                                    'NOTA_CREDITO_AUTOMATICO',
                                                    'DEBITO_AUTOMATICO',
                                                    );
    protected $_usuarioNombre = 'proceso_lanzado_manualmente';
    
    protected $_alumnosTrabajados = array();
    
    public function init()
    {
        parent::init();
        
    }

    /*
     * AJUSTE ENTRE MODELO DE GASTOS Y MODELO DE CUENTAS CORRIENTES.
     * 
     * 
     * EL ERROR:  Elementos Valuados cambian de valor una vez ya generada la factura.
     * Esto provoca diferencias entre el MODELO DE GASTOS y EL MODELO CUENTAS CORRIENTES.
     * 
     * CAUSA
     * Estos casos se han dado originado durante la migración desde excel,
     * al cobrar al alumno el valor que figuraba en el excel de pagos,
     * que por error u omisión quedo con valor distinto al que debería cobrarsele,
     * y luego manualmente el valor del item fue corregido dentro del sistema,
     * sin corregir también el sistema de cuentas corrientes ).
     * 
     * SOLUCION:
     * Incluir en cuentas corrientes movimientos para corregir el cambio de valor.
     * Excepciones:
     *      - Los alumnos que ya tengan ajustes previos, serán ignorados.
     * 
     * PROCESO:
     * 
     * - Identificar los alumnos que tienen saldo distinto
     * entre el MODELO DE GASTOS y EL MODELO CUENTAS CORRIENTES.
     * 
     * - Excluir los alumnos que estan en estado BAJA sin fecha de finalización.
     * 
     * - Excluir los alumnos que ya tiene ajustes manuales, 
     * pues no podría identificar a que corresponden esos ajustes.
     * 
     * - Loop de alumnos
     * 
     * - Loop de los Elementos Valuados al alumno, 
     * buscando su correspondiente en el modelo de CUENTAS CORRIENTES.
     * Si es encontrado y son diferentes, se genera un movimiento de ajuste.
     * Si el alumno está en estado BAJA y la fecha de baja es anterior al EV, es ignorado.
     * 
     * 
     * 
     * INPUT
     *      'sedes_id'
     *      'cursos_id'
     *      'anio'
     *       
     * OUTPUT
     *      Las salidas de tipo INFO, son solo documentativas para el resultado.
     *      
     * <array>
     *      'alumnos_de_baja_con_fecha_indicada'=> INFO
     *      'alumnos_de_baja_sin_fecha_indicada'=> INFO
     *      'alumnos_sin_diferencias'           => INFO. Nada que corregirles
     *      'alumnos_con_ajustes_previos'       => INFO. Tiene ajustes manuales anteriores a este proceso.
     *      'alumnos_con_diferencias_resolucion_manual' => INFO. Tiene ajustes, deberá corregirse manualmente.
     *      'alumnos_con_diferencias'           => INFO. Diferencias que reparará este proceso
     *      'alumnos_con_diferencias_detalle'   => Diferencias que reparará este proceso
     *          Este ultimo dato es un array que dice para cada alumno,
     *          que reparaciones debe hacerse. 
     *          Ello se indica en la key de cada subarray:
     *              'poner_a_cero_ev'                       Actualizar gastos
     *              'existe_en_gastos_y_no_en_ctacte'       Actualizar ctacte. Insert.
     *              'modificar_en_ctacte'                   Actualizar ctacte    
     *              'existe_en_ctacte_y_no_en_gastos'       Actualizar ctacte. Eliminar.
     *      
     * 
     */
    public function ajustarDiferenciaEntreValoresAsignadosYValoresDebitados( $sedes_id, $cursos_id, $anio, $soloVerLoQueSeProcesara=false )
    {
        $resultados = array('alumnos_de_baja_con_fecha_indicada'=> array(),
                            'alumnos_de_baja_sin_fecha_indicada'=> array(), 
                            'alumnos_sin_diferencias'           => array(),
                            'alumnos_con_ajustes_previos'       => array(), 
                            'alumnos_con_diferencias_resolucion_manual' => array(),
                            'alumnos_con_diferencias'           => array(),
                            'alumnos_con_diferencias_detalle'   => array(),
                            );
        
        $scxaAlumnosArray = $this->_getScxaAlumnosArray( $sedes_id, $cursos_id, $anio );
        
        foreach( $scxaAlumnosArray as $scxaAlumnoArray ){
            $alumnoId = $scxaAlumnoArray['dni'];
            
            if( in_array( $alumnoId, $this->_alumnosTrabajados ) ){
                continue;
            }else{
                $this->_alumnosTrabajados[] = $alumnoId;
            } 
            

            // check si el alumno está en estado BAJA con fecha previa al EV tratando.
            $fechaBajaUltima = $this->_getfechaBajaUltima( $alumnoId );            
            
            if( $scxaAlumnoArray['finalizo_motivo']!=null && $scxaAlumnoArray['fecha_finalizo']==null  ){
                // No se sabe hasta cuando debe cobrarse. 
                // No obstante, todas las facturas existentes deben coincidir con los valores.
                // Se calcula más abajo.
                $resultados['alumnos_de_baja_sin_fecha_indicada'][]=$this->_getIdAlumno( $scxaAlumnoArray );
            }elseif( $scxaAlumnoArray['fecha_finalizo']!=null  ){
                // si tiene la fecha indicada, todo lo posterior debe ser puesto en cero
                $resultados['alumnos_de_baja_con_fecha_indicada'][]=$this->_getIdAlumno( $scxaAlumnoArray );
            }
            
            
            
            $elementosValuadosDelAlumnoArray = $this->_getElementosValuadosDelAlumno( $alumnoId );
            $movimientosCtaCorrienteOBJS = $this->_getCuentaCorrienteDelAlumno( $alumnoId );

            
            if( $movimientosCtaCorrienteOBJS && $this->_hasAjustes( $movimientosCtaCorrienteOBJS ) ){
                $resultados['alumnos_con_ajustes_previos'][] = $this->_getIdAlumno( $scxaAlumnoArray );
                // Si el total de gastos es igual a total facturado + ajustes, está OK.
                $difTotalesGastosYFacturadoMasAjustes = $this->_diferenciasEntreTotalesDeGastosYFacturadoMasAjustes( $elementosValuadosDelAlumnoArray, $movimientosCtaCorrienteOBJS );
                if( $difTotalesGastosYFacturadoMasAjustes != 0){
                    // Habrá que chequear individualmente en proceso manual.
                    $resultados['alumnos_con_diferencias_resolucion_manual'][] = $this->_getIdAlumno( $scxaAlumnoArray );
                }
                continue;
            }
            
            
            $hayDifTotales = $this->_diferenciasEntreTotalesDeGastosYFacturado( $elementosValuadosDelAlumnoArray, $movimientosCtaCorrienteOBJS );
            //array(2) {
            //  ["control_cruzado_de_cobro"] => int(-8650)
            //  ["control_cruzado_de_cobro_desc"] => string(48) "PodrÃ­a haber items facturados de mÃ¡s por $8650"
            //}  
            // o FALSE si no hay diferencias
            
            $diferencias = $this->_identificarDiferencias( $elementosValuadosDelAlumnoArray, $movimientosCtaCorrienteOBJS, $fechaBajaUltima );
            if( count($diferencias)===0 && !$hayDifTotales ){
                $resultados['alumnos_sin_diferencias'][] = $this->_getIdAlumno( $scxaAlumnoArray );
                continue;
            }else{    
                $resultados['alumnos_con_diferencias'][] = $this->_getIdAlumno( $scxaAlumnoArray );
                $resultados['alumnos_con_diferencias_detalle'][ $this->_getIdAlumno( $scxaAlumnoArray ) ]= $diferencias ;
            }
            
        } // fin loop alumnos
        
        $resultadosFinales = array( 'analisis_previo' => $resultados );
        
        if( $soloVerLoQueSeProcesara ){
            //
        }else{
            $resultadosFinales['procesamiento'] = (!isset($resultados['alumnos_con_diferencias_detalle']))? null :
                    $this->_procesarDiferencias( $resultados['alumnos_con_diferencias_detalle'] );
            //$resultados['alumnos_de_baja'] = array_unique( $resultados['alumnos_de_baja'] );
        }
        
        return $resultadosFinales;
    }
    
    private function _identificarDiferencias( $elementosValuadosDelAlumnoArray, $movimientosCtaCorrienteOBJS, $fechaBaja=null  )
    {
        $diferencias = array();
        
        foreach( $elementosValuadosDelAlumnoArray as $evAlumnoArray  ){
            
            if( $fechaBaja!=null && 
                $evAlumnoArray['valor_final_calculado']>0 &&
                    // y anio-mes de inicio mayor o igual al anio-mes de baja
                substr($evAlumnoArray['fecha_inicio_calculado'],0,7) >= substr($fechaBaja,0,7)
            ){
                $diferencias['poner_a_cero_ev'][] = $evAlumnoArray;
                $evAlumnoArray['valor_final_calculado'] = 0;
            }
            
            $movCtaCteCorrespondiente = $this->getMovCtaCteCorrespondiente( $evAlumnoArray, $movimientosCtaCorrienteOBJS );

            if( !$movCtaCteCorrespondiente ){   // NO EXISTE
                if( $evAlumnoArray['valor_final_calculado']>0 ){ 
                    // ALTA
                    $diferencias['existe_en_gastos_y_no_en_ctacte'][] = $evAlumnoArray;
                }
            }elseif( $evAlumnoArray['valor_final_calculado'] == abs( $movCtaCteCorrespondiente->getMonto() ) ){
                // IGUALES
            }else{
                // MODIFICAR
                $diferencias['modificar_en_ctacte'][]= array( 'ev' => $evAlumnoArray, 'ctacte'=>$movCtaCteCorrespondiente->convertirEnArray() ) ;
            }
        }
        
        // A ELIMINAR
        foreach( $movimientosCtaCorrienteOBJS as $movimientoCtaCorrienteOBJ ){
            if( $movimientoCtaCorrienteOBJ->getTipoOperacion() == 'FACTURA_AUTOMATICA' ){
                $evCorrespondiente = $this->_getEVCorrespondiente( $movimientoCtaCorrienteOBJ, $elementosValuadosDelAlumnoArray );
                if( !$evCorrespondiente ){
                    $diferencias['existe_en_ctacte_y_no_en_gastos'][] = $movimientoCtaCorrienteOBJ ;
                }
            }
        }
        
        return $diferencias;
        
    }
    
    /*
     *              'poner_a_cero_ev'                       Actualizar gastos
     *              'existe_en_gastos_y_no_en_ctacte'       Actualizar ctacte. Insert.
     *              'modificar_en_ctacte'                   Actualizar ctacte    
     *              'existe_en_ctacte_y_no_en_gastos'       Actualizar ctacte. Eliminar.
     * 
     */
    
    private function _procesarDiferencias( $diferencias )
    {
        foreach( $diferencias as $alumnoId => $correccionesAlumno ){
            foreach( $correccionesAlumno as $tipoCorreccion => $items )
            switch( $tipoCorreccion ) {
                case 'poner_a_cero_ev':
                    $this->_ponerACeroEv( $items );
                    break;

                case 'existe_en_gastos_y_no_en_ctacte':
                    $this->_existeEnGastosYNoEnCtacte( $items );
                    break;

                case 'modificar_en_ctacte':
                    $this->_modificarEnCtacte( $items );
                    break;

                case 'existe_en_ctacte_y_no_en_gastos':
                    $this->_existeEnCtacteYNoEnGastos( $items );
                    break;

                default:
                    break;
            }
        }
    }
    
    /*
     * INPUT
     *      <array>
     *          [0]
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
                    'fecha_inicio_calculado
                    'evscxa_valor',
                    'alumnos_id', 
                    'apellido',
                    'nombres',
                    'nombre_espiritual',
                    'valor_modificado',
                    'valor_final_calculado'
                    'fecha_finalizo'
     *          [1]...ETC...
     * 
     */
    private function _ponerACeroEv( $evArray )
    {
        $elementoValuadoAlumnoColeccion = new ElementoValuadoAlumnoColeccion();

        $class = 'ElementoValuadoAlumno';
        $nuevosValores = array( 'valor_modificado'=>0, 'valor_modificado_motivo'=>'X'); //X es BAJA
        foreach( $evArray as $evAlumno ){
            // $
            if( $evAlumno['eva_id'] != null ){
                $evaObj = $elementoValuadoAlumnoColeccion->obtenerPorIdGeneral( $evAlumno['eva_id'], $class );
                $elementoValuadoAlumnoColeccion
                    ->modificacionactualizacionGeneral( $nuevosValores + $evaObj->convertirEnArray(), $class );
            }else{
                // alta
                $elementoValuadoAlumnoColeccion
                        ->altaGeneral(  array(  'elementosvaluados_sedes_cursosxanio_id'    => $evAlumno['evscxa_id'],
                                                'sedes_cursosxanio_alumnos_id'              => $evAlumno['scxaa_id']  
                                                ) + $nuevosValores, 
                                        $class );
            }
        }
    }
    private function _existeEnGastosYNoEnCtacte( $evArray )
    {
        foreach( $evArray as $evAlumno ){
            // Alta en CuentaCorriente
            $this->altaGeneral(  
                    array(  'alumnos_id'    => $evAlumno['alumnos_id'],
                            'tipo_operacion'=> 'FACTURA_AUTOMATICA',
                            'motivo'        => $evAlumno['anio'].', '.$evAlumno['nombre_computacional'].' '.$evAlumno['clasificador_nombre'].' '.$evAlumno['clasificador_valor'].' '.$evAlumno['ev_abreviatura'],
                            'observaciones' => 'CORRECCION_POR_OMISION',
                            'monto'         => -($evAlumno['valor_final_calculado']),
                            'persona_en_caja'=> 'no_tiene',
                            'comprobante_sede'  => null, 
                            'comprobante'       => null, 
                            'fecha_operacion'=> date('Y-m-d'),
                            'usuario_nombre' => $this->_usuarioNombre,

                        ), $this->_class_origen );
        }
    }
    /*
     * Genera un ajuste correctivo por diferencia con EVA
     */
    private function _modificarEnCtacte( $duplasEvArrayCtaCte )
    {
        foreach( $duplasEvArrayCtaCte as $duplaEvArrayCtaCte ){
            
            // Alta en CuentaCorriente de un ajuste
            
            $evAlumnoArray = $duplaEvArrayCtaCte['ev'];
            $ctacteArray = $duplaEvArrayCtaCte['ctacte'];
            
            $motivo = $evAlumnoArray['anio'].','.$evAlumnoArray['nombre_computacional'].' '.
                        $evAlumnoArray['clasificador_nombre'].' '.$evAlumnoArray['clasificador_valor'].' '.
                        $evAlumnoArray['ev_abreviatura'];
            $observaciones ='CORRECCION_POR_DIFERENCIA_DE_VALOR, '.
                            ' nominal '.$evAlumnoArray['valor_final_calculado'].' y cobrado '.
                                abs($ctacteArray['monto']);
            $diferencia = $evAlumnoArray['valor_final_calculado'] + $ctacteArray['monto']; // "monto" es negativo
            
            if( $diferencia < 0 ){
                $ctacteArray['tipo_operacion'] = 'NOTA_CREDITO_AUTOMATICO';
                $ctacteArray['monto'] = abs($diferencia);
            }else{
                $ctacteArray['tipo_operacion'] = 'DEBITO_AUTOMATICO';
                $ctacteArray['monto'] = -($diferencia);
            }
            
            $ctacteArray['motivo'] = $motivo;
            $ctacteArray['observaciones'] = $observaciones;
            $ctacteArray['fecha_operacion'] = date('Y-m-d');
            $ctacteArray['persona_en_caja'] = 'no_tiene';
            $ctacteArray['usuario_nombre']= $this->_usuarioNombre;
            $ctacteArray['comprobante_sede'] = null;
            $ctacteArray['comprobante'] = null;
            unset( $ctacteArray['id'] );
            $this->altaGeneral( $ctacteArray, $this->_class_origen );
        }
    }
    
    private function _existeEnCtacteYNoEnGastos( $items )
    {
        // los items deben ser dados de baja o cancelados
        foreach( $items as $item ){
            $ajuste = array();
            $ajuste['tipo_operacion'] = 'NOTA_CREDITO_AUTOMATICO';
            $ajuste['alumnos_id']   = $item['alumnos_id'];
            $ajuste['motivo']       = $item['motivo'];
            $ajuste['monto']        = -$item['monto'];
            $ajuste['usuario_nombre']= $this->_usuarioNombre;
            //
            $ajuste['observaciones'] = 'CORRECCION_POR_ITEM_NO_CORRESPONDIENTE';
            $ajuste['fecha_operacion'] = date('Y-m-d');
            $ajuste['persona_en_caja'] = 'no_tiene';
            $ajuste['comprobante_sede'] = null;
            $ajuste['comprobante'] = null;
                            
            $this->altaGeneral( $ctacteArray, $this->_class_origen );
        }
    }
    
    
    
    private function _getCuentaCorrienteDelAlumno( $alumnoId )
    {
        return $this->obtenerGeneral( array( 'alumnos_id' => $alumnoId ), 'id', 'CuentaCorriente', 'fecha_operacion' );
    }
    private function _hasAjustes( $movimientosCtaCorrienteOBJS )
    {
        foreach( $movimientosCtaCorrienteOBJS as $movCtaCteOBJ ){
            if( in_array( $movCtaCteOBJ->getTipoOperacion(), $this->_tipo_operacion_que_es_ajuste ) ){
                return true;
            }
        }
        return false;
    }
    
    private function _diferenciasEntreTotalesDeGastosYFacturado( $elementosValuadosDelAlumnoArray, $movimientosCtaCorrienteOBJS )
    {
        // 1) podría usar fns que facilitan mucho ese calculo, pero hacen otros selects,
        // 2) o puedo hacer un bucle en estos 2 parametros y ver así si suman diferencia.
        
        // creo que opto por la 2, es más simple, da menos vueltas,
        // y la puedo usar junto a la fn de Contable ->getIndicadorControlCruzadoEntreGastosYCuentasCorrientes( $totalAsignadoAlumno, $totalFacturadoAlumno )
        
        $totalValoresAsignados = 0;
        foreach( $elementosValuadosDelAlumnoArray as $eva ){
            $totalValoresAsignados+=$eva['valor_final_calculado'];
        }
        $totalFacturadoAlumno   = 0;
        $totalAjustadoAlumno    = 0;
        $totalPagadoAlumno      = 0;
        foreach( $movimientosCtaCorrienteOBJS as $ctacteOBJ ){
            $tipoOperacion = $ctacteOBJ->getTipoOperacion();
            $esAjuste = ( ($tipoOperacion=='NOTA_CREDITO_MANUAL' || $tipoOperacion=='DEBITO_MANUAL' ||
                           $tipoOperacion=='NOTA_CREDITO_AUTOMATICO' || $tipoOperacion=='DEBITO_AUTOMATICO' ))? true : false;
            $totalFacturadoAlumno+=( !$esAjuste && $ctacteOBJ->getMonto()<0)? abs($ctacteOBJ->getMonto()) : 0;
            $totalAjustadoAlumno+=( $esAjuste )? $ctacteOBJ->getMonto() : 0;
            $totalPagadoAlumno+=( !$esAjuste && $ctacteOBJ->getMonto()>0)? $ctacteOBJ->getMonto() : 0;
        }
        $saldoCtaCteAlumno = $totalPagadoAlumno + $totalFacturadoAlumno + $totalAjustadoAlumno;
        
        $resultadoArray = $this->getIndicadorControlCruzadoEntreGastosYCuentasCorrientes( $totalValoresAsignados, $totalFacturadoAlumno, $totalAjustadoAlumno, $totalPagadoAlumno, $saldoCtaCteAlumno  );
        
        return ( (count($resultadoArray)==0 )? false : $resultadoArray );
    }
    
    private function _diferenciasEntreTotalesDeGastosYFacturadoMasAjustes( $elementosValuadosDelAlumnoArray, $movimientosCtaCorrienteOBJS )
    {
        $totalValoresAsignados = 0;
        foreach( $elementosValuadosDelAlumnoArray as $eva ){
            $totalValoresAsignados+=$eva['valor_final_calculado'];
        }
        $totalFacturadoMasAjustesAlumno = 0;
        foreach( $movimientosCtaCorrienteOBJS as $ctacteOBJ ){
            $totalFacturadoMasAjustesAlumno+= ( strpos( $ctacteOBJ->getTipoOperacion(),'PAGO')!==false )? 0 : $ctacteOBJ->getMonto();
        }
        return $totalValoresAsignados + $totalFacturadoMasAjustesAlumno;
    }
        
    /*
     * OUTPUT
     *      key alumnos_id
     *      <array>
     *          [0]
                    'sedes_id', 
                    'anio',
                    'sedes_cursosxanio_id',
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
     *          [1]...ETC...
     * 
     */
    private function _getElementosValuadosDelAlumno( $alumnoId ){
        //return $this->getEVAsDetallado( $alumnoId );
        //return $this->_viewsColeccion->getEVAsConCualquierMontoDeEstosAlumnos( $alumnos_id );
        $viewsContableColeccion = new ViewsContableColeccion();
        $valores = $viewsContableColeccion->getAlumnosValores( 
                $fechaInicio=null, 
                $fechaFin=date('Y-m-d'), 
                $sedeId=0, 
                $soloMayoresACero=false, 
                $excluirCancelados=true,    // por ej al darse de baja
                $otrosWheres="alumnos_id='$alumnoId' " 
                );
        // dado que la fn previa devuelve los valores de muchos alumnos, 
        return ( ( is_array($valores) )? $valores[$alumnoId] : $valores );
    }
       
    
    /*
     * $evAlumnoArray   <array>
     *                          Basicamente precisa que tenga las keys 
     *                      'ev_abreviatura'
     *                      'clasificador_valor'
     *                      'anio'
     * $soloConDeudaPendiente   <boolean>   Permite buscar el item que está
     *                                      con deuda no saldada
     * 
     *  OUTPUT
     *      OBJ de CuentaCorriente  
     *      o
     *      FALSE
     */
    public function getMovCtaCteCorrespondiente(  $evAlumnoArray, $movimientosCtaCorrienteOBJS, $soloConDeudaPendiente=false  )
    {      
        if( !$movimientosCtaCorrienteOBJS ){
            return false;   // no hay movimientos para buscar
        }
        foreach( $movimientosCtaCorrienteOBJS as $ctacteOBJ ){
            $tipoOperacion  = $ctacteOBJ->getTipoOperacion();
            $motivo         = $ctacteOBJ->getMotivo();
            
            if( $tipoOperacion == 'FACTURA_AUTOMATICA' || 
                $tipoOperacion == 'DEBITO_MANUAL' || 
                $tipoOperacion == 'DEBITO_AUTOMATICO' ){
                 if(strpos( $motivo, $evAlumnoArray['ev_abreviatura'] ) !== false    // MAT, CU1, CU2, etc
                    //&& strpos( $motivo, 'profesorado' ) !== false   // "profesorado nivel 1", etc
                    && strpos( $motivo, $evAlumnoArray['nombre_computacional'] ) !== false   // "profesorado", etc
                    //&& strpos( $motivo, 'nivel') !== false   // "profesorado nivel 1", etc
                    && strpos( $motivo, $evAlumnoArray['clasificador_nombre']) !== false   // "nivel 1", etc
                    && strpos( $motivo, $evAlumnoArray['clasificador_valor'] ) !== false   // "1", etc
                    && substr( $motivo, 0, 4 )== $evAlumnoArray['anio']
                ){
                    if( $soloConDeudaPendiente && 
                        $ctacteOBJ->getCobertura() == $ctacteOBJ->getMonto() ){
                        continue;
                    }
                    return $ctacteOBJ;
                }
            }
        }
        return false;
    }
    private function _getEVCorrespondiente( $movimientoCtaCorrienteOBJ, $elementosValuadosDelAlumnoArray )
    {
        foreach( $elementosValuadosDelAlumnoArray as $evAlumnoArray ){
            $tipoOperacion  = $movimientoCtaCorrienteOBJ->getTipoOperacion();
            $motivo         = $movimientoCtaCorrienteOBJ->getMotivo();
            
            if(strpos( $motivo, $evAlumnoArray['ev_abreviatura'] ) !== false    // MAT, CU1, CU2, etc
                && strpos( $motivo, 'profesorado nivel '.$evAlumnoArray['clasificador_valor'] ) !== false   // "profesorado nivel 1", etc
                && substr( $motivo, 0, 4 )== $evAlumnoArray['anio']
            ){
                return $evAlumnoArray;
            }
            
        }
        return false;
    }
    
    
    /*
     * OUTPUT
     * <array>  
                cursos.*, 
                cxa.cursos_id,
                cxa.plan,
                sedes_id, 
                cursosxanio_id, 
                anio, 
                scxaa_id,
                sedes_cursosxanio_id,
                alumnos_id,
                scxaa_fecha_alta,
                fecha_finalizo,
                finalizo_motivo,
                concurriendo,
                alumnos.*
     */
    private function _getScxaAlumnosArray( $sedes_id, $cursos_id, $anio )
    {
        $query = new Query();

        // obtengo scxa_id, cursosxanio_id
        $sql =  "SELECT * FROM view_alumnos_por_sedes_cursos_y_planes ".
                "WHERE sedes_id = $sedes_id ".
                "  AND cursos_id = $cursos_id ".
                "  AND anio = $anio "
                ;
        return $query->ejecutarQuery( $sql );
    }
    
    private function _getAlumnos( $alumnosIds )
    {
        $alumnoColeccion = new AlumnoColeccion();
        return $alumnoColeccion->obtenerGeneral( array('dni'=>$alumnosIds), 'dni', 'Alumno' );
    }
    
    
    private function _getIdAlumno( $scxaAlumnoArray )
    {
        return  $scxaAlumnoArray['dni'].','.
                $scxaAlumnoArray['apellido'].','.
                $scxaAlumnoArray['nombres'];
    }
    
    
    private function _getfechaBajaUltima( $alumnoId )
    {
        $query = new Query();
        $sql = "SELECT MAX( fecha_finalizo ) AS ultima_fecha_de_baja
                FROM view_alumnos_por_sedes_cursos_y_planes
                WHERE alumnos_id = '$alumnoId'
                AND fecha_finalizo IS NOT NULL ";
        $r = $query->ejecutarQuery($sql);
        $fecha = ( count($r)>0 )? $r[0]['ultima_fecha_de_baja'] : false;
        return $fecha;
    }
    
}