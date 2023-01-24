<?php
/*
 * La idea de esta clase es centralizar las consultas desde otros modulos.
 * Al extender desde CuentaCorrienteColeccion, 
 * se puede invocar sus funciones desde esa clase o esta.
 * Además aquí implementa alguna funciones de consulta para facilitar a los
 * otros modulos.
 * 
 * Este modelo es una extension de CuentaCorrienteColeccion
 * con el objeto de separar conjuntos de información.
 * 
 * CuentaCorrienteColeccion hará los procesos más concretos y relativos
 * a la actualización de las tablas,
 * y
 * ContableColeccion obtendrá datos generales y de consulta del sistema contable.
 * 
 */

require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';
require_once 'admin/models/AlumnoColeccion.php';

/*
 * 
 *
 */
class ContableColeccion extends CuentaCorrienteColeccion
{
    private $_imgPagado         = 'circulo-verdesuave.png';
    private $_imgDeuda          = 'circulo-rojo.png';
    private $_imgDeudaParcial   = 'circulo-amarillo.png';
    private $_imgDeudaFutura    = 'circulo-gris.png';
    private $_imgPagadoMal      = 'eliminar_small.png';
    private $_imgDifCtrolCruzado= 'zoom.png';
    private $_imgOK             = 'ok-mini.png';
    private $_imgOKsuave        = 'editar.png';
    private $_imgsSistema       = '/imagenes/sistema/';
    
    private $_alumnoColeccion;
    
    public function init()
    {
        parent::init();  
        
        $this->_imgsSistema     = $this->BaseUrl.$this->_imgsSistema;
        $imagenWidth='13';
        // armo las imagenes dejando el tag abierto, para luego agregar title
        $this->_imgPagado       = "<img src='".$this->_imgsSistema.$this->_imgPagado."' width='$imagenWidth' ";
        $this->_imgDeuda        = "<img src='".$this->_imgsSistema.$this->_imgDeuda."' width='$imagenWidth' ";
        $this->_imgDeudaParcial = "<img src='".$this->_imgsSistema.$this->_imgDeudaParcial."' width='$imagenWidth' ";
        $this->_imgDeudaFutura  = "<img src='".$this->_imgsSistema.$this->_imgDeudaFutura."' width='$imagenWidth' ";
        $this->_imgPagadoMal    = "<img src='".$this->_imgsSistema.$this->_imgPagadoMal."' width='$imagenWidth' ";
        $this->_imgDifCtrolCruzado = "<img src='".$this->_imgsSistema.$this->_imgDifCtrolCruzado."' width='16' ";
        $this->_imgOK           = "<img src='".$this->_imgsSistema.$this->_imgOK."' width='1$imagenWidth' ";
        $this->_imgOKsuave      = "<img src='".$this->_imgsSistema.$this->_imgOKsuave."' width='26' ";
        
        $this->_alumnoColeccion = new AlumnoColeccion();
    }
    
    public function getAlumnosEVA( $alumnos_id )
    {
        return $this->funcionesSobreEVAs->getEvasData( $alumnos_id );
    }
    
    /**
     * Devuelve array con key alumnos,
     * y sus items contables asignados.  
     * Una key de ellas indica lo pagado hasta el momento para ese item:
     * "calculo_pago_asignado", si no existe está totalmente impago.
     * 
     * Util para generar un cuadro con la situación contable de los alumnos.
     * 
     * Proceso
     * Obtiene la SUMATORIA DE PAGOS, de cada alumno
     * Recorre 1x1 los cobros que debería hacersele, y determina hasta que item ha pagado
     * 
     * IMPORTANTE:
     * Lo que debe pagar, ahora lo estoy calculando en base al valor de cada EV,
     * ( MODELO DE GASTOS)
     * pero lo correcto sería hacerlo segun lo facturado ( MODELO CTASCTES ).
     * El DEBE menos el HABER me daría el monto real del alumno.
     * Pero... a traves de la tabla cuentas_corrientes no puedo relacionar con el item que se paga.
     * Por lo que el resultado de este proceso es más bien orientativo,
     * y no da un resultado exacto.
     * Para eso, habrá que chequear la tabla cuentas corrientes.
     * 
     * 
     * INPUT
     * $alumnos_id  <array>     Opcional. Permite filtrar solo por estos alumnos
     * $filtrar     <array>     Condiciones para filtrar los datos buscados
     *                          Por ejemplo 
     *                          array('sedes_cursosxanio_id'=>$scxaId)
     *                          
     * 
     * OUTPUT
        $itemsAsignadosPorAlumno
        array(2) {
          [14] => array(38) {       // alumnos_id
            [0] => array(15) {
              ["sedes_id"] => string(1) "4"
              ["anio"] => string(4) "2014"
              ["sedes_cursosxanio_id"] => string(1) "5"
              ["evscxa_id"] => string(2) "45"
              ["ev_id"] => string(1) "1"
              ["ev_abreviatura"] => string(3) "MAT"
              ["fecha_inicio_calculado"] => string(10) "2014-03-01"
              ["evscxa_valor"] => NULL
              ["alumnos_id"] => string(2) "14"
              ["apellido"] => string(13) "Baico Neumann"
              ["nombres"] => string(10) "Evelyn Sue"
              ["valor_modificado"] => string(3) "300"
              ["valor_final_calculado"] => string(3) "300"
              ["fecha_finalizo"] => NULL
              ["calculo_pago_asignado"] => string(3) "300"
              ["saldo"] saldo de la cuenta
            }
            [1] => array(15) {
              ["sedes_id"] => string(1) "4"
              ["anio"] => string(4) "2014"
              ["sedes_cursosxanio_id"] => string(1) "5"
              ["evscxa_id"] => string(2) "46"
              ["ev_id"] => string(1) "2"
              ["ev_abreviatura"] => string(3) "CU1"
              ["fecha_inicio_calculado"] => string(10) "2014-04-01"
              ["evscxa_valor"] => NULL
              ["alumnos_id"] => string(2) "14"
              ["apellido"] => string(13) "Baico Neumann"
              ["nombres"] => string(10) "Evelyn Sue"
              ["nombre_espiritual"] => string(13) "Baico Neumann"
              ["valor_modificado"] => string(3) "550"
              ["valor_final_calculado"] => string(3) "550"
              ["fecha_finalizo"] => NULL
              ["calculo_pago_asignado"] => string(3) "550"
              ["saldo"] saldo de la cuenta
            }
            etc ...
            Y AL FINAL DE LOS ITEMS DE CADA ALUMNO, 
            UN DATO EXTRA DE CONTROL CRUZADO
            ["control_cruzado_de_cobro"]        => -2000    
            ["control_cruzado_de_cobro_desc"]   => 'Podría haber items facturados de más'
          * 
            // FLAG de "control_cruzado_de_cobro", se agrega al array devuelto.
            // El monto total de valores asignados, debiera ser igual al total facturado,
            // ( claro, si el alumno no tuvo ajustes en su cuenta corriente ).
            // ASIGNADO - FACTURADO (imputado) debiera dar cero.
            // Dará 0(cero) OK, si ambos coinciden. ( no es necesarío el flag )
            // Dará negativo, si podría ser que algo se facturo de más.
            // Dará positivo, si podría ser que algo se facturo de menos.
            // 
            // Pero si hubo ajustes en alguno de los modelos
            // DE GASTOS o DE CUENTA CORRIENTE
            // y no en los dos a la vez, se rompe el criterio de igualdad.
            // ( Por ejemplo, si algo cambio de valor, 
            // y se ajusto en cuenta corriente, pero no se modifica 
            // el valor nominal de ese concepto eb GASTOS,
            // la igualdad se rompe ).
            // 
            // Lo único que nos deja tranquilos, es si luego de los ajustes,
            // el alumno finaliza con un saldo cero o positivo.
     */
    public function getEVAsDetallado( $alumnos_id=null, array $filtrar=null )
    {  
        //$saldos = $this->getAsignacionDePagosToEVAs( $alumnos_id );
        $saldos = $this->getAlumnosEVA( $alumnos_id );
        
        if( $filtrar ){
            $saldos = $this->_funcionesSobreEVAs->filtrarEVAs( $saldos, $filtrar, true );
        }
        return $saldos;
    }
    

    
    public function getAlumnosSaldos( $alumnos_id )
    {
        return $this->funcionesSobreEVAs->getEVAsYSusValoresAbonados( $alumnos_id );    
    }
    
    public function filtrarConDeuda( array $arrayValues )
    {
        $resultado = array();
        
        foreach( $arrayValues as $key => $values ){
            if( $values['monto'] == $values['cobertura'] ){
                continue;
            }
            $resultado[$key]=$values;
        }
        return $resultado;
    }
    
    
    /*
     * INPUT
     *         $itemsAsignadosPorAlumno = $this->_viewsContableColeccion->getEVAsConCualquierMontoDeEstosAlumnos( $alumnos_id );
     */
    public function sumValorFinalCalculado( $itemsAsignadosPorAlumno )
    {
        $total = 0;
        foreach( $itemsAsignadosPorAlumno as $key => $values ){
            $total+= ( isset($values['valor_final_calculado']) )? $values['valor_final_calculado'] : 0;
        }
        return $total;
    }
    
    
    /*
     * Para 1 alumno,
     * calcula la asignacion de pagos conociendo el total de sus pagos,
     * 
     * INPUT
     * $itemsAsignados  <array> items asignados al alumno en toda su historia
     *                          con detalle de cada uno.
     *                  El modo de calcularlos es:
     *                  $itemsAsignadosPorAlumno = $this->_viewsContableColeccion->getEVAsConCualquierMontoDeEstosAlumnos( array $alumnos_id );
     * OUTPUT
     * $itemsAsignados
     *      Se le agrega una key más, con detalle del monto pagado que le corresponde.
     *      "calculo_pago_asignado" 
     * 
            array(16) {
              [108] => array(16) {
                [21] => array(19) {
                  ["sedes_id"] => string(1) "3"
                  ["anio"] => string(4) "2017"
                  ["sedes_cursosxanio_id"] => string(2) "41"
                  ["nombre_computacional"] => string(11) "profesorado"
                  ["clasificador_nombre"] => string(5) "nivel"
                  ["clasificador_valor"] => string(1) "3"
                  ["evscxa_id"] => string(3) "421"
                  ["ev_id"] => string(1) "1"
                  ["ev_abreviatura"] => string(3) "MAT"
                  ["fecha_inicio_calculado"] => string(10) "2017-03-01"
                  ["evscxa_valor"] => string(3) "700"
                  ["alumnos_id"] => string(3) "108"
                  ["apellido"] => string(6) "Brahim"
                  ["nombres"] => string(13) "Alicia MarÃ­a"
                  ["nombre_espiritual"] => string(0) ""
                  ["valor_modificado"] => NULL
                  ["valor_final_calculado"] => string(3) "700"
                  ["fecha_finalizo"] => NULL
                  ["calculo_pago_asignado"] => string(3) "700"
                }
                [22] => array(19) {
                  ["sedes_id"] => string(1) "3"
                  ["anio"] => string(4) "2017"
                  ["sedes_cursosxanio_id"] => string(2) "41"
     *              .....
     */
    public function getMontosAsignadosDesdePagos( $itemsAsignados, $totalPagado )
    {
        // Agrego al array, el valor abonado para cada item segun el total facturado
        foreach( $itemsAsignados as $key => $values ){

            $asignable = ( $totalPagado > $values['valor_final_calculado'] )? $values['valor_final_calculado'] : $totalPagado;

            $itemsAsignados[$key]['calculo_pago_asignado'] = $asignable;

            $totalPagado-=$asignable;
            if( $totalPagado == 0){     // será igual a cero. No debiera ser nunca menor a cero.   
                break;          // Cambio de alumno.
            }else if( $totalPagado < 0 ){
                // nunca debiera salir por aca, pero lo dejo por si pasa.
                die('ERROR IMPORTANTE en calculo de pagos para el alumno ID='.$values['alumnos_id'] );
            }
        }
        return $itemsAsignados;
    }

    
    
    /*
     * similar salida a getAlumnosEVA() agregando además el campo
     *                   ["calculo_pago_asignado"] => string(3) "700"
     */
    public function getAsignacionDePagosToEVAs( $alumnos_id=null )    
    {
        $alumnos_id = ( is_array( $alumnos_id ) )? $alumnos_id : array( $alumnos_id );

        // Asignación de items
        // Es importante que aquí obtenga todos los items de la historia del alumno
        // y no solo los que pertenecen al año curso en cuestión,
        // pues podría estar omitiendo valores anteriores impagos.
        // Además los items deberán estar ordenados cronologicamente.
        $itemsAsignadosPorAlumno = $this->_viewContableColeccion->getEVAsConCualquierMontoDeEstosAlumnos( $alumnos_id );
        
        $totalesCtaCte = $this->getTotalesPorAlumnoPorTipoOperacion( array( 'alumnos_id'=>$alumnos_id ) );
        /* $totalesCtaCte:
            [549] => array(5) {
              ["FACTURA_AUTOMATICA"] => int(-3900)
              ["PAGO_MIGRACION"] => int(300)
              ["DEBITO_MANUAL"] => int(0)
              ["DEBITO_AUTOMATICO"] => int(0)
              ["NOTA_CREDITO_MANUAL"] => int(0)
              ["NOTA_CREDITO_AUTOMATICO"] => int(0)
              ["PAGO_MANUAL"] => int(0)
            }
            [550] => array(5) {
              ["FACTURA_AUTOMATICA"] => int(-18260)
              ["PAGO_MIGRACION"] => int(11980)
              ["DEBITO_MANUAL"] => int(0)
              ["DEBITO_AUTOMATICO"] => int(0)
              ["NOTA_CREDITO_MANUAL"] => int(0)
              ["NOTA_CREDITO_AUTOMATICO"] => int(0)
              ["PAGO_MANUAL"] => int(0)
            }
         * al final de cada alumno, guardo tambien estos totales como key
              'total_asignado'  sumatoria de todos los valor final calculado
              'facturado_total_por_alumno'
              'pagado_total_por_alumno'
              'ajustado_total_por_alumno'
              'haber_total_por_alumno'
              'control_cruzado_de_cobro'
         */
        
        // Los alumnos que no tienen pagos, no aparecen en $totalesCtaCte['pagado_total_por_alumno'],
        // por lo que algunos campos, como calculo_pago_asignado y el contro cruzado no estarán en la salida.
        foreach( $alumnos_id as $alumno_id ){
            
            //  verifico si tiene movimientos en su cuenta corriente, sino los declaro en cero.
            if( !$totalesCtaCte || !key_exists($alumno_id, $totalesCtaCte) ){
                $totalesCtaCte[ $alumno_id ] = array();
                $totalesCtaCte[ $alumno_id ]['PAGO_MIGRACION'] = 0;
                $totalesCtaCte[ $alumno_id ]['PAGO_MANUAL'] = 0;
                $totalesCtaCte[ $alumno_id ]['NOTA_CREDITO_MANUAL'] = 0;
                $totalesCtaCte[ $alumno_id ]['NOTA_CREDITO_AUTOMATICO'] = 0;
                $totalesCtaCte[ $alumno_id ]['DEBITO_MANUAL'] = 0;
                $totalesCtaCte[ $alumno_id ]['DEBITO_AUTOMATICO'] = 0;
                $totalesCtaCte[ $alumno_id ]['FACTURA_AUTOMATICA'] = 0;
                $totalesCtaCte[ $alumno_id ]['DEBITO_MANUAL'] = 0;
            }
            
            if( key_exists($alumno_id, $itemsAsignadosPorAlumno) ){

                $montoAAsignar =    $totalesCtaCte[ $alumno_id ]['PAGO_MIGRACION'] +
                                    $totalesCtaCte[ $alumno_id ]['PAGO_MANUAL'] +
                                    $totalesCtaCte[ $alumno_id ]['NOTA_CREDITO_MANUAL'] +
                                    $totalesCtaCte[ $alumno_id ]['NOTA_CREDITO_AUTOMATICO'] +
                                    $totalesCtaCte[ $alumno_id ]['DEBITO_MANUAL'] +
                                    $totalesCtaCte[ $alumno_id ]['DEBITO_AUTOMATICO'];  // en realidad este resta

                $saldoCtaCteAlumno = array_sum( $totalesCtaCte[ $alumno_id ] );

                // obtengo la suma de todos los valores finales de los items
                $totalAsignadoAlumno    = $this->sumValorFinalCalculado( $itemsAsignadosPorAlumno[$alumno_id] );


                //////////////////////////////////////////////////////// CTAS CTES ///////////////
                $totalDebitosOriginalesAlumno = $totalesCtaCte[$alumno_id]['FACTURA_AUTOMATICA'];

                $totalAjustadoAlumno    =   $totalesCtaCte[$alumno_id]['DEBITO_MANUAL'] + 
                                            $totalesCtaCte[$alumno_id]['DEBITO_AUTOMATICO'] + 
                                            $totalesCtaCte[$alumno_id]['NOTA_CREDITO_MANUAL'] + 
                                            $totalesCtaCte[$alumno_id]['NOTA_CREDITO_AUTOMATICO'];

                $totalPagadoAlumno      =   $totalesCtaCte[$alumno_id]['PAGO_MIGRACION'] +
                                            $totalesCtaCte[$alumno_id]['PAGO_MANUAL'];
                //////////////////////////////////////////////////////////////////////////////////

                $arrayDifsGASTOS_CTECTE = $this->getIndicadorControlCruzadoEntreGastosYCuentasCorrientes( $totalAsignadoAlumno, $totalDebitosOriginalesAlumno, $totalAjustadoAlumno, $totalPagadoAlumno, $saldoCtaCteAlumno );
                // Verifico si hay diferencias entre los Modelos. GAstos (total asignado) y CTACTE (total facturado)
                if( isset( $arrayDifsGASTOS_CTECTE[ 'control_cruzado_de_cobro' ]) ){
                    // Ajusto la diferencia en correspondencia con las CUENTAS CORRIENTES
                    // si es negativo, hay mas debitado que lo asignado. Lo resto al totalAsignado 
                    // si es positivo, hay mas asignado que debitado. Lo sumo al totalAsignado
                    $montoAAsignar+= $arrayDifsGASTOS_CTECTE[ 'control_cruzado_de_cobro' ];
                }            

                // Agrego el campo "calculo_pago_asignado"
                $itemsAsignadosPorAlumno[ $alumno_id ] = 
                        $this->getMontosAsignadosDesdePagos( $itemsAsignadosPorAlumno[ $alumno_id ], $montoAAsignar );

            }else{
                // el alumno no tiene ninguna data
                $totalAsignadoAlumno = 0;
                $totalDebitosOriginalesAlumno = 0;
                $totalAjustadoAlumno = 0;
                $totalPagadoAlumno = 0;
                $saldoCtaCteAlumno = 0;
            }
        

            $itemsAsignadosPorAlumno[ $alumno_id ]['total_asignado'] = $totalAsignadoAlumno;
            $itemsAsignadosPorAlumno[ $alumno_id ]['total_facturado'] = $totalDebitosOriginalesAlumno;
            $itemsAsignadosPorAlumno[ $alumno_id ]['total_ajustado'] = $totalAjustadoAlumno;
            $itemsAsignadosPorAlumno[ $alumno_id ]['total_haber'] = 0;
            $itemsAsignadosPorAlumno[ $alumno_id ]['total_pagado'] = $totalPagadoAlumno;
            $itemsAsignadosPorAlumno[ $alumno_id ]['saldo_cuenta_corriente'] = $saldoCtaCteAlumno;

            // Asigno el campo de control cruzado entre 
            // GASTOS y CUENTAS CORRIENTES si es que hay diferencias entre ambos
            $itemsAsignadosPorAlumno[ $alumno_id ] = ( isset($arrayDifsGASTOS_CTECTE) && is_array($arrayDifsGASTOS_CTECTE) )? $itemsAsignadosPorAlumno[ $alumno_id ] + $arrayDifsGASTOS_CTECTE : $itemsAsignadosPorAlumno[ $alumno_id ];
            
        }     
        return $itemsAsignadosPorAlumno;        
    }
    
    /*
     * En algunos casos, cuando recien se comienza con el sistema, 
     * y se viene desde migración excel,
     * hubo casos en que algunas modificaciones de valores al alumno,
     * no se representaron correctamente o la lógica era compleja
     * pues alteraba el valor por un lado y no se compensaba por otro.
     * Actualmente, estos casos, en sedes ya asentadas, no deberían presentarse.
     * 
     * Igualmente lo dejo para corregir cualquier diferencia.
     * 
     * Basicamente chequea que todo los valores finales del alumno, (valores asignados),
     * sean iguales a todo lo que debe cobrarsele.
     * 
     * Si no lo es, se ajusta dandole prioridad a las CuentasCorrientes.
     * 
     * OUTPUT
     *      DEVUELVE UN ARRAY  
     *      CON ELEMENTOS SI HAY DIFERENCIAS ENTRE AMBOS MODELOS
     */
    public function getIndicadorControlCruzadoEntreGastosYCuentasCorrientes( $totalValorFinalCalculadoAlumno, $totalDebitosOriginalesAlumno, $totalAjustadoAlumno, $totalPagadoAlumno, $saldoCtaCteAlumno )
    {
        $CTACTE_total_facturado = $totalDebitosOriginalesAlumno+$totalAjustadoAlumno;
        $resultado = array();
        if( $totalValorFinalCalculadoAlumno <> -$CTACTE_total_facturado ){ 
            $resultado[ 'control_cruzado_de_cobro' ] = $totalValorFinalCalculadoAlumno - (-$totalDebitosOriginalesAlumno);
            $resultado[ 'control_cruzado_de_cobro_desc' ] = 
                          'Valores Asignados= <span style="color:yellow">'.$totalValorFinalCalculadoAlumno.'</span>'.
                    ', <br>Débitos originales= '.$totalDebitosOriginalesAlumno.'</span>'.
                    ', <br>DIF= <span style="color:yellow">'.$resultado[ 'control_cruzado_de_cobro' ].'</span>'.
                    ', <br>Ajustes= '.$totalAjustadoAlumno. 
                    ', <br>Débitos finales= <span style="color:yellow">'.($totalDebitosOriginalesAlumno+$totalAjustadoAlumno).'</span>'.
                    ', <br>Pagado= '.$totalPagadoAlumno. 
                    ', <br>Saldo= '.$saldoCtaCteAlumno
                    ;
        }
        return $resultado;
    }
    
    
    /*
     * INPUT 
     * array salida de getAsignacionDePagosToEVAs()
     * 
     * $colocarColorDeuda   Reemplaza la deuda por un color,
     *                      y al saber el valor real de cada EV
     *                      conoce si la deuda es parcial o total,
     *                      con lo que grafica mejor la deuda.
     *      POR CADA FILA:
                array(71) {
                  [786] => array(7) {
                    [2198] => array(26) {
                      ["cuentas_corrientes_id"] => string(5) "43011"
                      ["alumnos_id"] => string(7) "5121986"
                      ["tipo_operacion"] => string(18) "FACTURA_AUTOMATICA"
                      ["monto"] => string(5) "-1900"
                      ["cobertura"] => string(1) "0"
                      ["motivo"] => string(29) "2018, CU6 profesorado nivel 1"
                      ["fecha_operacion"] => string(10) "2018-09-01"
                      ["scxa_id"] => string(3) "222"
                      ["sedes_id"] => string(1) "3"
                      ["anio"] => string(4) "2018"
                      ["cursos_id"] => string(1) "3"
                      ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 1"
                      ["descripcion"] => string(27) "Profesorado de Yoga Curso 1"
                      ["valor_modificado"] => NULL
                      ["valor_final_calculado"] => string(4) "1900"
                      ["pago_asignado"] => NULL
                      ["ev_abreviatura"] => string(3) "CU6"
                      ["evscxa_id"] => string(4) "2198"
                      ["evscxa_fecha_inicio"] => string(10) "2018-09-01"
                      ["evscxa_valor"] => string(4) "1900"
                      ["ev_numero_de_orden"] => string(1) "7"
                      ["ev_id"] => string(1) "7"
                      ["prioridad_segun_anio"] => string(1) "2"
                      ["sum_monto_debitos"] => string(5) "-1900"
                      ["sum_cobertura_debitos"] => string(1) "0"
                      ["sum_saldo_debitos"] => string(5) "-1900"
                    }
     *          etc
     * 
     * OUTPUT
            array(60) {     Para cada alumno da un array como el que sigue:
              [0] => array(15) {
                ["apellido"] => string(5) "Abila"
                ["nombres"] => string(11) "Nelida Elba"
                ["nombres_espiritual"] => string(0) ""
                ["deuda_otros_cursos"] => NULL
                ["deuda_MAT"] => string(75) "<img src="/gestion_estudiantes/public/imagenes/sistema/circulo-rojo.png" />"
                ["deuda_CU1"] => string(76) "<img src="/gestion_estudiantes/public/imagenes/sistema/circulo-verde.png" />"
                ["deuda_CU2"] => string(76) "<img src="/gestion_estudiantes/public/imagenes/sistema/circulo-verde.png" />"
                ["deuda_CU3"] => string(76) "<img src="/gestion_estudiantes/public/imagenes/sistema/circulo-verde.png" />"
                ["deuda_CU4"] => string(76) "<img src="/gestion_estudiantes/public/imagenes/sistema/circulo-verde.png" />"
                ["deuda_CU5"] => string(76) "<img src="/gestion_estudiantes/public/imagenes/sistema/circulo-verde.png" />"
                ["deuda_CU6"] => string(76) "<img src="/gestion_estudiantes/public/imagenes/sistema/circulo-verde.png" />"
                ["deuda_CU7"] => string(76) "<img src="/gestion_estudiantes/public/imagenes/sistema/circulo-verde.png" />"
                ["deuda_CU8"] => string(76) "<img src="/gestion_estudiantes/public/imagenes/sistema/circulo-verde.png" />"
                ["deuda_CU9"] => string(76) "<img src="/gestion_estudiantes/public/imagenes/sistema/circulo-verde.png" />"
                ["deuda_DEX"] => NULL
              }
     */
    public function resumirYTraspolarEnColoresSaldosCurso( 
                            $itemValues, 
                            $saldosAnterioresPorAlumno,
                            $saldosAnioCorriente,
                            $tieneLosNombresAlumnos=false
                            )
    {
        
        if( !$tieneLosNombresAlumnos ){
            $alumnos = $this->_alumnoColeccion->obtenerGeneral( array('dni'=>array_keys($itemValues) ), 'dni', 'Alumno' );
        }
        $traspolado = array();  
        foreach( $itemValues as $alumno_id => $items ){
            $saldoDelAnio = 0;
            // armo 1 fila
            $item = array();
            
            // de la data de cada EV solo me quedo con key => saldo
            foreach( $items as $key => $evValues ){
                
                if( !isset($item['alumnos_id']) ){
                    $item['alumnos_id']         = $alumno_id;
                    $item['apellido']           = ($tieneLosNombresAlumnos)? $evValues['apellido'] : $alumnos[$alumno_id]->getApellido();
                    $item['nombres']            = ($tieneLosNombresAlumnos)? $evValues['nombres'] : $alumnos[$alumno_id]->getNombres();
                    $item['nombre_espiritual']  = ($tieneLosNombresAlumnos)? $evValues['nombre_espiritual'] : $alumnos[$alumno_id]->getNombreEspiritual();
                    $saldoAnterior              = (isset($saldosAnterioresPorAlumno[$alumno_id])? (int)$saldosAnterioresPorAlumno[$alumno_id] : 0 );                    
                    $item[ 'saldo_anterior' ]   = $this->_getSaldoColor( $saldoAnterior );                    
                }
                // si el contenido no es un array, o es apenas un array de una key (alumnos_id), continuo:
                // ( se ha dado en casos de alumnos antiguos, 2014 o 2015, ... )
                if( !is_array($evValues) || count(array_keys($evValues))==1 ){
                    $item[ $key ] = $evValues;
                    continue;
                }
                
                $saldo = ( isset($evValues['sum_saldo_debitos'])? (int)$evValues['sum_saldo_debitos'] : 0 );
                $costoDelItem = ( isset($evValues['valor_final_calculado'])? (int)$evValues['valor_final_calculado'] : 0 );
                $item[ 'deuda_'.$evValues['ev_abreviatura'] ] = $this->_getImgSegunSaldo( $saldo, $costoDelItem );
                $saldoDelAnio+=$saldo;
            }
            $classSaldo = ( $saldoDelAnio>=0 )? 'signo_positivo' : 'signo_negativo';
            $item['saldo_del_anio'] = "<span class='$classSaldo'>".abs($saldoDelAnio).'</span>';
            
            $saldoCorriente = ( isset($saldosAnioCorriente[$alumno_id])? (int)$saldosAnioCorriente[$alumno_id] : 0 );
            $classSaldo = ( $saldoCorriente>=0 )? 'signo_positivo' : 'signo_negativo';
            $item['saldo_cuenta_corriente'] = "<span class='$classSaldo'>".abs($saldoCorriente).'</span>';
            
            $traspolado[]=$item;
        }
        return $traspolado;
    }
    private function _getImgSegunSaldo( $saldo, $costoDelItem=false )
    {
        switch ( $saldo ) {
            case 0:
                $img = $this->_imgPagado." class='divsConTitle' style='padding:4px;' title='Pago $".abs($costoDelItem)."' />";
                break;
            case ($saldo < 0 && $costoDelItem===false):
                $img = $this->_imgDeuda." class='divsConTitle' style='padding:4px;' title='Debe $".abs($saldo)."' />";
                break;
            case ($saldo < 0 && $costoDelItem!==false):
                $img = (( $saldo == -$costoDelItem )? $this->_imgDeuda : $this->_imgDeudaParcial) ." class='divsConTitle' style='padding:4px;' title='Debe $".abs($saldo)."' />";
                break;
            case ($saldo > 0):
                $img = $this->_imgPagado." class='divsConTitle' style='padding:4px;' title='A favor $".$saldo."' />";
                break;
            default:
                //$img = $this->_imgPagado." />";
                break;
        }
        return $img;
    }
    private function _getSaldoColor( $saldo )
    {
        switch ( $saldo ) {
            case 0:
                $texto = $this->_imgPagado." />";
                break;
            case ($saldo > 0):
                $texto = "<span style='color:green;'>$ $saldo</span>";
                break;
            case ($saldo < 0 ):
                $texto = '<span style="color:red;">$ '.abs($saldo).'</span>';
                break;
        }
        return $texto;
    }
    
    
    /*
     * INPUT 
     * array salida de getAlumnosSaldos()
            [10] => array(68) {
                ........
                ... ADEMAS DE MUCHOS CAMPOS DE view_alumnos_valores, TRAE ESTOS:
                ["id"] => string(1) "3"
                ["cursos_id"] => string(1) "3"
                ["sedes_id"] => string(1) "3"
                ["cursosxanio_id"] => string(1) "1"
                ["anio"] => string(4) "2018"
                ["scxaa_id"] => string(4) "2646"
                ["sedes_cursosxanio_id"] => string(3) "222"
                ["alumnos_id"] => string(3) "786"
                ["dni"] => string(3) "786"
                ["apellido"] => string(4) "Arru"
                ["nombres"] => string(5) "Maite"
                ["evscxa_id"] => string(4) "2194"
                ["evscxa_fecha_inicio"] => string(10) "2018-05-01"
                ["fecha_inicio_calculado"] => string(10) "2018-05-01"
                ["ev_id"] => string(1) "3"
                ["ev_abreviatura"] => string(3) "CU2"
                ["evscxa_valor"] => string(4) "1700"
                ["eva_id"] => NULL
                ["valor_modificado"] => NULL
                ["valor_modificado_motivo"] => NULL
                ["valor_final_calculado"] => string(4) "1700"
                ["ctacte_alumnos_id"] => string(3) "786"
                ["relacion_evscxa_id"] => string(4) "2193"
                ["monto"] => string(5) "-1700"
                ["cobertura"] => string(4) "-400"
                ["evscxaa_saldo"] => string(5) "-1300"    // sumatoria desde la tabla de relación
              }
     * 
     * 
     *  OUTPUT:
     *      array(
                [12] => array(10) {
                  ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 1"
                  ["alumnos_id"] => string(3) "786"
                  ["evscxa_id"] => string(4) "2193"
                  ["anio_mes"] => string(7) "2018-04"
                  ["ev_abreviatura"] => string(3) "CU1"
                  ["evscxa_valor"] => int(1700)
                  ["valor_final_calculado"] => int(1700)
                  ["evscxaa_saldo"] => int(-1300)
                  ["evscxaa_deuda"] => int(1300)    // abs
                  ["evscxaa_pago"] => int(400)
                  ["deuda_color"] => string(132) "<img src='/gestion_estudiantes/public/imagenes/sistema/circulo-amarillo.png' width='12'  class='divsConTitle' title='Debe $-1300' />"
                }
                [13] => array(10) {
                  ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 1"
                  ["alumnos_id"] => string(3) "786"
                  ["evscxa_id"] => string(4) "2194"
                  ["anio_mes"] => string(7) "2018-05"
                  ["ev_abreviatura"] => string(3) "CU2"
                  ["evscxa_valor"] => int(1700)
                  ["valor_final_calculado"] => int(1700)
                  ["evscxaa_saldo"] => int(-1700)
                  ["evscxaa_deuda"] => int(1700)    // abs
                  ["evscxaa_pago"] => int(0)
                  ["deuda_color"] => string(128) "<img src='/gestion_estudiantes/public/imagenes/sistema/circulo-rojo.png' width='12'  class='divsConTitle' title='Debe $-1700' />"
                }
     */
    public function resumirItemsDeudaAlumnoEnColores( $saldosUnAlumno )
    {
        $itemsResultado = array(); 
        
        foreach( $saldosUnAlumno as $alumno_id => $items ){
            
            $auxCursoNombre = '';
            $auxAnio = '';
            
            // de la data de cada EV solo me quedo con key => saldo
            foreach( $items as $evValues ){
            
                $itemLimpio = array();
                
                if( $auxCursoNombre == $evValues['nombre_humano'] && 
                    $auxAnio== substr( $evValues['fecha_inicio_calculado'],0,4) ){
                    $itemLimpio['nombre_humano'] = null;
                }else{
                    $itemLimpio['nombre_humano'] = $evValues['nombre_humano'];
                    $auxCursoNombre = $evValues['nombre_humano'];
                    $auxAnio = substr( $evValues['fecha_inicio_calculado'],0,4);
                }
                
                $itemLimpio['alumnos_id'] = $evValues['alumnos_id'];    // $alumno_id
                $itemLimpio['evscxa_id'] = $evValues['evscxa_id'];
                $itemLimpio['anio_mes'] = substr( $evValues['fecha_inicio_calculado'],0,-3);
                $itemLimpio['ev_abreviatura'] = $evValues['ev_abreviatura'];
                $itemLimpio['evscxa_valor'] = (int) $evValues['evscxa_valor'];
                $itemLimpio['valor_final_calculado'] = (int) $evValues['valor_final_calculado'];
                                
                // El saldo negativo es que debe, saldo positivo es que ha dejado a cuenta
                $itemLimpio['evscxaa_saldo'] = (int)$evValues['monto']-(int)$evValues['cobertura'];
                // 
                $itemLimpio['evscxaa_pago'] = abs( $evValues['cobertura'] );
                
                $saldoString = ( $itemLimpio['evscxaa_saldo']>=0 )? 'TODO-PAGADO' :
                            //( ($itemLimpio['evscxaa_saldo']==-$itemLimpio['evscxa_valor'] )? 'DEBE-TODO' : 'DEBE-PARCIAL');
                            ( ($evValues['cobertura']==0 )? 'DEBE-TODO' : 'DEBE-PARCIAL');
                 
                $itemLimpio['evscxaa_deuda'] = ($saldoString=='TODO-PAGADO')? $itemLimpio['evscxaa_saldo'] : abs( $itemLimpio['evscxaa_saldo'] );

                switch ($saldoString) {
                    case 'TODO-PAGADO':
                        $img = $this->_imgPagado." />";
                        break;
                    case 'DEBE-TODO':
                        $img = $this->_imgDeuda." />";
                        break;
                    case 'DEBE-PARCIAL':
                        $img = $this->_imgDeudaParcial." />";
                        break;

                    default:
                        $img = null;
                        break;
                }
                //$itemLimpio[ $evValues['ev_abreviatura'].'_img' ] = $img;
                $itemLimpio['deuda_color'] = $img;
                
                $itemsResultado[]= $itemLimpio;
            }
            
            //ver( $itemsResultado,'$itemsResultado' ); die();
            
            /*
            $item['saldo_cuenta_corriente']= 
                ( isset($item['saldo_cuenta_corriente'])  )? $item['saldo_cuenta_corriente'] : null;
            $classSaldo = ( $item['saldo_cuenta_corriente']>=0 )? 'signo_positivo' : 'signo_negativo';
            $item['saldo_cuenta_corriente'] = '<div class="'.$classSaldo.'">'.$item['saldo_cuenta_corriente'].'</div>';
             * 
             */
            
        }
        //ver($traspolado,'traspo');die();
        return $itemsResultado;
    }
    
    
        
    
}