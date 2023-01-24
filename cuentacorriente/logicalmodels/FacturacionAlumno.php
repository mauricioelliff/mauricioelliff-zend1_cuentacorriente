<?php

require_once 'ColeccionParaEsteProyecto.php';

require_once 'cuentacorriente/models/ContableColeccion.php';
require_once 'cuentacorriente/logicalmodels/FacturacionMensual.php';
require_once 'cuentacorriente/logicalmodels/InicializacionCobertura.php';

require_once 'admin/logicalmodels/CursadaInfo.php';

/**
 * Description of FacturacionAlumno
 *
 * @author mauricio
 */
class FacturacionAlumno extends ColeccionParaEsteProyecto
{
    private $_contableColeccion;
    private $_inicializacionCobertura;
    
    
    public function __construct() {
        parent::__construct();
        
        $this->_contableColeccion = new ContableColeccion();
        $this->_inicializacionCobertura = new InicializacionCobertura();

        $this->_CursadaInfo = CursadaInfo::getInstance();
    }
    
    
    // Crea los débitos en la cuenta corriente del alumno
    public function facturacionNuevoAlumnoEnCursada( $alumnos_id, $sedes_cursosxanio_id )
    {
        // Debitación de los items de la cursada.
        $this->_facturacionDeCuotas( $alumnos_id, $sedes_cursosxanio_id );
        
        // Distribución de coberturas, si hubiese créditos disponibles.
        $this->_trabajarCoberturas( $alumnos_id, $sedes_cursosxanio_id );
    }
    
    
    
    private function _facturacionDeCuotas( $alumnos_id, $sedes_cursosxanio_id )
    {
        $cursadaValues = $this->_CursadaInfo
                            ->get( [ 'sedes_cursosxanio_id'=>$sedes_cursosxanio_id ] );

        // CUENTA CORRIENTE
        // si tiene una cancelación (Nota de Crédito) por ese curso, 
        // significa que se está reinscribiendo nuevamente,
        // y debo eliminar esa cancelación

        $anioCursada = $cursadaValues['anio'];
        $sedes_id = $cursadaValues['sedes_id'];
        $tieneCancelacion = $this->_contableColeccion->tieneCancelacionDelCurso( $alumnos_id, $anioCursada, $sedes_cursosxanio_id );
        if( $tieneCancelacion ){
            // implica que la facturación ya estaba hecha y fue cancelada con un ajuste,
            // ahora elimino ese ajuste, creando un nuevo ajuste con valor inverso.
            $this->_contableColeccion->eliminarCancelacionDelCurso( $alumnos_id, $anioCursada, $sedes_id );
            return;
        }
        
        $facturacionMensual = new FacturacionMensual();       
        $anioDesde = ( $anioCursada )? $anioCursada : date('Y');
        // Dado que algunos items como MAT, proviene de unos meses previos o año anterior
        // ( Lo correcto sería usar la fecha del primer ev del curso )
        $anioDesde = $anioDesde - 1;
        $fechaDesde = $anioDesde.'-01-01';
        $fechaHasta = date('Y-m-d');
        
        $facturacionMensual->generarFacturacionMensual( $fechaDesde, $fechaHasta, $sedes_id, $mostrar_repetidos=false, $alumnos_id );
    }
    
    
    /*
     * Intenta inicializar las coberturas (para alumnos nuevos)
     * y luego intenta distribuir créditos.
     */
    private function _trabajarCoberturas( $alumnos_id, $sedes_cursosxanio_id )
    {
        $cursadaValues = $this->_CursadaInfo
                            ->get( [ 'sedes_cursosxanio_id'=>$sedes_cursosxanio_id ] );
        $sedes_id = $cursadaValues['sedes_id'];
        $anio = (isset($cursadaValues['anio']))? $cursadaValues['anio'] : date('Y');
        $this->_inicializacionCobertura->inicializacionParesCorrespondientes( $sedes_id, $anio, $alumnos_id );        
    }
}
