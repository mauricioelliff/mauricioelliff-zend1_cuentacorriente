<?php
/*
 * CuentaCorrienteColeccion hará los procesos más concretos y relativos
 * a la actualización de las tablas,
 * y
 * ContableColeccion obtendrá datos generales y de consulta del sistema contable.
 * 
 * 
 * 
 * El saldo de una cuenta se obtiene SUM(monto)-SUM(cobertura)
 * de FACTURAS Y AJUSTES
 * 
 * Los ajustes no deben ser indicaciones de pagos, sino correcciones a los 
 * montos que debe abonar el alumno. Disminuciones o aumentos.
 * 
 * Todo ajuste, como Nota de Crédito que hable de pagos, en realidad
 * debiera ser un pago y no una nota de crédito.
 * 
 * 
 * 
 * "motivo"
 *      Es muy útil para indicar a que se refiere el movimiento.
 *      Para generar la row del cobro, es parte del UNIQUE.
 *      Para pagos, no es UNIQUE, puesto que puede haber varios pagos parciales para lo mismo,
 *      o podría ser un pago anticipado que no define concretamente a que refiere.
 * 
 *  
 * 
 * EXPLICACION DEL CONCEPTO DE COBERTURA
    ctacte id		Elemento Valuado	MONTO	COBERTURA   SALDO
    ---------           ----------------        -----   ---------   ------
 
    MOMENTO 1, ingresa una deuda					
    1	CTACTE          CU1                     -100        0       -100

    1	RELACION	CU1                     -100	
	
    MOMENTO 2, se paga la deuda					
            CTACTE				
    1                   CU1                     -100        -100     0
    2                   CU1                      100         100     0

            RELACION				
    1                   CU1                     -100		
    2                   CU1                      100		

    MOMENTO 3, ingresa una nueva deuda					
            CTACTE				
    3                   CU2                     -100        0       -100
            RELACION				
    3                   CU2                     -100		

    MOMENTO 4, se paga la deuda y se deja a cuenta					
            CTACTE				
    3                   CU2                     -100        -100     0
    4                   CU2                      150         100     50

            RELACION				
    4                   CU2                     100		
					
	cuando ingrese un movimiento siguiente, 
        se buscará algun saldo restante que se pueda asignar para pagarlo				
 * 
 * 
 */

require_once 'ColeccionParaEsteProyecto.php';
require_once 'cuentacorriente/models/CuentaCorriente.php';
require_once 'cuentacorriente/models/RelacionEntreModuloGastoYModuloCuentaCorriente.php';

require_once 'admin/models/ElementoValuadoColeccion.php';
require_once 'admin/models/ElementoValuadoSedeCursoxanioColeccion.php';
require_once 'admin/logicalmodels/EvscxaDescripcionModificada.php';

require_once 'cuentacorriente/logicalmodels/FuncionesSobreEVAs.php';
require_once 'cuentacorriente/logicalmodels/Cobertura.php';
// require_once 'cuentacorriente/logicalmodels/CoberturaOperaciones.php';
require_once 'cuentacorriente/logicalmodels/RegistradorDeMovimiento.php';

require_once 'cuentacorriente/logicalmodels/PagoDesasignador.php';
require_once 'cuentacorriente/logicalmodels/PagoAnulador.php';

require_once 'default/models/Query.php';
require_once 'admin/models/AuditoriaColeccion.php';

/*
 * 
 *
 */
class CuentaCorrienteColeccion extends ColeccionParaEsteProyecto
{

    protected $_name    = 'yoga_cuentas_corrientes';
    protected $_id      = 'yoga_cuentas_corrientes_id';

    protected $_class_origen = 'CuentaCorriente';
    
    public $tipoOperacionDominio;
    public $altaNoViableMotivos;
    
    public $motivosPorTipoOperacion;
    
    public $funcionesSobreEVAs;
    
    protected $tiposEnColumnaDebe;
    protected $tiposEnColumnaHaber;


    protected $_prefijoParaComprobantes;
    
    
    protected $_cobertura;
    //private $_coberturaOperaciones; // privada porque haría requireloop si ella utiliza ctasctes
    
    const DESC_CANCELACION_CURSO = 'alumno_cancelado_del_curso';
    
    private $_evasDelAlumno;
    
    private $_ElementoValuadoSedeCursoxanioColeccion;
    private $_EvscxaDescripcionModificada;
        
    public function init()
    {
        parent::init();  
        
        $this->tipoOperacionDominio =
                array(
                        'FACTURA_AUTOMATICA', 
                        'DEBITO_MANUAL',        
                        'DEBITO_AUTOMATICO',        
                        'NOTA_CREDITO_MANUAL',
                        'NOTA_CREDITO_AUTOMATICO',
                        'PAGO_MANUAL',
                        'PAGO_MIGRACION',
                    );
        $this->tiposEnColumnaDebe =
                array(
                        'FACTURA_AUTOMATICA',   
                        'DEBITO_MANUAL',        
                        'DEBITO_AUTOMATICO',    
                    );
        $this->tiposEnColumnaHaber = 
                array(
                        'PAGO_MANUAL',
                        'PAGO_MIGRACION',
                        'NOTA_CREDITO_MANUAL',
                        'NOTA_CREDITO_AUTOMATICO',
                );
                
        
        $this->altaNoViableMotivos =
                array(
                    'FALTA_CAMPO_INDISPENSABLE',
                    'TIPO_DE_OPERACION_NO_IDENTIFICADA',
                    'MOTIVO_DE_OPERACION_NO_IDENTIFICADO',
                    'FECHA_DE_OPERACION_NO_VALIDA',
                    'SIN_MONTO',
                    'FALTAN_DATOS_PARA_VERIFICAR_UNIQUE',
                    'MOVIMIENTO_YA_REGISTRADO'
                );
        
        $this->motivosPorTipoOperacion = $this->getMotivoDominioSegunTipoOperacion();
        
        //
        $this->_prefijoParaComprobantes = 
                array( 
                    'FACTURA_AUTOMATICA'=>  null, 
                    'PAGO_MIGRACION'   =>  null, 
                    );
        
        $this->funcionesSobreEVAs = new FuncionesSobreEVAs( USUARIO_SEDE_ID );
        
        $this->_cobertura = new Cobertura();
        
        $this->_ElementoValuadoSedeCursoxanioColeccion = new ElementoValuadoSedeCursoxanioColeccion();
        $this->_EvscxaDescripcionModificada = new EvscxaDescripcionModificada( $this->_ElementoValuadoSedeCursoxanioColeccion );
        
    }
    
    public function getTipoOperacionDominio()
    {
        return $this->tipoOperacionDominio;
    }
    
    public function getTipoOperacionDebe(){
        return $this->tiposEnColumnaDebe;
    }
    
    public function getTipoOperacionHaber(){
        return $this->tiposEnColumnaHaber;
    }
    
    
    public function getPrefijosParaComprobantes( $tipoOperacionBuscado=false )
    {
        if( $tipoOperacionBuscado ){
            return ( isset($this->_prefijoParaComprobantes[$tipoOperacionBuscado]) )? $this->_prefijoParaComprobantes[$tipoOperacionBuscado] : false;
        }
        return $this->_prefijoParaComprobantes;
    }

    
    public function getMotivoDominioSegunTipoOperacion( $motivoBuscado=false )
    {
        $dominio = array();
        
        $elementoValuadoColeccion        = new ElementoValuadoColeccion();        
        
        $evAbreviaturas = array_keys( $elementoValuadoColeccion->obtenerGeneral( null, 'abreviatura', 'ElementoValuado' ) );
        
        $dominio['FACTURA_AUTOMATICA']  = $evAbreviaturas;
        $dominio['DEBITO_MANUAL']       = array( 'CORRECCION', 'MODIFICACION' );
        $dominio['DEBITO_AUTOMATICO']   = array( 'CORRECCION', 'MODIFICACION' );
        $dominio['NOTA_CREDITO_MANUAL'] = array( 'CORRECCION', 'MODIFICACION', 'PAGADO_RECIBO_PERDIDO' );
        $dominio['NOTA_CREDITO_AUTOMATICO'] = array( 'CORRECCION', 'MODIFICACION' );
        $dominio['PAGO_MANUAL']        = array_merge( array( 'A CUENTA' ), $evAbreviaturas );
        $dominio['PAGO_MIGRACION']     = array_merge( array( 'A CUENTA' ), $evAbreviaturas );
        
        if( $motivoBuscado ){
            return ( isset($dominio[$motivoBuscado]) )? $dominio[$motivoBuscado] : false;
        }
        return $dominio;
    }
    
    
    /*
     * Indica que campos son indispensables para cualquier alta
     */
    public function getCamposIndispensablesSegunTipoOperacion( $tipo_operacion )
    {
        $indispensables = array();
        
        switch ( $tipo_operacion ) {
            case 'PAGO_MANUAL':
                $indispensables = array( 'alumnos_id','tipo_operacion','motivo','fecha_operacion','comprobante','persona_en_caja','usuario_nombre' );
                break;
            case 'PAGO_MIGRACION':
            case 'DEBITO_MANUAL':
            case 'DEBITO_AUTOMATICO':
            case 'NOTA_CREDITO_MANUAL':
            case 'NOTA_CREDITO_AUTOMATICO':
                $indispensables = array( 'alumnos_id','tipo_operacion','motivo','fecha_operacion','monto','persona_en_caja','usuario_nombre' );
                break;
            case 'FACTURA_AUTOMATICA':
                $indispensables = array( 'alumnos_id','tipo_operacion','motivo','fecha_operacion','monto' ); // los datos que faltan los genera
                break;
            default:
                // no tiene datos indispensables.
                return false;
        }
        return $indispensables;
    }
    

    

    
    
    /*
     * estos campos de ser iguales, identifican un intento de alta duplicada
     */
    public function getCamposUniqueDefault()
    {
        return array(   'alumnos_id', 
                        'tipo_operacion', 
                        'motivo',   // los pagos parciales se indicarán concatenando: motivo + "_" + yyyy-mm-dd hh:mm:ss
                        'fecha_operacion', 
                        'monto', 
                        'comprobante_sede',
                        'comprobante' 
                    );
    }
    
    /*
     * Indica que campos son indispensables para cualquier alta
     * 
     */
    public function getCamposUniqueSegunTipoOperacion( $tipo_operacion )
    {
        $camposDefault = $this->getCamposUniqueDefault();
        $r = $camposDefault;
        
        switch ( $tipo_operacion ) {
            case 'PAGO_MANUAL':
            case 'DEBITO_MANUAL':
            case 'DEBITO_AUTOMATICO':
            case 'NOTA_CREDITO_MANUAL':
            case 'NOTA_CREDITO_AUTOMATICO':
                unset( $r[ array_search('comprobante_sede', $r ) ] );
                break;
                
            
            case 'FACTURA_AUTOMATICA':
                unset( $r[ array_search('fecha_operacion', $r ) ] );
                unset( $r[ array_search('monto', $r ) ] );
                unset( $r[ array_search('comprobante_sede', $r ) ] );
                unset( $r[ array_search('comprobante', $r ) ] );
                break;
            
             case 'PAGO_MIGRACION':
                 // los pagos por migracion en verdad, no deberían ser rebotados.
                 
                unset( $r[ array_search('comprobante', $r ) ] );    // El comprobante es una constante en caso de que falte. No puede ser con un numero correlativo adicional, pues no me sirve como campo unique. Podría no distinguir un mismo pago, pues quedaría con distinto comprobante.
                unset( $r[ array_search('comprobante_sede', $r ) ] );    
                unset( $r[ array_search('monto', $r ) ] );
                break;
                        
           default:
                break;
        }
        return $r;
    }
    
    
    
    public function esComprobanteValido( $tipoOperacion, $comprobante )
    {
        if( $tipoOperacion=='PAGO_MANUAL' && is_null($comprobante) ){
            return false;
        }
        return true;
    }
    
    
    /*
     * Antes de asentar cualquier movimiento,
     * busco si ya está asentado 
     * 
     * INPUT
     * $params  <array> 
            array(19) {
                ... entre otros valores,  llegan  ...
     
              ["seleccion_deuda_item"] => array(3) {
                [0] => string(4) "2221"         Indica los EVSCXA_ID que desea pagar
                [1] => string(4) "2222"
                [2] => string(8) "A_CUENTA"    indica que ha seleccionado algun item que tienen evscxa_id en null
                }
     
                o, si no ha seleccionado items, y a seleccionado A_CUENTA:
              ["hay_otro_concepto"] => string(8) "A_CUENTA"

              ["monto"] => string(2) "10"
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
     * 
     * OUTPUT
     *      <string>    o...        'FALTAN DATOS'      para poder responder
     *      <boolean>   o...        FALSE,              no se encontro nada
     */
    public function existeEsteMovimiento( $params )
    {
        $keysUniqueSegunTipoOperacion = $this->getCamposUniqueSegunTipoOperacion( $params['tipo_operacion'] );

        $keysYValuesAValidar = arrays_getAlgunasKeys( $params, $keysUniqueSegunTipoOperacion );
        // ¿están los datos minímos para hacer la verificación?
        if( ( count($keysYValuesAValidar) != count( $keysUniqueSegunTipoOperacion ) ) ){
            return 'FALTAN DATOS';
        }
        
        // Búsqueda simple por campos que pudieran ser iguales dentro 
        // de la propia tabla de cuentas corrientes. 
        // Evalua el motivo, pero debería evaluar por cierta similitud
        $existe = $this->obtenerGeneral( $keysYValuesAValidar, 'id', $this->_class_origen );
        
        // Búsqueda por el evscxa_id
        $existeSegunOperacion = $this->existeSegunTipoOperacion($params);
        return ( $existe || $existeSegunOperacion );
    }
    
    /*
     * Búsqueda con más lógica
     */
    public function existeSegunTipoOperacion( $mov )
    {
        $existe = false;
       
        switch ( $mov['tipo_operacion'] ) {
            case 'PAGO_MANUAL':
            case 'DEBITO_MANUAL':
            case 'DEBITO_AUTOMATICO':
            case 'NOTA_CREDITO_MANUAL':
            case 'NOTA_CREDITO_AUTOMATICO':
            case 'PAGO_MIGRACION':
                break;
            case 'FACTURA_AUTOMATICA':
                $existe = $this->_existeFacturaDeScxaId( $mov['alumnos_id'], $mov['evscxa_id'] );
                break;
            default:
                break;
        }
        return $existe;
    }
    
    // Verifica si el alumno, tiene una factura con el mismo código de evscxa
    // Devuelve el ID de cuenta corriente o FALSE
    private function _existeFacturaDeScxaId( $alumnos_id, $evscxa_id )
    {
        $sql = 'SELECT A.id FROM yoga_cuentas_corrientes AS A '
                . 'INNER JOIN yoga_cuentascorrientes_elementosvaluados AS B '
                . ' ON B.cuentas_corrientes_id = A.id '
                . 'WHERE A.alumnos_id = "'.$alumnos_id.'" '
                . ' AND A.tipo_operacion = "FACTURA_AUTOMATICA" '
                . ' AND elementosvaluados_sedes_cursosxanio_id = '.$evscxa_id;
        $Query = new Query();
        $values = $Query->ejecutarQuery( $sql );
        return ( isset($values[0]['id']) )? (int)$values[0]['id'] : false;
    }
    
    /*
     * Antes deberá preguntarse si el comprobante es automático o no,
     * ya que los automáticos si pueden repetirse, 
     * ya que en un pago se pueden generar varias transacciones.
     */
    public function existeComprobante( $sedes_id, $comprobante )
    {
        if( is_null($sedes_id) || is_null($comprobante) ){
            return false;
        }
        $buscar = array( 'comprobante_sede' => $sedes_id, 'comprobante' => $comprobante );
        $existe = $this->obtenerGeneral( $buscar, 'id', $this->_class_origen );
        return $existe;
    }
    
    
    
    /*
     * Modifica los valores de la row pasada, con el objeto recibido
     * Invocada desde la fn altaGeneral en Coleccion.php
     */
    public function actualizarRow( $row, $objeto )
    {
        if( $objeto->getId() ){
            $row->id = $objeto->getId();   
        }
        $row->origen                = $objeto->getOrigen();
        $row->alumnos_id            = $objeto->getAlumnosId();
        $row->tipo_operacion        = $objeto->getTipoOperacion();
        $row->motivo                = $objeto->getMotivo();
        $row->fecha_operacion       = $objeto->getFechaOperacion();
        $row->monto                 = $objeto->getMonto();
        $row->comprobante_sede      = $objeto->getComprobanteSede();
        $row->cobertura             = $objeto->getCobertura();
        $row->comprobante           = $objeto->getComprobante();
        $row->persona_en_caja       = $objeto->getPersonaEnCaja();
        $row->observaciones         = $objeto->getObservaciones();
        $row->usuario_nombre        = $objeto->getUsuarioNombre();
        $row->fecha_hora_de_sistema = $objeto->getFechaHoraDeSistema();
        
        $row->save();
        
        return $row->id;
    }
        
    
    
    /*
     * CON EL CONCEPTO DE COBERTURA, ESTA FN HA QUEDADO EN DESUSO POR AHORA
     * 
     * Devuelve el objeto que podría ser corregido por el usuario.
     * La condición es que ese último movimiento debe ser del día actual.
     */
    public function obtenerUltimoMovimientoEditable( $alumnoId )
    {
        $ultimoMovArray = $this->obtenerGeneral( 
                                    array(  'alumnos_id' => $alumnoId, 
                                            'DATE(fecha_hora_de_sistema) = CURDATE()',
                                            'tipo_operacion IN ( "NOTA_CREDITO_MANUAL", "DEBITO_MANUAL", "NOTA_CREDITO_AUTOMATICO", "DEBITO_AUTOMATICO", "PAGO_MANUAL" )'
                                        ), 
                                    'id','CuentaCorriente', 'fecha_hora_de_sistema DESC', null, null, null, 1 );
        $ultimoMovObjeto = ( $ultimoMovArray )? getPrimero($ultimoMovArray) : false ;
        return $ultimoMovObjeto;
    }
    
    
    /*
     * El motivo no es solo una descripción textual del movimiento. Es muy importante.
     * Ya que es uno de los campos que trabaja en el UNIQUE
     * evitando repeticiones de cobros.
     */
    public function getMotivoNormalizado(  
                                        $nombreHumano,
                                        $nombreComputacional, 
                                        $clasificadorNombre, 
                                        $clasificadorValor,
                                        $anio,
                                        $abreviatura
                                        )
    {
        $motivo = $anio.', '.$abreviatura.', ';
        
        // Voy a dejar de poner los strings para poder rastrear las facturas en 
        // base a su string técnico, que era útil cuando no tenía la tabla
        // cuentascorrientes_elementosvaluados. Si no hay problemas,
        // ya luego podre quitar todo estos comentarios. (27-04-2021)
        // $motivo.= ( $nombreComputacional=='tecnicatura' )? $nombreHumano.', ' : '';
        // $motivo.= $nombreComputacional.' '.$clasificadorNombre.' '.$clasificadorValor;
        // (27-04-2021)
        
        $motivo.= $nombreHumano;
        return $motivo;
    }
    
    
    
    private function _alta( $params )
    {
        $params['fecha_hora_de_sistema']= datetimeMicroseconds(); //date('Y-m-d H:i:s');       
        
        $id = $this->altaGeneral( $params, $this->_class_origen, 'id' );
        if( !$id ){
            $this->addColeccionMensajes( 'ERROR_QUERY_RESULTADO_GENERAL');
            return false;
        }
        
        // auditoria
        $auditoriaColeccion              = new AuditoriaColeccion();
        $a = $auditoriaColeccion->registrar( 'alta', 'cuentas_corrientes', $id, $params );
        if( !$a ){
            $this->addColeccionMensajes( 'ERROR_en registro de auditoria');
        }

        return $id;
    }
    
    
    
    
    /*
     * INPUT
     * $wheres  array con las condiciones extras
     * 
     * OUTPUT
            [257] => array(2) {
              ["FACTURA_AUTOMATICA"] => (-10100)
              ["PAGO_MIGRACION"] => (9500)
            }
            [258] => array(3) {
              ["FACTURA_AUTOMATICA"] => (-11500)
              ["NOTA_CREDITO_MANUAL"] => (2800)
              ["NOTA_CREDITO_AUTOMATICO"] => (2800)
              ["DEBITO_MANUAL"] => (2800)
              ["DEBITO_AUTOMATICO"] => (2800)
              ["PAGO_MANUAL"] => (1400)
              ["PAGO_MIGRACION"] => (4650)
            }
            [259] => array(3) {
              ["FACTURA_AUTOMATICA"] => (-5300)
              ["NOTA_CREDITO_MANUAL"] => (550)
              ["PAGO_MIGRACION"] => (1080)
            }
     * 
     */
    public function getTotalesPorAlumnoPorTipoOperacion( $wheres=false, $crearTiposVaciosConValorCero=true )
    {
        $select = $this->select();
        $select ->setIntegrityCheck(false)  //es importante colocar esto
                ->from( array( 'cuentacorriente'   => $this->_name ),
                        array( 'alumnos_id', 'tipo_operacion', 'sumatoria' => 'SUM(monto)'));
        if( $wheres ){
            $select = $this->construirElWhere( $select, $wheres );
        }
        $select->group( array('alumnos_id','tipo_operacion') );
        //print($select); die();

        $filasArray =  $this->fetchAll( $select )->toArray();

        if( !$filasArray || count($filasArray)==0 ){
            return false;
        }
        
        // preparo una salida, con un formato más simple,   key alumnos_id => array totales
        $resultado = array();
        foreach( $filasArray as $filaArray ){
            $alumnos_id = $filaArray['alumnos_id'];
            if( !isset( $resultado[ $alumnos_id ] ) ){
                $resultado[ $alumnos_id ] = array();
            }
            $resultado[ $alumnos_id ][$filaArray['tipo_operacion']] = $filaArray['sumatoria'];
        }
        if( $crearTiposVaciosConValorCero ){
            return $this->completarTipoMovimientoFaltantes( $resultado );
        }
        return $resultado;
    }
    public function completarTipoMovimientoFaltantes( $array )
    {
        foreach( $array as $alumnoId => $tipoMovimientoValues ){
            $tiposEnSuCtaCte = array_keys( $tipoMovimientoValues );
            $tiposExistentes = $this->tipoOperacionDominio;
            $faltantes = array_diff( $tiposExistentes, $tiposEnSuCtaCte );
            foreach( $faltantes as $tipo ){
                $array[$alumnoId][$tipo]=0;
            }
        }
        return $array;
    }
    
    
    
    /*
     * Devuelve un array con el saldo de los alumnos y sus datos.
     * Saldo:
     * Positivo, indica que tiene dinero en su cuenta
     * Negativo, que está adeudando
     * junto a toda la data que proviene de la tabla de alumnos
     * 
     */
    public function getSaldoYDatosAlumno( array $wheres=null )
    {
        $select = $this->select();
        $select ->setIntegrityCheck(false)  //es importante colocar esto
                ->from( array( 'alumnos'   => 'yoga_alumnos' ), '*' )
                ->joinLeft
                    (
                        array(  'cuentacorriente'   => $this->_name ),
                        'alumnos.dni = cuentacorriente.alumnos_id',      //union
                        array( 'saldo' => 'SUM(monto)')
                    ); 
        if( $wheres ){
            $select = $this->construirElWhere( $select, $wheres );
        }
        // print($select); 
        $resultado =  $this->fetchAll( $select )->toArray();
        return ( ( !$resultado || count($resultado)==0 )? false : $resultado );
    }
    
    /*
     * OUTPUT
     *  saldo   
     */
    public function getSaldoAlumno( $alumnos_id )
    {
        $select = $this->select();
        $select ->setIntegrityCheck(false)  //es importante colocar esto
                ->from( array( 'cuentacorriente'   => $this->_name ), 
                        array( 'saldo' => 'SUM(monto)') 
                        )
                ->where( "alumnos_id = '$alumnos_id' " )
                ;
        $resultado =  $this->fetchRow( $select ); // PHP7, Zend_Db_Table_Row no es countable
        return ( !($resultado instanceof Zend_Db_Table_Row)? false : $resultado->saldo );
    }
    public function getCoberturaAlumno( $alumnos_id )
    {
        $select = $this->select();
        $select ->setIntegrityCheck(false)  //es importante colocar esto
                ->from( array( 'cuentacorriente'   => $this->_name ), 
                        array( 'cobertura' => 'SUM(cobertura)') 
                        )
                ->where( "alumnos_id = '$alumnos_id' " )
                ;
        $resultado =  $this->fetchRow( $select );
        return ( ( !$resultado || is_array($resultado) && count($resultado)==0 )? false : $resultado->cobertura );
    }
    
    
    /*
     * Devuelve los objetos CuentaCorriente con signo negativo.
     * 
     * ATENCION: NO CONFUNDIR ESTA FUNCION CON getItemsDebitadosData
     * 
     * INPUT
     * $wheres           opcional   Default "monto < 0"
     */
    public function getItemsFacturados( array $wheres=null )
    {
        $wheres = ( is_array($wheres) )? $wheres : array();
        $wheres[] = 'monto < 0';
        return $this->obtenerGeneral( $wheres, 'id', $this->_class_origen );
    }
    
    
        
        /*
     * Genera 1 movimiento de signo opuesto, que totaliza a los buscados.
     * 
     * INPUT            
     *      'buscar'  <array>
     *                      'alumnos_id'
     *                      'sedes_id'
     *                      'cursos_id'
     *                      'anio' 
     *                      'sedes_cursosxanio_id'
     *                      'tipo_de_movimientos'  <string>    'facturados' | 'pagados'
     *                              // es mejor que no tenga default, para evitar errores por ausencia
     *       'otro_motivo_identificador' es un texto que suele ayudar a identificar más la operación y el registro creado
     * 
     * OUTPUT
     *  
     *      0    todo OK o no hubo nada por hacer
     *      FALSE   algo está mal o no es viable hacer una cancelación.
     * 
     */
    public function cancelarMovimientosDeAlumnoCurso( $buscar, $otro_motivo_identificador )
    {
        
        // Busco datos y nombres del curso en cuestión, que necesitaré luego:
        $viewCursos = new ViewSedeCurso();
        $buscar2 = $buscar;
        unset($buscar2['alumnos_id']);
        unset($buscar2['tipo_de_movimientos']);
        if( isset($buscar2['sedes_cursosxanio_id']) ){
            $buscar2['scxa_id']=$buscar2['sedes_cursosxanio_id'];
            unset($buscar2['sedes_cursosxanio_id']);
        }
        foreach( $buscar2 as $key => $value){
            if( is_null($value) ){
                unset( $buscar2[$key] );
            }
        }        
        $dataCursoArrays = $viewCursos->getSedeCursos( $buscar2 );
        if( !$dataCursoArrays ){
            // FALTA ALGUN DATO, EL CURSO NO SE ENCUENTRA
            return false;
        }
        $dataCursoValues = getPrimero( $dataCursoArrays );
        // fin busqueda de datos.
        
        $buscar['sedes_id'] = $dataCursoValues['sedes_id'];
        $buscar['cursos_id']= $dataCursoValues['cursos_id'];
        $buscar['anio']     = $dataCursoValues['anio']; // podría no llegar al principio
        $buscar['sedes_cursosxanio_id']= $dataCursoValues['sedes_cursosxanio_id'];
        $buscar['sedes_id']= $dataCursoValues['sedes_id'];
        // ["plan"] => string(4) "2010"
        
        // CHECKS
        $cancelacionMotivos = array( 'facturados', 'pagados' );
        if( !in_array( $buscar['tipo_de_movimientos'], $cancelacionMotivos ) ){
            $this->addColeccionMensajes( 'ERROR_ webmaster en cancelarMovimientosDeAlumnoCurso(), no hay indicación de parametros.tipo_de_movimientos' );
            return false;
        }
        
        // Hay algo en BD que impida cancelar la facturación del alumno?
        $viable = $this->esViableCancelarLaFacturacionAutomaticamente(
                                                        $buscar['alumnos_id'],
                                                        $dataCursoValues['scxa_id']
                                                        );
        if( !$viable ){
            $this->addColeccionMensajes( array( 'INFO_', 'No puede cancelarse la facturación automáticamente. Necesita una corrección manual desde Sistemas.' ) );
            return false;
        }
        // FIN CHECKS

        /*
         * IMPORTANTE:
        No puedo eliminar rows en la tabla de CtasCtes,
        ni puedo basarme solo en el año, ya que puede haber movimientos por otra cuestión.
        Puesto que no hay una relación directa a que corresponde concretamente cada movimiento.
        Cuando mucho, hay un campo texto que me orienta.
        Si el alumno tiene pagos, el proceso deberá hacerse manualmente.
        No habiendo pagos en ese año, 
        buscaré rows donde el campo motivo correspondan a la descripción
        anio + clasificador_nombre + clasificador_valor + ev_abreviatura
         */
        
        
        
        ////////////////////////////////////////////////////////////////////////
        $auxBuscar = array( 'alumnos_id' => $buscar['alumnos_id'], 
                            'SUBSTR( fecha_operacion, 1, 4 ) = "'.$buscar['anio'].'" ',
                            'motivo LIKE ("%'.$dataCursoValues['nombre_computacional'].' '.
                                            $dataCursoValues['clasificador_nombre'].' '.
                                            $dataCursoValues['clasificador_valor'].'%")',
                            (($buscar['tipo_de_movimientos']=='facturados')? 'monto < 0' : 'monto > 0' ),
                            'monto <> cobertura'    // pendientes de cancelación
                            );
        $ctacteObjetosAAnular = $this->obtenerGeneral( $auxBuscar, 'id', $this->_class_origen
        //para ver el query final                                   ,false,false,false,false,false,false,true
                                                    );
       
        if( !$ctacteObjetosAAnular ){
            return 0;    // no hay movimientos a corrregir
        }
        ////////////////////////////////////////////////////////////////////////
        $auxWheres = $buscar;
        unset( $auxWheres['tipo_de_movimientos'] ); // lo quito pues no está en los campos de la view
        $evACancelar = $this->funcionesSobreEVAs->getEvaToCancel( $auxWheres );
        if( !$evACancelar ){
            //            die('Error. No tiene definidos EVAS pero tiene facturaciones. Avise a Sistemas sobre este alumno.');
            //            return true;    // no hay EV a corrregir
        }
        ////////////////////////////////////////////////////////////////////////
        
        // Calculo del monto a anular
        $montoAnular = 0;
        foreach( $ctacteObjetosAAnular as $CuentaCorriente ){
            $montoAnular+= $CuentaCorriente->getSaldo();
        }
      
        /*
        $motivosACancelar = $this->_getMotivosACancelar( $evACancelar ); // array de descripciones
        $ctaCteQueCorrespondeALosMotivos = $this->_getCtaCteQueCorrespondeAMotivos( $ctacteObjetosAAnular, $motivosACancelar );
         */
        
        $registrar = array( 'alumnos_id'        => $buscar['alumnos_id'],
                            'tipo_operacion'    => 'NOTA_CREDITO_AUTOMATICO',        
                            'monto'             => -$montoAnular,
                            'motivo'            => 'CANCELAR CURSO DEL ALUMNO. '.$this->construirMotivoTexto($buscar).self::DESC_CANCELACION_CURSO.' '.$otro_motivo_identificador,
                            'fecha_operacion'   => date('Y-m-d'), 
                            'comprobante_sede'  => null,
                            'comprobante'       => null,
                            'persona_en_caja'   => 'proceso_automatico',
                            'usuario_nombre'    => USUARIO_NOMBRE,
                            'observaciones'     => 'Cancelación curso '.$buscar['anio'].'. NC automática para cancelar todas sus deudas.'
                                                    //implode(', ', $motivosACancelar )
                        );
        $registrar['sedes_id']= $buscar['sedes_id'];
        $registrar['permisosDelUsuario']= $this->variablesEnSessionRecuperar('permisosDelUsuario' );
        // $evscxaACancelar
        $registrar['seleccion_deuda_item']=                 
                array_keys( $this->_ElementoValuadoSedeCursoxanioColeccion->getArrayConDataElementosValuados( $dataCursoValues['sedes_cursosxanio_id'] ) );

        $resultado = $this->registrarMovimiento( $registrar );
        if( $resultado && !key_exists('ERROR',$resultado) ){
            return count( $resultado['pagos'] );
        }else{
            return false;
        }
    }
    public function esViableCancelarLaFacturacionAutomaticamente( $alumnos_id, $sedes_cursosxanio_id )
    {
        // importante: mantener el orden de los parametros.
        $inputs = array( $alumnos_id, $sedes_cursosxanio_id );

        return $this->mysqlBooleanFunction( 
                                    'esViableCancelarLaFacturacionAutomaticamente', 
                                    $inputs 
                                );
    }
    /*
    private function _getMotivosACancelar( $evACancelar )
    {
        $motivosBuscados = array();
        foreach( $evACancelar as $valuesEVA ){
            $motivosBuscados[]= $this->construirMotivoTexto( $valuesEVA );
        }
        return $motivosBuscados;
    }
    private function _getCtaCteQueCorrespondeAMotivos( $ctacteDelAlumnoAnio, $motivosACancelar )
    {
        $resultados = array();
        
        foreach( $ctacteDelAlumnoAnio as $CuentaCorriente ){
            foreach( $motivosACancelar as $motivoACancelarString ){
                if( $this->estaElMotivo1DentroDeMotivo2( $CuentaCorriente->getMotivo(), $motivoACancelarString ) ){
                    $resultados[]=$CuentaCorriente;
                    break;
                }
            }
        }
        return $resultados;
    }
    public function estaElMotivo1DentroDeMotivo2( $motivo1, $motivo2 )
    {
        // hacer una evaluación correcta es bastante complejo.
        // Ya que habría que analizar palabras compuestas como "profesorado nivel 2" por ejemplo.
        // Haré que simplemente todos los elementos del $motivo1 estén en el $motivo2
        $array1 = $this->_convertirMotivoEnArray( $motivo1 );
        $array2 = $this->_convertirMotivoEnArray( $motivo2 );
        
        return ( count( array_diff($array1, $array2 )==0 )? true : false ); // si hay valores de 1 que no están en 2, devuelve false
    }
    private function _convertirMotivoEnArray( $motivoString )
    {
        return array_filter( explode( ',', str_replace(' ', ',', $motivoString ) ) );
    }
     * 
     */
    
    
    public function moverCtaCteDeUnAlumnoAOtro( $alumnosIdOrigen, $alumnosIdDestino )
    {
        $query = new Query();
        // $query->actualizar( $TABLA, array $SET, string $WHERE )
        $query->actualizar( 'yoga_cuentas_corrientes', 
                            array('alumnos_id'=>$alumnosIdDestino), 
                            'alumnos_id = "'.$alumnosIdOrigen.'" '
                                    //.' AND tipo_operacion NOT LIKE "%FACTURA%" ' 
                            );
    }

    
    
    
    
    public function tieneCancelacionDelCurso( $alumnoId, $anio, $otroIdentificadorDeBusqueda='' )
    {
        $buscar = array('alumnos_id'        => $alumnoId ,
                        'tipo_operacion'    => 'NOTA_CREDITO_AUTOMATICO',
                        'motivo LIKE "%'.$anio.'%'.
                                        self::DESC_CANCELACION_CURSO.'%'.
                                        $otroIdentificadorDeBusqueda.'%"',
                        );
        $existe = $this->obtenerGeneral( $buscar, 'id', $this->_class_origen, false, true );
        return( $existe )? $existe : false;
    }
    public function eliminarCancelacionDelCurso( $alumnoId, $anio, $sedes_id  )
    {
        $existe = $this->tieneCancelacionDelCurso( $alumnoId, $anio );
        if( $existe ){
            // $this->eliminarGeneral( array( 'id' => $existe->getId() ) );
            $nuevoMovimiento = $existe->convertirEnArray();
            $nuevoMovimiento['tipo_operacion']= // invierto el movimiento
                    (strpos($existe['tipo_operacion'],'CREDITO')!==false)? 'DEBITO_AUTOMATICO' : 'NOTA_CREDITO_AUTOMATICO' ;
            $nuevoMovimiento['monto']= $existe['tipo_operacion']*(-1);
            $nuevoMovimiento['motivo']= str_replace( self::DESC_CANCELACION_CURSO , 'Se anula '.self::DESC_CANCELACION_CURSO, $existe['motivo'] );
            //$this->altaGeneral( $nuevoMovimiento, 'CuentaCorriente' );

            $registrar = array( 'alumnos_id'        => $nuevoMovimiento['alumnos_id'],
                                'tipo_operacion'    => $nuevoMovimiento['tipo_operacion'],        
                                'monto'             => $nuevoMovimiento['monto'],
                                'motivo'            => $nuevoMovimiento['motivo'],
                                'fecha_operacion'   => $anio.'-03-01', // por general, los cursos comienzan en esa fecha
                                'comprobante_sede'  => null,
                                'comprobante'       => null,
                                'persona_en_caja'   => 'proceso_automatico',
                                'usuario_nombre'    => USUARIO_NOMBRE,
                                'observaciones'     => 'Cancelación de la cancelación.'
                                                        //implode(', ', $motivosACancelar )
                            );
            
            $registrar['sedes_id']= $sedes_id;
            $registrar['permisosDelUsuario']= $this->variablesEnSessionRecuperar('permisosDelUsuario' );
            if( $this->registrarMovimiento( $registrar ) ){
                return true;
            }
        }
        return false;
    }    

    
    /*
     * El motivo, es un dato MUY IMPORTANTE,
     * Dado que entre el sistema de EV y Cobros, no hay una relación de 1a1,
     * este dato ayuda muchas veces a encontrar esta relación.
     * Por eso es muy importante mantener uniforme el criterio de como se construye
     * para saber siempre que texto buscar.
     * 
     * El criterio general, será concatenando estos campos en este orden,
     * y usando un espacio en blanco como separador:
     *      anio
     *      nombre_computacional        ( curso )
     *      clasificador_nombre         ( curso )
     *      clasificador_valor          ( curso )
     *      ev_abreviatura
     * 
     * Demás datos corren ya por cuenta de cada proceso, y sería conveniente
     * colocarlos en la parte final.
     * 
     * He cometido el error de armar este dato con pequeñas variantes,
     * de a poco esto debería lograr ser homogeneo para todos los procesos.
     */        
    public function construirMotivoTexto( $params )
    {
        $texto = '';
        $separador = ' ';
        $texto.= ( isset($params['anio']) )? $params['anio'].$separador: '';
        $texto.= ( isset($params['nombre_computacional']) )? $params['nombre_computacional'].$separador: '';
        $texto.= ( isset($params['clasificador_nombre']) )? $params['clasificador_nombre'].$separador: '';
        $texto.= ( isset($params['clasificador_valor']) )? $params['clasificador_valor'].$separador: '';
        $texto.= ( isset($params['ev_abreviatura']) )? $params['ev_abreviatura'].$separador: '';
        $texto.= ( isset($params['ev_descripcion']) )? '('.$params['ev_descripcion'].')'.$separador: '';
        
        return $texto;
    }
    
    
    /*
     * OUTPUT
     *  Todos los objetos cuenta corriente donde la fecha_operacion refiere al año solicitado,
     *  como también si esa fecha se encuentra dentro del motivo.
     */
    public function getCuentaCorrienteAlumnoAnio( $alumnos_id, $anio=null, $otrosWhere=null )
    {
        // Obtendrá tanto los que se procesaron en el año buscado,
        // como también, los que en la descripción refieran a ese año.
        $buscar = array( 'alumnos_id' => $alumnos_id );
        if( $anio ){
            $buscar[] = 'SUBSTR( fecha_operacion, 1, 4 ) = '.$anio.' OR '.
                        'motivo LIKE CONCAT("%", '.$anio.' , "%" )';
        }
        $wheres = ($otrosWhere)? ( is_array($otrosWhere)? $otrosWhere : array($otrosWhere) ) : array();

        return $this->obtenerGeneral( $buscar+$wheres, 'id', 'CuentaCorriente', 'fecha_operacion');
    }
    
    public function getDebitosDelAlumno( $alumnos_id=null, $coberturaPendiente=false, $anio=null )
    {
        $filtro = array( "monto < 0" );
        if( $alumnos_id ){
            $filtro[]= "alumnos_id = '$alumnos_id'";
        }
        if( $coberturaPendiente ){
            $filtro[]= 'monto < cobertura ';
        }
        if( $anio ){
            $filtro[]= "SUBSTR( fecha_operacion, 1, 4 ) = '$anio'";
        }
        return $this->obtenerGeneral( $filtro, 'id', 'CuentaCorriente', 'fecha_operacion');
    }
    public function getCreditosDelAlumno( $alumnos_id=null, $coberturaPendiente=false, $anioDeOperacion=null )
    {
        $filtro = array( "monto > 0" );
        if( $alumnos_id ){
            $filtro[]= "alumnos_id = '$alumnos_id' ";
        }
        if( $coberturaPendiente ){
            $filtro[]= 'monto > cobertura ';
        }
        if( $anioDeOperacion ){
            $filtro[]= "SUBSTR( fecha_operacion, 1, 4 ) = '$anioDeOperacion'";
        }
        return $this->obtenerGeneral( $filtro, 'id', 'CuentaCorriente', 'fecha_operacion');
    }
    
    
    /*
     * Obtiene todas los evscxa con cobertura pendiente.
     * Si se pasa $evscxa_id, pondrá esos items al principio de la lista.
     * 
     * $evscxa_id   <array> or <int>    Opcional. default null
     *              id de los items que se precisan
     * 
     * Dado que van agrupados por evscxa_id, mucha data que contiene cada array
     * es innecesaria o no aplicable (ya que se origino en items particulares).
     * 
     * OUTPUT
     * array(3) {
     *   [12310824] => array(25) {      // dni
     *     [6319] => array(31) {        // evscxa_id
     *       ["cuentas_corrientes_id"] => string(6) "101768"
     *       ["fecha_hora_de_sistema"] => string(26) "2021-06-25 17:02:20.597900"
     *       ["alumnos_id"] => string(8) "12310824"
     *       ["tipo_operacion"] => string(18) "FACTURA_AUTOMATICA"
     *       ["monto"] => string(5) "-4200"
     *       ["cobertura"] => string(1) "0"
     *       ["motivo"] => string(22) "Nivel 4, Cuota 1, 2021"
     *       ["fecha_operacion"] => string(10) "2021-04-01"
     *       ["scxa_id"] => string(3) "593"   
     *       ["sedes_id"] => string(1) "3"
     *       ["anio"] => string(4) "2021"   
     *       ["cursos_id"] => string(1) "6"   
     *       ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 4"   
     *       ["descripcion"] => string(27) "Profesorado de Yoga Curso 4"   
     *       ["valor_modificado"] => NULL   
     *       ["valor_final_calculado"] => string(4) "4200"   
     *       ["ev_abreviatura"] => string(3) "CU1"   
     *       ["ev_descripcion"] => string(7) "Cuota 1"   
     *       ["evscxa_id"] => string(4) "6319"   
     *       ["evscxa_fecha_inicio"] => string(10) "2021-04-01"   
     *       ["evscxa_valor"] => string(4) "4200"   
     *       ["ev_numero_de_orden"] => string(1) "6"   
     *       ["ev_id"] => string(1) "2"   
     *       ["prioridad_segun_anio"] => string(1) "1"   
     *       ["scxa_ordenado"] => string(1) "2"   
     *       ["sum_monto_debitos"] => string(5) "-4200"   
     *       ["sum_cobertura_debitos"] => string(1) "0"   
     *       ["sum_saldo_debitos"] => string(5) "-4200"   
     *       ["sum_monto_debitos_a_cuenta"] => string(1) "0"   
     *       ["sum_cobertura_debitos_a_cuenta"] => string(1) "0"   
     *       ["sum_saldo_debitos_a_cuenta"] => string(1) "0"   
     *     }   
     *     
     */
    public function getEvscxaPorSaldar( $alumnos_id, $evscxa_id=null )
    {   
        return $this->getPorSaldar( $alumnos_id, $evscxa_id, $cortePorEvscxaId=TRUE );
    }
    
    /*
     * Obtiene todo lo de cobertura incompleta (referente a deudas)
     * 
     * $evscxa_id   <array> or <int>    Opcional. default null
     *              id de los items que se priorizan
     *      <array> key evscxa_id => monto
     * 
     * OUTPUT
     *    key por cuentas_corrientes_id
     * 
     *         array(2) {
     *           [42213] => array(20) {         
     *             ["cuentas_corrientes_id"] => string(5) "42213"  *************
     *             ["alumnos_id"] => string(3) "865"
     *             ["monto"] => string(5) "-1700"
     *             ["cobertura"] => string(5) "-1000"
     *             ["motivo"] => string(29) "2018, CU4 profesorado nivel 2"
     *             ["valor_final_calculado"] => string(4) "1700"
     *             ["pago_asignado"] => string(5) "-1700"
     *             ["sedes_cursosxanio_id"] => string(3) "212"
     *             ["evscxa_id"] => string(4) "2106"   !!!!!!!!!!!
     *             ["evscxa_fecha_inicio"] => string(10) "2018-07-01"
     *             ["evscxa_valor"] => string(4) "1700"
     *             ["ev_numero_de_orden"] => string(1) "5"
     *             ["ev_id"] => string(1) "5"
     *             ["ev_abreviatura"] => string(3) "CU4"
     *             ["ev_descripcion"] => string(3) "Cuota 4"
     *             ["sedes_id"] => string(1) "3"
     *             ["anio"] => string(4) "2018"
     *             ["cursos_id"] => string(1) "4"
     *             ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 2"
     *             ["descripcion"] => string(27) "Profesorado de Yoga Curso 2"
     *             ["prioridad_segun_anio"] => string(1) "1"
     *          }
     */
    public function getRowsPorSaldar( $alumnos_id, $deudasPrioritarias=null )
    {   
        return $this->getPorSaldar( $alumnos_id, $deudasPrioritarias, $cortePorEvscxaId=FALSE );
    }    

    /*
     * Brinda data de las deudas por saldar, 
     * colocando las marcadas al principio de la lista.
     * 
     * $alumnos_id
     * $deudasPrioritarias   <array> or <int>    Opcional. default null
     *                          id de los items que se precisan
     *                      <array> key evscxa_id => monto
     * $agruparPorEvscxa  <boolean>
     *                          false:  Brinda todas las rows
     *                          true:   Agrupa por id de evscxa
     * 
     * Según el valor de $agruparPorEvscxa, obtendrá todas las rows de la tabla,
     * y si es TRUE, todos los evscxa_id pendientes de cobertura:
     * OUTPUT
     * 
     *  1) SALIDA DE ROWS DE LA TABLA
     *    key cuentas_corrientes_id
     * 
     *         array(2) {
     *           [42213] => array(20) {         
     *             ["cuentas_corrientes_id"] => string(5) "42213"  *************
     *             ["alumnos_id"] => string(3) "865"
     *             ["monto"] => string(5) "-1700"
     *             ["cobertura"] => string(5) "-1000"
     *             ["motivo"] => string(29) "2018, CU4 profesorado nivel 2"
     *             ["valor_final_calculado"] => string(4) "1700"
     *             ["pago_asignado"] => string(5) "-1700"
     *             ["sedes_cursosxanio_id"] => string(3) "212"
     *             ["evscxa_id"] => string(4) "2106"   !!!!!!!!!!!
     *             ["evscxa_fecha_inicio"] => string(10) "2018-07-01"
     *             ["evscxa_valor"] => string(4) "1700"
     *             ["ev_numero_de_orden"] => string(1) "5"
     *             ["ev_id"] => string(1) "5"
     *             ["ev_abreviatura"] => string(3) "CU4"
     *             ["ev_descripcion"] => string(3) "Cuota 4"
     *             ["sedes_id"] => string(1) "3"
     *             ["anio"] => string(4) "2018"
     *             ["cursos_id"] => string(1) "4"
     *             ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 2"
     *             ["descripcion"] => string(27) "Profesorado de Yoga Curso 2"
     *             ["prioridad_segun_anio"] => string(1) "1"
     *          }
     * 
     * 2) SALIDA DE EVSCXA_ID PENDIENTES DE SALDAR
     * Dado que van agrupados por evscxa_id, mucha data que contiene cada array
     * es innecesaria o no aplicable (ya que se origino en items particulares).
     * array(3) {
     *   [12310824] => array(25) {      // dni
     *     [6319] => array(31) {        // evscxa_id
     *       ["cuentas_corrientes_id"] => string(6) "101768"
     *       ["fecha_hora_de_sistema"] => string(26) "2021-06-25 17:02:20.597900"
     *       ["alumnos_id"] => string(8) "12310824"
     *       ["tipo_operacion"] => string(18) "FACTURA_AUTOMATICA"
     *       ["monto"] => string(5) "-4200"
     *       ["cobertura"] => string(1) "0"
     *       ["motivo"] => string(22) "Nivel 4, Cuota 1, 2021"
     *       ["fecha_operacion"] => string(10) "2021-04-01"
     *       ["scxa_id"] => string(3) "593"   
     *       ["sedes_id"] => string(1) "3"
     *       ["anio"] => string(4) "2021"   
     *       ["cursos_id"] => string(1) "6"   
     *       ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 4"   
     *       ["descripcion"] => string(27) "Profesorado de Yoga Curso 4"   
     *       ["valor_modificado"] => NULL   
     *       ["valor_final_calculado"] => string(4) "4200"   
     *       ["ev_abreviatura"] => string(3) "CU1"   
     *       ["ev_descripcion"] => string(7) "Cuota 1"   
     *       ["evscxa_id"] => string(4) "6319"   
     *       ["evscxa_fecha_inicio"] => string(10) "2021-04-01"   
     *       ["evscxa_valor"] => string(4) "4200"   
     *       ["ev_numero_de_orden"] => string(1) "6"   
     *       ["ev_id"] => string(1) "2"   
     *       ["prioridad_segun_anio"] => string(1) "1"   
     *       ["scxa_ordenado"] => string(1) "2"   
     *       ["sum_monto_debitos"] => string(5) "-4200"   
     *       ["sum_cobertura_debitos"] => string(1) "0"   
     *       ["sum_saldo_debitos"] => string(5) "-4200"   
     *       ["sum_monto_debitos_a_cuenta"] => string(1) "0"   
     *       ["sum_cobertura_debitos_a_cuenta"] => string(1) "0"   
     *       ["sum_saldo_debitos_a_cuenta"] => string(1) "0"   
     *     }   
     * 
     */
    public function getPorSaldar( $alumnos_id, $deudasPrioritarias=false, $agruparPorEvscxa=false )
    {
        $filtrar = $this->getFiltroParaObtenerMovimientosQuePuedenSerPagados( $agruparPorEvscxa );
        $deudas = $this->getCuentaCorrienteEvscxa( $alumnos_id, $filtrar, $agruparPorEvscxa );
        $deudas = ($deudas)? $deudas : array();        
        return ($deudasPrioritarias)? $this->_ponerLosSeleccionadosPrimero( $deudas, 'evscxa_id', $deudasPrioritarias ) : $deudas;
    }
    
    /*
     * items del año seleccionado o posteriores.
     */
    public function getEvscxaActuales( $alumnos_id, $anio=null )
    {
        $anio = ($anio)? $anio : date('Y');
        $filtrar =  "(sum_monto_debitos < 0 OR sum_monto_debitos_a_cuenta < 0 ) AND ".
                    "EXTRACT(year FROM fecha_operacion) >= $anio"; 
        $listaDeudas = $this->getCuentaCorrienteEvscxa( $alumnos_id, $filtrar, $itemsPorEvscxa=true );
        return $listaDeudas;
    }
    
    
    
    private function _ponerLosSeleccionadosPrimero( $lista, $campoFiltro, $prioritarios )
    {
        $buscados = ( is_array($prioritarios) )? $prioritarios : array($prioritarios);
        $primeros = array();
        foreach( $lista as $key => $values ){
            // if( $values[ $campoFiltro ] == $prioritarios ){
            if( in_array( $values[ $campoFiltro ], $buscados ) ){
                $primeros[ $key ] = $values ;
                unset( $lista[ $key ] );
            }
        }
        return $primeros+$lista;
    }
    
    
    /*
     * INPUT
     * $alumnos_id  <int>
     * $anio        <int>   año de trabajo o proceso,
     *                      es una referencia para calcular la prioridad de las deudas.
     * 
     * OUTPUT
     *  array de objetos CuentaCorriente, dando prioridad a:
     *      1° deudas del propio año    ordenadas de más viejas a más nuevas
     *      2° deudas años anteriores   ordenadas de más nuevas a más viejas
     *      3° deudas futuras           ordenadas de más viejas a más nuevas
     */
    public function getDeudasDelAlumnoOLD( $alumnos_id, $anio, array $evscxaIdsPrioritarios=null )
    {
        if( !$anio ){ die('en getDeudasDelAlumno no recibi año'); }
        
        
        // 1° array con las deudas del propio año, orden default: fecha_operacion ASC
        $buscarA = array(   "alumnos_id = '$alumnos_id' ", 
                            "SUBSTR( fecha_operacion, 1, 4 ) = $anio " ,
                            "monto < 0",
                            "monto < cobertura"
                        );
        $r1 = $this->obtenerGeneral( $buscarA, 'id', 'CuentaCorriente', 'fecha_operacion');
        $r1Ascendente = ( is_array($r1) )? $r1 : array();
        
        // 2° array con las deudas de años anteriores, orden inverso default: fecha_operacion DESC
        $buscarB = array(   "alumnos_id = '$alumnos_id' ", 
                            "SUBSTR( fecha_operacion, 1, 4 ) < $anio " ,
                            "monto < 0",
                            "monto < cobertura"
                        );
        $r2 = $this->obtenerGeneral( $buscarB, 'id', 'CuentaCorriente', 'fecha_operacion');
        $r2b= ( is_array($r2) )? $r2 : array();
        $r2Descendente = array_reverse( $r2b, true );
        
        // 3° array con deudas de años posteriores, orden default: fecha_operacion ASC
        $buscarC = array(   "alumnos_id = '$alumnos_id' ", 
                            "SUBSTR( fecha_operacion, 1, 4 ) > $anio " ,
                            "monto < 0",
                            "monto < cobertura"
                        );
        $r3 = $this->obtenerGeneral( $buscarC, 'id', 'CuentaCorriente', 'fecha_operacion');
        $r3Ascendente = ( is_array($r3) )? $r3 : array();
        
        $deudasTodas = $r1Ascendente + $r2Descendente + $r3Ascendente;
        
        //
        if( $evscxaIdsPrioritarios ){
            $deudasPrioritarias = $this->getDeudasSeleccionadas( $alumnos_id, $evscxaIdsPrioritarios );
            // Las deudasPrioritarias quedan arriba de todas las deudas:
            foreach( array_keys($deudasPrioritarias) as $ctacteId ){
                unset( $deudasTodas[ $ctacteId ] );
            }
            $deudasTodas = $deudasPrioritarias + $deudasTodas;
        }
        
        return $deudasTodas;
    }
    
    public function getDeudasDataPrioritariasPrimero( $alumnoId, $anio, $ctacteIdIndicadas=null )
    {
        $deudas = $this->getDeudasData( $alumnoId, $anio );
        if( !$deudas || !$ctacteIdIndicadas ){
            return $deudas;
        }
        if( $ctacteIdIndicadas ){
            // Las deudasPrioritarias quedan arriba de todas las deudas:
            if( !isset( $deudas[ $ctacteIdIndicadas ] ) ){
                return $deudas; // ha llegado un dato erróneo.
            }
            $deudaPrioritaria = array( $ctacteIdIndicadas => $deudas[ $ctacteIdIndicadas ] );
            unset( $deudas[ $ctacteIdIndicadas ] );
            return $deudaPrioritaria + $deudas;
        }else{
            return $deudas;
        }
    }
    
    /*
     * Devuelve todas las deudas del alumno, ordenadas por:
     * 1° Año actual
     * 2° Años anteriores
     * 3° Años futuros
     */
    public function getDeudasData( $alumnoId, $anioProcesando )
    {
        // para evitar que el sql quede mal armado, hago un control muy bruto
        if( !$alumnoId || !is_numeric($anioProcesando) ){
            echo '<br>ERROR GRAVE EN getDeudasData(), parametros incorrectos<br>';
            ver($alumnoId, '$alumnoId');
            ver($anioProcesando, '$anioProcesando');
            die();
        }
        
        $sql =  "SELECT *, " .
                    "IF( SUBSTR( fecha_operacion, 1, 4 ) = $anioProcesando, 1, ".
                    "   IF( SUBSTR( fecha_operacion, 1, 4 ) < $anioProcesando, 2, 3 )  ) " .
                    "AS prioridad_segun_anio " .
                "FROM view_cuentas_corrientes " .
                "WHERE alumnos_id = '$alumnoId' ".
                "AND monto < 0 AND monto < cobertura ".
                "ORDER BY prioridad_segun_anio, fecha_operacion";

         $query = new Query();
        $deudasArray = $query->ejecutarQuery( $sql );
        if( $deudasArray && count($deudasArray)>0 ){
            $resultado = array();
            foreach( $deudasArray as $values ){
                $resultado[ $values['cuentas_corrientes_id'] ] = $values;
            }
            return $resultado;
        }
       return false;
    }
    
    public function getItemsDebitadosData( $alumnoId, $stringOtrosWheres=null )
    {
        $sql =  "SELECT * " .
                "FROM view_cuentas_corrientes " .
                "WHERE alumnos_id = '$alumnoId' AND monto < 0 ".
                (($stringOtrosWheres)? "AND $stringOtrosWheres " : '').
                "ORDER BY fecha_operacion";

         $query = new Query();
        $deudasArray = $query->ejecutarQuery( $sql );
        if( $deudasArray && count($deudasArray)>0 ){
            $resultado = array();
            foreach( $deudasArray as $values ){
                $resultado[ $values['cuentas_corrientes_id'] ] = $values;
            }
            return $resultado;
        }
       return false;
    }    
    
    
        
    /*
     * Devuelve los objetos de la cuenta corriente,
     * que se corresponde con los items recibidos,
     * en un array de objetos CuentaCorriente y key  id
     */
    public function getDeudasSeleccionadasOLD( $alumnos_id, array $evscxaIds )
    {
        $select = $this->select();
        $select ->setIntegrityCheck(false)  //es importante colocar esto
                ->from( array( 'cuentacorriente'   => $this->_name ), 
                        '*' )
                ->join  // inner join
                    ( array(  'ev'   => 'yoga_cuentascorrientes_elementosvaluados' ),
                        'ev.cuentas_corrientes_id = cuentacorriente.id',      //union
                        //array('evscxa_id' => 'elementosvaluados_sedes_cursosxanio_id') 
                        null
                    )
                ; 
        $wheres = array( "cuentacorriente.alumnos_id = '$alumnos_id' ",
                        'ev.elementosvaluados_sedes_cursosxanio_id' => $evscxaIds,
                        'monto < 0 AND monto < cobertura'
                        );
        $select = $this->construirElWhere( $select, $wheres );
        // print($select); 
        $rowset =  $this->fetchAll( $select );
        
        return $this->rowsetToObjetos( $rowset, $this->_class_origen, 'id' );
    }
    
    

    
    
    /*
     * <array>
     *      key alumnos_id
                <array>
                subkey evscxa_id  =>
                                    array
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
                                        'ev_descripcion',
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
     * 
     */
    private function _getEvasDelAlumno( $alumnos_id )
    {
        if( $this->_evasDelAlumno == null ){
            $evasDelAlumno = $this->funcionesSobreEVAs->getEvasData( $alumnos_id );
            if( $evasDelAlumno ){
                $this->_evasDelAlumno = $evasDelAlumno[ $alumnos_id ];
            }
        }
        return $this->_evasDelAlumno;
    }

    
    /*
     * INPUT
     * $wheres           opcional. 
     * 
     * OUTPUT
     * array
            array(129) {
     *          alumnos_id  =>  monto
                     [14] => (4250)
                    [108] => (4250)
                    [112] => (4250)
                    [113] => (4250)
                    [115] => (1375)
                    [120] => (250)
                    [122] => (4250)
     * 
     */
    public function getSaldos( $wheres=false )
    {
        $wheres = ($wheres)? $wheres : array();
        return $this->_getSumatoria( $wheres );
    }
    

    public function getSumatoriaFacturacionPorAlumno( $wheres=false )
    {
        $wheres = ($wheres)? $wheres : array();
        $wheres[]=' monto < 0';
        $wheres[]='tipo_operacion = "FACTURA_AUTOMATICA"';
        //$wheres[]=' tipo_operacion NOT IN ( "NOTA_CREDITO_MANUAL", "NOTA_CREDITO_AUTOMATICO" ) ';
        return $this->_getSumatoria( $wheres );
    }
    public function getSumatoriaAjustesPorAlumno( $wheres=false )
    {
        $wheres = ($wheres)? $wheres : array();
        $wheres[]=' tipo_operacion IN ( "NOTA_CREDITO_MANUAL", "DEBITO_MANUAL", "NOTA_CREDITO_AUTOMATICO", "DEBITO_AUTOMATICO" ) ';
        return $this->_getSumatoria( $wheres );
    }    
    public function getSumatoriaHaberPorAlumno( $wheres=false )
    {
        $wheres = ($wheres)? $wheres : array();
        $wheres[]=' monto > 0';
        //$wheres[]=' tipo_operacion NOT IN ( "NOTA_CREDITO_MANUAL", "NOTA_CREDITO_AUTOMATICO" ) ';
        return $this->_getSumatoria( $wheres );
    }
    public function getSumatoriaDePagosPorAlumno( $wheres=false )
    {
        $wheres = ($wheres)? $wheres : array();
        $wheres[]=' monto > 0';
        $wheres[]=' tipo_operacion NOT IN ( "NOTA_CREDITO_MANUAL", "NOTA_CREDITO_AUTOMATICO" ) ';
        return $this->_getSumatoria( $wheres );
    }
    private function _getSumatoria( $wheres )
    {
        $select = $this->select();
        $select ->setIntegrityCheck(false)  //es importante colocar esto
                ->from( array( 'cuentacorriente'   => $this->_name ),
                        array( 'alumnos_id', 'sumatoria' => 'SUM(monto)'));

        $select = $this->construirElWhere( $select, $wheres );
        $select->group('alumnos_id');
        //print($select); 

        $filasArray =  $this->fetchAll( $select )->toArray();

        if( !$filasArray || count($filasArray)==0 ){
            return false;
        }
        
        /* en modo Query:
        $query = new Query();
        $sql =  'SELECT alumnos_id, SUM(monto) AS pagado '.
                'FROM yoga_cuentas_corrientes '.
                $where.' '.
                'GROUP BY alumnos_id '
                ;        
        $filasArray = $query->ejecutarQuery( $sql );
         * 
         */
        
        
        // preparo una salida, con un formato más simple,   key alumnos_id => sum pagos
        if( count($filasArray)>0 ){
            $resultado = array();
            foreach( $filasArray as $sumPagosAlumno ){
                $resultado[ $sumPagosAlumno['alumnos_id'] ] = $sumPagosAlumno['sumatoria'];
            }
            return $resultado;
        }else{
            return false;
        }
    }
    
    public function getSumatoriaDePagosEnElAnio( $alumnos_id, $anio )
    {
        $wheres = array();
        $wheres[]= "alumnos_id = '$alumnos_id' ";
        $wheres[]= "EXTRACT( year FROM fecha_operacion) = $anio";
        $wheres[]=' monto > 0';
        $wheres[]=' tipo_operacion NOT IN ( "NOTA_CREDITO_MANUAL", "NOTA_CREDITO_AUTOMATICO" ) ';
        return $this->obtenerGeneral( $wheres, 'id', $this->_class_origen );
    }
    
    // Solo brinda los items pagados que tienen id de EV a que refiere
    public function getDetalleDelPago( $cuentaCorrienteId )
    {
        $sql = 'SELECT * FROM view_asignaciones_pago WHERE id = '.$cuentaCorrienteId;
        $Query = new Query();
        return $Query->ejecutarQuery( $sql );
    }
    
    public function getFacturasDelPago( CuentaCorriente $cuentaCorriente_pago )
    {
        $asignacionesDetalle = $this->getDetalleDelPago( $cuentaCorriente_pago->getId() );
        $ids = array_values_recursive( arrays_getAlgunasKeysArrays($asignacionesDetalle, 'deuda_id'));
        return $this->obtenerGeneral( [ 'id' => array_values_recursive($ids) ], 'id', 'CuentaCorriente' );
    }    
    
    public function pagoDesasignar( $cuentaCorrienteId )
    {
        $PagoDesasignador = new PagoDesasignador( $this, $cuentaCorrienteId );
        $facturasModificadas = $PagoDesasignador->desasignar();
    }
    
    
    /*
     * INPUT
     *  <INT> O <array> de alumnos_id
     * 
     * OUTPUT
     *  <array>
     *      alumnos_id  => array(
     *                              'facturado_total_por_alumno'
     *                              'pagado_total_por_alumno'
     *                              'ajustado_total_por_alumno'
     *                          )
     * 
     * Los ajustes ya se incluyen en los facturado y pagado, 
     * ya que estos solo evaluan el signo del monto
     */
    public function getTotalesPorAlumno( $alumnos_id )
    {
        $wheres = array( 'alumnos_id' => $alumnos_id );
        
        $respuesta = 
            array(
                'facturado_total_por_alumno' => $this->getSumatoriaFacturacionPorAlumno( $wheres ),
                'pagado_total_por_alumno'    => $this->getSumatoriaDePagosPorAlumno( $wheres ),
                'ajustado_total_por_alumno'  => $this->getSumatoriaAjustesPorAlumno( $wheres ),
                'haber_total_por_alumno'     => $this->getSumatoriaHaberPorAlumno( $wheres ),
                );
        // cada item respuesta debe ser minímo un array, aun si el alumno buscado no está.
        $respuesta['facturado_total_por_alumno']= ( is_array($respuesta['facturado_total_por_alumno']) )? $respuesta['facturado_total_por_alumno'] : array();
        $respuesta['pagado_total_por_alumno']   = ( is_array($respuesta['pagado_total_por_alumno']) )? $respuesta['pagado_total_por_alumno'] : array();
        $respuesta['ajustado_total_por_alumno'] = ( is_array($respuesta['ajustado_total_por_alumno']) )? $respuesta['ajustado_total_por_alumno'] : array();
        
        return $respuesta;
    }
    
    
    
    /*
     * Detalle de pagos hechos o pendientes del alumno.
     * 
     * Pueden obtenerse item a item segun la cuenta corriente,
     * o agrupados por tipo de objeto (evscxa_id)
     * 
     * INPUT
     * $alumnos_id      <int> o <array>
     *                          Si es un <int> devolverá los datos justos para ese alumno.
     *                          Si es un array, devolverá los datos con key primaria por alumnoId
     * $filtrar         <string>    Me quedo solo con las filas de interes.
     *                              Si el resultado va agrupado por evscxa, entonces, 
     *                              los $filtrar deben ir por fuera del primer query, 
     *                              para no condicionar las rows tratadas.
     *                              Sino, simplemente filtrar, sobre los datos ya calculados.
     * 
     * $groupByEvscxa   <boolean>   Para los casos en que el usuario,
     *                              selecciona un evscxa,
     *                              no me interesará conocer cuantos items están
     *                              relacionados en las cuentas corrientes.
     *                              Para esos casos deberá setearse en True.
     * 
     * OUTPUT
     * MUY IMPORTANTE ES EL ORDEN DE SALIDA DE LAS ROWS, ej. para dar prioridad de pagos:
     * Orden de salida:
     *      1°  items sin identificación de evscxa_id (podrían ser items A_CUENTA)
     *      2°  items del año corriente
     *      3°  items de años anteriores
     *      4°  items de años futuros
     * 
     * <array>      key => MUY IMPORTANTE !!! **********************************
     *                          Si el parametro $groupByEvscxa está en TRUE
     *                          la key será por evscxa_id,
     *                          por FALSE, será cuentas_corrientes_id.
     *                          Ya que por false, si hubiera más de una row,
     *                          en la salida se pisaría con misma clave evscxa_id.
     * 
     *                          Además, con $groupByEvscxa, se generá otros campos que totalizan correctamente,
     *                          "sum_monto_debitos",
     *                          "sum_cobertura_debitos",
     *                          "sum_saldo_debitos",
     *                          "sum_monto_debitos_a_cuenta",
     *                          "sum_cobertura_debitos_a_cuenta",
     *                          "sum_saldo_debitos_a_cuenta",
     *                          ¿Por qué? Imagina que hay un pago que fue distribuido entre varios débitos,
     *                          sumar el monto del pago, y no el monto que realmente fue distribuido al item, 
     *                          me daría totales falsos.
     * 
     * Ejemplo, si en el menú seleccione elementos pendientes de pago (agrupados por evscxa_id), 
     * esta funcion devolverá un array de arrays similares a este:
     * 
                [2220] => array(30) {                   *******************
                  ["cuentas_corrientes_id"] => string(5) "45233"
                  ["alumnos_id"] => string(8) "23700502"
                  ["tipo_operacion"] => string(18) "FACTURA_AUTOMATICA"
                  ["monto"] => string(5) "-1500"
                  ["cobertura"] => string(1) "0"
                  ["motivo"] => string(29) "2018, CU6 profesorado nivel 2"
                  ["fecha_operacion"] => string(10) "2018-09-01"
                  ["scxa_id"] => string(3) "228"
                  ["sedes_id"] => string(1) "9"
                  ["anio"] => string(4) "2018"
                  ["cursos_id"] => string(1) "4"
                  ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 2"
                  ["descripcion"] => string(27) "Profesorado de Yoga Curso 2"
                  ["valor_modificado"] => NULL
                  ["valor_final_calculado"] => string(4) "1500"
                  ["pago_asignado"] => NULL
                  ["ev_abreviatura"] => string(3) "CU6"
                  ["ev_descripcion"] => string(3) "Cuota 6"
                  ["evscxa_id"] => string(4) "2220"     *******************
                  ["evscxa_fecha_inicio"] => string(10) "2018-09-01"
                  ["evscxa_valor"] => string(4) "1500"
                  ["ev_numero_de_orden"] => string(1) "7"
                  ["ev_id"] => string(1) "7"
                  ["prioridad_segun_anio"] => string(1) "1"
                  ["scxa_ordenado"] => string(1) "0"
                  ["sum_monto_debitos"] => string(5) "-1500"
                  ["sum_cobertura_debitos"] => string(1) "0"
                  ["sum_saldo_debitos"] => string(5) "-1500"
                  ["sum_monto_debitos_a_cuenta"] => string(1) "0"       no refieren a algo en particular
                  ["sum_cobertura_debitos_a_cuenta"] => string(1) "0"   no refieren a algo en particular
                  ["sum_saldo_debitos_a_cuenta"] => string(1) "0"       no refieren a algo en particular
                }
                ...
     * 
     *      el campo del query "sum_monto_debitos_a_cuenta" es para los que no tiene evscxa_id
     *      y que van como debitos o créditos generales.
     */
    public function getCuentaCorrienteEvscxa( $alumnos_id, $filtrar='', $groupByEvscxa=false )
    {
        $alumnos_id2 = ( is_array($alumnos_id) )? $alumnos_id : array( $alumnos_id );
        $anioProcesando = date('Y');

        $sql =  
            "SELECT *, " .
                // Orden: 1° año actual, 2° años anteriores, 3° años futuros:
                "(IF( EXTRACT( year FROM fecha_operacion) = $anioProcesando, 1, ".
                "   IF( evscxa_id IS NULL, 2, ".
                "       IF( EXTRACT( year FROM fecha_operacion) < $anioProcesando, 3, 4 )  )  ) )" .
                " AS prioridad_segun_anio, " .
                "( IF( scxa_id IS NULL, 1, 2) ) AS scxa_ordenado ". // los que son null van debajo
                
            // Cuando haga agrupación por EVscxa, para saber la deuda real del item, 
            // y que los valores parciales de pagos ensucien los totales, deberé discriminar solo por los débitos,
            // ya que ellos son los que me indican realmente el total de la deuda, y su cobertura:
            // ¿Por qué digo que de tomar los pagos parciales podrían ensuciar los totales? 
            // Imagina que hay un pago que fue distribuido entre varios débitos,
            // sumar el monto del pago, y no el monto que realmente fue distribuido al item, me daría totales falsos.
            // El pago que realmente se destino al item lo da el campo "pago_asignado".
            // Asi que, con los 2 campos siguientes, obtengo el real de monto y cobertura para esos EV
            ( ($groupByEvscxa)? ', SUM( CASE WHEN evscxa_id IS NOT NULL AND monto<0 THEN monto ELSE 0 END ) AS sum_monto_debitos ' : '' ).
            ( ($groupByEvscxa)? ', SUM( CASE WHEN evscxa_id IS NOT NULL AND monto<0 THEN cobertura ELSE 0 END ) AS sum_cobertura_debitos ' : '' ).
            ( ($groupByEvscxa)? ', SUM( CASE WHEN evscxa_id IS NOT NULL AND monto<0 THEN (monto-cobertura) ELSE 0 END ) AS sum_saldo_debitos ' : '' ).
                
            // y para valores A_CUENTA, es decir que no refieren a algo en particular
            ( ($groupByEvscxa)? ', SUM( CASE WHEN evscxa_id IS NULL AND monto<0 THEN monto ELSE 0 END ) AS sum_monto_debitos_a_cuenta ' : '' ).
            ( ($groupByEvscxa)? ', SUM( CASE WHEN evscxa_id IS NULL AND monto<0 THEN cobertura ELSE 0 END ) AS sum_cobertura_debitos_a_cuenta ' : '' ).
            ( ($groupByEvscxa)? ', SUM( CASE WHEN evscxa_id IS NULL AND monto<0 THEN (monto-cobertura) ELSE 0 END ) AS sum_saldo_debitos_a_cuenta ' : '' ).
                
            " FROM view_cuentas_cursos_evas ".
            " WHERE alumnos_id IN ('".implode("', '",$alumnos_id2)."' ) ".
            ( ($groupByEvscxa===true)? ' AND evscxa_id IS NOT NULL ' : '' ).
            ( ($groupByEvscxa)? ' GROUP BY alumnos_id, evscxa_id  ' : '' ).
            " ORDER BY alumnos_id, scxa_ordenado, prioridad_segun_anio, scxa_id, ev_numero_de_orden, fecha_operacion " 
            ;    
        
        // Termino de filtrar correctamente segun lo buscado:
        if( $filtrar!='' ){
            $sql = 'SELECT * FROM ( '.$sql.' ) AS A  '.(( $filtrar!='' )? ' WHERE '.$filtrar : '' );
            $sql.= ' ORDER BY alumnos_id, scxa_ordenado, prioridad_segun_anio, scxa_id, ev_numero_de_orden, fecha_operacion';
        }

        
        $query = new Query();
        $evArray = $query->ejecutarQuery( $sql );
        if( $evArray && count($evArray)>0 ){
            $resultado = array();
            $campoKey = ($groupByEvscxa)? 'evscxa_id' : 'cuentas_corrientes_id';
            foreach( $evArray as $values ){            
                $subkey = ($groupByEvscxa && is_null($values['evscxa_id']))? 'A_CUENTA' : (int) $values[$campoKey] ;
                $resultado[ $values['alumnos_id'] ][ $subkey ] = $values;
            }
            return ( is_array($alumnos_id) )? $resultado : $resultado[$alumnos_id];
        }
       return false;        
    }

    /*
     * Esta es una función para usar posteriormente al 
     * resultado de getCuentaCorrienteEvscxa()
     * Identificando al correspondiente Objeto Cuenta Corriente, 
     * manteniendo el mismo orden que tenían los ctacteId en $evscxaArrays
     */
    public function transformarLaListaDeArraysEnListaDeObjetos( $evscxaArrays )
    {
        if( !$evscxaArrays || is_array($evscxaArrays) && count($evscxaArrays)==0 ){
            return array();
        }
        $resultado = array();
        $objetos = $this->obtenerGeneral( array('id'=>array_keys($evscxaArrays)), 'id', $this->_class_origen );
        foreach( $evscxaArrays as $cuentaCorrienteId => $itemValues ){
            $resultado[ $cuentaCorrienteId ] = $objetos[$cuentaCorrienteId];
        }
        return $resultado;
    }
            
    /*
     * OUTPUT
     *      <array>         Objetos Cuenta Corriente 
     *                      obtenidos desde el evscxaId
                array(1) {
                  [0] => array(13) {
                    ["cuentas_corrientes_id"] => string(5) "42213"
                    ["alumnos_id"] => string(3) "865"
                    ["tipo_operacion"] => string(18) "FACTURA_AUTOMATICA"
                    ["motivo"] => string(29) "2018, CU4 profesorado nivel 2"
                    ["fecha_operacion"] => string(10) "2018-07-01"
                    ["monto"] => string(5) "-1700"
                    ["cobertura"] => string(5) "-1000"
                    ["comprobante"] => string(8) "no_tiene"
                    ["persona_en_caja"] => string(18) "proceso_automatico"
                    ["observaciones"] => string(0) ""
                    ["usuario_nombre"] => string(18) "proceso_automatico"
                    ["fecha_hora_de_sistema"] => string(19) "2018-07-01 20:37:22.123456"
                    ["evscxa"] => string(4) "2106"
                  }
                }     
                ...
     * 
     *                  
     */
    public function getDesdeEvscxa( $alumnos_id, $evscxaId, $wheres='' )
    {
        /*
        $sql =  'SELECT * FROM yoga_cuentas_corrientes AS ctacte '.
                'LEFT JOIN yoga_cuentascorrientes_elementosvaluados AS ev '.
                'ON ev.cuentas_corrientes_id = ctacte.id'.
                ''.
                ''.
                'WHERE alumnos_id = "'.$alumnos_id.'" '.
                ' AND elementosvaluados_sedes_cursosxanio_id = '.$evscxaId
                ( ($wheres!='')? ' AND '.$wheres.' ' : '' )
                ;
         * 
         */
        $wheres = ( is_array($wheres) )? $wheres : ( ( $wheres<>'')? array($wheres) : array() ) ;
        $select = $this->select();
        $select ->setIntegrityCheck(false)  //es importante colocar esto
                ->from( array( 'ctacte'   => 'yoga_cuentas_corrientes' ), '*' )
                ->joinLeft
                    (   array(  'ev'   => 'yoga_cuentascorrientes_elementosvaluados' ),
                        'ev.cuentas_corrientes_id = ctacte.id',      //union
                        array( 'evscxa' => 'elementosvaluados_sedes_cursosxanio_id',
                               'pago_asignado' )
                    )
                ;
        $wheres[] = "alumnos_id = '$alumnos_id' AND ev.elementosvaluados_sedes_cursosxanio_id = $evscxaId ";
        $select = $this->construirElWhere( $select, $wheres );
        //print($select); 
        $resultado =  $this->fetchAll( $select )->toArray();
        return ( ( !$resultado || count($resultado)==0 )? false : $resultado );
    }
    
    
    /*
     * Esta fn es invocada cuando se modificado un valor general del curso.
     * Apunta a corregir las cuentas corrientes de alumnos involucrados en este cambio.
     * Dado que individualmente ya se trabajaron alumnos que tenían EVA propio,
     * llega un parámetro más que indica que alumnos deberán trabajarse ahora.
     * es decir, aquellos que no precisan crear un registro EVA,
     * ya que se basan en el valor general.
     */
    public function postCambioEnUnItemDePrecioDeCurso( $evscxaId, $valorOriginal, $valorNuevo, $alumnosSinEva )
    {
        $diferenciaValores = $valorOriginal - $valorNuevo ;
        if( $diferenciaValores == 0 ){
            return 0; // nada por hacer
        }

        // Obtengo varios datos de los Elementos Valuados, desde una view
        $evData = $this->funcionesSobreEVAs->busquedaDeEvscxa( $evscxaId, $alumnosSinEva );

        foreach( $evData as $alumnoId => $arrayKeyValues ){
            $values = getPrimero( $arrayKeyValues );
            $this->_procesarModificacionDeValorEV( $values, $valorOriginal, $valorNuevo, 'precio general' );
        }
    }
    
    /*
     * Esta fn es invocada cuando se han modificado los valores del curso.
     * Generará la facturación mensual si aun no fue hecha.
     * 
     * Devuelve FALSE si encontro algún error durante el proceso.
     * 
     */
    public function postCambiosEnPreciosDeCurso( $sedes_id, $anio=null )
    {
        // corro la facturación para esa sede.
        require_once 'api/models/ApiCuentaCorriente.php';
        $model = new ApiCuentaCorriente();
        $anio = (!$anio)? date('Y') : $anio;
        if( $anio > date('Y') ){
            return true; 
        }
        /* el proceso de generación de deudas es costoso
        // Si quisiera pedir solo desde 2 meses atrás:
        $fechaDesde = (date('m')<=2)? (date('Y').'-01-01') : (date('Y').'-'.str_pad((date('m')-2),'0',2,STR_PAD_LEFT).'-01') ;
        $html = $model->generarDeudasEnFechaDeCobro( ['sedes_id'=>$sedes_id, 'fecha_desde'=>$fechaDesde ] );
         */
        $html = $model->generarDeudasEnFechaDeCobro( ['sedes_id'=>$sedes_id, 'anio'=>$anio] );
        return ( strpos($html,'error')!==false )? false : true;
    }
    
    
    /*
     * Esta fn es invocada cuando
     * el usuario ha modificado algun valor de un alumno (EVA).
     * Dicho cambio al llegar aquí, ya se ha registrado en la tabla de EVAs.
     * 
     * OUTPUT
     *      FALSE   Error. Algun dato no fue encontrado.
     *      TRUE    se realizo un movimiento en la cuenta corriente.
     *      0       No hay ajuste por hacer.
     * 
     * REFACTORING PENDIENTE 
     * GRAN PARTE DE LO QUE HACE ACA, DEBE HACERLO EL RegistradorDeMovimiento
     */
    public function evaModificado( $evscxa_id, $valorOriginal, $valorNuevo, $codMotivo, $alumnosIds )
    {
        $diferenciaValores = $valorOriginal - $valorNuevo ;
        if( $diferenciaValores == 0 ){
            return true; // nada por hacer
        }
            

        // Obtengo varios datos de los Elementos Valuados, desde una view
        // $evas = $this->funcionesSobreEVAs->busquedaDeEva( $evaId );
        $evas = $this->funcionesSobreEVAs->busquedaDeEvscxa( $evscxa_id, $alumnosIds );
        if( !$evas ){
            return true; // Algo pinta incoherente. No hay nada por modificar.
        }
        $arrayValues = getPrimero( getPrimero($evas ) ); //saco las 2 keys que encierran los resultados
        /*
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
        'ev_descripcion',
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
        $descripcionExtra = (( $codMotivo )? 'motivo '.$codMotivo : '' );

        return $this->_procesarModificacionDeValorEV( $arrayValues, $valorOriginal, $valorNuevo, $descripcionExtra );
    }
    
    // está fn. al final quedo sin uso. la dejo por si luego hace falta. 
    // sino, más adelante, habrá que eliminarla.  La usaría evaColecc
    public function evaEliminado( $evscxaId, $valorOriginal, $valorNuevo, $alumnos_id )
    {
        $diferenciaValores = $valorOriginal - $valorNuevo ;
        if( $diferenciaValores == 0 ){
            return true; // nada por hacer
        }

        // Obtengo varios datos de los Elementos Valuados, desde una view
        $evas = $this->funcionesSobreEVAs->busquedaDeEvscxa( $evscxaId, $alumnos_id );
        $arrayValues = getPrimero( getPrimero($evas ) ); //saco las 2 keys que encierran los resultados
        return $this->_procesarModificacionDeValorEV( $arrayValues, $valorOriginal, $valorNuevo, 'precio para el alumno' );
    }
    
    /*
     * ESTE PROCESO DEBERÍA SER REFACTORIZADO 
     * LLEVANDOLO HACIA EL RegistroCredito.php y RegistroDebito.php
     * --------------------------------
     * Crea un ajuste(débito o crédito) 
     * modificando la cobertura del item asosicado en CuentaCorriente.
     * Si no corresponde a ningun item existente en la tabla cuentas_corrientes, 
     * se notifica al usuario que no se genero movimiento en cuenta corriente.
     * Si el ajuste fue creado, se dispara el proceso_de_distribucion_de_saldos (coberturas).
     * 
     * Busqueda del item relacionado en cuentas_corrientes:
     * A partir del EVA se obtiene el EVSCXA_id.
     * La correspondencia entre el EVSCXA_id es buscada en esta secuencia:
     * 1° en la tabla relacional se obtiene el cuenta_corriente_id,
     * si existe, se trabaja con ese item.
     * 
     * Si no es encontrado:
     * 2° a partir del EVSCXA_id se obtiene la fecha de inicio asignada al item.
     * Si la fecha-mes a que corresponde el EV es superior a la fecha actual,
     * no deberá hacerse Débito o Crédito.
     * Si la fecha-mes es viable, es igual o anterior 
     * se busca en cuentas corrientes todo lo que pertenece a esa 
     * fecha de operación.
     * Obteniendo esos items, se busca posible coincidencia en la descripción
     * del "motivo" en cuentas_corrientes.
     * 
     * Si en ningun caso es encontrado el item, 
     * no se generará movimiento en cuentas_corrientes, 
     * y se intentará avisar al usuario, que el cambio de EVA no genero
     * movimientos en cuentas_corrientes.
     * 
     * 
     * INPUT
     * $arrayValues
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
                'ev_descripcion',
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
     * 
     * $valorOriginal   es el que tenía antes de modificar ahora
     * $valorNuevo
     * $motivoDesc  es una descripción o identificación de la causa que motiva el cambio,
     *              por ejemplo: "cambio del valor general", o 
     *                          "cambio para el alumno"
     * 
     * OUTPUT
     * <boolean>
     *          TRUE        proceso ok. el padre podrá continuar proceso.
     *          FALSE       hubo error. el padre no debe continuar proceso.
     * 
     */
    private function _procesarModificacionDeValorEV( $arrayValues, $valorOriginal, $valorNuevo, $observaciones='' )
    {
        // Evaluo si es de una fecha futura, y descarto.
        // Si es igual, es probable que aun no se haya ejecutado el proceso de generación de deudas.
        if( $arrayValues['fecha_inicio_calculado'] > date('Y-m-d') ){
            return true;
        }
        
        
        // RECOPILACIÓN DE DATOS
        
        $esUnCredito = ( $valorNuevo < $valorOriginal )? true : false;
        $diferenciaValores = $valorOriginal - $valorNuevo ;
        $tipoOperacion = ( $diferenciaValores < 0 )? 'DEBITO_AUTOMATICO':'NOTA_CREDITO_AUTOMATICO';  
        $valorOriginal = ( $tipoOperacion == 'NOTA_CREDITO_AUTOMATICO' )? $valorOriginal : -$valorOriginal;
        $valorNuevo = ( $tipoOperacion == 'NOTA_CREDITO_AUTOMATICO' )? $valorNuevo : -$valorNuevo;
        
        $anioInicioCalculado = substr( $arrayValues['fecha_inicio_calculado'], 0, 4);
        $anioMesInicioCalculado = substr( $arrayValues['fecha_inicio_calculado'], 0, 7);
        $esMesDeHoy = ( $anioMesInicioCalculado == date('Y-m') )? true : false;
        $esAnioMesPosterior = ( $anioMesInicioCalculado > date('Y-m') )? true : false;
        $esDeEsteAnio = ( $anioInicioCalculado == date('Y') )? true : false;
        

        // BUSQUEDA DEL MOVIMIENTO DEUDA PRIMERA DEL ITEM
        
        // 1.METODO CON ID DEL EVSCXA 
        $ctaCteId = $this->_existeFacturaDeScxaId( $arrayValues['alumnos_id'], $arrayValues['evscxa_id'] );
        if( $ctaCteId ){
            $CuentaCorrienteDebito = $this->obtenerPorIdGeneral( $ctaCteId, $this->_class_origen );
        }else{
        
            // 2.METODO ANTIGUO, USANDO LOS STRINGS ESCRITOS EN EL MOTIVO
            // ( las rows más antiguas, solo pueden encontrarse con estos metódos )
            // En algunos casos, si el motivo fue modificado o no contiene
            // los identificadores primarios del curso(nombre,clasificador_valor,etc)
            // el item no será encontrado.
            
            $relacionEntreModuloGastoYModuloCuentaCorriente = new RelacionEntreModuloGastoYModuloCuentaCorriente();
            // De $arrayValues, solo tomará estas keys: 'ev_abreviatura'
            //                                          'clasificador_valor'
            //                                          'anio'
            // *** POR AHORA LA BUSQUEDA DEL DEBITO ES POR STRING Y NO POR EL ID DEL EVSCXA
            // Un item puede tener asociado varios movimientos.
            // Primero buscaré el DEBITO que aun esté en deuda, y sino, 
            // el DEBITO que ya fue saldado.
            $movimientosDeLaCuentaDelAlumno = $this->obtenerGeneral( array( 'alumnos_id' => $arrayValues['alumnos_id'] ), 'id', $this->_class_origen  );
            $CuentaCorrienteDebito = $relacionEntreModuloGastoYModuloCuentaCorriente
                                            ->getMovCtaCteCorrespondiente( 
                                                    $arrayValues, 
                                                    $movimientosDeLaCuentaDelAlumno,
                                                    $conDeuda=true
                                                ); 
            if( !$CuentaCorrienteDebito ){
                // lo busco de otra manera. ( Podría no encontrarlo tampoco )
                $CuentaCorrienteDebito = $relacionEntreModuloGastoYModuloCuentaCorriente
                                            ->getMovCtaCteCorrespondiente( 
                                                    $arrayValues, 
                                                    $movimientosDeLaCuentaDelAlumno,
                                                    $conDeuda=false
                                                );
            }
        }
        
        /*
         * IMPORTANTE:
         * Si el débito no está generado, puede deberse a varias situaciones:
         *  1- aun no ha llegado la fecha de generación de la deuda.
         *  2- se refiera a un año anterior, que ya el cron no está ejecutando.
         *  3- o que el valor original era cero, y por eso no se generó la deuda.
         * 
         * Para estos casos, o se genera la deuda, o se genera el ajuste.
         * Si es caso:
         *  1: Nada por hacer. El cambio será generado cuando llegue el momento.
         *  2 y 3: Se puede hacer la generación de deuda o el ajuste. 
         *      Creo que lo más correcto sería hacer un ajuste, ya que ello,
         *      dejaría claro que el cambio fue hecho fuera de tiempo por 
         *      un cambio manual.
         *      Sin embargo, si llegase a correrse la generación de deudas hacia atrás,
         *      se generaría la deuda de ese item, con lo cuál estaría generandose
         *      la deuda 2 veces, una como ajuste y otra como deuda normal.
         *      Así que para evitar ello, haré que se genere la deuda.
         */
        if( !$CuentaCorrienteDebito 
            //    && 
            //(   !$esDeEsteAnio      // El débito no se generará vía cron ya que es un año viejo.
            //    ||                  // o ...
            //    $valorOriginal==0   // Se le asigna valor ahora, 
            //                        //
            //                        // Generaré la deuda ahora, sin Ajuste
            //)
        ){
            // 1:
            if( $esAnioMesPosterior ){
                return true;    // nada por hacer
            }
            // 2 y 3:
            require_once 'cuentacorriente/logicalmodels/FacturacionMensual.php';
            $model = new FacturacionMensual();
            // desde, hasta, sede, repetidos=false, alumno 

            $r = $model->generarFacturacionMensual( 
                            substr($arrayValues['fecha_inicio_calculado'],0,4).'-01-01', 
                            $fechaDeFinDeCobros=substr($arrayValues['fecha_inicio_calculado'],0,8).'28', // solo para correr ese mes
                            $sedeIdAProcesar=$arrayValues['sedes_id'],
                            $logSiYaEstabaCobrado=false,
                            $alumnosId=$arrayValues['alumnos_id']
                        );
            return true;    // debo devolver TRUE para que el proceso continue ok
            
            
        }else{
            // Existe en cta corriente
            //      
            // Creación de ajuste, con la cobertura saldo.            
            
            if( $esUnCredito ){
                // MODIFICACIÓN DE LA COBERTURA DEL ITEM ORIGINAL
                // ( esto quizás pudiera obviarse y dejar que se encargue el 
                // distribuidor de créditos-coberturas )
                
                // Habrá que actualizar la cobertura del item original.
                // La cuenta para obtener la cobertura modificada es un poquito más compleja, 
                // le solicito al model de Cobertura el valor:
                $debitoValorAModificarCobertura = 
                                    $this->_cobertura
                                        ->getValorAModificarCobertura( 
                                                $valorOriginal, 
                                                $valorNuevo, 
                                                $CuentaCorrienteDebito->getMonto(), 
                                                $CuentaCorrienteDebito->getCobertura()
                                            );
                $debitoNuevaCobertura = $CuentaCorrienteDebito->getCobertura() + $debitoValorAModificarCobertura;

                // Modifico la cobertura:
                if( $CuentaCorrienteDebito->getCobertura() <> $debitoNuevaCobertura ){
                    $CuentaCorrienteDebito->setCobertura( $debitoNuevaCobertura );
                    $x=$this->modificacionactualizacionGeneral($CuentaCorrienteDebito, $this->_class_origen );
                }
                // El alta del ajuste con su cobertura, lo creo más abajo.
                
                
            }else{
                $debitoValorAModificarCobertura = 0;
            }

            $ajusteMonto = $diferenciaValores ; 
            // LA COBERTURA, VA CON DISTINTO SIGNO DE DEBITO A CREDITO
            $ajusteCobertura = -$debitoValorAModificarCobertura;
            
            
        }
            
        /*
        // Si tenía un valor, y el mes es anterior, 
        // siendo que no se encontro la identificación del débito $CuentaCorrienteDebito;
        // podría ser porque el débito nunca se genero
        }elseif( $valorOriginal!=0 && !$esMesDeHoy ){
            // Podría ser síntoma de algun error:
            $arrayValues['error_descripcion'] = 'Se modifica un precio asignado en eva de algo que no está debitado';
            if( !$this->enviarMailAWebmaster( $arrayValues ) ){
                $this->addColeccionMensajes( 
                        'ERROR_ Hay alumnos que no tienen facturado este item.<br>'.
                        'AVISE A SISTEMAS el nombre del alumno con el que surgió este error.');
                return false; // la actualización del eva se retracta
            }
            $ajusteMonto = $valorOriginal-$valorNuevo;
            $ajusteCobertura = 0;
         */
                        
        
        // ALTA DEL AJUSTE
        $ajuste = array();
        $ajuste['tipo_operacion'] = $tipoOperacion; 
        // si bien los movimientos se llaman DEBITO o CREDITO MANUAL, 
        // lo correcto sería llamarlos AUTOMATICO        
        $ajuste['comprobante']      = null;        
        $ajuste['persona_en_caja']  = USUARIO_NOMBRE;
        $ajuste['usuario_nombre']   = USUARIO_NOMBRE;
        $ajuste['alumnos_id']       = $arrayValues['alumnos_id'];        
        $ajuste['fecha_operacion'] = date('Y-m-d');
        // El motivo no es solo una descripción textual del movimiento. Es muy importante.
        // Ya que es uno de los campos que trabaja en el UNIQUE
        // evitando repeticiones de cobros.
        // Dado que en las primeras versiones de la contabilidad, no había manejo de ids en los movimientos,
        // la manera del sistema para encontrar referencias a un movimiento, es escaneando y analizando este texto.
        $ajuste['motivo'] = $this->getMotivoNormalizado( 
                                            $arrayValues['nombre_humano'],
                                            $arrayValues['nombre_computacional'],
                                            $arrayValues['clasificador_nombre'],
                                            $arrayValues['clasificador_valor'],
                                            $arrayValues['anio'],
                                            $arrayValues['ev_abreviatura']
                                        );
        $descripcionNormalizada = $this->_EvscxaDescripcionModificada->getDescripcion( $arrayValues['evscxa_id'] );
        $ajuste['motivo']= ( $descripcionNormalizada )? $descripcionNormalizada : $ajuste['motivo'];
        $observaciones = ( empty($observaciones) )? '' : ", $observaciones, ";
        $ajuste['observaciones'] = "Ajuste automático al modificar ".$arrayValues['ev_abreviatura']."$observaciones de $".abs($valorOriginal).' a $'.abs($valorNuevo);
        $ajuste['monto'] = $ajusteMonto;
        
        $ajuste['cobertura'] = $ajusteCobertura;

        $ajuste['id'] = $this->altaGeneral( $ajuste, $this->_class_origen );
        
        $CuentaCorrienteAjuste = new CuentaCorriente( $ajuste );
        
        
        // ALTAS EN LA TABLA DE RELACION (cuentascorrientes_elementosvaluados)
        $registrador = new RegistradorDeMovimiento( $this, $this->funcionesSobreEVAs );        
        // relación con el movimiento que modifica el valor.
        $registrador->crearRelacionMovimiento( $CuentaCorrienteAjuste, $arrayValues['evscxa_id'], $ajusteMonto );        
        
        
        // Busca si hay créditos disponibles para distribuir        
        $registrador->distribuirCreditos( $arrayValues['alumnos_id'], $anio=null, $impactar=true );
        
        // Auditoria. Registro el ajuste
        $auditoriaColeccion = new AuditoriaColeccion();
        $auditoriaColeccion->registrar( 'alta', 'cuentas_corrientes', $ajuste['id'], $ajuste );

        
        return true;
    }
    
    
        
    /*
     * 
     * 
     * OUTPUT
     *      Por error:
     *      <array>             
     *          key => 'ERROR'  => array() descripciones key de los errores.
     *                                      El movmiento no era viable.
     *      Por ok:
     *      <array>     
     *          'objetos'     => array, Detalle con los objetos CuentaCorriente débitos trabajados:
     *          'pagos'       => array, Pagos realizados a cada uno.
     *          'sobrante'    => num,   Dinero que ha quedado a favor del alumno.
     * 
     */
    public function registrarPagoDesdeDataGrid( $params )
    {
        $params['tipo_operacion'] = 'PAGO_MANUAL';
        $params['motivo'] = ''; // tiene que estar declarado. Será calculado luego.
        $params['origen'] = 'A'; // ACADEMICO
        return $this->registrarMovimiento( $params );
    }
    
    public function registrarAjusteDesdeDataGrid( $params )
    {
        $params['tipo_operacion'] = ( $params['planilla']=='nota_debito' )? 'DEBITO_MANUAL' : 'NOTA_CREDITO_MANUAL';
        $params['motivo'] = (!empty($params['observaciones']))? trim($params['observaciones']) : 'Ajuste correctivo';
        $params['origen'] = 'A'; // ACADEMICO
        return $this->registrarMovimiento( $params );
    }

    /*
     * Ver $params en RegistradorDeMovimiento->registrarMovimiento()
     */
    public function registrarMovimiento( $params )
    {
        $registrador = new RegistradorDeMovimiento( $this, $this->funcionesSobreEVAs );
        return $registrador->registrarMovimiento( $params );
    }
    
    // Busca creditos con saldo a favor que puedan ser distribuidos en coberturas
    // (impacta en las tablas)
    public function distribuirTodosLosCreditosLibres( $sedes_id )
    {
        $registrador = new RegistradorDeMovimiento( $this, $this->funcionesSobreEVAs );
        return $registrador->distribuirTodosLosCreditosLibres( $sedes_id );
    }
    
    public function getFiltroParaObtenerMovimientosQuePuedenSerPagados( $vaAgrupadoPorEvscxa=true )
    {
        if( $vaAgrupadoPorEvscxa ){
            return
            '( sum_monto_debitos < 0 AND sum_monto_debitos < sum_cobertura_debitos ) OR '.
            '( sum_monto_debitos_a_cuenta < 0 AND sum_monto_debitos_a_cuenta < sum_cobertura_debitos_a_cuenta )';
        }else{
            return 'monto < 0 AND monto < cobertura'; 
        }
    }
    
    // mov. que son débitos y tienen algun pago hecho.
    // Si no tienen pagos, en realidad, lo que debe hacerse 
    // es un cambio de precio del item sobre el menú de precios.
    // Y si no refieren a ningun evscxa,
    // es decir que son movimientos generales a cuenta
    // deben tener un saldo <> 0        
    public function getFiltroParaObtenerMovimientosQuePuedenAplicarNotaDeDebito( $vaAgrupadoPorEvscxa=true )
    {
        if( $vaAgrupadoPorEvscxa ){
            return
                'sum_monto_debitos < 0 AND sum_cobertura_debitos <> 0 AND evscxa_id IS NOT NULL '.
                'OR ( evscxa_id IS NULL AND (sum_monto_debitos-sum_cobertura_debitos) <> 0 )';   
        }else{
            return 'monto < 0 AND cobertura <> 0 AND evscxa_id IS NOT NULL '. 
                   'OR ( evscxa_id IS NULL AND (monto-cobertura) <> 0 )';
        }
    }
    
    // mov. que son débitos 
    public function getFiltroParaObtenerMovimientosQuePuedenAplicarNotaDeCredito( $vaAgrupadoPorEvscxa=true )
    {
        if( $vaAgrupadoPorEvscxa ){
            return
                'sum_monto_debitos < 0 AND sum_monto_debitos < sum_cobertura_debitos ';   
        }else{
            return 'monto < 0 AND monto < cobertura';
        }
    }
    
    public function getFiltroParaObtenerDebitos( $vaAgrupadoPorEvscxa=false )
    {
        if( $vaAgrupadoPorEvscxa ){
            // Por qué no debo filtrar simplemente por "monto<0"?:
            // R: Durante el group by, la fila que queda por cada evscxa
            // puede ser la descripción de un débito o un crédito, 
            // y su monto, más allá de los totalizadores como sum_monto_debitos,
            // puede ser un valor positivo,  con lo que al llegar al 
            // segundo query que filtra por montos < 0, esa fila quedaría fuera.
            // La solución que encontre para que me traiga los totales fue 
            // filtrar por sum_monto_debitos<0 y no por monto<0.
            // ( En realidad, lo correcto sería modificar el SQL, para que 
            // luego del group by, se quede solo con las filas de montos < 0  )
            return 'sum_monto_debitos < 0';
        }else{
            return 'monto < 0 '; 
        }
    }
    

}