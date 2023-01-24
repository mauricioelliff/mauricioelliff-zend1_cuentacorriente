<?php
if( !defined('__GESTION_ESTA_MISFUNCIONES_CARGADA__') ){
    require_once MILIBRARY_PATH.'misFunciones.php';
}
require_once 'test/models/TesterInterfase.php';
require_once 'test/models/Tester.php';
require_once 'cuentacorriente/models/ContableColeccion.php';

// si hago la llamada directamente a CuentaCorrienteColeccion
// me da un error no pudé solucionar.
class cancelarMovimientosDeAlumnoCurso implements TesterInterfase
{
    public $Tester;
    private $_contableColeccion;
    private $_nombreDeLaFuncionATestear = 'cancelarMovimientosDeAlumnoCurso';
    
    public function __construct( Tester $Tester ) 
    {
        //parent::construct();
        $this->Tester = $Tester;
        
        $this->_contableColeccion = new ContableColeccion();
    }
    
    
    public function testear()
    {
        // $clase = get_class($this->Tester);
        // $metodos = $this->Tester->getMetodosDeLaClase( $clase );
        
        foreach( $this->_getArrayDePruebas() as $key => $prueba ){
            $numPrueba = $key+1;
            
            $respuesta = $this->ejecutarLoQueSeVaATestear(  $prueba['param1'], 
                                                            $prueba['param2'] 
                                                        );
            $this->Tester
                ->evaluarResultado( 
                                    // nombreFuncionEvaluada
                                            $this->_nombreDeLaFuncionATestear, 
                                    //parametrosQueSeLeEnviaron
                                            array($prueba['param1'], 
                                                $prueba['param2']
                                            ),
                                    // respuestaObtenida
                                            $respuesta, 
                                    // respuestaEsperada
                                            $prueba['esperado'] ,
                                    //        
                                            $numPrueba
                                    );
        }
    }
    
    public function ejecutarLoQueSeVaATestear( $param1, $param2 )
    {
        $fn = $this->_nombreDeLaFuncionATestear;
        
        return $this->_contableColeccion->$fn( $param1, $param2 );
    }
    
    private function _getArrayDePruebas()
    {
        //  param1 => alumnos_id,   param2 => sedes_cursosxanio_id
        $pruebas =  array( 
                        array(
                            'param1'    => false,    
                            'param2'    => false,   
                            'esperado'  => false,
                        ),
                        array( 
                            'param1'=>  array(
                                                'alumnos_id'            => 20891442, 
                                                'sedes_id'              => 3,
                                                'cursos_id'             => 3,
                                                'anio'                  => 2019,
                                                'sedes_cursosxanio_id'  => 291,
                                                'tipo_de_movimientos'   => 'facturados'
                                        ),
                            'param2'    => false, // 'otro motivo identificador',   
                            'esperado'  => true,
                        ),

                    );
        return $pruebas;

    }
    
    


}



