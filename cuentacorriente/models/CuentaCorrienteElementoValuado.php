<?php
/**
 * Esta tabla se utiliza para identificar cada item punitorio de pago.
 * Se registra su incorporación como deuda, 
 * y luego los pagos que se le van realizando.
 * 
 * Esta tabla es usada en pantallas para mostrar que items están en deuda.
 * Serviría también para control cruzado con la cobertura de los items.
 * 
 * Registra cuanto se debita y acredita a los distintos EVSCXA
 * Cada EVSCXA tiene N items que referencian a sus movimientos en CtasCtes.
 * Por lo general, casos comunes, cada EVSCXA tendra un registro que indique su
 * facturación y otro su pago. Cada uno de ellos por su monto correspondiente.
 */

class CuentaCorrienteElementoValuado
{
    private $_id;       
    private $_cuentas_corrientes_id;
    private $_elementosvaluados_sedes_cursosxanio_id;
    private $_pago_asignado;

    public function __construct( array $valores )
    {
        $this->_id                          = isset( $valores['id'] )                           ? $valores['id'] : null;
        $this->_cuentas_corrientes_id       = isset( $valores['cuentas_corrientes_id'] )        ? $valores['cuentas_corrientes_id'] : null;       
        $this->_elementosvaluados_sedes_cursosxanio_id 
                                            = isset( $valores['elementosvaluados_sedes_cursosxanio_id'] )  
                                                                                                ? $valores['elementosvaluados_sedes_cursosxanio_id'] : null;       
        $this->_pago_asignado              = isset( $valores['pago_asignado'] )               ? $valores['pago_asignado'] : null;       
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getCuentasCorrientesId()
    {
        return $this->_cuentas_corrientes_id;
    }

    public function getElementosValuadosSedesCursosxanioId()
    {
        return $this->_elementosvaluados_sedes_cursosxanio_id;
    }

    public function getMontoAsignado()
    {
        return $this->_pago_asignado;
    }



     /*
     * Convierte el objeto en array.
     * Debe hacerse desde la propia clase pues se trata de variables privadas.
     */
    public function convertirEnArray()
    {
        //return get_object_vars($this); //esto lo devuelve con los underscord
        $miIterator=array();
        foreach($this as $key => $value) {
            $key=substr($key,1); //con esto le quito el underscord primero.
            $miIterator[$key]=$value;
        }
        return $miIterator;
    }



}