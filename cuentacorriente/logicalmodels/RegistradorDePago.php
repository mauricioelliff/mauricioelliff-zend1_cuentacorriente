<?php

require_once 'cuentacorriente/logicalmodels/RegistroCredito.php';
require_once 'cuentacorriente/logicalmodels/InconsistenciaSumaCobertura.php';
require_once 'admin/models/SedeCursoxanioAlumnoColeccion.php';


/**
 * Registra 1 ingreso de dinero. 
 * Puede referir a 1 o muchos items.
 */
class RegistradorDePago extends RegistroCredito
{    
    
    /* $debitosTrabajados
     *  <array>     Detalle con los objetos CuentaCorrienteDebito  saldados
            array(4) {
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
              ["dondeDistribuir"] => array(0) {}
              ["evscxa"] => array(2) {
                [334] => array      en este ej. podría haber más de un débito al mismo item
                            ctacteid
                            [41861] => int(1700)
                            [41545] => int(300)
                [335] => array
                            [41545] => int(300)
              }
              ["credito_disponible"] => int(0)
              ["CuentaCorrienteCredito"] => object(CuentaCorriente)#841 (14) {
                ["_id":"CuentaCorriente":private] => NULL
                ["_origen":"CuentaCorriente":private] => string(1) "A"
                ["_alumnos_id":"CuentaCorriente":private] => string(8) "30548405"
                ["_tipo_operacion":"CuentaCorriente":private] => string(11) "PAGO_MANUAL"
                ["_fecha_operacion":"CuentaCorriente":private] => string(10) "2022-10-04"
                ["_monto":"CuentaCorriente":private] => int(9300)
                ["_cobertura":"CuentaCorriente":private] => int(9200)
                ["_motivo":"CuentaCorriente":private] => string(106) "Nivel 1, Cuota 8, 2021($4600). Nivel 1, Cuota 9, 2021($4600). Suscripción Plataforma(practicantes)($300)."
                ["_comprobante_sede":"CuentaCorriente":private] => NULL
                ["_comprobante":"CuentaCorriente":private] => NULL
                ["_persona_en_caja":"CuentaCorriente":private] => string(7) "Nashika"
                ["_observaciones":"CuentaCorriente":private] => string(0) ""
                ["_usuario_nombre":"CuentaCorriente":private] => NULL
                ["_fecha_hora_de_sistema":"CuentaCorriente":private] => string(26) "2022-10-05 16:38:32.264000"
              }
              ["fueTodoPagado"] => bool(true)
              ["monto"] => int(9300)
              ["otros_conceptos"] => array(2) {
                ["formacion"] => int(100)
                ["plataforma_practicantes"] => int(300)
              }
              ["flag_paga_que"] => 0    no paga nada  ["flag_paga_que"] => 0    no paga nada
                                   1    paga únicamente curso en año actual
                                   2    paga más de un curso del año actual
                                   3    paga cursos de años pasados
              }
    */
    public function registrar()
    {
        $this->_calcular();
        
        if( !$this->simular ){
            $this->_impactar(); 
        }
        
        $this->_descripcionesAnteErrores();
        
        return $this->respuestas;
    }
    
    
    // Obtiene toda la data necesaria como si el pago fuese registrado
    private function _calcular()
    {
        // Deudas previas se envia luego a Ajnia Centros, durante el proceso de comprobante
        $this->deudasPreviasAlPago = $this->cuentaCorrienteColeccion
                                            ->getEvscxaPorSaldar( $this->alumnos_id );
        
        $this->_checkOtrosConceptos();
        
        $this->calcularMontos();
        
        if( key_exists('ctacte_id',$this->datosDelMov) && $this->datosDelMov['ctacte_id'] ){
            $this->CuentaCorrienteCredito = 
                    $this->cuentaCorrienteColeccion->obtenerPorIdGeneral( $this->datosDelMov['ctacte_id'], 'CuentaCorriente');
        }else{
            $this->CuentaCorrienteCredito = new CuentaCorriente($this->datosDelMov);
        }
        
        // el monto de 'otros conceptos' se cubre en la propia generación de la deuda.
        $this->datosDelMov['cobertura'] =  $this->respuestas['monto_otros_conceptos'];
        $this->modificaCredito('cobertura',$this->respuestas['monto_otros_conceptos'] );
            

        $this->_calculaAcademico(); 
        
        // Arma el string de motivo
        $this->modificaCredito( 'motivo', $this->_getMotivosOtrosConceptos().' '.
                                          $this->descripcionMotivosAcademico() 
                                );
        $this->respuestas['CuentaCorrienteCredito'] = $this->CuentaCorrienteCredito;
        
        // Agrego un flag para indicar que estaría pagando cursos distintos, 
        // o cursos anteriores al año actual.
        $this->respuestas['flag_paga_que']= $this->_flagPagaVariosCursos();        
    }
    
    /* Hace efectivo el registro del pago en las tablas
     * OUTPUT
     *      FALSE si algo fallo
     *      
     */
    private function _impactar()
    {
        // Write deudas 'otros conceptos'
        // 'otros conceptos' generan la deuda automáticamente (ya que no existen en Académico)
        foreach( $this->otrosConceptosPaga as $key => $monto ){
            if( $key == ElementosValuadosExtras::defaultFormacion() ){
                continue;
            }
            $this->_impactarDeudaOtroConcepto( ElementosValuadosExtras::getDescripcion([$key]), $monto );
        }
        
        // Crea el pago 
        if( is_null( $this->CuentaCorrienteCredito->getId() ) ){
            if( !$id=$this->_primeraEscrituraDelPago() ){
                return FALSE; 
            }
            $this->CuentaCorrienteCredito = 
                    $this->cuentaCorrienteColeccion->obtenerPorIdGeneral( $id, 'CuentaCorriente');
        
            // Obtiene el num de comprobante desde Ajnia Centros. Actualiza en memoria.
            $this->modificaCredito( 'comprobante', $this->_getComprobante() );
            $this->modificaCredito( 'comprobante_sede',  
                                    //$this->_getSedeIdAPartirDelCodigoEnRecibo() 
                                    $this->_getSedeIdAPartirDeLaSedeDelEstudiante()
                                );
        }else{
            // es una reasignación de pago
        }
        // cobertura: 'otros conceptos' se deja completa.
        $this->modificaCredito('cobertura', $this->respuestas['monto_otros_conceptos']);
        
        // Actualizar datos del pago en su row
        $this->cuentaCorrienteColeccion
                ->modificacionactualizacionGeneral( $this->CuentaCorrienteCredito, 'CuentaCorriente' );
                
        // Se impactan los pagos en los debitos (la cobertura y en tabla de relación)
        $this->coberturaOperaciones
                ->impactarCredito(  $this->CuentaCorrienteCredito,  // actualizado post distribución 
                                    $this->respuestas['evscxa'] );
        
        // Check
        $i = new InconsistenciaSumaCobertura();
        if( $mensaje=$i->hayError() ){
            $arrayCredito = $this->CuentaCorrienteCredito->convertirEnArray();
            array_push( $arrayCredito,$mensaje );
            $this->cuentaCorrienteColeccion
                    ->enviarMailAWebmaster( $arrayCredito );
        }
 
        // Acciones post pagos
        $evento = new PagoRealizado( $this->datosDelMov['alumnos_id'], $this->respuestas );
        Eventos::lanzar( $evento );
    }
    

    private function _checkOtrosConceptos()
    {
        // Puede indicarse un monto a cuenta de 'formacion'. 
        $formacion = ElementosValuadosExtras::defaultFormacion();
        if( !key_exists($formacion, $this->otrosConceptosPaga) ){
            $this->otrosConceptosPaga[$formacion]=0; // inicializo el totalizador
        }
        
        // Items incorrectos se suman a cuenta 'formacion' (hacks o errores front)
        $erroneos = array_diff( array_keys($this->otrosConceptosPaga), array_keys($this->otrosConceptos) );
        foreach( $erroneos as $key ){
            $this->respuestas['errores']['conceptos_incorrectos'][$key]=$this->otrosConceptosPaga[$key];
            $this->otrosConceptosPaga[$formacion]+= $this->otrosConceptosPaga[$key]; // suma monto
            unset( $this->otrosConceptosPaga[$key] );
        }
        $this->respuestas['otros_conceptos'] = $this->otrosConceptosPaga;
    }
        
    private function _impactarDeudaOtroConcepto( $descripcion, $monto )
    {
        $datosDelMovDeuda = $this->datosDelMov;
        $datosDelMovDeuda['tipo_operacion']='FACTURA_AUTOMATICA';
        $datosDelMovDeuda['motivo']= 'Costo '.$descripcion;
        $datosDelMovDeuda['monto']=-$monto;
        $datosDelMovDeuda['cobertura']=-$monto; // luego genero el pago
        $datosDelMovDeuda['comprobante']=null;
        unset( $datosDelMovDeuda['id'] ); // no confundir con el id de la tabla
        // los pagos vía api, ya vendrán con 'fecha_hora_de_sistema'
        if( key_exists('fecha_hora_de_sistema',$datosDelMovDeuda) ){
            // set microtime 1 milesima antes, para que en los listados aparezca la deuda con anterioridad
            $dt=$datosDelMovDeuda['fecha_hora_de_sistema'];
            $mt= (int)substr($dt,strpos($dt,'.')+1,6);
            $newMt = $mt-1;
            $datosDelMovDeuda['fecha_hora_de_sistema']=substr($dt,0,strpos($dt,'.')).'.'.$newMt;
        }
        $this->cuentaCorrienteColeccion->altaGeneral( $datosDelMovDeuda, 'CuentaCorriente' );
    }
    
    private function _calculaAcademico()
    {
        // Pongo los items seleccionados al principio.
        $listaDeudas = $this->cuentaCorrienteColeccion
                            ->getRowsPorSaldar( $this->alumnos_id, 
                                                array_keys( $this->evscxaIdsPaga ) );
        // Rows de la tabla cuentas corrientes:
        $DebitosDondeDistribuir = $this->cuentaCorrienteColeccion
                                        ->transformarLaListaDeArraysEnListaDeObjetos( $listaDeudas );
        // array de evscxa_id a que row de cuentas corrientes aplica
        $aux = arrays_getAlgunasKeysArrays($listaDeudas, ['evscxa_id']);
        $ctaIdxEvscxaId = array_map(function ($x){return $x['evscxa_id'];}, $aux) ;
        // quito null y empty
        $ctaIdxEvscxaId = ( is_array($ctaIdxEvscxaId)) ? array_filter( $ctaIdxEvscxaId ) : null; 
        $evscxaIdXCtacteId = ( is_array($ctaIdxEvscxaId) && count($ctaIdxEvscxaId)>0 )? array_flip( $ctaIdxEvscxaId ): null;
        $deb = $this->coberturaOperaciones->distribuirUnCredito($this->CuentaCorrienteCredito, 
                                                                $this->evscxaIdsPaga,
                                                                $evscxaIdXCtacteId,
                                                                $DebitosDondeDistribuir );
        // Agrego a los totales, los datos luego de distribuir el crédito
        $this->respuestas = $deb + $this->respuestas;
    }
    
    // de los debitosTrabajados, obtiene todos los datos para solicitar el comprobante
    private function _getComprobante()
    {
        // ¿es necesario generar comprobante?
        if( $this->datosDelMov['tipo_operacion']=='PAGO_MANUAL'
            && !isset($this->datosDelMov['comprobante'] ) ){
            
            $ComprobanteDesdeAjnaCentros = new ComprobanteDesdeAjnaCentros();
            $cAC = $ComprobanteDesdeAjnaCentros
                    ->getComprobanteDesdeAjnaCentros( 
                            $this->datosDelMov['alumnos_id'], 
                            $this->datosDelMov['comprobante_sede'], 
                            $this->datosDelMov['comprobante_envio'], 
                            $this->datosDelMov['comprobante_mail'], 
                            $this->listaDeudasPorEvscxa, 
                            $this->respuestas,
                            $this->descripcionMotivosAcademico().$this->_getMotivosOtrosConceptos()
                        );

            if( $cAC !== false ){
                return trim($cAC);  
            }else{
                $mje='Recibo no generado. Error en AjnaCentros.';
                $this->respuestas['errores'][]=$mje;
                $this->_MiMensajero->addColeccionMensajes( array( 'ERROR', $mje ) );
                return null;
            }
        }
        return $this->datosDelMov['comprobante'];
    }
    
    // OUTPUT: sedes_id <int>
    private function _getSedeIdAPartirDeLaSedeDelEstudiante()
    {
        $SedeCursoxanioAlumnoColeccion = new SedeCursoxanioAlumnoColeccion();
        return $SedeCursoxanioAlumnoColeccion
                ->getSedeDelAlumno( $this->CuentaCorrienteCredito->getAlumnosId() );
    }    
    private function _getSedeIdAPartirDelCodigoEnRecibo()
    {
        $comprobante = $this->CuentaCorrienteCredito->getComprobante();
        if( substr( $comprobante, 0, 5) === 'LOCAL' ){ // lo he dejado de usar, ahora pongo el código de sede
            return USUARIO_SEDE_ID;
        }
        $sedeStringCodigo = substr( $comprobante, 0, 2);
        if( !is_string($sedeStringCodigo) ){
            return USUARIO_SEDE_ID;
        }
        $sql = "SELECT id_sede_centro FROM sedes_centros WHERE codigo = '$sedeStringCodigo'";
        $Query = new Query();
        $resultado = $Query->ejecutarQuery($sql);
        if( !$resultado || count($resultado)==0 ){
            return null;   // default USUARIO_SEDE_ID
        }
        return (int) $resultado[0]['id_sede_centro'];
    }
    
    
    // El motivo se alterará después, cuando se vea, a que se distribuyó el pago.
    private function _primeraEscrituraDelPago()
    {
        if( !$id = $this->cuentaCorrienteColeccion
                                ->altaGeneral( $this->CuentaCorrienteCredito, 'CuentaCorriente' ) ){
            return FALSE;
        }
        // Auditoría
        $this->auditoriaColeccion
                ->registrar( 'alta', 'cuentas_corrientes', $this->CuentaCorrienteCredito->getId(), 
                            arrays_getAlgunasKeys( $this->datosDelMov, $this->getCamposIndispensables($this->datosDelMov['tipo_operacion']) ) );
        return $id;
    }
            
    private function _getMotivosOtrosConceptos()
    {
        $motivo='';
        if( count($this->respuestas['otros_conceptos'])>0 ){
            // motivo será la concatenación del motivo deuda + el importe pagado:
            foreach( $this->respuestas['otros_conceptos'] as $key => $monto ){
                if( $key==ElementosValuadosExtras::defaultFormacion() ) continue;
                
                $motivo.= ElementosValuadosExtras::getDescripcion($key)."($monto). ";
            }
        }
        return $motivo;
    }
    
    
    /*
     * Flag que ayuda a colocar un alert para el usuario.
     * Para lograr el dato, analiza el string de motivo. (lo cual es acoplativo, 
     * pero por ahora lo dejo así, lo correcto sería obtener el dato desde el curso en cuestion)
     * OUTPUT
     * 0    no paga nada
     * 1    paga únicamente curso en año actual
     * 2    paga más de un curso del año actual
     * 3    paga cursos de años pasados
     */
    private function _flagPagaVariosCursos()
    {
        $debitosTrabajados = $this->respuestas;
        if( !$debitosTrabajados && count($debitosTrabajados)==0 ){
            return false;
        }
        // $dataPagos = $this->_ElementoValuadoSedeCursoxanioColeccion->getCursosFromEvscxa( $evscxaids );
        $anios = [];
        $cursos = [];
        foreach( $debitosTrabajados['pagos'] as $ctaCteId => $monto ){
            $motivo = $debitosTrabajados['objetos_debito'][$ctaCteId]->getMotivo();
            $curso = trim(substr( $motivo, 0, strpos($motivo,',')) );
            $stringDesdeLaPrimeraComa = substr( $motivo, strpos($motivo,',')+1);
            $stringDesdeLaSegundaComa = substr( $stringDesdeLaPrimeraComa, strpos($stringDesdeLaPrimeraComa,',')+1);
            $stringAnio = trim(substr( $stringDesdeLaSegundaComa,1,4));
            $anio = ( strlen($stringAnio)==4 && is_numeric($stringAnio) )? $stringAnio :
                        substr( $debitosTrabajados['objetos_debito'][$ctaCteId]->getFechaOperacion(), 0, 4 ); 
            if( !in_array($anio,$anios) ){
                $anios[]=$anio;
            }
            if( !in_array($curso,$cursos) ){
                $cursos[]=$curso;
            }
        }
        if( count($anios)==0 ){
            return false; // no hay selección. quizás fue 'otros conceptos'
        }
        if( count($anios)>1 || $anios[0] < date('Y') ){
            $r = 3;
        }elseif( count($cursos)>1 && count($anios)==1 && $anios[0]==date('Y') ){
            $r = 2;
        }elseif(count($cursos)==1 && count($anios)==1 && $anios[0]==date('Y') ){
            $r = 1;
        }elseif( count($cursos)>0 || count($anios)>0 ){
            $r = FALSE; // no debiera salir por aquí
        }else{
            $r = 0;
        }
        return $r;
    }
    
    private function _descripcionesAnteErrores()
    {
        // Otros Conceptos
        if( key_exists('ERROR', $this->respuestas ) &&
            key_exists('conceptos_incorrectos',$this->respuestas['ERROR']) ){
            
            foreach( $this->respuestas['ERROR']['conceptos_incorrectos'] as $key => $monto ){
                $this->respuestas['ERROR'][]='Error en Otros Conceptos: No existe key = '.$key.'<br>';
            }
        }
    }
}