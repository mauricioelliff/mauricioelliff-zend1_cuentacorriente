<?php
/*
 * Ejecución de procesos especiales
 * 
 * 
 * 
 * http://admin.escueladenathayoga.com.ar/gestion/cuentacorriente/reparacion/igualargastoyctacte/sedes_id/3
 * 
 */
require_once 'application/controllerParaEsteProyecto.php';

require_once 'cuentacorriente/models/RelacionEntreModuloGastoYModuloCuentaCorriente.php';

class Cuentacorriente_ReparacionController extends controllerParaEsteProyecto
{
    
    public function init()
    {
        parent::init();
        
        ini_set('max_execution_time', 1800); // 1800 segundos 
        // y como he visto que no me ha funcionado 1 vez en test, agrego la ste. linea:
        set_time_limit ( 1800 ); // 1800 segundos . Para tiempo ilimitado: set_time_limit (0);
        
    }
    
    
    
    /*
     * Corresponde al proceso documentado en /docs/procesos/reparacion_errores_del_sistema.txt
     * donde trata de encontrar y reparar diferencias entre 
     * el modelo de GASTOS y el de CUENTAS CORRIENTES
     * 
     * Trabaja por alumno y no por curso. 
     * Aunque el curso sirve para determinar con que alumnos trabajar.
     * Luego la data del alumno, corresponde a toda la de su historia.
     * 
     * INPUT
     * 
     * sedes_id
     * soloVerLoQueSeProcesara  DEFAULT     'false'
     *                          Permite indicar si solo quiero ver un detalle 
     *                          de lo que hará el proceso
     */
    public function igualargastoyctacteAction()
    {
        $this->apagarLayout();
        $this->apagarView();
        
        $params = $this->getRequest()->getParams();
        $params = remove_all_HTML_array( $params );
        
        if( !isset($params['sedes_id']) || !is_numeric($params['sedes_id']) ){
            die('falta el parametro sedes_id');
        }
        $sedes_id = (int) $params['sedes_id'];
        
        $cursosAProcesar = array( 3, 4, 5, 6 ); // los 4 niveles del profesorado
        $aniosAProcesar = array( 2014, 2015, 2016, 2017, 2018, 2019, 2020 );

        

        $soloVerLoQueSeProcesara = $this->getRequest()->getParam('soloVerLoQueSeProcesara');
        $soloVerLoQueSeProcesara = ( isset($params['soloVerLoQueSeProcesara']) && strtolower($params['soloVerLoQueSeProcesara'])==='true')? true : false;
        
        $relacionEntreModuloGastoYModuloCuentaCorriente = new RelacionEntreModuloGastoYModuloCuentaCorriente();
        foreach( $aniosAProcesar as $anio ){
            foreach( $cursosAProcesar as $cursos_id ){
                $resultado = $relacionEntreModuloGastoYModuloCuentaCorriente
                                    ->ajustarDiferenciaEntreValoresAsignadosYValoresDebitados( $sedes_id, $cursos_id, $anio, $soloVerLoQueSeProcesara );
                ver( $resultado, 'RESULTADOS sede '.$sedes_id.', '.$anio.', curso: '.$cursos_id );
            }
        }
        die();
    }
    
    
    
    /*
     * NO HE PROBADO BIEN ESTA FUNCIÓN
     */
    public function distribuircreditoslibresenlasedeAction()
    {
        $this->apagarLayout();
        $this->apagarView();
        
        $sedes_id = $this->getRequest()->getParam('sedes_id');
        
        $this->cuentaCorrienteColeccion->distribuirTodosLosCreditosLibres( $sedes_id );
        
        die('ok');
    }
    
    
    /*
     * Previamente a correr este Action,
     * debe haberse ejecutado los sql en reparacion_de_errores.sql
     * que preparan la tabla yoga_cuentas_corrientes_erroneos
     */
    function pagosDuplicadosAction()
    {
        $duplicadosSQL='SELECT fecha_hora_de_sistema FROM yoga_cuentas_corrientes_erroneos';
        $Query = new Query();
        $timeStampsErroneos = $Query->ejecutarQuery( $duplicadosSQL );
        $timeStamps = array_values_recursive( $timeStampsErroneos );
        $erroneos = $this->cuentaCorrienteColeccion
                        ->obtenerGeneral(['fecha_hora_de_sistema'=>$timeStamps],'id','CuentaCorriente');
        $resultados = $this->eliminarCreditos( $erroneos );
        ver($resultados,'$resultados');
        die(' fin');
        
        /* Situación en detalle de los alumnos 
        $auxFiltrar = $this->cuentaCorrienteColeccion->getFiltroParaObtenerDebitos( $vaAgrupadoPorEvscxa=true );     
        $debitosAlumnos = $this->cuentaCorrienteColeccion
                                ->getCuentaCorrienteEvscxa( $alumnosId, $auxFiltrar, $itemsPorEvscxa=true );
        */ 
    }
    private function eliminarCreditos( $CuentaCorrienteErroneos )    
    {
        require_once 'cuentacorriente/logicalmodels/EliminarCredito.php';
        $EliminarCredito = new EliminarCredito();
        $resultados = [];
        foreach( $CuentaCorrienteErroneos as $CuentaCorriente ){
            if( $EliminarCredito->procesar( $CuentaCorriente ) ){
                $resultados['ok'][]=$CuentaCorriente;
            }else{
                $resultados['error'][]=$CuentaCorriente;
            }
        }
        return $resultados;
    }
    private function eliminarDebitos( $CuentaCorrienteErroneos )    
    {
        require_once 'cuentacorriente/logicalmodels/EliminarDebito.php';
        $EliminarDebito = new EliminarDebito();
        $resultados = [];
        foreach( $CuentaCorrienteErroneos as $CuentaCorriente ){
            echo '<br>Alumno: '.$CuentaCorriente->getAlumnosId().' ';
            if( $EliminarDebito->procesar( $CuentaCorriente ) ){
                $resultados['ok'][]=$CuentaCorriente;
            }else{
                $resultados['error'][]=$CuentaCorriente;
            }
        }
        return $resultados;
    }
    
    
    /*
     * Por confusión de cuotas y plataforma, en el curso de Egresados.
     * Se eliminan todas las facturas de plataforma, durante el profesorado.
     */
    public function plataformaDeudasErroneasEliminarAction()
    {
        $stringDeBusqueda = isset($_GET['busqueda'])? $_GET['busqueda'] : '%Suscripción Plataforma%'; // 
        $sql = "SELECT ctas.id 
                FROM yoga_cuentas_corrientes AS ctas
                INNER JOIN yoga_cuentascorrientes_elementosvaluados AS ev
                    ON ev.cuentas_corrientes_id = ctas.id 
                INNER JOIN view_elementosvaluados_por_sedes_cursos_y_planes AS view
                    ON view.evscxa_id = ev.elementosvaluados_sedes_cursosxanio_id
                INNER JOIN view_cursos_inicio_fin AS profesorado_fechas
                    ON profesorado_fechas.sedes_cursosxanio_id IN
                        (   SELECT sedes_cursosxanio_id 
                            FROM view_sedes_cursos_y_planes
                            WHERE clasificador_valor = '9' AND anio = 2021
                                AND sedes_id = view.sedes_id 
                        )
                WHERE ( motivo LIKE '$stringDeBusqueda' )
                    AND tipo_operacion = 'FACTURA_AUTOMATICA' 
                    AND alumnos_id IN (
                        SELECT DISTINCT(alumnos_id)
                        FROM view_alumnos_por_sedes_cursos_y_planes
                        WHERE nombre_computacional = 'servicio' and clasificador_valor=1 AND anio=2021 
                        AND alumnos_id IN (
                                    SELECT alumnos_id 
                                    FROM view_alumnos_por_sedes_cursos_y_planes
                                    WHERE clasificador_valor = '9' and anio = 2021
                                )
                    )
                    AND view.evscxa_fecha_inicio >= profesorado_fechas.fecha_inicio
                ";
        $Query = new Query();
        $resultado = $Query->ejecutarQuery($sql);
        $ids = array_values_recursive( $resultado );
        $erroneos = $this->cuentaCorrienteColeccion
                        // ->obtenerGeneral( ['id'=>$ids], 'id', 'CuentaCorriente' );
                        ->obtenerPorIdGeneral( $ids,'CuentaCorriente');

        $resultados = $this->eliminarDebitos( $erroneos );
        ver($erroneos,'$erroneos'); 
        ver($resultados,'$resultados');
        die(' fin');
    }
    
    
    public function corregirPagosMudrasAction()
    {
        require_once 'cuentacorriente/logicalmodels/CorrectorPagosErroneosOtrosConceptos.php';
        $CorrectorPagosErroneosMudras = new CorrectorPagosErroneosMudras( 'mudra', 'Taller Mudras' );
        $CorrectorPagosErroneosMudras->corregir();
        die();
    }
        
}