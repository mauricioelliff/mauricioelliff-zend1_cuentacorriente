<?php

/*
 * La suma de Cobertura debe dar cero
 */
require_once 'admin/logicalmodels/Inconsistencia.php';

class InconsistenciaSumaCobertura extends Inconsistencia
{
    private $_mensaje = 'Error en InconsistenciaSumaCobertura.php';
    
    public function hayError()
    {
        $sql = 'SELECT SUM(cobertura) AS "suma" FROM yoga_cuentas_corrientes';
        $resultado = $this->_Query->ejecutarQuery($sql);
        if( !$resultado || !is_array($resultado) ){
            return ( $this->_mensaje.': El query no funciona.' );
        }
        if( !isset($resultado[0]['suma']) ){
            return ( $this->_mensaje.': Resultado no esperado: '.json_encode($resultado) );
       }
        if( (int)$resultado[0]['suma'] <> 0 ){
            return ( $this->_mensaje.': La cobertura de toda la tabla, no da cero: '.(int)$resultado[0]['suma'] );
        }
        return false; // Todo bien. 
    }
}
