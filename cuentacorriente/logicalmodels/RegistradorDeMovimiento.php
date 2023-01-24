<?php

/*
 * 
 */

/**
 * Description of RegistrarCreditoODebito
 *
 * @author mauricio
 */

require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';
require_once 'cuentacorriente/models/CuentaCorrienteErroneoColeccion.php';
require_once 'cuentacorriente/models/CuentaCorrienteElementoValuadoColeccion.php';
require_once 'cuentacorriente/logicalmodels/FuncionesSobreEVAs.php';
require_once 'cuentacorriente/logicalmodels/CoberturaOperaciones.php';
require_once 'cuentacorriente/logicalmodels/ComprobanteDesdeAjnaCentros.php';
require_once 'cuentacorriente/logicalmodels/VerificadorCuentaCorriente.php';
require_once 'cuentacorriente/logicalmodels/RegistradorCreditoFactory.php';
require_once 'admin/logicalmodels/ElementosValuadosExtras.php';
require_once 'admin/models/SedeCursoxanioAlumnoColeccion.php';
require_once 'admin/models/ElementoValuadoSedeCursoxanioColeccion.php';
require_once 'admin/logicalmodels/EvscxaDescripcionModificada.php';
require_once 'admin/models/AlumnoColeccion.php';
require_once 'api/logicalmodels/ApiEmisorNotificacion.php';

require_once 'eventos/eventos/PagoRealizado.php';

require_once 'admin/models/AuditoriaColeccion.php';
require_once 'extensiones/generales/MiMensajero.php'; 

class RegistradorDeMovimiento {
    
    private $_cuentaCorrienteColeccion;
    private $_cuentaCorrienteErroneoColeccion;
    private $_funcionesSobreEVAs;
    private $_coberturaOperaciones;
    private $_verificadorCuentaCorriente;
    private $_ElementoValuadoSedeCursoxanioColeccion;
    private $_EvscxaDescripcionModificada;
    private $_auditoriaColeccion;
    private $_otrosConceptos;
    private $_MiMensajero;
    
    public function __construct( CuentaCorrienteColeccion $CuentaCorrienteColeccion,
                                 FuncionesSobreEVAs $FuncionesSobreEVAs=null ) 
    {
        $this->_cuentaCorrienteColeccion = $CuentaCorrienteColeccion;
        if(!$FuncionesSobreEVAs){
            $FuncionesSobreEVAs = new FuncionesSobreEVAs( USUARIO_SEDE_ID );
        }
        $this->_funcionesSobreEVAs      = $FuncionesSobreEVAs;
        $this->_coberturaOperaciones    = new CoberturaOperaciones( USUARIO_SEDE_ID,
                                                                    $this->_cuentaCorrienteColeccion,
                                                                    $this->_funcionesSobreEVAs
                                                                    );
        $this->_verificadorCuentaCorriente = new VerificadorCuentaCorriente( $CuentaCorrienteColeccion );
        
        $this->_cuentaCorrienteErroneoColeccion = new CuentaCorrienteErroneoColeccion();
        
        $this->_ElementoValuadoSedeCursoxanioColeccion = new ElementoValuadoSedeCursoxanioColeccion();
        $this->_EvscxaDescripcionModificada = new EvscxaDescripcionModificada( $this->_ElementoValuadoSedeCursoxanioColeccion );

        $this->_otrosConceptos = ElementosValuadosExtras::otrosConceptos();
        
        $this->_auditoriaColeccion = new AuditoriaColeccion();
        $this->_MiMensajero = new MiMensajero();
    }
    
    
    
    /*
     * INPUT
     * 
        params  <array>
            array(16) {
              ["module"] => string(5) "admin"
              ["controller"] => string(13) "administrador"
              ["action"] => string(13) "datagridsave&"
              ["sedes_id"] => string(1) "3"
              ["alumnos_id"] => string(3) "865"  o  ["alumnoId"]
              ["planilla"] => string(18) "alumno_items_deuda"
              ["objetoId"] => string(0) ""
              ["seleccion_deuda_item"] => string(5) "34275"
              ["monto"] => string(3) "100"
              ["fecha_operacion"] => string(10) "17/09/2018"
              ["comprobante"] => string(4) "sdaf"
              ["persona_en_caja"] => string(7) "Nashika"
              ["observaciones"] => string(8) "asdfasfd"
              ["simularPago"] => string(1) "0"
              ["permisosDelUsuario"] => array(3) {
                ["usuarioNombre"] => string(9) "LOCALHOST"
                ["rol"] => string(13) "administrador"
                ["sede"] => int(3)
              }
              ["sede"] => string(1) "3"
            }
     * 
     * 
     */
    /*
    public function registrarPagoDesdeDataGrid( $params )
    {
        $otrosDatos = $this->completarDatosPagoDesdeDatagrid( $params );
        if( is_string($otrosDatos) ){
            return array( 'ERROR' => array($otrosDatos) );  // error
        }
        $pagoDatosCompletos = $otrosDatos + $params;

        $simularPago = ( isset($params['simularPago']) && (int)$params['simularPago'] )? true : false;

        return $this->registrarPago( $pagoDatosCompletos, $simularPago );
        // return $this->registrarMovimiento( $pagoDatosCompletos, $params['sedes_id'], $simularPago );
    }
    public function registrarPagoDesdeMigracion( $params )
    {
        $otrosDatos = $this->completarDatosPagoDesdeDatagrid( $params );
        if( is_string($otrosDatos) ){
            return array( 'ERROR' => array($otrosDatos) );  // error
        }
        $pagoDatosCompletos = $otrosDatos + $params;

        $simularPago = ( isset($params['simularPago']) && (int)$params['simularPago'] )? true : false;

        return $this->registrarPago( $pagoDatosCompletos, $simularPago );
        // return $this->registrarMovimiento( $pagoDatosCompletos, $params['sedes_id'], $simularPago );
    }
     * 
     */
    
    public function distribuirTodosLosCreditosLibres( $sedes_id=null )
    {
        $this->_coberturaOperaciones->distribuirTodosLosCreditosLibres( $sedes_id );        
    }
    
    public function distribuirCreditos( $alumnos_id, $anio=null, $impactar=true )
    {
        $this->_coberturaOperaciones->distribuirCreditos( $alumnos_id, $anio, $impactar );        
    }
    
    /*
     * Registra movimientos en la tabla CuentaCorriente.
     * Permite simular la operación para que el operador confirme.
     * 
     * INPUT
     * $params  <array> 
            array(19) {
              ["module"] => string(5) "admin"
              ["controller"] => string(13) "administrador"
              ["action"] => string(12) "datagridsave"
              ["sedes_id"] => string(1) "9"
              ["alumnos_id"] => string(8) "23700502"      o  ["alumnoId"]
              ["planilla"] => string(11) "pago_alumno"
              ["objetoId"] => string(0) ""
     
              ["seleccion_deuda_item"] => array() {
                        [evscxaId] => importe
                        [evscxaId] => importe
                        [evscxaId] => importe
                        "mudras"   => importe
                        "mantras"  => importe
                        "clases"   => importe
                    }
              ["monto"] => string(2) "10" // suma de importes
              ["fecha_operacion"] => string(10) "2018-11-24"
              ["comprobante"] => string(3) "dfs"
              ["comprobante_tipo"]  => "manual" o "automatico"
              ["persona_en_caja"] => string(12) "Laukika Devi"
              ["observaciones"] => string(4) "asdf"
              ["simularPago"] => string(1) "0"
              ["pagoYaSimulado"] => string(1) "0"
              ["permisosDelUsuario"] => array(3) {
                ["usuarioNombre"] => string(9) "LOCALHOST"
                ["rol"] => string(13) "administrador"
                ["sede"] => int(3)
              }
              ["sede"] => string(1) "9"
              ["tipo_operacion"] => string(11) "PAGO_MANUAL"
              ["motivo"] => string(0) ""
            }
     *  simularPago   <boolean>     Default 0(FALSE).
     *           De estar en 1(TRUE) devuelve los datos
     *           como si el pago se hiciese en verdad,
     *           lo que puede permitir al operador,
     *           aceptar o no la operación de pago antes de 
     *           que realmente se lleve a cabo.
     * 
     *           Sería útil cuando se paga más del item indicado,
     *           o cuando el pago es A_CUENTA,
     *           permitiendo que el usuario vea y confirme el pago .
     * 
     * 
     * OUTPUT
     *      <array>             
     *          key => 'ERROR'  => array() descripciones key de los errores.
     *                                      El movmiento no era viable.
     *      
     *      
     *      <array>     'credito' => id del credito
     *                                      Si no hubo débitos a los que asignar el pago.
     * 
     *      ADEMÁS SI SE TRATA DE UN PAGO O NOTA DE CREDITO:
     *      Caso 1:     No paga nada y el importe queda a cuenta:
     *      <array>
     * 
     *      Caso 2:     Paga distintos items.
     *      <array>     
     *          'objetos'       => array, Detalle con los objetos CuentaCorriente débitos trabajados:
     *          'pagos'         => array, Pagos realizados a cada uno.
     *         EJ:
            array(3) {
                  ["objetos"] => array(2) {
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
                    ...
                  }
                  ["pagos"] => array(2) {
                    [41861] => int(1700)
                    [41545] => int(300)
                  }
                  
                }
     * 
     */
    public function registrarMovimiento( $params )
    {
        $simularAccion = ( isset($params['simularPago']) && (int)$params['simularPago'] )? true : false;
        
        $params = $this->_completoValoresDelMovimiento( $params );
    
        // ¿Es alumno de la sede en cuestión o es un administrador?
        $sedeCursoxanioAlumnoColeccion = new SedeCursoxanioAlumnoColeccion();
        $alumnosDeLaSede = $sedeCursoxanioAlumnoColeccion->getAlumnosDeLaSede($params['sedes_id']);
        if( $params['permisosDelUsuario']['rol']!='administrador' &&
            !in_array( $params['alumnos_id'], $alumnosDeLaSede )
            ){
            return ['ERROR'=> $this->_agregarCabeceraDeErrores( 
                                    'El alumno no pertenece a la sede . '.$params['sedes_id'] )
                    ];
        }
        
        // ¿Tiene los datos básicos para registrar el movimiento?
        $viable = $this->esAltaViable( $params );
        if( $viable !== TRUE ){
            if( $params['tipo_operacion'] =='FACTURA_AUTOMATICA' ){
                return true; // seguramente ya se registro
            }
            if( isset($params['origen']) && $params['origen']=='P' 
                && $viable!='EXISTE_EN_RECHAZADOS' ){
                $this->_agregarEnRechazados( $params, $viable );
            }
            return ['ERROR' => $viable ];
        }
        
        // Procesos 
        switch ( $params['tipo_operacion'] ) {
            case 'DEBITO_MANUAL':
            //case 'DEBITO_AUTOMATICO': // AUN NO PASA POR ACA. se resuelve en CuentasCorrientesColeccion
                // Los débitos deben ser montos negativos
                $params['monto'] = ( $params['monto']<0 )? $params['monto'] : -$params['monto'];
                $out = $this->_registrarNotaDebito( $params, $simularAccion );
                $this->_enviarMailSiHayInconsistencias( $params['alumnos_id'] );
                break;
            case 'FACTURA_AUTOMATICA':
                $params['monto'] = ( $params['monto']<0 )? $params['monto'] : -$params['monto'];
                $out = $this->_registrarDebitoAutomatico( $params ); // y distribuye créditos
                break;
            case 'PAGO_MANUAL':
            // case 'PAGO_MIGRACION':  // desuso
            case 'NOTA_CREDITO_MANUAL':
            //case 'NOTA_CREDITO_AUTOMATICO':   // AUN NO PASA POR ACA. se resuelve en CuentasCorrientesColeccion
                $keysRelevantes = [ 'sedes_id',
                                    'alumnos_id',
                                    'tipo_operacion',
                                    'motivo',
                                    'comprobante_mail',
                                    'comprobante_envio',
                                    'comprobante_sede',
                                    'comprobante',  // viene en api pagos desde ajñia centros
                                    'monto',        // viene en api pagos desde ajñia centros
                                    'persona_en_caja',
                                    'usuario_nombre',
                                    'observaciones',
                                    'simularPago',
                                    'pagoYaSimulado',
                                    'fecha_operacion',
                                    'fecha_hora_de_sistema',
                                    'seleccion_deuda_item',
                                    'seleccion_otros_conceptos',
                                    'a_cuenta',
                                    'permisosDelUsuario',
                                    'hay_otro_concepto',
                                    'ctacte_id',    // usado en reasignación de pagos
                                    'origen',
                                    ];                            
                $datosDelMov = arrays_getAlgunasKeys($params, $keysRelevantes);
                $out = $this->_registrarCredito( $datosDelMov, $simularAccion );   
                // La migración ya no se usa:
                // la distribución de los créditos en los débitos, se hace en la parte final
                // de la migración, por eso se pide aquí que no se haga.
                // $out = $this->_registrarPagoMigracion( $params, $distribuirEnDebitos=false );                
                $this->_enviarMailSiHayInconsistencias( $datosDelMov['alumnos_id'] );
                break;
            default:
                $out = ['ERROR'=> $this->_agregarCabeceraDeErrores( 'Movimiento para cuentas corrientes no identificado.' ) ];
                break;
        }
        
        $this->_procesosPostMovimiento( $params, $out );
                
        // Lo quito de rechazados si estaba como tal
        if( $params['origen']=='P' ){
            $this->_eliminarDeRechazados( $params['fecha_hora_de_sistema'] );
        }
        
        return $out;
    }
    
    // $deudaData['evscxa_id']
    private function _procesosPostMovimiento( $pre, $post )
    {
        switch ( $pre['tipo_operacion'] ) {
            case 'DEBITO_MANUAL':
                break;
            case 'DEBITO_AUTOMATICO':
                break;
            case 'FACTURA_AUTOMATICA':
                // Si es referente a una suscripción
                // (si lo quisiese llevar a objeto podría ser,
                // una clase por cada tipo de operación, 
                // una clase por diferentes tipos de movimientos implicantes de los EV,
                // como sería una Suscripcion ).
                $Query = new Query();
                $sql = 'SELECT IF(ev_abreviatura LIKE "SUS%", true, false) es_suscripcion '
                        . 'FROM view_elementosvaluados_por_sedes_cursos_y_planes WHERE evscxa_id = '.$pre['evscxa_id'];
                $resultado = $Query->ejecutarQuery( $sql );
                if( isset($resultado[0]['es_suscripcion']) && (int)$resultado[0]['es_suscripcion'] ){
                    $notificador = new ApiEmisorNotificacion();
                    $notificador->emitirNoticia('notificacion_a_externos_cambio_datos_alumnos');
                }
                break;
            case 'PAGO_MANUAL':
                break;
            case 'NOTA_CREDITO_MANUAL':
                break;
            case 'NOTA_CREDITO_AUTOMATICO':
                break;
            case 'PAGO_MIGRACION':
                break;
            default:
                break;
        }
    }
    
    
    /*
     * OUTPUT
     *      Por ERROR:
     *      <array>             
     *          key => 'ERROR'  => array() descripciones key de los errores.
     *                                      El movimiento no era viable.
     * 
     *      Por OK:
     * 
     *      Caso 1:     No paga nada y el importe queda a cuenta:
     *      <array>
     *          
     * 
     *      Caso 2:     Paga distintos items.
     *      <array>     
     *          'objetos_debito'=> array, Detalle con los objetos CuentaCorriente débitos trabajados:
     *          'pagos'         => array, Pagos realizados a cada uno.
     *          'otros_conceptos' => null o array con las keys de los otros conceptos pagos
     *                              y como valor su importe (excento "formación")
     * 
            array(3) {
                  ["objetos_debito"] => array(2) {
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
                    ...
                  }
                  ["pagos"] => array(2) {
                    [41861] => int(1700)
                    [41545] => int(300)
                  }
       
                  ["evscxa"] => array(2) {
                    [334] => array
                                ctacteid
                                [41861] => int(1700)
                                [41545] => int(300)
                    [335] => array
                                [41545] => int(300)
                  }
       
                  ["credito_disponible"] => int(0)
                  
                }
     * 
     */
    private function _registrarCredito( $datosDelMov, $simularPago=false )
    {
        $RegistradorDeCredito = RegistradorCreditoFactory::getRegistrador( $datosDelMov, $simularPago );
        return $RegistradorDeCredito->registrar();
    }
        
    
    
    /*
     * INPUT
     * 
     * $deudaData
            array{                     
                'alumnos_id'        
                'tipo_operacion'    
                'motivo'            
                'fecha_operacion'   
                'monto'             
                'comprobante'       
                'persona_en_caja'   
                'usuario_nombre'    
                //'observaciones'   
                //'fecha_hora_de_sistema' 
                'permisosDelUsuario'
                'evscxa_id'         
              }
     * $actualizacionCoberturas     <boolean>   Normalmente las coberturas serán actualizadas.
     *                                          Sin embargo, procesos como la migración,
     *                                          actualizan las coberturas al final
     *                                          de todo el proceso de toma de datos.
     * 
     * OUTPUT
     *      Por error:
     *      <array>             
     *          key => 'ERROR'  => array() descripciones key de los errores.
     *                                      El movmiento no era viable.
     *      Por ok:
     *          TRUE
     * 
     */
    private function _registrarDebitoAutomatico( $deudaData )
    {
        // 1° Registro del movimiento.

        $deudaData['id'] = $this->_cuentaCorrienteColeccion->altaGeneral( $deudaData, 'CuentaCorriente' );
        if( !$deudaData['id'] ){
            return ['ERROR'=> $this->_agregarCabeceraDeErrores( 'Error en escritura de deuda regular en CuentaCorriente.' ) ];
        }
        $CuentaCorrienteDebito = new CuentaCorriente( $deudaData );
        
        // Auditoría
        $this->_auditoriaColeccion
                ->registrar( 'alta', 'cuentas_corrientes', $deudaData['id'], 
                            arrays_getAlgunasKeys( $deudaData, $this->_getCamposIndispensables($deudaData['tipo_operacion']) ) );
        
        // Crea registro en tabla de relación
        if( isset( $deudaData['evscxa_id'] ) ){
            $this->_coberturaOperaciones->crearRelacionMovimiento( $CuentaCorrienteDebito, (int)$deudaData['evscxa_id'], $CuentaCorrienteDebito->getMonto() );   // origen débito
        }
        
        // recalcula las coberturas.
        $this->_coberturaOperaciones
                ->distribuirCreditos( $deudaData['alumnos_id'], substr($deudaData['fecha_operacion'],0,4), $impactar=true );
        
        return TRUE;
    }
    
    
    // ESTA FN ESTA EN MODO PARCHE,,,PARA ALGUNAS FUNCIONES DE CUENTACORRIENTECOLECCION  
    public function crearRelacionMovimiento( $CuentaCorriente, $evscxaId, $montoAsignado )
    {
        $this->_coberturaOperaciones->crearRelacionMovimiento( $CuentaCorriente, $evscxaId, $montoAsignado );  
    }    
    
    /*
     * Recibe un importe que debe aplicarse para aumentar la deuda de un item,
     * disminuyendo el monto que se haya pagado, con un máximo de hacerlo llegar a cero.
     * Esa restricción puede saltearse indicando que 
     * la Nota de Débito se ajusta a la cuenta completa ( A_CUENTA ).
     *  $datosDelMov['seleccion_deuda_item']=> array de items o 'A_CUENTA'
     * 
     * INPUT
     *  $datosDelMov <array>
     * 
     * OUTPUT
     *      Por error:
     *      <array>             
     *          key => 'ERROR'  => array() descripciones key de los errores.
     *                                      El movimiento no era viable.
     *      Por ok:
     *          TRUE
     * 
     */
    private function _registrarNotaDebito( $datosDelMov, $simularAccion=false )
    {
        $alumnos_id = $datosDelMov['alumnos_id'];
               
        // CONTROLES PREVIOS
        $itemsValidos = $this->_cuentaCorrienteColeccion->getEvscxaActuales( $alumnos_id, $datosDelMov['anio'] );
        $evscxaIdValidos = ( array_values_recursive(arrays_getAlgunasKeysArrays($itemsValidos, 'evscxa_id')));
        if( count($datosDelMov['seleccion_deuda_item'])==0 && $datosDelMov['a_cuenta']==0 ){
            return [ 'ERROR'=> $this->_agregarCabeceraDeErrores( 'No hay items seleccionados.' ) ];
        }
        if( count($datosDelMov['seleccion_deuda_item'])>0 ){
            if( count(array_diff(array_keys($datosDelMov['seleccion_deuda_item']),$evscxaIdValidos))>0){
                return [ 'ERROR'=> $this->_agregarCabeceraDeErrores( 'Algún item seleccionado no es correcto para aplicar un débito.' ) ];
            }
        }
        if( count($datosDelMov['seleccion_deuda_item'])>1 ){
            return [ 'ERROR'=> $this->_agregarCabeceraDeErrores( 'Indique solo 1 item para aplicar un débito.' ) ];
        }

        if( count($datosDelMov['seleccion_deuda_item'])==0 && $datosDelMov['a_cuenta']<>0 ){
            $datosDelMov['seleccion_deuda_item']='A_CUENTA'; // Por ahora el front no permite a cuenta
        }
        
        $itemValidoId = key( $datosDelMov['seleccion_deuda_item'] );
        
        // El elemento seleccionado, debe existir en el dominio de los posibles.
        if( $datosDelMov['seleccion_deuda_item']!='A_CUENTA' ){
            // Se busca la ctacte a que se quiere aumentar, es decir, 
            // el debito que comenzo la deuda, por ejemplo de una factura.
            $filtroItemCtaCteOrigen ='monto<0 AND evscxa_id = '.$itemValidoId ;
            $debitoDestinoValues = $this->_cuentaCorrienteColeccion 
                                        ->getCuentaCorrienteEvscxa( $alumnos_id, $filtroItemCtaCteOrigen, false );
            // es un débito, solo es un movimiento, me quedo con el primer item del array
            $debitoDestinoValues = ( $debitoDestinoValues && is_array($debitoDestinoValues) )? getPrimero($debitoDestinoValues) : $debitoDestinoValues;
            
            $descripcionNormalizada = $this->_EvscxaDescripcionModificada->getDescripcion( $itemValidoId );
            $datosDelMov['motivo']= ( $descripcionNormalizada )? $descripcionNormalizada : $datosDelMov['motivo'];
        }else{
            // A_CUENTA
            $debitoDestinoValues = null; 
        }
        
        
        // ¿Se encontro que está aumentando la nota de débito?
        if( !is_null($debitoDestinoValues) ){
            /* $debitoDestinoValues
             * 
             * ["cuentas_corrientes_id"] => string(5) "56259"
             * ["alumnos_id"] => string(8) "20586865"
             * ["tipo_operacion"] => string(13) "DEBITO_MANUAL"
             * ["monto"] => string(4) "-100"
             * ["cobertura"] => string(4) "-100"
             * ["motivo"] => string(29) "2018, MAT profesorado nivel 1"
             * ["fecha_operacion"] => string(10) "2019-03-04"
             * ["scxa_id"] => string(3) "222"
             * ["sedes_id"] => string(1) "3"
             * ["anio"] => string(4) "2018"
             * ["cursos_id"] => string(1) "3"
             * ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 1"
             * ["descripcion"] => string(27) "Profesorado de Yoga Curso 1"
             * ["valor_modificado"] => NULL
             * ["valor_final_calculado"] => string(4) "1200"
             * ["ev_abreviatura"] => string(3) "MAT"
             * ["evscxa_id"] => string(4) "2192"
             * ["evscxa_fecha_inicio"] => string(10) "2018-03-01"
             * ["evscxa_valor"] => string(4) "1200"
             * ["ev_numero_de_orden"] => string(1) "1"
             * ["ev_id"] => string(1) "1"
             * ["prioridad_segun_anio"] => string(1) "1"
             * ["scxa_ordenado"] => string(1) "2"
             */
            
            $CuentaCorrienteDebitoDestino = $this->_cuentaCorrienteColeccion
                                                    ->obtenerPorIdGeneral(
                                                        $debitoDestinoValues['cuentas_corrientes_id'], 
                                                        'CuentaCorriente' 
                                                        );
            
            // CONTROLES, (POR AHORA HE QUITADO ESTE CONTROL)
            // si hay un item seleccionado, 
            // sin importar que ya haya pagado todo. 
            // la deuda resultante no debe pasar el precio del item (con su sumatoria de ND y NC),
            // sino, sería en realidad, un cambio de precio.
            // if( !$esViable ){ return $this->_returnErrores(); }
            // FIN CONTROLES

        }else{
            $CuentaCorrienteDebitoDestino = null;
        }

        $debitosTrabajados = array( 'objetos_debito'    => array(), 
                                    'pagos'             => array(), 
                                    'monto'             => 0,
                                    'monto_formacion'   => 0,
                                    'monto_academico'   => 0,
                                    'a_cuenta'          => 0,
                                    );

        // 1° Registro del movimiento inicial.
        if( !$simularAccion ){
            $datosDelMov['id'] = $this->_cuentaCorrienteColeccion->altaGeneral( $datosDelMov, 'CuentaCorriente' );        
            $datosDelMov['motivo']='Nota Débito manual a cuenta. '.date('Y'); //luego actualizaré el texto.
            if( !$datosDelMov['id'] ){
                return ['ERROR'=> $this->_agregarCabeceraDeErrores( 'Error en escritura de nuevo pago en CuentaCorriente.' ) ];
            }
            // Auditoría
            $this->_auditoriaColeccion
                    ->registrar( 'alta', 'cuentas_corrientes', $datosDelMov['id'], 
                                arrays_getAlgunasKeys( $datosDelMov, $this->_getCamposIndispensables($datosDelMov['tipo_operacion']) ) );
        }
        
        // pongo aquí el armado del objeto origen, pues ya obtuve su ID al hacer el alta.
        $CuentaCorrienteDebitoOrigen = new CuentaCorriente( $datosDelMov );

        
        // 2° Distribución del monto.  
        if( $datosDelMov['seleccion_deuda_item']=='A_CUENTA' ){
            
            if( !$simularAccion ){                
                //Intentará distribuir créditos para ver si alguno lo puede pagar.
                $aux = $this->_coberturaOperaciones
                        ->distribuirCreditos(   $datosDelMov['alumnoId'], 
                                                null, // busco créditos de cualquier año.  antes: substr( $datosDelMov['fecha_operacion'], 0, 4 ), 
                                                $impactar=true
                                            );
                // Si algo se proceso, reemplazo los resultados con esos datos.
                // $resultadoDistri = ( count($aux['objetos_debito'])>0 )? $aux : $debitosTrabajados;
            }
        }else{
            $datosDelMov['motivo']= $debitoDestinoValues['motivo'];
                                    //$debitoDestinoValues['nombre_humano'].' '.
                                    //$debitoDestinoValues['ev_abreviatura'];
                                    // ... coincidirá con el ctacte motivo del destino


            $resultadoDistri = $this->_coberturaOperaciones
                                        ->distribuirUnDebitoEnOtroDebito(  
                                                                $CuentaCorrienteDebitoOrigen, 
                                                                $CuentaCorrienteDebitoDestino,
                                                                $debitoDestinoValues['evscxa_id'],
                                                                $simularAccion 
                                                            );
            if( $resultadoDistri && !$simularAccion){            
                // Actualizo el motivo del pago, con todo lo que fue pagado.
                $this->_cuentaCorrienteColeccion->modificacionactualizacionGeneral( $datosDelMov, 'CuentaCorriente' );
                // $auditoriaColeccion->registrar( 'modificacion', 'cuentas_corrientes', $datosDelMov['id'], $antesYDespues );
            }
            
            // Busca si había dinero a cuenta y saldar
            if( !$simularAccion ){                
                //Intentará distribuir créditos para ver si alguno lo puede pagar.
                $aux = $this->_coberturaOperaciones
                        ->distribuirCreditos(   $datosDelMov['alumnoId'], 
                                                null, // busco créditos de cualquier año.  antes: substr( $datosDelMov['fecha_operacion'], 0, 4 ), 
                                                $impactar=true
                                            );
                // Si algo se proceso, reemplazo los resultados con esos datos.
                // $resultadoDistri = ( count($aux['objetos_debito'])>0 )? $aux : $debitosTrabajados;
            }
            
            //ver($resultadoDistri,'$resultadoDistri');
            $debitosTrabajados = $resultadoDistri + $debitosTrabajados;
        }
        $debitosTrabajados['monto']             = $datosDelMov['monto'];
        $debitosTrabajados['monto_academico']   = $datosDelMov['monto'];
        
        // *********************************************************************
        if(!$simularAccion){
            //$this->_repararErroresViejosConCobertura( $alumnos_id );
        }
        
        
        return $debitosTrabajados ;
    }
    
        
    /*
     * OUTPUT
     * TRUE             si es viable
     * <string><array>  si no lo es     errores
     */
    public function esAltaViable( $params )
    {
        if( $params['monto']==0 ){
            return 'NO HAY MONTO INDICADO';
        } 
        
        // tipo de operación válido
        if( !isset($params['tipo_operacion']) || 
            !in_array(  $params['tipo_operacion'], 
                        $this->_cuentaCorrienteColeccion->getTipoOperacionDominio() ) ){
            return 'TIPO_DE_OPERACION_NO_IDENTIFICADA';
        }
        
        
        if( ($errores=$this->verificacionCamposIndispensables( $params )) !==TRUE ){
            return array_merge( array( 'FALTA_CAMPO_INDISPENSABLE' ),( is_array($errores)? $errores : array($errores) ) );
        }
        
        /* motivo dentro del dominio de motivos
        if( !in_array( $params['motivo'], 
            $this->_cuentaCorrienteColeccion->motivosPorTipoOperacion[ $params['tipo_operacion'] ] ) ){
            //return 'MOTIVO_DE_OPERACION_NO_IDENTIFICADO';
        }
         */
                
        // verificación fecha de operación válida. DEBE TENER EXACTAMENTE 10 DIGITOS TOTAL. SINO DA ERROR.
        if( !validateDate( $params['fecha_operacion'] ) ){
            return 'FECHA_DE_OPERACION_NO_VALIDA';
        }
        if( $params['fecha_operacion']>date('Y-m-d') ){
            return 'FECHA_DE_OPERACION_FUTURA';
        }
        
        // Si existe dentro de los rechazados
        if( isset($params['fecha_hora_de_sistema']) ){
            $buscar = [ 'fecha_hora_de_sistema' => $params['fecha_hora_de_sistema'] ];
            $CuentaCorrienteErroneo = 
                    $this->_cuentaCorrienteErroneoColeccion
                        ->obtenerGeneral( $buscar, 'id', 'CuentaCorrienteErroneo', false, true );
            if( $CuentaCorrienteErroneo ){
                return 'EXISTE_EN_RECHAZADOS';
            }
        }
        
        // Si se trata de una reasignación de pago, el id vendrá como dato
        if( key_exists('ctacte_id',$params) 
            && is_numeric($params['ctacte_id']) 
            && $params['ctacte_id']>0 
        ){
            return true;
        }
        
        // verificación unique
        $existe = $this->_cuentaCorrienteColeccion->existeEsteMovimiento( $params );
        if( $existe === 'FALTAN DATOS' ){
            return 'FALTAN_DATOS_PARA_VERIFICAR_UNIQUE';
        }elseif( $existe===true ){
            return 'MOVIMIENTO_YA_REGISTRADO';
        }
        
        /* comprobante válido
         * Los pagos tienen una conformación peculiar .
         * Los pagos necesitan desplegarse, para saber que se pago, y luego poder solicitar
         * a Practicantes el recibo e indicarle que se pago.
         * Lo correcto quizás sería 
         *      solicitar un recibo-id, 
         *      registrar el pago, 
         *      y otra interacción podría informar a Practicantes que pago cada recibo.
         */
        if( $params['origen']=='P' && 
            !$this->_cuentaCorrienteColeccion
                    ->esComprobanteValido( $params['tipo_operacion'], $params['comprobante'] )){
            return array( 'COMPROBANTE_NO_VALIDO', 
                        '( En sede '.$params['comprobante_sede'].' )' );
        }
        
        // Verificación monto . Puede ser positivo o negativo, pero no cero.
        if( !isset($params['monto']) || $params['monto']==0 ){
            return 'SIN _MONTO';
        }
        
        // Si es un pago que proviene de Practicantes vía api,
        // check de que no esté repetido
        if( $params['origen']=='P' && $this->_esRepetido($params) ){
            return 'PAGO_DUPLICADO';
        }
        return true;
    }
    
    
    /*
     * 
     * Para los pagos que llegan desde Practicantes,
     * que pueden venir duplicados desde el sistema de Practicantes.
     * NORMALMENTE SERÁN RECHAZADOS ANTES PUES SUELEN LLEGAR SIN COMPROBANTE(numero de recibo).
     * Pero si tal situación se corrige pero persiste como duplicado,
     * el siguiente filtro debería detenerlos:
     * Si intenta pagar algo que ya está pago y cubierto.
     * O si se intenta pagar el mismo item con casi todos los mismos datos.
     * seleccion_deuda_item: es el elementosvaluados_sedes_cursosxanio_id
     */
    private function _esRepetido( $params )
    {
        if( $params['tipo_operacion']<>'PAGO_MANUAL' || $params['origen']<>'P' ){
            return false;
        }

        $seleccionados=[];
        $otrosConceptos=[];
        foreach( $params['seleccion_deuda_item'] as $evscxa_id ){
            if( !is_numeric($evscxa_id) ){
                $otrosConceptos[]= ElementosValuadosExtras::getAlMenosUnItemDesdeCadena($params['observaciones']);
            }else{
                $seleccionados[]=$evscxa_id;
            }
        }
        $otrosConceptos = (count($otrosConceptos)>0)? array_values_recursive($otrosConceptos) : [];
        
        if( count($seleccionados)>0 && $this->_loQuePagaEstaPago( $params, $seleccionados ) ){
            return true;
        }
        
        if( $this->_existeOtroPagoMuyParecido( $params, $seleccionados, $otrosConceptos ) ){
            return true;
        }
        
        return false;
    }
    
    private function _loQuePagaEstaPago( $params, $seleccionados )
    {
        $deudas = $this->_cuentaCorrienteColeccion->getEvscxaPorSaldar( $params['alumnos_id'] );
        if( !$deudas || !is_array($deudas) || count($deudas)==0 ){
            return true; // no tiene deudas
        }
        if( array_diff( $seleccionados, array_keys($deudas) )>0 ){
            return false; 
        }
        return true; 
    }
    
    // Si existe un pago con timestamp dentro de los 10 minutos(600 segundos),
    // para el mismo alumno, usuario_nombre, persona_en_caja, monto y
    // con intención de pago de los que alguno de los existentes paga,
    // se considera repetido
    private function _existeOtroPagoMuyParecido( $params, $seleccionados, $otrosConceptos )
    {
        if( count($seleccionados)==0 ){
            $ev_sql = '';
        }else{
            $ev_sql = ' AND ctaeva.elementosvaluados_sedes_cursosxanio_id IN ( '.
                    implode(', ', $seleccionados ).' ) ';
        }
        
        $dateTime = $params['fecha_hora_de_sistema'];
        $sql = 'SELECT count(*) AS existe
                FROM yoga_cuentas_corrientes AS cta '.
                ( ($ev_sql<>'')? 'INNER JOIN yoga_cuentascorrientes_elementosvaluados AS ctaeva
                    ON ctaeva.cuentas_corrientes_id = cta.id' : '' ).
                " WHERE 
                    tipo_operacion = 'PAGO_MANUAL'
                    AND fecha_hora_de_sistema BETWEEN 
                        CONCAT( DATE('$dateTime'), ' ', SEC_TO_TIME(TIME_TO_SEC('$dateTime')-600) )
                        AND 
                        CONCAT( DATE('$dateTime'), ' ', SEC_TO_TIME(TIME_TO_SEC('$dateTime')+600) )
                    AND alumnos_id = '".$params['alumnos_id']."'  
                    AND persona_en_caja = '".$params['persona_en_caja']."' 
                    AND monto = ".$params['monto'].
                    $ev_sql.
                ( ( count($otrosConceptos)>0 )? ' AND motivo REGEXP "'.implode('|',$otrosConceptos).'" ' : '' );

        $Query = new Query();
        $resultado = $Query->ejecutarQuery( $sql );

        return ($resultado[0]['existe']=='0')? false : true ;
    }
    
    /*
     * INPUT
     * $params  <array> 
            alumnos_id
            tipo_operacion           
            fecha_operacion 
            monto
            comprobante
            persona_en_caja     
            observaciones
            usuario_nombre      
            fecha_hora_de_sistema
     * 
     * OUTPUT
     *      TRUE si es viable,
     *      <string> <array> con el/los ERROR/es si no lo es
     */
    public function verificacionCamposIndispensables( $params )
    {
        if( !isset( $params['tipo_operacion'] ) ){
            return( array('ERROR_FORM_DATO_FALTA','tipo_operacion') );
        }
        
        $camposIndispensables = 
                $this->_getCamposIndispensables( $params['tipo_operacion'] );
        if( !$camposIndispensables ){
            return( 'ERROR_QUERY_RESULTADO_PARAMETROS_INCORRECTOS' );
        }
        foreach( $camposIndispensables as $campo ){
            // si se trata de un pago de origen ACADEMICO, 
            // el comprobante se obtendrá luego.
            if( $params['tipo_operacion']=='PAGO_MANUAL' && 
                $params['origen']=='A' &&
                $campo=='comprobante' ){
                continue;
            }
            
            if( !isset( $params[ $campo ] ) ){            
                return( array( 'ERROR_FORM_DATO_FALTA', $campo) );
            }
        }
        
        // el ID DNI es válido?
        $alumnoColeccion = new AlumnoColeccion();
        // if( !esDniValido( $params['alumnos_id'] ) ){
        // 2020-09-13 CAMBIO LA CONDICION A SI EL DNI EXISTE
        $existe = $alumnoColeccion->obtenerPorIdGeneral($params['alumnos_id'], 'Alumno', 'dni');
        if( !$existe ){
            return( array(  'ERROR_ALUMNO_ID', '"'.$params['alumnos_id'].'"') );
        }
        
        return true;
    }
    
    private function _getCamposIndispensables( $tipoOperacion )
    {
        return $this->_cuentaCorrienteColeccion->getCamposIndispensablesSegunTipoOperacion( $tipoOperacion );
    }
    
    // Verifica si la cuenta contiene alguna incongruencia
    private function _enviarMailSiHayInconsistencias( $alumnos_id )
    {
        $check = $this->_verificadorCuentaCorriente->getEstadoCuentaCorrienteAlumno( $alumnos_id );
        if( !$check ){
            // Por ahora enviaré un mail a webmaster
            // para que la revise inmediatamente para poder encontrar la causa del error.
            require_once 'default/models/MailToWebmaster.php';
            $mailToWebmaster = new MailToWebmaster();
            $mailToWebmaster->send("La cuenta de alumnos_id: $alumnos_id, presenta inconsistencias");
        }
    }
    
    // Por ahora solo van a rechazados los movimientos que llegan desde Practicantes
    private function _agregarEnRechazados( $params, $errores )
    {
        $erroresString = ( is_array($errores) )? implode(', ', $errores) : $errores;

        $buscar = [ 'fecha_hora_de_sistema' => $params['fecha_hora_de_sistema'] ];
        $CuentaCorrienteErroneo = 
                $this->_cuentaCorrienteErroneoColeccion
                    ->obtenerGeneral( $buscar, 'id', 'CuentaCorrienteErroneo', false, true );
        if( $CuentaCorrienteErroneo ){
            // Actualizo la causa de rechazo
            $values = $CuentaCorrienteErroneo->convertirEnArray();
            $values['error']= $erroresString;
            $this->_cuentaCorrienteErroneoColeccion->modificacionactualizacionGeneral($values, 'CuentaCorrienteErroneo');
        }else{
            $params['error']= $erroresString;
            unset( $params['id'] );
            $id=$this->_cuentaCorrienteErroneoColeccion->altaGeneral( $params,'CuentaCorrienteErroneo');
            $this->_auditoriaColeccion->registrar( 'alta', 'cuentas_corrientes_erroneos', $id, $erroresString );
        }
    }
    
    
    private function _eliminarDeRechazados( $fecha_hora_de_sistema )
    {
        $this->_cuentaCorrienteErroneoColeccion 
                ->eliminarGeneral( ['fecha_hora_de_sistema'=>$fecha_hora_de_sistema] );
    }
    
    
    // OUTPUT array key "ERROR" => array values
    private function _agregarCabeceraDeErrores( $errores )
    {
        $errores = ( is_array($errores) )? $errores :  array( $errores );
        array_unshift( $errores,'ERROR_MOVIMIENTO_NO_PERMITIDO' );// coloco este mje como primero
        return $errores;
    }
    
    private function _getMontoMovimiento( $params )
    {
        $hayPagoAcademico = ( key_exists('seleccion_deuda_item',$params) && count($params['seleccion_deuda_item'])>0 )
                           || ( key_exists('evscxa_id',$params) && is_array($params['evscxa_id']) && ( array_values_recursive(arrays_getAlgunasKeysArrays($params, 'evscxa_id')))>0 );
        $hayPagoDeOtrosConceptos = ( key_exists('seleccion_otros_conceptos',$params) && count($params['seleccion_otros_conceptos'])>0 );
        $hayACuenta = ( key_exists('a_cuenta',$params) && (int)$params['a_cuenta']>0 );
        $hayGeneracionDeudasMensuales = key_exists('evscxa_id',$params);
        $m =   ( $hayPagoAcademico? array_sum( array_values_recursive($params['seleccion_deuda_item'])) : 0 )
                +( $hayPagoDeOtrosConceptos? array_sum( array_values_recursive($params['seleccion_otros_conceptos'])) : 0 )
                +( $hayACuenta? $params['a_cuenta'] : 0 )
                // Y si se trata de generar las deudas mensuales:
                +( $hayGeneracionDeudasMensuales? $params['monto'] : 0 ) // array_sum( array_values_recursive($params['evscxa_id']) ) : 0 )
                ;
        return $m;
    }
    
    private function _completoValoresDelMovimiento( $params )
    {
        $params['alumnos_id']       = ( isset($params['alumnos_id']) )? $params['alumnos_id'] : $params['alumnoId'];
        $params['usuario_nombre']   = USUARIO_NOMBRE;
        $params['fecha_operacion']  = (isset($params['fecha_operacion']))? (fechaEnFormatoYmd( $params['fecha_operacion'], $separadorOutput='-' ) ) : date('Y-m-d');
        $params['comprobante_tipo'] = 'automatico';  // no hay más manual
        $params['comprobante']= ( isset($params['comprobante']) )? $params['comprobante'] : null;
        $params['comprobante_sede'] = ( isset($params['comprobante_sede']))? $params['comprobante_sede'] : $params['sedes_id']; // USUARIO_SEDE_ID;
        $params['comprobante_envio']= (isset($params['comprobanteEnvio']) && $params['comprobanteEnvio']=='1')? '1' : '0';
        $params['comprobante_mail'] = ( $params['comprobante_envio']=='1' 
                                        && !empty($params['Alumno']->getMail()) )? $params['Alumno']->getMail() : '';
        $descripcionNormalizada = ( isset($params['evscxa_id']))? $this->_EvscxaDescripcionModificada->getDescripcion( $params['evscxa_id'] ) : null;
        $params['motivo']= ( $descripcionNormalizada )? $descripcionNormalizada : $params['motivo'];
        
        // key 'formación' informado por api ACentros, ya no sigue como key 'formacion', sino como 'a_cuenta'
        $params['a_cuenta'] = ( key_exists('a_cuenta', $params) )? $params['a_cuenta'] : 
                                ( (key_exists('seleccion_otros_conceptos', $params) && is_array($params['seleccion_otros_conceptos']) && key_exists(ElementosValuadosExtras::defaultFormacion(), $params['seleccion_otros_conceptos']))? $params['seleccion_otros_conceptos'][ElementosValuadosExtras::defaultFormacion()]: 0 );
        unset( $params['seleccion_otros_conceptos'][ElementosValuadosExtras::defaultFormacion()] );
        $params['monto'] = $this->_getMontoMovimiento( $params );        
        return $params;
    }
    
}
