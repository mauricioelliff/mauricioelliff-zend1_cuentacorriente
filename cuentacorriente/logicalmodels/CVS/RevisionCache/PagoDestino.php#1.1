<?php

require_once 'cuentacorriente/models/CuentaCorrienteColeccion.php';
require_once 'cuentacorriente/models/ContableColeccion.php';
// require_once 'cuentacorriente/models/CuentaCorrienteElementoValuadoColeccion.php';
require_once 'admin/models/AuditoriaColeccion.php';

require_once 'extensiones/generales/MiMensajero.php'; 


/**
 * 
 *
 * 
 */
class PagoDestino 
{
    private $_cuentaCorrienteId;
    private $_cuentaCorrienteColeccion;
    private $_contableColeccion;
    private $_view;
    
    public function __construct( $cuentaCorrienteId, $view )
    {
        $this->_cuentaCorrienteId = $cuentaCorrienteId;
        $this->_cuentaCorrienteColeccion = new CuentaCorrienteColeccion();
        $this->_contableColeccion = new ContableColeccion();
        $this->_view = $view;
    }
    
    /*
     * Presenta una pantalla con las cosas que se pagarían con el monto indicado,
     * si sistemas selecciona los items a pagar.
     * El usuario puede aprobar o cancelar.
     * 
     * INPUT
     * $values <array>
     *      'sedes_id'  Sede que está trabajando
     *      'permisos'  Permisos del usuario
     *      'titulo'    titulo para la ventana emergente
     *      'botones'   Array asociativo con propiedad => value de botones html
     * 
     * OUTPUT
     * json con data de como se conformaría el destino del pago si Sistemas decide
     *  
     */
    public function propuestoPorSistemas( $params, $simularPago=true )
    {
        $CuentaCorriente = $this->_cuentaCorrienteColeccion
                                ->obtenerPorIdGeneral($this->_cuentaCorrienteId,'CuentaCorriente');
        $CuentaCorriente_values = $CuentaCorriente->convertirEnArray();

        $array = [  'comprobante_envio'     => 0,
                    'seleccion_deuda_item'  => 'formacion',
                    'evscxa_id'             => 'A_CUENTA',  // creo no necesario
                    'simularPago'           => $simularPago, 
                    'sedes_id'              => $params['sedes_id']
                ];
        $array['permisosDelUsuario'] = $params['permisosDelUsuario'];
        $array['alumnoId'] = $CuentaCorriente->getAlumnosId();
        
        $values = $CuentaCorriente_values + $array;
$values['cobertura']=0; // esto ya debió quedar así cuando se hace la desasignación
        $respuesta = $this->_cuentaCorrienteColeccion
                                ->registrarMovimiento( $values );        
        if( is_array($respuesta) && !key_exists( 'ERROR', $respuesta ) ){
            // render respuesta view
            $this->_view->resultadoPago = $respuesta;
            $this->_view->saldo_alumno = $this->_contableColeccion->getSaldoAlumno( $array['alumnoId'] );
            $this->_view->botones = $params['botones'];
            $respuesta['html']= $this->_view->render('/administrador/pago_confirmar.phtml');
            $respuesta['titulo']= $params['titulo'];
        }
        return $respuesta;
    }
    
    
}
