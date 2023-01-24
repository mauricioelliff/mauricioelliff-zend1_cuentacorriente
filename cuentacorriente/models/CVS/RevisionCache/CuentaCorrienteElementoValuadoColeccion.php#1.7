<?php
/*
 * La tabla de relación indica
 * cada item de cuentas corrientes, a que evscxa corresponde.
 * Lo que permite un control a cada alumno de que se le cobro o pago
 * en cada item de las cuentas corrientes.
 * Comunmente cada item evscxa tendrá una row donde se genero la deuda,
 * y una row donde se efectuo el pago.
 * 
 * 
 * IMPORTANTE:
 * Los debitos se guardan con valor NULL, pues no le encontre aun el uso de guardar el dato.
 * Sin embargo, es importante que esos debitos no se borren de esta tabla,
 * pues funcionan para visualizar las deudas del alumno.
 * 
 */

require_once 'extensiones/generales/Coleccion.php';
require_once 'cuentacorriente/models/CuentaCorrienteElementoValuado.php';

/*
 * 
 *
 */
class CuentaCorrienteElementoValuadoColeccion extends Coleccion
{

    protected $_name    = 'yoga_cuentascorrientes_elementosvaluados';
    protected $_id      = 'yoga_cuentascorrientes_elementosvaluados_id';

    protected $_class_origen = 'CuentaCorrienteElementoValuado';
       
        
    public function init()
    {
        parent::init();  
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
        $row->cuentas_corrientes_id         = $objeto->getCuentasCorrientesId();
        $row->elementosvaluados_sedes_cursosxanio_id  
                                            = $objeto->getElementosValuadosSedesCursosxanioId();
        $row->pago_asignado                = $objeto->getMontoAsignado();
        
        $row->save();
        
        return $row->id;
    }
    
    
    /*
     * Altas y actualización 
     * generada por un movimiento en Cuentas Corrientes.
     * $CuentaCorriente, puede ser un débito o un crédito.
     */
    public function actualizaRelacion( $CuentaCorriente, $evscxaId, $montoAhora )
    {
        $relValues = array( 'cuentas_corrientes_id'                 => $CuentaCorriente->getId(),
                            'elementosvaluados_sedes_cursosxanio_id'=> $evscxaId,
                            );        
        $existe = $this->obtenerGeneral( $relValues, 'id', 'CuentaCorrienteElementoValuado', false, true );
        if( !$existe ){
            // El monto asignado, sirve saber el monto destinado a cada cosa.
            // Funciona igual que ctas ctes. Los débitos nacen con su valor en negativo,
            // y los pagos en positivo. Así, sumarizando todo por evscxa_id,
            // puede obtenerse el saldo del item. (conformado por todos sus débitos y créditos).
            $relValues['pago_asignado']= $montoAhora;// ($CuentaCorriente->getMonto()<0)? null : $montoAhora; 
            $this->altaGeneral($relValues, 'CuentaCorrienteElementoValuado');
        }else{
            // Si la relación ya existía, es decir, mismo ctacte id, pagando misma cosa;
            // es que probablemente se estén pagando distintas deudas referidos a la misma cosa.
            // (podría ser que esa cosa tuviese notas de débito asociadas)
            // y entonces, el pago asignado se recalcula:
            $relValues = $existe->convertirEnArray();
            $relValues['pago_asignado']+= $montoAhora; 
            $this->modificacionactualizacionGeneral($relValues, 'CuentaCorrienteElementoValuado');
        }
    }

}