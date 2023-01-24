<?php
/*
 * Ejecución de procesos especiales
 * 
 * 
 * 
 * http://admin.escueladenathayoga.com.ar/gestion/cuentacorriente/test/test/funcion/evaModificado
 * 
 */
require_once 'application/controllerParaEsteProyecto.php';

require_once 'cuentacorriente/models/RelacionEntreModuloGastoYModuloCuentaCorriente.php';

class Cuentacorriente_TestController extends controllerParaEsteProyecto
{
    private $_relacion;
    private $_params;
    private $_sedes_id = 3;
    
    public function init()
    {
        parent::init();
        
        ini_set('max_execution_time', 1800); // 1800 segundos 
        // y como he visto que no me ha funcionado 1 vez en test, agrego la ste. linea:
        set_time_limit ( 1800 ); // 1800 segundos . Para tiempo ilimitado: set_time_limit (0);
        
        $this->_relacion = new RelacionEntreModuloGastoYModuloCuentaCorriente();
        $this->_funcionesSobreEvas = new FuncionesSobreEVAs($this->_sedes_id);
        $this->_coberturaOperaciones = 
                new CoberturaOperaciones(
                                    $this->_sedes_id, 
                                    $this->cuentaCorrienteColeccion, 
                                    $this->_funcionesSobreEvas );

        
        $this->apagarLayout();
        $this->apagarView();
        
        $this->_params = remove_all_HTML_array( $this->getRequest()->getParams() );
        
        
        
    }
    
    // PARA PROBAR, INVOCA A ESTE ACTION, PASANDO funcion A EJECUTAR.
    // *************************************************************************
    // *************************************************************************
    // *************************************************************************
    public function testAction()
    {
        $funcion = $this->_params['funcion'];
        $this->$funcion();
        
        echo '<br>fin testAction()';
    }
    // *************************************************************************
    // *************************************************************************
    // *************************************************************************

    
    
    // FUNCIONES PARA PRUEBAS
    public function evaModificado()
    {
        // extraer estos valores desde la view_
        // SELECT * FROM view_alumnos_valores WHERE alumnos_id = 16585560 
        // (para que eva_id tenga valor debe estar modificado )
        $sql = 'UPDATE yoga_elementos_valuados_alumnos '
                . 'SET valor_modificado= 1198, valor_modificado_motivo="D" '
                . 'WHERE id= 29898';
        $query = new Query();
        //$query->ejecutarCualquierSql($sql);

        $evaId = 29898;
        $valorOriginal = 1200; 
        $valorNuevo = 1199;
        
        $this->cuentaCorrienteColeccion->evaModificado( $evaId, $valorOriginal, $valorNuevo );
    }
    
    public function distri()
    {

        $registrador = new RegistradorDeMovimiento( $this->cuentaCorrienteColeccion, $this->_funcionesSobreEvas );
        $registrador->distribuirCreditos( 16585560 );

        die('listo');
    }
    
    
    
    
    public function distribuirCreditos()
    {
        
        $r = $this->_coberturaOperaciones
                    ->distribuirCreditos( $alumnos_id = 30374293, null, $impactar=false );
        ver($r, 'trabajadoss');
        die();
    }
    
    
}