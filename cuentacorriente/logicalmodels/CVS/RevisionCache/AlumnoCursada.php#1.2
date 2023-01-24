<?php
/*
 * Brinda información respecto a la cuenta corriente
 * de un alumno en una cursada específica.
 */

require_once 'default/models/Query.php';


class AlumnoCursada 
{
    private $_alumnos_id;
    private $_sedes_cursosxanio_id;
    
    public function __construct( $alumnos_id, $sedes_cursosxanio_id ) {
        $this->_alumnos_id = $alumnos_id;
        $this->_sedes_cursosxanio_id = $sedes_cursosxanio_id;
    }
    
    
    // Devuelve las rows que refieren a los items facturados
    // Incluye las Nota de crédito que pudiese tener por cambio de valor.
    public function getRowsDeFacturacion()
    {
        $sql = 'SELECT cta.*, ev.elementosvaluados_sedes_cursosxanio_id 
                FROM yoga_cuentas_corrientes AS cta
                LEFT JOIN yoga_cuentascorrientes_elementosvaluados AS ev
                ON ev.cuentas_corrientes_id = cta.id
                WHERE alumnos_id = "'.$this->_alumnos_id.'" 
                    AND (tipo_operacion IN( "FACTURA_AUTOMATICA",
                                            "NOTA_CREDITO_AUTOMATICO",
                                            "DEBITO_AUTOMATICO",
                                            "DEBITO_MANUAL"
                                           )
                    )
                    AND cta.id IN (
                        SELECT cuentas_corrientes_id 
                        FROM yoga_cuentascorrientes_elementosvaluados AS ctasev
                        WHERE elementosvaluados_sedes_cursosxanio_id IN (
                            SELECT id
                            FROM yoga_elementosvaluados_sedes_cursosxanio
                            WHERE sedes_cursosxanio_id = '.$this->_sedes_cursosxanio_id.' 
                        )
                )';    
        $Query = new Query();
        $resultado = $Query->ejecutarQuery($sql);
        return $resultado;
    }
    
    
    public function getRowsDePagos()
    {
        $sql = 'SELECT cta.*, ev.elementosvaluados_sedes_cursosxanio_id 
                FROM yoga_cuentas_corrientes AS cta
                LEFT JOIN yoga_cuentascorrientes_elementosvaluados AS ev
                ON ev.cuentas_corrientes_id = cta.id
                WHERE alumnos_id = "'.$this->_alumnos_id.'" 
                    AND tipo_operacion = "PAGO_MANUAL"
                    AND cta.id IN (
                        SELECT cuentas_corrientes_id 
                        FROM yoga_cuentascorrientes_elementosvaluados AS ctasev
                        WHERE elementosvaluados_sedes_cursosxanio_id IN (
                            SELECT id
                            FROM yoga_elementosvaluados_sedes_cursosxanio
                            WHERE sedes_cursosxanio_id = '.$this->_sedes_cursosxanio_id.' 
                        )
                )';    
        $Query = new Query();
        $resultado = $Query->ejecutarQuery($sql);
        return $resultado;
    }
    
    public function coberturaDeFacturacion()
    {
        $rowsDeFacturacion = $this->getRowsDeFacturacion();
        if( !$rowsDeFacturacion ){
            return 0;
        }
        $suma = 0;
        foreach( $rowsDeFacturacion as $row ){
            $suma+= (-$row['cobertura'] );
        }
        return $suma;
    }
    
    private function _sumaRows( $array, $campo )
    {
        if( !is_array($array) || count($array)==0 ){
            return 0;
        }
        $suma = 0;
        foreach( $array as $row ){
            $suma+= $row[$campo];
        }
        return $suma;
    }
    
    /*
     * Elimina las facturas y sus débitos o créditos.
     * Y quita a los pagos la cobertura correspondiente.
     */
    public function borrarMovimientos()
    {
        //$cursadaInfo = $this->getCursadaInfo( ['sedes_cursosxanio_id'=>$sedes_cursosxanio_id] );
        
        $debitos = $this->getRowsDeFacturacion();
        $creditos = $this->getRowsDePagos();
        
        $coberturaDebitos = abs( $this->_sumaRows( $debitos, 'cobertura' ) );
        
        $this->_deleteRows( $debitos );
        
        // Créditos: resta coberturas
        $Query = new Query();
        if( $coberturaDebitos>0 && count($creditos)>0 ){
            $coberturaQuitada = 0;
            foreach( $creditos as $credito ){
                if( $coberturaQuitada >= $coberturaDebitos ){
                    break;
                }
                if( $credito['cobertura']==0 ){
                    continue;
                }
                $coberturaResto = $coberturaDebitos - $coberturaQuitada;
                $creditoAQuitarEnRow = ( $credito['cobertura']>$coberturaResto )? $coberturaResto : $credito['cobertura'] ; 
                $sql = "UPDATE yoga_cuentas_corrientes SET cobertura = (cobertura-$creditoAQuitarEnRow) ".
                        'WHERE id = '.$credito['id'];
                $Query->ejecutarCualquierSql( $sql );
                $coberturaQuitada+=$creditoAQuitarEnRow;
            }
        }
    }    
    
    private function _deleteRows( $rowsArray )
    {
        if( !is_array($rowsArray) || count($rowsArray)==0 ){
            return 0;
        }
        $ids = array_values_recursive( arrays_getAlgunasKeysArrays( $rowsArray, 'id' ) );
        $where = 'id IN ( '.implode(', ', $ids ).') ';
        $Query = new Query();
        //$sql = "DELETE yoga_cuentas_corrientes WHERE $where ";
        //$Query->ejecutarCualquierSql($sql); // también funciona
        $Query->borrar( 'yoga_cuentas_corrientes', $where );
    }
    
    
}
