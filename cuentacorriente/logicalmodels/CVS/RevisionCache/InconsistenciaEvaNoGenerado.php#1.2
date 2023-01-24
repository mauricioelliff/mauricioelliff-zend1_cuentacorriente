<?php

/*
 * Check de que el EVA se inserte al hacer el pago de matricula.
 * 
 */
require_once 'admin/logicalmodels/Inconsistencia.php';

class InconsistenciaEvaNoGenerado extends Inconsistencia
{
    private $_mensaje = 'Error en InconsistenciaEvaNoGenerado.php';
    
    public function hayError()
    {
        // , ctas.motivo, ctas.monto, ctas.fecha_hora_de_sistema, viewevas.evscxa_id, viewevas.evscxa_valor, viewevas.valor_modificado, viewevas.valor_modificado_motivo, viewevas.valor_final_calculado
        $sql = 'SELECT ctas.alumnos_id
                FROM yoga_cuentas_corrientes AS ctas
                LEFT JOIN yoga_cuentascorrientes_elementosvaluados AS ctaseva
                    ON ctaseva.cuentas_corrientes_id = ctas.id
                LEFT JOIN view_alumnos_valores AS viewevas
                    ON viewevas.evscxa_id = ctaseva.elementosvaluados_sedes_cursosxanio_id
                        AND viewevas.alumnos_id = ctas.alumnos_id
                WHERE ctas.tipo_operacion = "PAGO_MANUAL" 
                    AND viewevas.nombre_computacional = "profesorado"
                    AND viewevas.ev_abreviatura = "MAT"
                    AND YEAR(fecha_hora_de_sistema)='.date('Y').
                    ' AND viewevas.valor_modificado IS NULL
                ';
        $resultado = $this->_Query->ejecutarQuery($sql);

        if( !is_array($resultado) ){        // !$resultado sale por true si el array es vacio
            return ( $this->_mensaje.': El query no funciona.' );
        }
        if( count($resultado)>0 ){
            return ( $this->_mensaje.': Alumnos con errores: '.json_encode($resultado) );
       }
        return false; // Sin errores 
    }
}
