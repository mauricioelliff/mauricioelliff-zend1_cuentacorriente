<?php


/**
 * Esta clase maneja las operaciones de actualización de coberturas y 
 * actualización de la tabla de relación entre cuentas corrientes y elementos valuados.
 * 
 * Aclaraciones:
 * De menor importancia, en los parámetros de las funciones aquí,
 * suele aparecer primero el año antes que la sede,
 * ya que en cuentas corrientes, es más importante el año, ya que define muchas situaciones.
 * Y en muchos casos el parámetro sede no interesa.
 *
 * @author mauricio
 */

require_once 'extensiones/generales/ClaseBaseAbstracta.php';

require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';
require_once 'cuentacorriente/models/CuentaCorrienteElementoValuadoColeccion.php';

require_once 'cuentacorriente/logicalmodels/FuncionesSobreEVAs.php';
require_once 'cuentacorriente/logicalmodels/Cobertura.php';
require_once 'cuentacorriente/logicalmodels/MotivoCuentaCorriente.php';

require_once 'admin/models/SedeCursoxanioColeccion.php';
require_once 'admin/models/SedeCursoxanioAlumnoColeccion.php';
require_once 'admin/models/SedeColeccion.php';
// require_once 'default/models/Query.php';

class CoberturaOperaciones extends ClaseBaseAbstracta
{
    
    private $_cuentaCorrienteColeccion;
    private $_relacionColeccion;
    private $_funcionesSobreEVAs;
    private $_cobertura;
    private $_sedeCursoxanioColeccion;
    private $_sedeCursoxanioAlumnoColeccion;
    private $_sedeColeccion;
    private $_alumnoCursos; // view values con toda la data de sus cursos
    public $sedes_id;
    
 
    
    public function __construct($sedes_id,
                                CuentaCorrienteColeccion $CuentaCorrienteColeccion, 
                                FuncionesSobreEVAs $FuncionesSobreEVAs=null,
                                // y opcinal ...
                                CuentaCorrienteElementoValuadoColeccion $CuentaCorrienteElementoValuadoColeccion=null
                                )
    {
        parent::__construct();
        
        $this->sedes_id = $sedes_id;
        
        $this->_sedeColeccion = new SedeColeccion();
        $this->_cuentaCorrienteColeccion= $CuentaCorrienteColeccion;// new CuentaCorrienteColeccion();
        $this->_funcionesSobreEVAs      = $FuncionesSobreEVAs; //new FuncionesSobreEVAs();        
        
        if( !$CuentaCorrienteElementoValuadoColeccion ){
            $CuentaCorrienteElementoValuadoColeccion = new CuentaCorrienteElementoValuadoColeccion();
        }
        $this->_relacionColeccion       = $CuentaCorrienteElementoValuadoColeccion; // new CuentaCorrienteElementoValuadoColeccion();
        
        $this->_cobertura               = new Cobertura();
        $this->_motivoCuentaCorriente    = new MotivoCuentaCorriente();
        
        $this->_sedeCursoxanioColeccion = new SedeCursoxanioColeccion();
        $this->_sedeCursoxanioAlumnoColeccion = new SedeCursoxanioAlumnoColeccion();
        
    }
    
    /*
     * 
     */
    public function distribuirTodosLosCreditosLibres( $sedes_id=null )
    {
        $sedes_id = ( is_null($sedes_id) )? $this->sedes_id : $sedes_id;
        if( isset($sedes_id) ){
            $sedes = [ $sedes_id ];
        }else{
            $sedes = array_keys( $this->_sedeColeccion->getSoloSedes() );
        }
        
        foreach( $sedes as $id ){
            $alumnosIdsDeLaSede = $this->_sedeCursoxanioAlumnoColeccion
                                        ->getAlumnosDeLaSede( $id );
            if( $alumnosIdsDeLaSede ){
                foreach( $alumnosIdsDeLaSede as $alumnos_id ){
                    $this->distribuirCreditos( $alumnos_id, null, $impactar=true );
                }
            }
        }
    }
    
    
    /*
     * Lo utilizo en la función de impactar.
     * 
     * Distribuye el saldo de créditos a débitos.
     * 
     * REFACTORING PENDIENTE:---------------------------------------------------
     * la distribución de créditos, debe dar prioridad
     * al pago de lo que el crédito intenta saldar, 
     * y no, directamente de la lista de deudas.
     * Habrá que hacer algo similar a cuando el usuario carga un pago, o 
     * Por ej. en $relacionEntreModuloGastoYModuloCuentaCorriente
     *              ->getMovCtaCteCorrespondiente()
     *          hace una busqueda de a que movimiento refiere ese motivo.
     *          Usado en CuentaCorrienteColeccion->_procesarModificacionDeValorEV()
     * -------------------------------------------------------------------------         
     * 
     * INPUT
     * $alumnos_id
     * $anio    <int>   MUY IMPORTANTE.
     *                  De no estar presente, toma los créditos en un orden general,
     *                  y los distribuye en débitos en un orden general.
     *                  De estar presente,
     *                  SOLO TRABAJARÁ CON CRÉDITOS DE ESE AÑO (desde fecha_operacion), 
     *                  Y LOS VOLCARÁ A DÉBITOS DE ESE AÑO.
     * $simularDistribucion
     * 
     * OUTPUT
     *      <array>     Detalle con los objetos CuentaCorrienteDebito  saldados
                array(3) {
                ["objetos_debito"] => array(2) 
     *          ["pagos"]
     *          ["fueTodoPagado"]   Es un boolano para indicar si esta todo pagado
     *          
     */
    public function distribuirCreditos( $alumnos_id, $anio=null, $impactar=false )
    {
        $trabajados = array('objetos_debito'    => array(), 
                            'pagos'             => array(), 
                            'fueTodoPagado'     => false 
                            );

        // CHECKS DE QUE EXISTA ALGÚN CRÉDITO SIN CUBRIR TOTALMENTE
        $creditosLibres = $this->_cuentaCorrienteColeccion
                                ->getCreditosDelAlumno( $alumnos_id, $coberturaPendiente=true, $anio ); 

        if( !$creditosLibres ){
            $trabajados['fueTodoPagado'] = true;
            return $trabajados;
        }
        
        // CHECKS DE QUE EXISTA ALGO DONDE PODER CUBRIR DEUDA
        $dondeDistribuir = $this->_cuentaCorrienteColeccion->getRowsPorSaldar( $alumnos_id );
        if( !$dondeDistribuir || 
            !is_array($dondeDistribuir) || count($dondeDistribuir)==0 ){
            $trabajados['fueTodoPagado'] = true;
            return $trabajados;
        }
             
        // CHECK DE QUE CORRESPONDAN AL AÑO SOLICITADO
        if( isset($anio) ){
            $dondeDistribuir = 
                    array_filter($dondeDistribuir, 
                                function ($item) use ($anio) {
                                        return ( substr($item['fecha_operacion'],0,4) == $anio );
                                });
        }  
        if( count($dondeDistribuir)==0 ){
            $trabajados['fueTodoPagado'] = true;
            return $trabajados;
        }
        
        $objetosDondeDistribuirDesordenados = $this->_cuentaCorrienteColeccion->obtenerGeneral( array('id' => array_keys($dondeDistribuir)), 'id', 'CuentaCorriente' );
        // $objetosDondeDistribuir DEBE TENER EL ORDEN DE $dondeDistribuir
        $objetosDondeDistribuir = [];
        foreach( array_keys($dondeDistribuir) as $id ){
            if( key_exists($id,$objetosDondeDistribuirDesordenados) ){
                $objetosDondeDistribuir[$id]=$objetosDondeDistribuirDesordenados[$id];
            }
        }
        
        // Comienzo del proceso.
        // Iré consiguiendo los input para lanzar distribuirUnCredito()
        // con cada crédito que exista:
        $distris = [];
        foreach( $creditosLibres as $CuentaCorrienteCredito ){ 
            if( !$objetosDondeDistribuir || 
                is_array($objetosDondeDistribuir) && count($objetosDondeDistribuir)==0){
                break;
            }
            $distris[ $CuentaCorrienteCredito->getId() ] = 
                        $this->distribuirUnCredito( $CuentaCorrienteCredito, 
                                                    null,   // $evscxaidsPaga
                                                    null,   // $evscxaIdXCtacteId ,
                                                    $objetosDondeDistribuir 
                                                );
            // agrego el CuentaCorrienteCredito antes de iniciar la distribución
            // (es decir, como estaba en un principio, sin asentar las coberturas)
            $distris[ $CuentaCorrienteCredito->getId() ][ 'CuentaCorrienteCredito' ] =
                $this->_cuentaCorrienteColeccion->obtenerPorIdGeneral( $CuentaCorrienteCredito->getId(), 'CuentaCorriente');    
        }
        if( $impactar && count($distris)>0 ){
            $aux=end($distris);
            //$fueTodoPagado = is_array($aux) && key_exists('fueTodoPagado',$aux)? $aux['fueTodoPagado'] : true ;
            $this->impactarCreditos( $distris );
        }
        return $distris;
    }
    
    
    /*
     * INPUT
     *      trabaja la salida de $this->distribuirCreditos()
     * 
     * $acreditaciones
     *   <array>   
     *      <array>   
     *        creditoID => 
     *          'CuentaCorrienteCredito'   => CuentaCorrienteCredito      <CuentaCorriente>
                'evscxa' => array(2) {
                        [334] => array      en este ej. podría haber más de un débito al mismo item
                                    debitoId
                                    [41861] => int(1700)  $
                                    [41545] => int(300)   $
                        [335] => array
                                    [41545] => int(300)
                      }
     */
    public function impactarCreditos( array $distribuciones )
    {
        foreach( $distribuciones as $creditoData ){
            $this->impactarCredito( $creditoData['CuentaCorrienteCredito'], $creditoData['evscxa'] );
        }
    }
    
    /*
     * Recorre la lista de debitos con sus pagos ya calculados, 
     * y los impacta en las tablas de cobertura y relación.
     * 
     * $CuentaCorrienteCredito  origen. Aun con cobertura en cero.
     * $debitos                 destinos
     *      <array>
     *      $EvscxaXDebitosYSuPago id => 
     *              <array> debitoId => montoPagado
     *      ( Extracto de debitosTrabajados )
     *      ej:
              array(2) {
                [334] => array      en este ej. podría haber más de un débito al mismo item
                            ctacteid
                            [41861] => int(1700)  $
                            [41545] => int(300)   $
                [335] => array
                            [41545] => int(300)
              }
     * 
     */
    public function impactarCredito( CuentaCorriente $CuentaCorrienteCredito, array $EvscxaXDebitosYSuPago )
    {
        foreach( $EvscxaXDebitosYSuPago as $evscxaId => $pagos ){
            
            foreach( $pagos as $ctacteId => $monto ){
        
                $CuentaCorrienteDebito = $this->_cuentaCorrienteColeccion
                                                ->obtenerPorIdGeneral($ctacteId, 'CuentaCorriente');
                // Modificacion de los objetos
                $this->setCoberturasDeUnCreditoAUnDebito( $CuentaCorrienteCredito, $CuentaCorrienteDebito, $monto );

                // Escribe en las tablas, los cambios en débito y crédito, 
                // y en la tabla de relación con evscxa
                $this->escribirCoberturas( $CuentaCorrienteCredito, $CuentaCorrienteDebito, $monto, $evscxaId );
            }
        }
    }
    
    /*
     * Distribuye el credito disponible, entre deudas con cobertura pendiente.
     * (Las rows de la tabla cuenta_corriente no son actualizadas aquí ahora)
     * 
     * INPUT
     * $CuentaCorrienteCredito      <CuentaCorriente>
     *        Es un pago en memoria. Inexistente aun.
     *        Se verá afectado por referencia, 
     *        según el pago se vaya distribuyendo.
     * $evscxaidsPaga   Son las indicaciones de lo que se quiere pagar
     *      array(1) {
                [6305] => int(2)        evscxaId => monto
              }
     * $evscxaIdXCtacteId
     *      array(1) {
     *          [6305] => int(111618)
     *      }
     * $DebitosDondeDistribuir
     * array(1) {
     *   [111618] => object(CuentaCorriente)#844 (14) {
     *     ["_id":"CuentaCorriente":private] => string(6) "111618"
     *     ["_origen":"CuentaCorriente":private] => string(1) "A"
     *     ["_alumnos_id":"CuentaCorriente":private] => string(8) "17880732"
     *     ["_tipo_operacion":"CuentaCorriente":private] => string(18) "FACTURA_AUTOMATICA"
     *     ["_fecha_operacion":"CuentaCorriente":private] => string(10) "2021-12-01"
     *     ["_monto":"CuentaCorriente":private] => string(8) "-4600.00"
     *     ["_cobertura":"CuentaCorriente":private] => string(5) "-3.00"
     *     ["_motivo":"CuentaCorriente":private] => string(22) "Nivel 2, Cuota 9, 2021"
     *     ["_comprobante_sede":"CuentaCorriente":private] => string(1) "3"
     *     ["_comprobante":"CuentaCorriente":private] => string(0) ""
     *     ["_persona_en_caja":"CuentaCorriente":private] => string(18) "proceso_automatico"
     *     ["_observaciones":"CuentaCorriente":private] => NULL
     *     ["_usuario_nombre":"CuentaCorriente":private] => string(13) "USUARIO_BATCH"
     *     ["_fecha_hora_de_sistema":"CuentaCorriente":private] => string(26) "2021-12-01 04:00:09.572300"
     *   }
     * }
     * 
     * 
     * OUTPUT
     *      <array>     Detalle con los objetos CuentaCorrienteDebito  saldados
                array(3) {
                  ["objetos_debito"] => array(2) {  OBJETOS AFECTADOS
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
                      ["_alumnos_id":"CuentaCorriente":private] => string(3) "865"
                      ["_tipo_operacion":"CuentaCorriente":private] => string(18) "FACTURA_AUTOMATICA"
                      ["_fecha_operacion":"CuentaCorriente":private] => string(10) "2018-05-01"
                      ["_monto":"CuentaCorriente":private] => string(5) "-1700"
                      ["_cobertura":"CuentaCorriente":private] => int(-400)
                      ["_motivo":"CuentaCorriente":private] => string(29) "2018, CU2 profesorado nivel 2"
                      ["_comprobante":"CuentaCorriente":private] => string(8) "no_tiene"
                      ["_persona_en_caja":"CuentaCorriente":private] => string(18) "proceso_automatico"
                      ["_observaciones":"CuentaCorriente":private] => string(231) "Sistemas inicializa coberturas. Le asigna $100Sistemas inicializa coberturas. Le asigna $300Sistemas inicializa coberturas. Le asigna $25Sistemas inicializa coberturas. Le asigna $1275Sistemas inicializa coberturas. Le asigna $1700"
                      ["_usuario_nombre":"CuentaCorriente":private] => string(18) "proceso_automatico"
                      ["_fecha_hora_de_sistema":"CuentaCorriente":private] => string(19) "2018-05-04 21:45:07.123456"
                    }
                  }
                  ["pagos"] => array(2) {
                    [41861] => int(1700)
                    [41545] => int(300)
                  }
                  ["evscxa"] => array(2) {
                    [334] => array      en este ej. podría haber más de un débito al mismo item
                                ctacteid
                                [41861] => int(1700)
                                [41545] => int(300)
                    [335] => array
                                [41545] => int(300)
                    Si no tiene un id de evscxa, 
                    la key será 0 (ej. 'otros conceptos'):
                    ['0'] => array
                                ctacteid
                                [120724] => 150
                                [120728] => 200
                                [120727] => 100
                  }
                  ["credito_disponible"] => int(0)
                   "fueTodoPagado"  => boolean (en true es que no hay más deuda)
                }
     */
    public function distribuirUnCredito(CuentaCorriente $CuentaCorrienteCredito,
                                        $evscxaidsPaga=null,
                                        $evscxaIdXCtacteId=null,
                                        $DebitosDondeDistribuir=null )
    {
        // DISTRIBUCION DEL CREDITO
        $debitosTrabajados = array( 'objetos_debito'=> array(), 
                                    'pagos'         => array(), 
                                    'dondeDistribuir'=>$DebitosDondeDistribuir, // pendiente de saldar
                                    'evscxa'        => array(),
                                    'fueTodoPagado' => false
                                    );

        if( !$DebitosDondeDistribuir ){ 
            $debitosTrabajados['fueTodoPagado']=true;
            return $debitosTrabajados;
        }
        
        // Los debitos donde distribuir, ya vienen ordenados según la prioridad de pago
        // (incluso si ha elegido items)
        foreach( $DebitosDondeDistribuir as $ctacteId => $CuentaCorrienteDebito ){            
            
            // Chequeo si hay dinero para repartir
            if ( $CuentaCorrienteCredito->getSaldo() <= 0 ){
                break;  
            }

            // 2 PASOS EN LA COBERTURA: CALCULO Y LUEGO ESCRITURA.
            // Modifico por ahora virtualmente (ya que puede querer simular)
            // (Objetos PHP mantienen su valor por referencia implícita)
            
            // Toma un primer valor si hay indicación de pago:
            if( is_array($evscxaIdXCtacteId) && is_array($evscxaidsPaga)
                && key_exists($evscxaId=array_search($ctacteId,$evscxaIdXCtacteId), $evscxaidsPaga )){
                $pagara = ($evscxaidsPaga[$evscxaId] <= $CuentaCorrienteCredito->getSaldo())? $evscxaidsPaga[$evscxaId] : $CuentaCorrienteCredito->getSaldo();
            }else{
                $pagara = $CuentaCorrienteCredito->getSaldo();
            }
            // Calcula cuanto es lo que realmente se asignará a ese item,
            // y modifica los objetos por referencia.
            $pagaAhora = $this->setCoberturasDeUnCreditoAUnDebito(  $CuentaCorrienteCredito, 
                                                                    $CuentaCorrienteDebito,
                                                                    $pagara );
            if( !$pagaAhora ){
                continue;   // el item pudo tener el saldo completado
            }

            $debitosTrabajados['objetos_debito'][ $ctacteId ] = $CuentaCorrienteDebito;
            $debitosTrabajados['pagos'][ $ctacteId ]   = $pagaAhora;

            // Si ya tengo el evscxa_id, se podrá registrar en la tabla de relación.
            // Si no, quedará como una débito o crédito general.
            if( is_array($evscxaIdXCtacteId) && is_array($evscxaidsPaga)
                    && array_search($ctacteId,$evscxaIdXCtacteId)
                    && key_exists($evscxaId=array_search($ctacteId,$evscxaIdXCtacteId), $evscxaidsPaga )){
                // $evscxaId
            }elseif( $aux=$this->_relacionColeccion->obtenerGeneral(
                            ['cuentas_corrientes_id'=>$CuentaCorrienteDebito->getId()],
                            'id', 'CuentaCorrienteElementoValuado', false, true ) ){
                $evscxaId = $aux->getElementosValuadosSedesCursosxanioId();
            }else{
                // Trato de encontrarlo, ya que el débito podría 
                // no estar cargado aun en la tabla de relación 
                // (cuentascorrientes_elementosvaluados).
                // Pero solo tendrá coincidencias en strings anteriores a 2021-04-27 
                // cuando cambie la descripción del motivo, 
                // dejando directamente el nombre humano del curso.
                // en CuentaCorrienteColeccion->getMotivoNormalizado().
                // 
                // (El ste. acceso a funcionesSobreEva me ha quedado sucio, ya que tengo
                // que acceder a ella para pasarle parametros que ella misma calcula).

                $evscxaId = $this->_getEvscxaidDesdeElMotivo( $CuentaCorrienteDebito );
            }
                
            $debitosTrabajados['evscxa'][ $evscxaId ][ $ctacteId ] = $pagaAhora;

            
            if( $CuentaCorrienteDebito->getSaldo()==0 ){
                unset( $DebitosDondeDistribuir[ $ctacteId ] );
            }else{
                // calculo valores para devolver la lista de pendiente actualizada
                // $dondeDistribuir[ $ctacteId ]['monto']=$CuentaCorrienteDebito->getMonto();
                // $dondeDistribuir[ $ctacteId ]['cobertura']=$CuentaCorrienteDebito->getCobertura();
            }
        }
        
        if( count($DebitosDondeDistribuir)==0 ){
            $debitosTrabajados['fueTodoPagado']=true;
        }
        $debitosTrabajados['dondeDistribuir'] = $DebitosDondeDistribuir;   // deja los elementos que aun no han sido cubiertos

        return $debitosTrabajados;
    }
    
    /*
     * Se utiliza cuando se quiere aumentar la deuda de un alumno sobre un item.
     * No se trata de aumentar el valor del item. Eso sería otra cuestión.
     * Sino que se disminuye la cobertura del item, lo que aumenta la deuda.
     * 
     */
    public function distribuirUnDebitoEnOtroDebito( CuentaCorriente $CuentaCorrienteDebitoOrigen, 
                                                    CuentaCorriente $CuentaCorrienteDebitoDestino,
                                                    $evscxa_id=false,
                                                    $simularAccion=false )
    {
        // Se achicará la cobertura hasta un máximo de cero:
        
        // Objetos PHP mantienen su valor por referencia implícita
        // Luego de asignar, $restoNoAplicable será el importe que quede con cobertura pendiente.
        // por ejemplo si la row tenía moto -1200 e ingresa ahora -1500. excedente -300
        // que quedará pendiente en el nuevo ajuste.
        $restoNoAplicable = $this->setCoberturasDeUnDebitoAOtroDebito( 
                                        $CuentaCorrienteDebitoOrigen, 
                                        $CuentaCorrienteDebitoDestino  );

        // ESCRITURA EN LAS TABLAS
        if( !$simularAccion ){
            $this->escribirCoberturas(  $CuentaCorrienteDebitoOrigen, 
                                        $CuentaCorrienteDebitoDestino, 
                                        $CuentaCorrienteDebitoOrigen->getMonto(), 
                                        (int)$evscxa_id 
                                        );
        }

        $debitosTrabajados = array( 'objetos_debito'   => array( $CuentaCorrienteDebitoDestino->getId() => $CuentaCorrienteDebitoDestino ), 
                                    'pagos'     => array( $CuentaCorrienteDebitoDestino->getId() => $CuentaCorrienteDebitoOrigen->getMonto() ) );
        return $debitosTrabajados;
    }
    /*
     * OUTPUT
     *      FALSE   Si la operación que intenta realizarse es errónea.
     *        o
     *      TRUE    
     * 
     *      Objetos PHP mantienen su valor por referencia implícita.
     */
    public function setCoberturasDeUnDebitoAOtroDebito( CuentaCorriente $CuentaCorrienteDebitoOrigen, 
                                                        CuentaCorriente $CuentaCorrienteDebitoDestino )
    {
        // la cobertura final, no puede superar cero. (ya que es un débito).
        // Si supera, ese resto debe será cobertura pendiente.
        $coberturaMaximaADisminuir = $CuentaCorrienteDebitoDestino->getCobertura();
        $diferenciaAAplicar = ($coberturaMaximaADisminuir-$CuentaCorrienteDebitoOrigen->getMonto()>0 )? $coberturaMaximaADisminuir : $CuentaCorrienteDebitoOrigen->getMonto();
        
        // si la ND supera el monto del origen, no se cubrirá esa diferencia, 
        // y quedará esa cobertura pendiente.
        $coberturaQueQuedaraPendiente = $CuentaCorrienteDebitoOrigen->getMonto() - $diferenciaAAplicar;
        
        $CuentaCorrienteDebitoDestino->setCobertura( $CuentaCorrienteDebitoDestino->getCobertura()-$diferenciaAAplicar );
        $CuentaCorrienteDebitoOrigen->setCobertura( $diferenciaAAplicar );

        return $coberturaQueQuedaraPendiente;
        
        /*
        if( $saldoCoberturaDestino > 0 ){      // puede que este control ya esté hecho.
            return false; // ERROR, no puede superar la cobertura actual.
        }else{  
            $CuentaCorrienteDebitoDestino->setCobertura( $saldoCoberturaDestino );
            $CuentaCorrienteDebitoOrigen->setCobertura( $CuentaCorrienteDebitoOrigen->getMonto() );
        }
        return true;   
         * 
         */         
    }
    
    
    /*
     * Modifica la cobertura del Crédito y el Débito por objeto referencia,
     * devolviendo el monto que el crédito saldo hacia el débito.
     * (No afecta a las tablas, solo a los objetos INPUT)
     * 
     * OUTPUT
     *      FALSE   Si el débito o el crédito tiene su saldo completado
     *          o
     *      <int>   que corresponde al monto que el crédito cedió al débito.
     * 
     *      Objetos PHP mantienen su valor por referencia implícita.
     */
    public function setCoberturasDeUnCreditoAUnDebito(  CuentaCorriente $CuentaCorrienteCredito, 
                                                        CuentaCorriente $CuentaCorrienteDebito,
                                                        $montoIndicado=null )
    {
        $saldoCoberturaDebito = $CuentaCorrienteDebito->getMonto() - $CuentaCorrienteDebito->getCobertura();
        if( $saldoCoberturaDebito >= 0 ){
            return false; // La deuda está totalmente cubierta. Sin cambios.
        }
        $saldoCoberturaCredito = $CuentaCorrienteCredito->getMonto()-$CuentaCorrienteCredito->getCobertura();
        if( $saldoCoberturaCredito<= 0){
            return false; // El credito tiene cobertura completa
        }
        if( isset($montoIndicado) && $montoIndicado == 0 ){
            return $pagaAhora=0; // Ha indicado pagar cero
        }
        
        // si hay monto indicado, no debe sobrepasar el crédito disponible
        if( isset($montoIndicado) && $montoIndicado > 0 ){
            $pagaAhora = $montoIndicado;
        }else{
            $pagaAhora = $saldoCoberturaCredito;
        }
        $pagaAhora = ( -$saldoCoberturaDebito >= $pagaAhora )? $pagaAhora : -$saldoCoberturaDebito;

        $CuentaCorrienteDebito->setCobertura( $CuentaCorrienteDebito->getCobertura()-$pagaAhora );
        $CuentaCorrienteCredito->setCobertura( $CuentaCorrienteCredito->getCobertura()+$pagaAhora );
        
        return $pagaAhora;            
    }
    
    /*
     * Habiendo ya modificado la cobertura de un Crédito y un Débito,
     * por un pago realizado,
     * actualiza las tablas.
     */
    public function escribirCoberturas( CuentaCorriente $CuentaCorrienteCredito, 
                                        CuentaCorriente $CuentaCorrienteDebito, 
                                        $pagaAhora=null,
                                        $evscxaId=null  )
    {
        $this->_cuentaCorrienteColeccion->modificacionactualizacionGeneral( $CuentaCorrienteDebito, 'CuentaCorriente' );
        $this->_cuentaCorrienteColeccion->modificacionactualizacionGeneral( $CuentaCorrienteCredito, 'CuentaCorriente' );

        // Creo la relación si aun no existe, de cta cte id del pago.
        // (La relación de los débitos se hace en otra operatoria, durante la creación de ellos).
        if( $evscxaId ){
            $this->crearRelacionMovimiento( $CuentaCorrienteCredito, (int)$evscxaId, $pagaAhora );
        }
    }
    
    
    /*
     * Proceso de inicialización. Aun no hay data de cobertura ni relación cargada.
     * Se asigna los crédito a un único débito, si es que puede ser identificado. 
     * (De haber sobrante, no interesa aquí, 
     * quedara una cobertura libre para posteriores calculos).
     */
    public function asignaCreditosConCoberturaPendienteASusDebitosRespectivos( $alumnos_id, $anio )
    {
        $this->_alumnoCursos = null;    // se trata de un nuevo alumno. reseteo.
        //$cursosDelAlumno = $this->_getCursosDelAlumno( $alumnos_id );
        
        // no filtro por año, ya que puede ser un pago atrasado, que refiera un año anterior.
        $creditosPendientes = $this->_cuentaCorrienteColeccion
                                    ->getCreditosDelAlumno( 
                                            $alumnos_id, $coberturaPendiente=true, $anio );
        if( !$creditosPendientes ){
            return;
        }
        
        foreach( $creditosPendientes as $CuentaCorrienteCredito ){ 
            
            $evscxaId = $this->_getEvscxaidDesdeElMotivo( $CuentaCorrienteCredito);

            if( $evscxaId ){
                // Busco el débito que contenga el mismo evscxa
                $CuentaCorrienteDebito = $this->getDebitoDesdeCredito( $alumnos_id, $anio, $evscxaId );
            }else{
                // la deuda no existe dentro de los EVA validos. 
                // Suele darse cuando un alumno se da baja y sus EVAs siguientes ya no figuran,
                // o es un credito general que no refiere a un EVA particular.
                // Intentaré encontrar un Debito que tenga similar motivo
                $CuentaCorrienteDebito = $this->getDebitoConIgualMotivo( $CuentaCorrienteCredito );
            }
           
            if( $CuentaCorrienteDebito ){
                // 2 PASOS EN LA COBERTURA.  CALCULO Y LUEGO ESCRITURA.
                // (Los Objetos PHP son modificados dentro de la fn por referencia)
                $pagaAhora = $this->setCoberturasDeUnCreditoAUnDebito(  $CuentaCorrienteCredito, 
                                                                        $CuentaCorrienteDebito  );
                if( $pagaAhora != false ){
                    $this->escribirCoberturas( 
                                $CuentaCorrienteCredito, $CuentaCorrienteDebito, $pagaAhora, $evscxaId );
                }
            }            
        }
    }
    
// _getDebitoDesdeCredito( $evscxaIdDelCredito, $anio ) 
    public function getDebitoDesdeCredito( $alumnos_id, $anio, $evscxaIdDelCredito )
    {
        $debitos = $this->_cuentaCorrienteColeccion->getDebitosDelAlumno( $alumnos_id, $coberturaPendiente=false );
        if( !$debitos ){
            return;
        }
                
        foreach( $debitos as $CuentaCorriente ){
            
            $evscxaId = $this->_getEvscxaidDesdeElMotivo( $CuentaCorriente);
            if( !$evscxaId ){
                continue;
            }
            
            if( $evscxaId == $evscxaIdDelCredito ){
                return $CuentaCorriente;
            }
        }
        return false;
    }
    
    /*
     * Dado un motivo X desde un crédito, busca su par en los débitos incompletos.
     * 
     */
    // _getDebitoConIgualMotivo( $CuentaCorrienteIngreso )
    public function getDebitoConIgualMotivo( $CuentaCorrienteCredito )
    {
        $debitosPendientes = 
                $this->_cuentaCorrienteColeccion
                        ->getDebitosDelAlumno( $CuentaCorrienteCredito->getAlumnosId(), $coberturaPendiente=true );
        if( !$debitosPendientes ){
            return false;
        }
        
        $identificadoresBuscados = $this->_queDiceElMotivo( $CuentaCorrienteCredito );
        /*  array(5) {  ["anio"]  
                        ["ev_abreviatura"]  
                        ["nombre_computacional"]  
                        ["clasificador_nombre"]  
                        ["clasificador_valor"]  
         */
        foreach( $debitosPendientes as $CuentaCorriente ){
            $identificadoresDelItem = $this->_queDiceElMotivo( $CuentaCorriente );
            
            if( $identificadoresDelItem['anio']==$identificadoresBuscados['anio'] &&
                $identificadoresDelItem['ev_abreviatura']==$identificadoresBuscados['ev_abreviatura'] &&
                $identificadoresDelItem['clasificador_valor']==$identificadoresBuscados['clasificador_valor']    
                    ){
                return $CuentaCorriente;
            }
        }
        return false;
    }
    
    
    public function crearRelacionesDebitosSinCreditos( $alumnos_id, $anio=null )
    {
        $debitosPendientes = $this->_cuentaCorrienteColeccion
                                    ->getDebitosDelAlumno( $alumnos_id, $coberturaPendiente=true, $anio );
        if( !$debitosPendientes ){
            return;
        }
        foreach( $debitosPendientes as $CuentaCorriente ){       
            // Solo me quedo con aquellos que nunca se han trabajado
            if( $CuentaCorriente->getCobertura()==0 ){  
                $this->_creaRelacionDebitoSinCredito( $CuentaCorriente );
            }
        } 
    }
    
    
    // Inserta en la tabla de relación, los débitos a los que no tuvieron pagos.
    private function _creaRelacionDebitoSinCredito( CuentaCorriente $CuentaCorriente )
    {
        $evscxaId = $this->_getEvscxaidDesdeElMotivo( $CuentaCorriente);
        if( $evscxaId ){
            $this->crearRelacionMovimiento( $CuentaCorriente, $evscxaId, $CuentaCorriente->getMonto() );
        }
    }
    
    
    public function crearRelacionMovimiento( $CuentaCorriente, $evscxaId, $montoAsignado )
    {
        $this->_relacionColeccion->actualizaRelacion( $CuentaCorriente, $evscxaId, $montoAsignado );
    }
    
    
    
    // Esta función se usará toda vez que se esté analizando un movimiento de ctacte
    // que no exista aun en la tabla de relación.
    // Además solo encontrará coincidencias para descripciones anteriores a 
    // 2021-04-27 cuando modifique la construcción del motivo por el nombre humano del curso.
    // En CuentaCorrienteColeccion->getMotivoNormalizado().
    private function _getEvscxaidDesdeElMotivo( CuentaCorriente $CuentaCorriente)
    {
        $identificadoresDetectados = $this->_queDiceElMotivo( $CuentaCorriente );
        $identificadoresDetectados['anio']=( $identificadoresDetectados['anio']!=null )? $identificadoresDetectados['anio']: $CuentaCorriente->getAnio(); // $CuentaCorriente->getAnio();
        // array( 'anio'=>..., 'clasificador_valor'=>..., 'ev_abreviatura'=>... );
        $evscxaId = $this->_funcionesSobreEVAs
                            ->buscarSuCorrespondienteEvscxaId( $identificadoresDetectados );
        return $evscxaId;
    }
    
    
    private function _getCursosDelAlumno( $alumnos_id )
    {
        if( !isset( $this->_alumnoCursos ) ){
            $this->_alumnoCursos = $this->_sedeCursoxanioAlumnoColeccion->getViewValuesCursosDelAlumno( $alumnos_id );
            /*
                array(2) {
                  [0] => array(53) {
                    ["id"] => string(1) "3"
                    ["nombre_humano"] => string(30) "Profesorado Natha Yoga Nivel 1"
                    ["nombre_computacional"] => string(11) "profesorado"
                    ["descripcion"] => string(27) "Profesorado de Yoga Curso 1"
                    ["clasificador_valor"] => string(1) "1"
                    ["clasificador_nombre"] => string(5) "nivel"
                    ["cursos_id_padre"] => string(1) "2"
                    ["cursos_id"] => string(1) "3"
                    ["plan"] => string(4) "2010"
                    ["nombre_sede_centro"] => string(8) "Necochea"
                    ["sedes_id"] => string(1) "3"
                    ["cursosxanio_id"] => string(1) "1"
                    ["anio"] => string(4) "2017"
                    ["scxaa_id"] => string(4) "2145"
                    ["sedes_cursosxanio_id"] => string(3) "211"
                    ["alumnos_id"] => string(3) "786"
                    ["scxaa_fecha_alta"] => NULL
                    ["concurriendo"] => string(1) "0"
                    ["fecha_finalizo"] => string(10) "2017-10-01"
                    ["finalizo_motivo"] => string(1) "B"
                    ["dni"] => string(3) "786"
                    ["apellido"] => string(4) "Arru"
                    ["nombres"] => string(5) "Maite"
                    ["id_viejo"] => NULL
                    ["legajo"] => string(0) ""
                    ["nombre_espiritual"] => string(0) ""
                    ["fecha_nacimiento"] => NULL
                    ["mail"] => string(20) "maite.arrm@gmail.com"
                    ["facebook"] => string(0) ""
                    ["telefono_fijo"] => string(0) ""
                    ["telefono_celular"] => string(11) "2262 535367"
                    ["telefono_whatsapp"] => NULL
                    ["fecha_alta"] => NULL
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
               */
        }
        return $this->_alumnoCursos;
    }
    
    
    /*
     *  2021-04-27 cambie la descripción del motivo 
     * en CuentaCorrienteColeccion->getMotivoNormalizado(), 
     * dejando directamente el nombre humano del curso.
     * 
     * OUTPUT
     *      <array>
                array(5) {
                  ["anio"] => string(4) "2017"
                  ["ev_abreviatura"] => string(3) "MAT"
                  ["nombre_computacional"] => string(11) "profesorado"
                  ["clasificador_nombre"] => string(5) "nivel"
                  ["clasificador_valor"] => string(1) "1"
                }
     */
    private function _queDiceElMotivo( CuentaCorriente $CuentaCorrienteCredito )
    {
        $identificadores = $this->_motivoCuentaCorriente
                                ->getIdentificadores( $CuentaCorrienteCredito->getMotivo() );
        $identificadores['alumnos_id'] = $CuentaCorrienteCredito->getAlumnosId();                        
        // El curso, en algunos textos del motivo, no viene declarado,
        // pero conociendo el alumno, y el año, puedo saber en que curso estaba.
        if( $identificadores['nombre_computacional'] == null    ||
            $identificadores['clasificador_nombre'] == null     ||
            $identificadores['clasificador_valor'] == null      
            ){
            foreach( $this->_getCursosDelAlumno( $CuentaCorrienteCredito->getAlumnosId() ) as $cursoValues ){
                if( $cursoValues['anio'] == $identificadores['anio'] ){
                    $identificadores['nombre_computacional'] = $cursoValues['nombre_computacional'];
                    $identificadores['clasificador_nombre'] = $cursoValues['clasificador_nombre'];
                    $identificadores['clasificador_valor'] = $cursoValues['clasificador_valor'];
                    
                    break;
                }
            }
        }
        
        return $identificadores;
    }
    
    
}