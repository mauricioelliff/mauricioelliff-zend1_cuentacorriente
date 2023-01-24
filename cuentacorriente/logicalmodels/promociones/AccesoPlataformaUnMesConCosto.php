<?php



// CREO QUE NO TENGO ESTA CLASE EN USO    





require_once 'cuentacorriente/logicalmodels/promociones/Promocion.php';
require_once 'cuentacorriente/logicalmodels/promociones/PromocionInterfase.php';

require_once 'admin/models/SedeCursoxanioAlumnoColeccion.php';
require_once 'admin/models/ElementoValuadoAlumnoColeccion.php';
require_once 'admin/logicalmodels/plataforma/ServicioPlataformaSede.php';
require_once 'admin/logicalmodels/plataforma/ServicioPlataformaAlumno.php';
require_once 'default/models/Query.php';

require_once 'admin/logicalmodels/ServicioPlataforma.php';
/*
 * Aunque la class setea un mes de acceso con costo,
 * podría promocionar el mes siguiente si el mes actual ya estuviese
 * muy avanzado.
 * ( Aunque a la persona simplemente se le dice que tiene un mes a partir de hoy
 * y se dará cuenta que tiene más acceso del previsto 
 * solo si revisa los días entre su final de mes y el fin de mes calendario )
 */
class AccesoPlataformaUnMesConCosto extends ServicioPlataformaSede //extends Promocion implements PromocionInterfase
{
    public $alumnos_id;
    private $_fecha;
    
    private $_plataformas = []; // quizás tenga que trabajar con el año actual, y a veces con el próximo
    
    private $_motivo;
    private $_motivosDeNoAcceso;
    
    const dia_a_partir_del_cual_se_aplica_mes_siguiente = 13;
    
    public function __construct( $alumnos_id, $fechaSolicitud, $motivoString='por_aviso' ) 
    {
        $this->alumnos_id = $alumnos_id; 
        $this->_fecha = substr( $fechaSolicitud,0,10 );
        
        $this->_motivo = $this->getMotivos($motivoString);
        $this->_motivosDeNoAcceso = $this->getMotivosQueNoPermitenAccesoAPlataforma();
    }
    
       
    private function _anioSolicitud(){
        return substr( $this->_fecha, 0, 4 );
    }
    private function _mesSolicitud(){
        return substr( $this->_fecha, 5, 2 );
    }
    private function _diaSolicitud(){
        return substr( $this->_fecha, 8, 2 );
    }
    
    /*
     */
    public function darAcceso()
    {
        // CHECK SI YA ESTÁ HECHO
        $anioEvaluando = $this->_anioSolicitud();
        $ServicioPlataformaAlumno = $this->_getPlataformaAlumno( $anioEvaluando );
        if( !$ServicioPlataformaAlumno->esValida() ){
            return false;   // error. siendo estudiante debería tener acceso en esa fecha
        }        
        $mesAMes = $this->_getDataMesesAnio( $anioEvaluando );
        for( $mes=$this->_mesSolicitud(); $mes<=12; $mes++ ){
            if( key_exists($mes, $mesAMes) && 
                $mesAMes[$mes]['valor_modificado_motivo']==$this->_motivo ){
                return true; // ya está hecho. Hay 1 precio que responde a este motivo.
            }
        }
        // Check si ya está hecho en el año futuro. 
        $anioEvaluando = $this->_anioSolicitud()+1;
        $ServicioPlataformaAlumno = $this->_getPlataformaAlumno( $anioEvaluando );
        if( $ServicioPlataformaAlumno->esValida() ){
            $mesAMes = $this->_getDataMesesAnio( $anioEvaluando );
            for( $mes=1; $mes<=12; $mes++ ){
                if( key_exists($mes, $mesAMes) && 
                    $mesAMes[$mes]['valor_modificado_motivo']==$this->_motivo ){
                    return true; // ya está hecho. Hay 1 precio que responde a este motivo.
                }
            }
        }
        
        // ACCESO :
        
        // Intento primero para el año de solicitud.
        $anioEvaluando = $this->_anioSolicitud();
        $ServicioPlataformaAlumno = $this->_getPlataformaAlumno( $anioEvaluando );
        if( $ServicioPlataformaAlumno->esValida() ){
            $mesAMes = $ServicioPlataformaAlumno->getDataMesAMes();
            for( $mes=$this->_mesSolicitud(); $mes<=12; $mes++ ){
                if( $ServicioPlataformaAlumno->esMesSinAcceso($mesAMes[$mes]) ){
                    $valor = $mesAMes[$mes]['valor_configurado'];                    
                    $ServicioPlataformaAlumno->darAccesoConMesDePago( $this->_motivo, $valor, $mes, $anioEvaluando );
                    $this->_setearACeroMesPosteriorSiCorresponde( $anioEvaluando, $mes );
                    return true;
                }
            }
        }
        // Intenta con el año siguiente
        $anioEvaluando = $this->_anioSolicitud()+1;
        $ServicioPlataformaAlumno = $this->_getPlataformaAlumno( $anioEvaluando );
        if( $ServicioPlataformaAlumno->esValida() 
            && !$ServicioPlataformaAlumno->estaInscriptoAlumno() ){
            // el estudiante no está inscripto a formación.
            // Hay que incorporarlo a la plataforma, sin acceso a todos los meses
            // excepto el mes indicado.
            $ServicioPlataformaAlumno->inscribirAServicio( $facturar=false );
            $this->_setearTodosLosMesesSinUso( $ServicioPlataformaAlumno );
        }
        
        // Rastrea el año futuro en busca de un mes a dar acceso
        return $this->_darAccesoAlPrimerMesSinAcceso( $ServicioPlataformaAlumno );
    }
    
    private function _setearTodosLosMesesSinUso( $ServicioPlataformaAlumno )
    {
        $motivoSinUso = $ServicioPlataformaAlumno->getMotivos('no_utilizado');
        $ServicioPlataformaAlumno->quitarAccesoAMeses( $motivoSinUso, $operarContable=false );
    }
    
    private function _darAccesoAlPrimerMesSinAcceso( $ServicioPlataformaAlumno )
    {
        $mesAMes = $ServicioPlataformaAlumno->getDataMesAMes();
        for( $mes=1; $mes<=12; $mes++ ){
            if( $ServicioPlataformaAlumno->esMesSinAcceso($mesAMes[$mes]) ){
                $valor = $mesAMes[$mes]['valor_configurado']; // valor general
                $ServicioPlataformaAlumno->darAccesoConMesDePago( $this->_motivo, $valor, $mes );
                return true;
            }
        }
        return false;
    }

    private function _getPlataformaAlumno($anio=null)
    {
        $anio = (is_null($anio))? date('Y') : $anio;
        if( !key_exists($anio, $this->_plataformas ) ){
            $ServicioPlataformaAlumno = 
                    new ServicioPlataformaAlumno( new ServicioPlataformaSede( $this->sedes_id, $anio), $this->alumnos_id );
            $this->_plataformas[ $anio ]= $ServicioPlataformaAlumno;
        }else{
            $ServicioPlataformaAlumno = $this->_plataformas[ $anio ];
        }
        return $ServicioPlataformaAlumno;
    }
    

    /* OUTPUT
     * <array> 
        [0] array(5) {
                ["evscxa_fecha_inicio"] => string(10) "2015-03-01"
                ["evscxa_valor"] => string(3) "500"
                ["ev_id"] => string(1) "1"
                ["ev_descripcion"] => string(9) "Matricula"
                ["numero_de_orden"] => string(1) "1"
                ["valor_modificado_motivo"] => string(1) "B"
                ["valor_modificado"] => string(2) "10"
     */
    private function _getDataMesesAnio( $anio=null )
    {
        $anio = ( is_null($anio) )? date('Y') : $anio;
        $ServicioPlataformaAlumno = $this->_getPlataformaAlumno($anio);
        return (!$ServicioPlataformaAlumno)? false: $ServicioPlataformaAlumno->getDataMesAMes();
    }
    
    
    // aplica bonificación si corresponde
    private function _setearACeroMesPosteriorSiCorresponde( $anioEvaluando, $mes )
    {
        if( $mes > $this->_mesSolicitud() ||
            ( $mes == $this->_mesSolicitud() && 
                $this->_diaSolicitud() < $this::dia_a_partir_del_cual_se_aplica_mes_siguiente ) 
        ){
            return;
        }
        // $anioMesProximo  '2021-10'   
        $anioMesProximo = ($mes==12)? (($anioEvaluando+1).'-01') : ($anioEvaluando.'-'.str_pad($mes+1,2,'0',STR_PAD_LEFT) );
        $anio = substr($anioMesProximo,0,4);
        $mes = substr($anioMesProximo,5,2);
        $ServicioPlataformaAlumno = $this->_getPlataformaAlumno($anio);
        if( !$ServicioPlataformaAlumno->estaInscriptoAlumno() ){
            $ServicioPlataformaAlumno->inscribirAServicio( $facturar=false );
            
            $this->_setearTodosLosMesesSinUso($ServicioPlataformaAlumno);
        }
        $ServicioPlataformaAlumno->promocionarMeses( $this->alumnos_id, ["$anio-$mes"], $this->_motivo );
    }        
    
}
