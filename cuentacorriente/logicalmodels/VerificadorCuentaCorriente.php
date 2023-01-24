<?php
/**
 * Description of VerificadorCuentaCorriente
 *
 * @author mauricio
 */

require_once 'default/models/Query.php';

class VerificadorCuentaCorriente 
{
    
    public function __construct( CuentaCorrienteColeccion $CuentaCorrienteColeccion ) 
    {
        $this->_cuentaCorrienteColeccion = $CuentaCorrienteColeccion;
    }
    
    
    
    public function getEstadoCuentaCorrienteAlumno( $alumnos_id )
    {
        /* Se buscarán registros erróneos como:
         * 
            --   1° Pagos con cobertura negativa:
            --      100         -100            200
            monto > 0 AND cobertura < 0           OR

            --  2° Pagos con cobertura mayor que su monto
            --      100         120             -20
            monto > 0 AND cobertura > monto       OR

            --   3° Deudas con coberturas mayores a cero
            --      -100	10              -110
            monto < 0 AND cobertura > 0           OR

            --  4° Deudas con coberturas inferiores a su monto
            --      -100	-200            100
            monto < 0 AND cobertura < monto
         */
        $sql1= "SELECT COUNT(*) AS rows_con_errores
                FROM yoga_cuentas_corrientes
                WHERE
                    (
                    monto > 0 AND cobertura < 0           OR
                    monto > 0 AND cobertura > monto       OR
                    monto < 0 AND cobertura > 0           OR
                    monto < 0 AND cobertura < monto
                    )
                    AND alumnos_id = '$alumnos_id'
                ;";
        $sql2= "SELECT SUM(cobertura) AS suma_coberturas 
                FROM yoga_cuentas_corrientes
                WHERE alumnos_id = '$alumnos_id'
                ;";
        
        $query = new Query();
        
        $resultado1 = $query->ejecutarQuery( $sql1 );
        $resu1 = ( (int)$resultado1[0]['rows_con_errores'] === 0 )? TRUE : FALSE;
        
        $resultado2 = $query->ejecutarQuery( $sql2 );
        $resu2 = ( (int)$resultado2[0]['suma_coberturas'] === 0 )? TRUE : FALSE;
        
        return $resu1 && $resu2;
    }
    
    
}
