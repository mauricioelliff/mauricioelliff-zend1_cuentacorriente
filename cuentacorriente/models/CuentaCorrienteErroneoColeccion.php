<?php
/*
 * 
 */

require_once 'ColeccionParaEsteProyecto.php';
require_once 'cuentacorriente/models/CuentaCorrienteErroneo.php';
require_once 'admin/models/AuditoriaColeccion.php';

/*
 * 
 *
 */
class CuentaCorrienteErroneoColeccion extends ColeccionParaEsteProyecto
{

    protected $_name    = 'yoga_cuentas_corrientes_erroneos';
    protected $_id      = 'yoga_cuentas_corrientes_erroneos_id';

    protected $_class_origen = 'CuentaCorrienteErroneo';
    
        
    public function init()
    {
        parent::init();  
    }
    
    public function getDominioErrores()
    {
        return ['SIN_MONTO', 
                'PAGO_DUPLICADO', 
                'PAGO_COMO_NOTA_DE_CREDITO' ];
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
        $row->error                 = $objeto->getError();
        
        $row->save();
        
        return $row->id;
    }
        

}